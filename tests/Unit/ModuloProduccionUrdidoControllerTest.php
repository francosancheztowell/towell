<?php

namespace Tests\Unit;

use App\Http\Controllers\Urdido\Configuracion\ModuloProduccionUrdidoController;
use App\Models\Urdido\UrdJuliosOrden;
use App\Models\Urdido\UrdProgramaUrdido;
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

        $schema->create('UrdJuliosOrden', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Folio')->nullable();
            $table->integer('Julios')->nullable();
            $table->integer('Hilos')->nullable();
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
            $table->integer('Hilos')->nullable();
            $table->string('TipoAtado')->nullable();
            $table->string('CveEmpl1')->nullable();
            $table->string('NomEmpl1')->nullable();
            $table->float('Metros1')->nullable();
            $table->integer('Turno1')->nullable();
            $table->float('Tara')->nullable();
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

    /**
     * Regresion: el plan de julios cambio de Hilos despues de crear las filas.
     * El grupo viejo queda huerfano y el sincronizador creaba el grupo nuevo
     * desde cero, duplicando la orden con filas que la vista ni siquiera pintaba.
     */
    public function test_no_duplica_filas_cuando_el_plan_cambia_de_hilos(): void
    {
        DB::connection('sqlsrv')->table('UrdProgramaUrdido')->insert([
            'Id' => 5, 'Folio' => '00077', 'Status' => 'En Proceso', 'Incorrecto' => 0,
        ]);
        // plan: 6 julios de Hilos 395 + 1 de Hilos 396 = 7
        DB::connection('sqlsrv')->table('UrdJuliosOrden')->insert([
            ['Folio' => '00077', 'Julios' => 6, 'Hilos' => 395],
            ['Folio' => '00077', 'Julios' => 1, 'Hilos' => 396],
        ]);
        // tabla: 1 fila de 396 + 6 CAPTURADAS con Hilos 461 (grupo huerfano)
        $filas = [['Folio' => '00077', 'Hilos' => 396, 'NoJulio' => 'J0', 'HoraInicial' => '06:00']];
        for ($i = 1; $i <= 6; $i++) {
            $filas[] = ['Folio' => '00077', 'Hilos' => 461, 'NoJulio' => "J{$i}", 'HoraInicial' => '07:00'];
        }
        DB::connection('sqlsrv')->table('UrdProduccionUrdido')->insert($filas);

        $controller = new ModuloProduccionUrdidoController;
        $metodo = new \ReflectionMethod($controller, 'ensureProductionRecordsExist');
        $metodo->setAccessible(true);
        $orden = UrdProgramaUrdido::find(5);
        $julios = UrdJuliosOrden::where('Folio', '00077')->get();

        $metodo->invoke($controller, $orden, $julios, 7);

        $total = DB::connection('sqlsrv')->table('UrdProduccionUrdido')->where('Folio', '00077')->count();
        $this->assertSame(7, $total, "El folio debe quedar en el total del plan (7), no duplicado. Quedo en {$total}.");
        // y las 6 filas capturadas siguen ahi
        $this->assertSame(6, DB::connection('sqlsrv')->table('UrdProduccionUrdido')
            ->where('Folio', '00077')->where('Hilos', 461)->count());
    }

    /**
     * Un esqueleto con Hilos viejo se realinea al plan en vez de quedar huerfano
     * (y provocar que se cree el grupo nuevo duplicando la orden).
     */
    public function test_reproyecta_hilos_de_esqueletos_al_plan(): void
    {
        DB::connection('sqlsrv')->table('UrdProgramaUrdido')->insert([
            'Id' => 7, 'Folio' => '00500', 'Status' => 'En Proceso', 'Incorrecto' => 0,
        ]);
        DB::connection('sqlsrv')->table('UrdJuliosOrden')->insert([
            ['Folio' => '00500', 'Julios' => 3, 'Hilos' => 400],
        ]);
        // 3 esqueletos con el Hilos anterior (350): nadie los remapeo al editar el plan
        DB::connection('sqlsrv')->table('UrdProduccionUrdido')->insert([
            ['Folio' => '00500', 'Hilos' => 350],
            ['Folio' => '00500', 'Hilos' => 350],
            ['Folio' => '00500', 'Hilos' => 350],
        ]);

        $this->sincronizar(7, '00500', 3);

        $this->assertSame(3, DB::connection('sqlsrv')->table('UrdProduccionUrdido')->where('Folio', '00500')->count(),
            'No debe duplicar: los 3 esqueletos se realinean, no se recrean.');
        $this->assertSame(3, DB::connection('sqlsrv')->table('UrdProduccionUrdido')
            ->where('Folio', '00500')->where('Hilos', 400)->count());
    }

    /** Una fila capturada con Hilos fuera del plan es historia: no se reescribe. */
    public function test_no_reescribe_hilos_de_filas_con_captura(): void
    {
        DB::connection('sqlsrv')->table('UrdProgramaUrdido')->insert([
            'Id' => 8, 'Folio' => '00600', 'Status' => 'En Proceso', 'Incorrecto' => 0,
        ]);
        DB::connection('sqlsrv')->table('UrdJuliosOrden')->insert([
            ['Folio' => '00600', 'Julios' => 2, 'Hilos' => 400],
        ]);
        DB::connection('sqlsrv')->table('UrdProduccionUrdido')->insert([
            ['Folio' => '00600', 'Hilos' => 461, 'NoJulio' => 'J1', 'KgBruto' => 300],  // capturada
            ['Folio' => '00600', 'Hilos' => 350, 'NoJulio' => null, 'KgBruto' => null], // esqueleto
        ]);

        $this->sincronizar(8, '00600', 2);

        $filas = DB::connection('sqlsrv')->table('UrdProduccionUrdido')
            ->where('Folio', '00600')->orderBy('Id')->get();
        $this->assertSame(2, $filas->count());
        $this->assertSame(461, (int) $filas[0]->Hilos, 'La capturada conserva su Hilos.');
        $this->assertSame(400, (int) $filas[1]->Hilos, 'El esqueleto si se realinea.');
    }

    private function sincronizar(int $ordenId, string $folio, int $total): void
    {
        $controller = new ModuloProduccionUrdidoController;
        $metodo = new \ReflectionMethod($controller, 'ensureProductionRecordsExist');
        $metodo->setAccessible(true);
        $metodo->invoke(
            $controller,
            UrdProgramaUrdido::find($ordenId),
            UrdJuliosOrden::where('Folio', $folio)->get(),
            $total
        );
    }
}
