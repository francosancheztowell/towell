<?php

namespace Tests\Feature\Tejido;

use App\Http\Controllers\Tejido\Reportes\ReporteMarcasFinalesController;
use App\Models\Planeacion\ReqProgramaTejido;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Tejido\Concerns\ModuloTejido;
use Tests\TestCase;

/** La velocidad del reporte de Marcas finales (y su eficiencia) sale de la orden en proceso de Planeación. */
class ReporteMarcasFinalesVelocidadTest extends TestCase
{
    use ModuloTejido;

    public function test_velocidad_de_la_orden_en_proceso_por_telar(): void
    {
        $this->prepararSqlite();
        Schema::connection('sqlsrv')->create(ReqProgramaTejido::tableName(), function ($t): void {
            $t->increments('Id');
            $t->string('NoTelarId')->nullable();
            $t->integer('VelocidadSTD')->nullable();
            $t->boolean('EnProceso')->default(false);
        });
        DB::connection('sqlsrv')->table(ReqProgramaTejido::tableName())->insert([
            ['NoTelarId' => '201', 'VelocidadSTD' => 286, 'EnProceso' => 0], // ya no está en proceso
            ['NoTelarId' => '201', 'VelocidadSTD' => 360, 'EnProceso' => 1],
            ['NoTelarId' => '202', 'VelocidadSTD' => 300, 'EnProceso' => 1],
            ['NoTelarId' => '202', 'VelocidadSTD' => 330, 'EnProceso' => 1], // la más reciente gana
            ['NoTelarId' => '401', 'VelocidadSTD' => null, 'EnProceso' => 1],
            ['NoTelarId' => '305', 'VelocidadSTD' => 300, 'EnProceso' => 0], // sin orden en proceso
        ]);

        $metodo = new \ReflectionMethod(ReporteMarcasFinalesController::class, 'obtenerVelocidadesPorTelar');
        $velocidades = $metodo->invoke(new ReporteMarcasFinalesController);

        $this->assertSame([201 => 360.0, 202 => 330.0, 401 => 0.0], $velocidades->all());
        // El reporte busca por el número de telar entero (TejMarcasLine.NoTelarId casteado a int).
        $this->assertSame(360.0, $velocidades[(int) '201']);
        $this->assertNull($velocidades[305] ?? null);
    }
}
