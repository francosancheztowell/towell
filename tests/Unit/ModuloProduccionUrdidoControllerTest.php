<?php

namespace Tests\Unit;

use App\Http\Controllers\Urdido\Configuracion\ModuloProduccionUrdidoController;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

class ModuloProduccionUrdidoControllerTest extends TestCase
{
    use UsesSqlsrvSqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');
        config()->set('app.timezone', 'America/Mexico_City');

        $schema = Schema::connection('sqlsrv');

        $schema->create('UrdProgramaUrdido', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Folio')->nullable();
            $table->string('Status')->nullable();
            $table->date('FechaFinaliza')->nullable();
            $table->string('SalonTejidoId')->nullable();
            $table->integer('Incorrecto')->nullable();
        });

        $schema->create('UrdProduccionUrdido', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Folio')->nullable();
            $table->date('Fecha')->nullable();
            $table->string('HoraInicial')->nullable();
            $table->string('HoraFinal')->nullable();
            $table->float('KgNeto')->nullable();
            $table->integer('Finalizar')->nullable();
            $table->float('Vueltas')->nullable();
            $table->float('Diametro')->nullable();
            $table->integer('Hilatura')->nullable();
            $table->integer('Maquina')->nullable();
            $table->integer('Operac')->nullable();
            $table->integer('Transf')->nullable();
            $table->string('NoJulio')->nullable();
            $table->float('KgBruto')->nullable();
            $table->integer('AX')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_finalizar_applies_monthly_cutoff_and_rewrites_all_production_dates(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-01 08:29:59', 'America/Mexico_City'));

        DB::connection('sqlsrv')->table('UrdProgramaUrdido')->insert([
            'Id' => 1,
            'Folio' => '00031',
            'Status' => 'En Proceso',
            'FechaFinaliza' => null,
            'SalonTejidoId' => 'Mc Coy 1',
        ]);

        DB::connection('sqlsrv')->table('UrdProduccionUrdido')->insert([
            [
                'Id' => 10,
                'Folio' => '00031',
                'Fecha' => '2026-05-01',
                'HoraInicial' => '06:00',
                'HoraFinal' => '07:00',
                'NoJulio' => 'J1',
                'KgBruto' => 120,
                'KgNeto' => 100,
                'Finalizar' => 0,
            ],
            [
                'Id' => 11,
                'Folio' => '00031',
                'Fecha' => '2026-05-02',
                'HoraInicial' => '07:00',
                'HoraFinal' => '08:00',
                'NoJulio' => 'J2',
                'KgBruto' => 115,
                'KgNeto' => 95,
                'Finalizar' => 0,
            ],
        ]);

        $controller = new class extends ModuloProduccionUrdidoController
        {
            protected function ensureUserCanEdit(): void {}

            protected function traitHasNegativeKgNetoByFolio(string $folio): bool
            {
                return false;
            }

            protected function validarHorasRegistros(string $folio): ?string
            {
                return null;
            }
        };

        $response = $controller->finalizar(Request::create('/urdido/modulo-produccion-urdido/finalizar', 'POST', [
            'orden_id' => 1,
        ]));

        $payload = $response->getData(true);

        $this->assertTrue($payload['success']);
        $this->assertSame('Finalizado', DB::connection('sqlsrv')->table('UrdProgramaUrdido')->where('Id', 1)->value('Status'));
        $this->assertSame(
            '2026-04-30',
            substr((string) DB::connection('sqlsrv')->table('UrdProgramaUrdido')->where('Id', 1)->value('FechaFinaliza'), 0, 10)
        );
        $this->assertSame(
            ['2026-04-30', '2026-04-30'],
            DB::connection('sqlsrv')->table('UrdProduccionUrdido')
                ->where('Folio', '00031')
                ->orderBy('Id')
                ->pluck('Fecha')
                ->map(fn ($fecha) => substr((string) $fecha, 0, 10))
                ->all()
        );
        $this->assertSame(2, DB::connection('sqlsrv')->table('UrdProduccionUrdido')->where('Folio', '00031')->where('Finalizar', 1)->count());
    }

    /**
     * Las filas se pre-crean como esqueleto desde el plan de julios. Un julio
     * planeado que no se corrio se descarta sin preguntar: es el caso normal.
     */
    public function test_finalizar_descarta_esqueletos_sin_preguntar(): void
    {
        [$controller] = $this->escenarioConRegistroIncompleto(conCaptura: false);

        $response = $controller->finalizar(Request::create('/f', 'POST', ['orden_id' => 1]));

        $this->assertTrue($response->getData(true)['success']);
        // el esqueleto se fue, la fila corrida quedo cerrada
        $this->assertNull(DB::connection('sqlsrv')->table('UrdProduccionUrdido')->where('Id', 21)->first());
        $this->assertSame(1, (int) DB::connection('sqlsrv')->table('UrdProduccionUrdido')->where('Id', 20)->value('Finalizar'));
    }

    /** Pero una fila CON captura y sin horas no se tira sin confirmacion. */
    public function test_finalizar_pide_confirmacion_antes_de_descartar_registros_incompletos(): void
    {
        [$controller] = $this->escenarioConRegistroIncompleto();

        $response = $controller->finalizar(Request::create('/f', 'POST', ['orden_id' => 1]));
        $payload = $response->getData(true);

        $this->assertFalse($payload['success']);
        $this->assertSame(422, $response->getStatusCode());
        $this->assertTrue($payload['requiere_confirmacion']);
        $this->assertSame(1, $payload['registros_a_descartar']);
        // nada se borro y la orden sigue abierta
        $this->assertSame(2, DB::connection('sqlsrv')->table('UrdProduccionUrdido')->count());
        $this->assertSame('En Proceso', DB::connection('sqlsrv')->table('UrdProgramaUrdido')->where('Id', 1)->value('Status'));
    }

    /** Con confirmacion si descarta, pero nunca toca filas ya enviadas a AX. */
    public function test_finalizar_confirmado_descarta_incompletos_y_respeta_ax(): void
    {
        [$controller] = $this->escenarioConRegistroIncompleto(axEnIncompleto: true);

        $response = $controller->finalizar(Request::create('/f', 'POST', [
            'orden_id' => 1,
            'confirmar_descarte' => true,
        ]));

        $this->assertTrue($response->getData(true)['success']);
        // la fila incompleta tiene AX=1: sobrevive y NO se marca Finalizar
        $incompleto = DB::connection('sqlsrv')->table('UrdProduccionUrdido')->where('Id', 21)->first();
        $this->assertNotNull($incompleto);
        $this->assertNotSame(1, (int) $incompleto->Finalizar);
        // la completa si se cierra
        $this->assertSame(1, (int) DB::connection('sqlsrv')->table('UrdProduccionUrdido')->where('Id', 20)->value('Finalizar'));
    }

    private function escenarioConRegistroIncompleto(bool $axEnIncompleto = false, bool $conCaptura = true): array
    {
        DB::connection('sqlsrv')->table('UrdProgramaUrdido')->insert([
            'Id' => 1, 'Folio' => '00099', 'Status' => 'En Proceso',
            'FechaFinaliza' => null, 'SalonTejidoId' => 'Mc Coy 1', 'Incorrecto' => 0,
        ]);

        DB::connection('sqlsrv')->table('UrdProduccionUrdido')->insert([
            [
                'Id' => 20, 'Folio' => '00099', 'Fecha' => '2026-05-10',
                'HoraInicial' => '06:00', 'HoraFinal' => '07:00',
                'NoJulio' => 'J1', 'KgBruto' => 120, 'KgNeto' => 100, 'Finalizar' => 0, 'AX' => 0,
            ],
            [
                // esqueleto = sin julio/peso/roturas, aunque el trait ya le puso
                // Fecha y Oficial 1 en la carga de pagina
                'Id' => 21, 'Folio' => '00099', 'Fecha' => '2026-05-10',
                'HoraInicial' => null, 'HoraFinal' => null,
                'NoJulio' => $conCaptura ? 'J2' : null,
                'KgBruto' => $conCaptura ? 118 : null,
                'KgNeto' => $conCaptura ? 98 : null,
                'Finalizar' => 0,
                'AX' => $axEnIncompleto ? 1 : 0,
            ],
        ]);

        $controller = new class extends ModuloProduccionUrdidoController
        {
            protected function ensureUserCanEdit(): void {}

            protected function traitHasNegativeKgNetoByFolio(string $folio): bool
            {
                return false;
            }
        };

        return [$controller];
    }
}
