<?php

namespace Tests\Feature\Planeacion;

use App\Models\Planeacion\ReqModelosCodificados;
use App\Services\Planeacion\ProgramaTejido\CatalogoModelos;
use App\Services\Planeacion\RevivirOrdenProgramaDesdeCatService;
use Illuminate\Support\Facades\DB;
use ReflectionProperty;
use Tests\Feature\Planeacion\Concerns\ProgramaTejidoFixtures;
use Tests\TestCase;

/**
 * Revivir una orden desde Codificación: el largo del Modelo (LargoToalla) va a LargoCrudo. Antes
 * se mandaba a LargoToalla, que el programa no tiene; el filtro de $fillable lo tiraba y la orden
 * revivida quedaba sin largo ni PesoGRM2.
 */
class RevivirOrdenLargoTest extends TestCase
{
    use ProgramaTejidoFixtures;

    public function test_el_largo_del_modelo_llega_a_largo_crudo_y_calcula_peso_grm2(): void
    {
        (new ReflectionProperty(CatalogoModelos::class, 'datosArrayCache'))->setValue(null, []);
        $this->prepararSuperficies();
        $this->createTablaDesdeModelo(ReqModelosCodificados::class);
        $this->createTablaDbo('ReqCalendarioLine', ['CalendarioId' => 'text', 'FechaInicio' => 'text', 'FechaFin' => 'text']);
        DB::table('ReqModelosCodificados')->insert(['SalonTejidoId' => 'SMIT', 'TamanoClave' => 'MB-REV', 'LargoToalla' => 90, 'AnchoToalla' => 50]);
        $catId = DB::table('CatCodificados')->insertGetId([
            'OrdenTejido' => '36100', 'TelarId' => 205, 'Departamento' => 'SMIT', 'ClaveModelo' => 'MB-REV',
            'P_crudo' => 450, 'Pedido' => 1000, 'Saldos' => 1000, 'FechaFinaliza' => '2026-10-01 08:00:00',
        ]);

        $id = app(RevivirOrdenProgramaDesdeCatService::class)->ejecutar($catId)['programa_id'];

        $fila = DB::table('ReqProgramaTejido')->where('Id', $id)->first();
        $this->assertSame([450, 90, 50.0], [(int) $fila->PesoCrudo, (int) $fila->LargoCrudo, (float) $fila->AnchoToalla]);
        $this->assertEqualsWithDelta(1000.0, (float) $fila->PesoGRM2, 0.001, '450 x 10000 / (90 x 50)');
    }
}
