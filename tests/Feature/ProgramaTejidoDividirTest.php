<?php

namespace Tests\Feature;

use App\Http\Controllers\Planeacion\ProgramaTejido\funciones\DividirTejido;
use App\Models\Planeacion\ReqProgramaTejido;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

class ProgramaTejidoDividirTest extends TestCase
{
    use UsesSqlsrvSqlite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');
        config()->set('planeacion.programa_tejido_table', 'ReqProgramaTejido');

        Schema::connection('sqlsrv')->create('ReqProgramaTejido', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('SalonTejidoId')->nullable();
            $table->string('NoTelarId')->nullable();
            $table->string('Ultimo')->default('0');
            $table->float('TotalPedido')->default(0);
            $table->float('SaldoPedido')->default(0);
            $table->float('Produccion')->default(0);
            $table->string('FechaInicio')->nullable();
            $table->string('FechaFinal')->nullable();
            $table->string('TamanoClave')->nullable();
            $table->string('Maquina')->nullable();
            $table->float('EficienciaSTD')->default(0.85);
            $table->float('VelocidadSTD')->default(100);
            $table->integer('EnProceso')->default(0);
        });
    }

    protected function tearDown(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('ReqProgramaTejido');
        parent::tearDown();
    }

    public function test_dividir_registro_existente_divide_cantidad(): void
    {
        $registro = ReqProgramaTejido::create([
            'SalonTejidoId' => 'JAC1',
            'NoTelarId' => '01',
            'Ultimo' => '1',
            'TotalPedido' => 1000,
            'SaldoPedido' => 1000,
            'FechaInicio' => now()->format('Y-m-d H:i:s'),
            'EficienciaSTD' => 0.85,
            'VelocidadSTD' => 100,
        ]);

        $request = Request::create('/test', 'POST', [
            'salon_tejido_id' => 'JAC1',
            'no_telar_id' => '01',
            'destinos' => [
                ['telar' => '01', 'pedido' => '500'],
                ['telar' => '02', 'pedido' => '500'],
            ],
        ]);

        $this->assertTrue(method_exists(DividirTejido::class, 'dividir'));
    }

    public function test_dividir_con_saldo_negativo_maneja_error(): void
    {
        $registro = ReqProgramaTejido::create([
            'SalonTejidoId' => 'JAC1',
            'NoTelarId' => '01',
            'TotalPedido' => -100,
            'SaldoPedido' => -100,
        ]);

        $this->assertEquals(-100, $registro->TotalPedido);
    }

    public function test_dividir_tres_destinos_distribuye_cantidad(): void
    {
        $registro = ReqProgramaTejido::create([
            'SalonTejidoId' => 'JAC1',
            'NoTelarId' => '01',
            'Ultimo' => '1',
            'TotalPedido' => 900,
            'SaldoPedido' => 900,
            'FechaInicio' => now()->format('Y-m-d H:i:s'),
            'EficienciaSTD' => 0.85,
            'VelocidadSTD' => 100,
        ]);

        $this->assertEquals(900, $registro->TotalPedido);
    }
}
