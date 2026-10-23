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
 * Escritura de OrdPrincipal por grupo de OrdCompartida (= NoProduccion del líder desde
 * 23-oct-2026; antes su ItemId), recálculo de líder y número temporal de los grupos sin
 * NoProduccion (Vincular del modal, Dividir sin liberar) que se fija al liberar.
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

    public function test_escribe_el_no_produccion_del_lider_en_el_grupo_y_en_cat_codificados(): void
    {
        ActualizarOrdPrincipal::ejecutar(50);

        // Texto (nvarchar(15) desde el 23-oct-2026).
        $this->assertSame(
            ['1' => '50', '2' => '50', '3' => '50', '4' => null],
            DB::table('ReqProgramaTejido')->orderBy('Id')->pluck('OrdPrincipal', 'Id')->mapWithKeys(fn ($v, $k) => [(string) $k => $v === null ? null : (string) $v])->all()
        );
        $this->assertSame('2026-10-07 08:00:00', (string) DB::table('ReqProgramaTejido')->where('Id', 2)->value('UpdatedAt'));
        $this->assertNull(DB::table('ReqProgramaTejido')->where('Id', 4)->value('UpdatedAt'));

        $cat = DB::table('CatCodificados')->orderBy('Id')->get()->keyBy('Id');
        $this->assertSame([50, 50, 1], [(int) $cat[11]->OrdPrincipal, (int) $cat[11]->OrdCompartida, (int) $cat[11]->OrdCompartidaLider]);
        $this->assertSame([50, 50, null], [(int) $cat[12]->OrdPrincipal, (int) $cat[12]->OrdCompartida, $cat[12]->OrdCompartidaLider]);
        // Telar vacío: no filtra por telar y toma la primera fila de esa orden.
        $this->assertSame([50, 50, null], [(int) $cat[13]->OrdPrincipal, (int) $cat[13]->OrdCompartida, $cat[13]->OrdCompartidaLider]);
        $this->assertNull($cat[14]->OrdPrincipal, 'otro telar de la misma orden no se toca');
        $this->assertNull($cat[15]->OrdPrincipal, 'otro grupo no se toca');
    }

    public function test_sin_lider_o_con_no_produccion_vacio_no_escribe_nada(): void
    {
        DB::table('ReqProgramaTejido')->where('OrdCompartida', 50)->update(['OrdCompartidaLider' => null]);
        ActualizarOrdPrincipal::ejecutar(50);

        foreach (['   ', null] as $noProduccion) {
            DB::table('ReqProgramaTejido')->where('Id', 4)->update(['NoProduccion' => $noProduccion]);
            ActualizarOrdPrincipal::ejecutar('60');
        }
        ActualizarOrdPrincipal::ejecutar(null);

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
        $this->assertSame([51], DB::table('ReqProgramaTejido')->where('OrdCompartida', 50)->distinct()->pluck('OrdPrincipal')->map(fn ($v) => (int) $v)->all());
        $this->assertSame(51, (int) DB::table('CatCodificados')->where('Id', 11)->value('OrdPrincipal'));
        $this->assertSame(1, (int) DB::table('CatCodificados')->where('Id', 12)->value('OrdCompartidaLider'));
        $this->assertNull(DB::table('CatCodificados')->where('Id', 11)->value('OrdCompartidaLider'));
        $this->assertNull(OrdCompartida::recalcularLiderYOrdPrincipalPorOrdCompartida(0));
    }

    public function test_numero_temporal_no_choca_con_folios_y_no_tiene_lider(): void
    {
        $this->assertSame('900000980', OrdCompartida::temporal(980));
        $this->assertTrue(OrdCompartida::esTemporal('900000980'));
        $this->assertTrue(OrdCompartida::esTemporal(900000980), 'del JSON o de un cast viejo puede llegar como número');
        $this->assertFalse(OrdCompartida::esTemporal('37068'));
        $this->assertFalse(OrdCompartida::esTemporal('M3175'), 'folio de Muestras');
        $this->assertFalse(OrdCompartida::esTemporal(null));
        $this->assertSame([null, null, 'M3175', '37068'], array_map(OrdCompartida::normalizar(...), [' ', 0, ' M3175 ', 37068]));

        DB::table('ReqProgramaTejido')->insert([
            ['Id' => 7, 'NoTelarId' => '207', 'NoProduccion' => null, 'OrdCompartida' => 900000007, 'OrdCompartidaLider' => null, 'FechaInicio' => '2026-10-05 06:00:00'],
            ['Id' => 8, 'NoTelarId' => '208', 'NoProduccion' => null, 'OrdCompartida' => 900000007, 'OrdCompartidaLider' => null, 'FechaInicio' => '2026-10-04 06:00:00'],
        ]);
        $this->assertNull(OrdCompartida::recalcularLiderYOrdPrincipalPorOrdCompartida(900000007));
        $this->assertSame(0, DB::table('ReqProgramaTejido')->where('OrdCompartida', 900000007)->where('OrdCompartidaLider', 1)->count());
    }

    public function test_al_liberar_la_primera_el_grupo_temporal_toma_su_no_produccion_y_ella_queda_de_lider(): void
    {
        DB::table('ReqProgramaTejido')->insert([
            ['Id' => 7, 'NoTelarId' => '207', 'NoProduccion' => '37070', 'ItemId' => 'A9', 'OrdCompartida' => 900000007, 'OrdCompartidaLider' => null, 'FechaInicio' => '2026-10-05 06:00:00'],
            ['Id' => 8, 'NoTelarId' => '208', 'NoProduccion' => null, 'ItemId' => 'A9', 'OrdCompartida' => 900000007, 'OrdCompartidaLider' => null, 'FechaInicio' => '2026-10-04 06:00:00'],
            ['Id' => 9, 'NoTelarId' => '209', 'NoProduccion' => null, 'ItemId' => 'A9', 'OrdCompartida' => 900000007, 'OrdCompartidaLider' => null, 'FechaInicio' => '2026-10-06 06:00:00'],
        ]);
        DB::table('CatCodificados')->insert(['Id' => 17, 'OrdenTejido' => '37070', 'TelarId' => '207']);
        $registro = ReqProgramaTejido::find(7);

        OrdCompartida::fijarAlLiberar($registro);

        $grupo = DB::table('ReqProgramaTejido')->whereIn('Id', [7, 8, 9])->orderBy('Id')->get();
        $this->assertSame([37070, 37070, 37070], $grupo->pluck('OrdCompartida')->map(fn ($v) => (int) $v)->all());
        // La 8 empieza antes, pero sin NoProduccion no puede ser líder.
        $this->assertSame([1, null, null], $grupo->pluck('OrdCompartidaLider')->map(fn ($v) => $v === null ? null : (int) $v)->all());
        $this->assertSame([37070, 37070, 37070], $grupo->pluck('OrdPrincipal')->map(fn ($v) => (int) $v)->all());
        // El registro en memoria ya trae lo nuevo: el writer de CatCodificados lo copia tal cual.
        $this->assertSame([37070, 1, 37070], [(int) $registro->OrdCompartida, (int) $registro->OrdCompartidaLider, (int) $registro->OrdPrincipal]);
        $this->assertSame([37070, 37070, 1], array_map('intval', array_values((array) DB::table('CatCodificados')->where('Id', 17)->first(['OrdPrincipal', 'OrdCompartida', 'OrdCompartidaLider']))));

        // La segunda que se libera ya no cambia el número ni el líder.
        DB::table('ReqProgramaTejido')->where('Id', 8)->update(['NoProduccion' => '37071']);
        OrdCompartida::fijarAlLiberar(ReqProgramaTejido::find(8));
        $this->assertSame(1, DB::table('ReqProgramaTejido')->where('OrdCompartida', 37070)->where('OrdCompartidaLider', 1)->count());
        $this->assertSame(1, (int) DB::table('ReqProgramaTejido')->where('Id', 7)->value('OrdCompartidaLider'));
    }

    public function test_al_liberar_no_toca_grupos_normales_ni_ordenes_sin_grupo(): void
    {
        DB::table('ReqProgramaTejido')->insert(['Id' => 7, 'NoTelarId' => '207', 'NoProduccion' => '37075', 'OrdCompartida' => null]);
        $antes = DB::table('ReqProgramaTejido')->orderBy('Id')->get();

        OrdCompartida::fijarAlLiberar(ReqProgramaTejido::find(2));
        OrdCompartida::fijarAlLiberar(ReqProgramaTejido::find(7));

        $this->assertEquals($antes, DB::table('ReqProgramaTejido')->orderBy('Id')->get());
    }

    /** Muestras libera con folio M: el grupo, el líder y OrdPrincipal llevan "M3175" tal cual. */
    public function test_en_muestras_el_grupo_temporal_toma_el_folio_m_y_es_principal(): void
    {
        DB::table('ReqProgramaTejido')->insert([
            ['Id' => 7, 'NoTelarId' => '207', 'NoProduccion' => 'M3175', 'OrdCompartida' => '900000007', 'OrdCompartidaLider' => null, 'FechaInicio' => '2026-10-05 06:00:00'],
            ['Id' => 8, 'NoTelarId' => '208', 'NoProduccion' => null, 'OrdCompartida' => '900000007', 'OrdCompartidaLider' => null, 'FechaInicio' => '2026-10-04 06:00:00'],
        ]);
        DB::table('CatCodificados')->insert(['Id' => 17, 'OrdenTejido' => 'M3175', 'TelarId' => '207']);
        $registro = ReqProgramaTejido::find(7);

        OrdCompartida::fijarAlLiberar($registro);

        $grupo = DB::table('ReqProgramaTejido')->whereIn('Id', [7, 8])->orderBy('Id')->get(['OrdCompartida', 'OrdCompartidaLider', 'OrdPrincipal']);
        $this->assertSame([['M3175', 1, 'M3175'], ['M3175', null, 'M3175']], $grupo->map(fn ($r) => [$r->OrdCompartida, $r->OrdCompartidaLider === null ? null : (int) $r->OrdCompartidaLider, $r->OrdPrincipal])->all());
        $this->assertSame(['M3175', 'M3175'], [$registro->OrdCompartida, $registro->OrdPrincipal]);
        $cat = DB::table('CatCodificados')->where('Id', 17)->first();
        $this->assertSame(['M3175', 'M3175', 1], [$cat->OrdCompartida, $cat->OrdPrincipal, (int) $cat->OrdCompartidaLider]);

        // Dividir/Vincular/Balancear lo buscan como texto.
        $this->assertSame(7, OrdCompartida::recalcularLiderYOrdPrincipalPorOrdCompartida(' M3175 '));
    }
}
