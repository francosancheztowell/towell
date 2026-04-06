<?php

namespace Tests\Unit;

use App\Http\Controllers\Engomado\Produccion\ModuloProduccionEngomadoController;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

class ModuloProduccionEngomadoControllerTest extends TestCase
{
    use UsesSqlsrvSqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');
        config()->set('app.timezone', 'America/Mexico_City');

        $schema = Schema::connection('sqlsrv');

        $schema->create('EngProgramaEngomado', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Folio')->nullable();
            $table->string('Status')->nullable();
            $table->date('FechaFinaliza')->nullable();
        });

        $schema->create('EngProduccionEngomado', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Folio')->nullable();
            $table->date('Fecha')->nullable();
            $table->float('KgNeto')->nullable();
            $table->integer('Finalizar')->nullable();
        });

        $schema->create('EngProduccionFormulacion', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Folio')->nullable();
            $table->string('Status')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_finalizar_uses_last_production_date_outside_monthly_cutoff_and_preserves_rows(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-01 08:30:00', 'America/Mexico_City'));

        DB::connection('sqlsrv')->table('EngProgramaEngomado')->insert([
            'Id' => 1,
            'Folio' => '00128',
            'Status' => 'En Proceso',
            'FechaFinaliza' => null,
        ]);

        DB::connection('sqlsrv')->table('EngProduccionEngomado')->insert([
            [
                'Id' => 10,
                'Folio' => '00128',
                'Fecha' => '2026-03-05',
                'KgNeto' => 120,
                'Finalizar' => 0,
            ],
            [
                'Id' => 11,
                'Folio' => '00128',
                'Fecha' => '2026-03-06',
                'KgNeto' => 118,
                'Finalizar' => 0,
            ],
            [
                'Id' => 12,
                'Folio' => '00128',
                'Fecha' => '2026-03-07',
                'KgNeto' => 119,
                'Finalizar' => 0,
            ],
        ]);

        DB::connection('sqlsrv')->table('EngProduccionFormulacion')->insert([
            'Id' => 1,
            'Folio' => '00128',
            'Status' => 'Creado',
        ]);

        $controller = new class extends ModuloProduccionEngomadoController
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

        $response = $controller->finalizar(Request::create('/engomado/modulo-produccion-engomado/finalizar', 'POST', [
            'orden_id' => 1,
        ]));

        $payload = $response->getData(true);

        $this->assertTrue($payload['success']);
        $this->assertSame('Finalizado', DB::connection('sqlsrv')->table('EngProgramaEngomado')->where('Id', 1)->value('Status'));
        $this->assertSame(
            '2026-03-07',
            substr((string) DB::connection('sqlsrv')->table('EngProgramaEngomado')->where('Id', 1)->value('FechaFinaliza'), 0, 10)
        );
        $this->assertSame(3, DB::connection('sqlsrv')->table('EngProduccionEngomado')->where('Folio', '00128')->where('Finalizar', 1)->count());
        $this->assertSame(
            ['2026-03-05', '2026-03-06', '2026-03-07'],
            DB::connection('sqlsrv')->table('EngProduccionEngomado')
                ->where('Folio', '00128')
                ->orderBy('Id')
                ->pluck('Fecha')
                ->map(fn ($fecha) => substr((string) $fecha, 0, 10))
                ->all()
        );
        $this->assertSame('Finalizado', DB::connection('sqlsrv')->table('EngProduccionFormulacion')->where('Id', 1)->value('Status'));
    }

    public function test_finalizar_applies_monthly_cutoff_and_rewrites_all_production_dates(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-01 08:29:59', 'America/Mexico_City'));

        DB::connection('sqlsrv')->table('EngProgramaEngomado')->insert([
            'Id' => 2,
            'Folio' => '00129',
            'Status' => 'En Proceso',
            'FechaFinaliza' => null,
        ]);

        DB::connection('sqlsrv')->table('EngProduccionEngomado')->insert([
            [
                'Id' => 20,
                'Folio' => '00129',
                'Fecha' => '2026-05-01',
                'KgNeto' => 121,
                'Finalizar' => 0,
            ],
            [
                'Id' => 21,
                'Folio' => '00129',
                'Fecha' => '2026-05-02',
                'KgNeto' => 122,
                'Finalizar' => 0,
            ],
        ]);

        DB::connection('sqlsrv')->table('EngProduccionFormulacion')->insert([
            'Id' => 2,
            'Folio' => '00129',
            'Status' => 'Creado',
        ]);

        $controller = new class extends ModuloProduccionEngomadoController
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

        $response = $controller->finalizar(Request::create('/engomado/modulo-produccion-engomado/finalizar', 'POST', [
            'orden_id' => 2,
        ]));

        $payload = $response->getData(true);

        $this->assertTrue($payload['success']);
        $this->assertSame('Finalizado', DB::connection('sqlsrv')->table('EngProgramaEngomado')->where('Id', 2)->value('Status'));
        $this->assertSame(
            '2026-04-30',
            substr((string) DB::connection('sqlsrv')->table('EngProgramaEngomado')->where('Id', 2)->value('FechaFinaliza'), 0, 10)
        );
        $this->assertSame(
            ['2026-04-30', '2026-04-30'],
            DB::connection('sqlsrv')->table('EngProduccionEngomado')
                ->where('Folio', '00129')
                ->orderBy('Id')
                ->pluck('Fecha')
                ->map(fn ($fecha) => substr((string) $fecha, 0, 10))
                ->all()
        );
        $this->assertSame(2, DB::connection('sqlsrv')->table('EngProduccionEngomado')->where('Folio', '00129')->where('Finalizar', 1)->count());
        $this->assertSame('Finalizado', DB::connection('sqlsrv')->table('EngProduccionFormulacion')->where('Id', 2)->value('Status'));
    }
}
