<?php

namespace Tests\Unit\Planeacion;

use App\Actions\Planeacion\ProgramaTejido\ActualizarOrdPrincipal;
use App\Models\Planeacion\Catalogos\CatCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Services\Planeacion\ProgramaTejido\OrdCompartida;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/**
 * Caracterización de la escritura de OrdPrincipal por grupo de OrdCompartida
 * (antes VincularTejido::actualizarOrdPrincipalPorOrdCompartida) y del recálculo de líder
 * (antes OrdCompartidaHelper::recalcularLiderYOrdPrincipalPorOrdCompartida).
 */
class ActualizarOrdPrincipalCharacterizationTest extends TestCase
{
    use UsesSqlsrvSqlite;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-07 08:00:00');
        $this->createTablaDesdeModelo(ReqProgramaTejido::class);
        $this->createTablaDesdeModelo(CatCodificados::class);

        DB::table('ReqProgramaTejido')->insert([
            ['Id' => 1, 'NoTelarId' => '201', 'NoProduccion' => '50', 'ItemId' => ' A7001 ', 'OrdCompartida' => 50, 'OrdCompartidaLider' => 1, 'FechaInicio' => '2026-10-02 06:00:00', 'TotalPedido' => 100],
            ['Id' => 2, 'NoTelarId' => '202', 'NoProduccion' => '51', 'ItemId' => 'A7002', 'OrdCompartida' => 50, 'OrdCompartidaLider' => null, 'FechaInicio' => '2026-10-01 06:00:00', 'TotalPedido' => 300],
            ['Id' => 3, 'NoTelarId' => null, 'NoProduccion' => '52', 'ItemId' => 'A7003', 'OrdCompartida' => 50, 'OrdCompartidaLider' => null, 'FechaInicio' => '2026-10-03 06:00:00', 'TotalPedido' => 200],
            ['Id' => 4, 'NoTelarId' => '204', 'NoProduccion' => '60', 'ItemId' => 'A8001', 'OrdCompartida' => 60, 'OrdCompartidaLider' => 1, 'FechaInicio' => '2026-10-01 06:00:00', 'TotalPedido' => 100],
        ]);
        DB::table('CatCodificados')->insert([
            ['Id' => 11, 'OrdenTejido' => '50', 'TelarId' => '201'],
            ['Id' => 12, 'OrdenTejido' => '51', 'TelarId' => '202'],
            ['Id' => 13, 'OrdenTejido' => '52', 'TelarId' => '999'],
            ['Id' => 14, 'OrdenTejido' => '51', 'TelarId' => '777'],
            ['Id' => 15, 'OrdenTejido' => '60', 'TelarId' => '204'],
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_escribe_item_id_del_lider_en_el_grupo_y_en_cat_codificados(): void
    {
        ActualizarOrdPrincipal::ejecutar(50);

        $this->assertSame(
            ['1' => 'A7001', '2' => 'A7001', '3' => 'A7001', '4' => null],
            DB::table('ReqProgramaTejido')->orderBy('Id')->pluck('OrdPrincipal', 'Id')->mapWithKeys(fn ($v, $k) => [(string) $k => $v])->all()
        );
        $this->assertSame('2026-10-07 08:00:00', (string) DB::table('ReqProgramaTejido')->where('Id', 2)->value('UpdatedAt'));
        $this->assertNull(DB::table('ReqProgramaTejido')->where('Id', 4)->value('UpdatedAt'));

        $cat = DB::table('CatCodificados')->orderBy('Id')->get()->keyBy('Id');
        $this->assertSame(['A7001', 50, 1], [$cat[11]->OrdPrincipal, (int) $cat[11]->OrdCompartida, (int) $cat[11]->OrdCompartidaLider]);
        $this->assertSame(['A7001', 50, null], [$cat[12]->OrdPrincipal, (int) $cat[12]->OrdCompartida, $cat[12]->OrdCompartidaLider]);
        // Telar vacío: no filtra por telar y toma la primera fila de esa orden.
        $this->assertSame(['A7001', 50, null], [$cat[13]->OrdPrincipal, (int) $cat[13]->OrdCompartida, $cat[13]->OrdCompartidaLider]);
        $this->assertNull($cat[14]->OrdPrincipal, 'otro telar de la misma orden no se toca');
        $this->assertNull($cat[15]->OrdPrincipal, 'otro grupo no se toca');
    }

    public function test_sin_lider_o_sin_item_id_no_escribe_nada(): void
    {
        DB::table('ReqProgramaTejido')->where('OrdCompartida', 50)->update(['OrdCompartidaLider' => null]);
        ActualizarOrdPrincipal::ejecutar(50);

        DB::table('ReqProgramaTejido')->where('Id', 4)->update(['ItemId' => '   ']);
        ActualizarOrdPrincipal::ejecutar(60);

        $this->assertSame(0, DB::table('ReqProgramaTejido')->whereNotNull('OrdPrincipal')->count());
        $this->assertSame(0, DB::table('CatCodificados')->whereNotNull('OrdPrincipal')->count());
    }

    public function test_recalcular_lider_elige_la_fecha_inicio_mas_temprana_y_propaga_ord_principal(): void
    {
        $this->assertSame(2, OrdCompartida::recalcularLiderYOrdPrincipalPorOrdCompartida(50));

        $this->assertSame(
            [null, 1, null],
            DB::table('ReqProgramaTejido')->where('OrdCompartida', 50)->orderBy('Id')->pluck('OrdCompartidaLider')->map(fn ($v) => $v === null ? null : (int) $v)->all()
        );
        $this->assertSame(['A7002'], DB::table('ReqProgramaTejido')->where('OrdCompartida', 50)->distinct()->pluck('OrdPrincipal')->all());
        $this->assertSame(1, (int) DB::table('CatCodificados')->where('Id', 12)->value('OrdCompartidaLider'));
        $this->assertNull(DB::table('CatCodificados')->where('Id', 11)->value('OrdCompartidaLider'));
        $this->assertNull(OrdCompartida::recalcularLiderYOrdPrincipalPorOrdCompartida(0));
    }
}
