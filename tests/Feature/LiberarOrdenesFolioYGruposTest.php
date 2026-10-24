<?php

namespace Tests\Feature;

use App\Http\Controllers\Planeacion\ProgramaTejido\LiberarOrdenesController;
use App\Models\Sistema\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\Feature\Concerns\EscenarioLiberarOrdenes;
use Tests\TestCase;

/**
 * Liberar (23-oct-2026): cada superficie toma su folio (Programa 37071, Muestras M3175), un grupo
 * con número temporal toma el NoProduccion de su primera orden liberada, la pantalla de Muestras
 * usa sus rutas y un renglón ya liberado no se vuelve a liberar.
 */
class LiberarOrdenesFolioYGruposTest extends TestCase
{
    use EscenarioLiberarOrdenes;
    use UsesSqlsrvSqlite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararLiberar();
    }

    protected function tearDown(): void
    {
        $this->limpiarLiberar();
        parent::tearDown();
    }

    /** Tres órdenes en un grupo con número temporal (Vincular del modal o Dividir sin liberar). */
    private function sembrarGrupoTemporal(int $temporal): array
    {
        return [
            $this->sembrarRegistro(['NoTelarId' => '201', 'OrdCompartida' => $temporal]),
            // La más antigua, pero sin NoProduccion no puede ser líder.
            $this->sembrarRegistro(['NoTelarId' => '202', 'OrdCompartida' => $temporal, 'FechaInicio' => Carbon::now()->subDays(5)->format('Y-m-d H:i:s')]),
            $this->sembrarRegistro(['NoTelarId' => '203', 'OrdCompartida' => $temporal]),
        ];
    }

    /** @return list<array{0: int, 1: int|null, 2: int|null}> OrdCompartida, líder y OrdPrincipal por Id */
    private function estadoGrupo(array $ids): array
    {
        return DB::connection('sqlsrv')->table('ReqProgramaTejido')->whereIn('Id', $ids)->orderBy('Id')->get()
            ->map(fn ($r) => [(int) $r->OrdCompartida, $r->OrdCompartidaLider === null ? null : (int) $r->OrdCompartidaLider, $r->OrdPrincipal === null ? null : (int) $r->OrdPrincipal])
            ->all();
    }

    private function catDe(string $orden): array
    {
        $cat = DB::connection('sqlsrv')->table('CatCodificados')->where('OrdenTejido', $orden)->first();

        return [(int) $cat->OrdCompartida, $cat->OrdCompartidaLider === null ? null : (int) $cat->OrdCompartidaLider, (int) $cat->OrdPrincipal];
    }

    /**
     * La primera orden que se libera de un grupo temporal le da su NoProduccion: todo el grupo
     * cambia de número, ella queda de líder y su CatCodificados ya sale con eso. Las siguientes no
     * cambian ni el número ni el líder.
     */
    public function test_la_primera_liberada_de_un_grupo_temporal_le_da_su_no_produccion_y_queda_de_lider(): void
    {
        [$a, $b, $c] = $this->sembrarGrupoTemporal(900000101);
        foreach (['77010' => '201', '77011' => '202'] as $orden => $telar) {
            DB::connection('sqlsrv')->table('CatCodificados')->insert(['OrdenTejido' => $orden, 'TelarId' => $telar, 'ItemId' => 'IT100', 'InventSizeId' => 'STD', 'Departamento' => 'JACQUARD']);
        }

        $this->assertTrue($this->liberar([$this->itemLiberar($a, '77010')])->getData(true)['success']);

        $this->assertSame([[77010, 1, 77010], [77010, null, 77010], [77010, null, 77010]], $this->estadoGrupo([$a, $b, $c]));
        $this->assertSame([77010, 1, 77010], $this->catDe('77010'));
        $this->assertSame(0, DB::connection('sqlsrv')->table('ReqProgramaTejido')->where('OrdCompartida', 900000101)->count());

        // La segunda (más antigua) se libera después: el líder y el número no cambian.
        $this->assertTrue($this->liberar([$this->itemLiberar($b, '77011')])->getData(true)['success']);

        $this->assertSame([[77010, 1, 77010], [77010, null, 77010], [77010, null, 77010]], $this->estadoGrupo([$a, $b, $c]));
        $this->assertSame([77010, null, 77010], $this->catDe('77011'));
    }

    /** En un lote con dos del mismo grupo temporal, manda la primera del lote. */
    public function test_lote_con_dos_del_mismo_grupo_temporal_toma_el_numero_de_la_primera_del_lote(): void
    {
        [$a, $b, $c] = $this->sembrarGrupoTemporal(900000201);

        $this->assertTrue($this->liberar([$this->itemLiberar($c, '77020'), $this->itemLiberar($a, '77021')])->getData(true)['success']);

        $this->assertSame([[77020, null, 77020], [77020, null, 77020], [77020, 1, 77020]], $this->estadoGrupo([$a, $b, $c]));
    }

    /** Un grupo normal (número = NoProduccion de su líder) no cambia al liberar a otro miembro. */
    public function test_liberar_un_miembro_de_grupo_normal_no_cambia_numero_ni_lider(): void
    {
        $lider = $this->sembrarRegistro(['NoTelarId' => '201', 'NoProduccion' => '77030', 'OrdCompartida' => 77030, 'OrdCompartidaLider' => 1, 'OrdPrincipal' => 77030]);
        $parte = $this->sembrarRegistro(['NoTelarId' => '202', 'OrdCompartida' => 77030, 'OrdPrincipal' => 77030]);

        $this->assertTrue($this->liberar([$this->itemLiberar($parte, '77031')])->getData(true)['success']);

        $this->assertSame([[77030, 1, 77030], [77030, null, 77030]], $this->estadoGrupo([$lider, $parte]));
    }

    public function test_programa_libera_con_el_folio_de_planeacion(): void
    {
        $this->sembrarFolios();
        $id = $this->sembrarRegistro();

        $this->assertTrue($this->liberar([['id' => $id, 'bomId' => 'BOM-CRUDO-01', 'bomName' => 'LISTA MATERIALES CRUDO 01']])->getData(true)['success']);

        $this->assertSame('37071', DB::connection('sqlsrv')->table('ReqProgramaTejido')->where('Id', $id)->value('NoProduccion'));
        $this->assertSame(['Planeacion' => 37071, 'Muestras' => 3174], $this->consecutivos());
    }

    /** Antes Muestras tomaba el folio de Planeacion: ahora usa su secuencia con prefijo M. */
    public function test_muestras_libera_con_su_folio_m_y_no_consume_el_de_planeacion(): void
    {
        $this->sembrarFolios();
        $id = $this->sembrarRegistro();

        $this->assertTrue($this->liberar([['id' => $id, 'bomId' => 'BOM-CRUDO-01', 'bomName' => 'LISTA MATERIALES CRUDO 01']], '/planeacion/muestras/liberar-ordenes/procesar')->getData(true)['success']);

        $this->assertSame('M3175', DB::connection('sqlsrv')->table('ReqProgramaTejido')->where('Id', $id)->value('NoProduccion'));
        $this->assertSame(['Planeacion' => 37070, 'Muestras' => 3175], $this->consecutivos());
    }

    /** Un grupo de Muestras (número temporal) toma el folio M de su primera orden, también en CatCodificados. */
    public function test_muestras_el_grupo_temporal_toma_el_folio_m(): void
    {
        $this->sembrarFolios();
        [$a, $b] = [
            $this->sembrarRegistro(['NoTelarId' => '201', 'OrdCompartida' => '900000301']),
            $this->sembrarRegistro(['NoTelarId' => '202', 'OrdCompartida' => '900000301']),
        ];
        DB::connection('sqlsrv')->table('CatCodificados')->insert(['OrdenTejido' => 'M3175', 'TelarId' => '201', 'ItemId' => 'IT100', 'InventSizeId' => 'STD', 'Departamento' => 'JACQUARD']);

        $this->assertTrue($this->liberar([$this->itemLiberar($a, '')], '/planeacion/muestras/liberar-ordenes/procesar')->getData(true)['success']);

        $grupo = DB::connection('sqlsrv')->table('ReqProgramaTejido')->whereIn('Id', [$a, $b])->orderBy('Id')->get();
        $this->assertSame([['M3175', 'M3175', 'M3175'], [null, 'M3175', 'M3175']], $grupo->map(fn ($r) => [$r->NoProduccion, $r->OrdCompartida, $r->OrdPrincipal])->all());
        $this->assertSame([1, null], $grupo->map(fn ($r) => $r->OrdCompartidaLider === null ? null : (int) $r->OrdCompartidaLider)->all());
        $cat = DB::connection('sqlsrv')->table('CatCodificados')->where('OrdenTejido', 'M3175')->first();
        $this->assertSame(['M3175', 'M3175', 1], [$cat->OrdCompartida, $cat->OrdPrincipal, (int) $cat->OrdCompartidaLider]);
    }

    /** @return array{rutas: array<string, string>, html: string} la vista de Liberar renderizada en $ruta */
    private function paginaLiberar(string $ruta): array
    {
        $this->attachDboFlogTables();
        $request = Request::create($ruta, 'GET', ['dias' => 10.999]);
        app()->instance('request', $request);
        $view = (new LiberarOrdenesController)->index($request);
        $this->actingAs(new Usuario(['nombre' => 'QA']));
        $html = $view->render();
        $this->assertSame(1, preg_match("/data-pagina='([^']+)'/", $html, $m));

        return ['rutas' => json_decode(html_entity_decode($m[1]), true)['rutas'], 'html' => $html];
    }

    /**
     * 23-oct-2026: la pantalla de Muestras posteaba a la ruta de Programa. Como MuestrasPrograma
     * comparte los Id, liberar una muestra re-liberaba la orden de Programa con ese Id.
     */
    public function test_la_pagina_de_muestras_usa_las_rutas_de_muestras(): void
    {
        $this->sembrarFolios();
        $this->sembrarRegistro();
        ['rutas' => $muestras, 'html' => $html] = $this->paginaLiberar('/planeacion/muestras/liberar-ordenes');
        $this->assertSame(route('muestras.liberar-ordenes.procesar'), $muestras['procesar']);
        $this->assertSame(route('muestras.index'), $muestras['redirect']);
        foreach (['tipoHilo', 'bom', 'codigoDibujo', 'flog'] as $clave) {
            $this->assertStringContainsString('/planeacion/muestras/liberar-ordenes/', $muestras[$clave], $clave);
        }

        // Dice que son muestras y qué folio va a tomar (sin consumirlo).
        $this->assertStringContainsString('<title>Liberar Muestras · Towell</title>', $html);
        $this->assertStringContainsString('placeholder="Auto (desde M3175)"', $html);

        ['rutas' => $programa, 'html' => $html] = $this->paginaLiberar('/planeacion/programa-tejido/liberar-ordenes');
        $this->assertSame(route('programa-tejido.liberar-ordenes.procesar'), $programa['procesar']);
        $this->assertSame(route('catalogos.req-programa-tejido'), $programa['redirect']);
        $this->assertStringContainsString('<title>Liberar Órdenes · Towell</title>', $html);
        $this->assertStringContainsString('placeholder="Auto (desde 37071)"', $html);
        $this->assertSame(['Planeacion' => 37070, 'Muestras' => 3174], $this->consecutivos(), 'la vista previa no consume folios');
    }

    /** Un renglón que ya tiene NoProduccion no se vuelve a liberar (no se pisa su orden ni sus marbetes). */
    public function test_no_vuelve_a_liberar_un_registro_que_ya_tiene_no_produccion(): void
    {
        $liberado = $this->sembrarRegistro(['NoProduccion' => '37040', 'TotalRollos' => 24, 'SaldoMarbete' => 24]);
        $nuevo = $this->sembrarRegistro(['NoTelarId' => '202']);
        $antes = DB::connection('sqlsrv')->table('ReqProgramaTejido')->orderBy('Id')->get();

        $response = $this->liberar([$this->itemLiberar($nuevo, '77050'), $this->itemLiberar($liberado, '77051')]);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString($liberado.' (orden 37040)', $response->getData(true)['message']);
        $this->assertEquals($antes, DB::connection('sqlsrv')->table('ReqProgramaTejido')->orderBy('Id')->get(), 'no se escribe nada del lote');
    }
}
