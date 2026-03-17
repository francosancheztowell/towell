<?php

namespace Tests\Unit;

use App\Http\Controllers\Engomado\Produccion\ModuloProduccionEngomadoController;
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

    public function test_finalizar_uses_fecha_from_last_production_row_for_folio(): void
    {
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
            protected function ensureUserCanEdit(): void
            {
            }

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
        $this->assertSame('Finalizado', DB::connection('sqlsrv')->table('EngProduccionFormulacion')->where('Id', 1)->value('Status'));
    }
}
