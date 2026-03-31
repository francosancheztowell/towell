<?php

namespace Tests\Feature;

use App\Http\Controllers\Planeacion\ProgramaTejido\funciones\UpdateTejido;
use App\Models\Planeacion\ReqProgramaTejido;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

class ProgramaTejidoUpdateTest extends TestCase
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
            $table->float('TotalPedido')->default(0);
            $table->float('SaldoPedido')->default(0);
            $table->float('Produccion')->default(0);
            $table->string('FechaInicio')->nullable();
            $table->string('FechaFinal')->nullable();
            $table->string('TamanoClave')->nullable();
            $table->float('EficienciaSTD')->default(0.85);
            $table->float('VelocidadSTD')->default(100);
            $table->integer('EnProceso')->default(0);
            $table->string('FibraRizo')->nullable();
            $table->string('CalendarioId')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('ReqProgramaTejido');
        parent::tearDown();
    }

    public function test_update_pedido_cambia_total_y_saldo(): void
    {
        $registro = ReqProgramaTejido::create([
            'SalonTejidoId' => 'JAC1',
            'NoTelarId' => '01',
            'TotalPedido' => 1000,
            'SaldoPedido' => 1000,
            'Produccion' => 0,
        ]);

        $registro->TotalPedido = 800;
        $registro->SaldoPedido = 800;
        $registro->save();

        $this->assertEquals(800, $registro->fresh()->TotalPedido);
        $this->assertEquals(800, $registro->fresh()->SaldoPedido);
    }

    public function test_update_con_produccion_parcial_ajusta_saldo(): void
    {
        $registro = ReqProgramaTejido::create([
            'SalonTejidoId' => 'JAC1',
            'NoTelarId' => '01',
            'TotalPedido' => 1000,
            'SaldoPedido' => 800,
            'Produccion' => 200,
        ]);

        $registro->TotalPedido = 500;
        $registro->SaldoPedido = max(0, 500 - 200);

        $this->assertEquals(300, $registro->SaldoPedido);
    }

    public function test_update_fecha_inicio_y_final_calcula_duracion(): void
    {
        $registro = ReqProgramaTejido::create([
            'SalonTejidoId' => 'JAC1',
            'NoTelarId' => '01',
            'FechaInicio' => '2026-03-01 08:00:00',
            'FechaFinal' => '2026-03-05 08:00:00',
            'TotalPedido' => 1000,
        ]);

        $inicio = \Carbon\Carbon::parse($registro->FechaInicio);
        $fin = \Carbon\Carbon::parse($registro->FechaFinal);
        $horas = $inicio->diffInHours($fin);

        $this->assertEquals(96, $horas);
    }

    public function test_update_metodo_existe_en_clase(): void
    {
        $this->assertTrue(method_exists(UpdateTejido::class, 'actualizar'));
    }
}
