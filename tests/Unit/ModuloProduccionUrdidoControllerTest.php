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
                'KgNeto' => 100,
                'Finalizar' => 0,
            ],
            [
                'Id' => 11,
                'Folio' => '00031',
                'Fecha' => '2026-05-02',
                'HoraInicial' => '07:00',
                'HoraFinal' => '08:00',
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
}
