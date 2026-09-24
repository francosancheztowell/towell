<?php

namespace Tests\Feature\Planeacion;

use App\Models\Planeacion\ReqProgramaTejido;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Planeacion\Concerns\ProgramaTejidoFixtures;
use Tests\TestCase;

/**
 * PT-01 · 01.5 — Efectos derivados del observer y catches silenciosos.
 *
 * PT-01 congeló los fallos que el observer se tragaba (Log::warning + éxito aparente).
 * PT-02 los contuvo y los tests se INVIRTIERON (no se borraron): hallazgos 1, 4, 5 y 6
 * de 01-SUMMARY.md. Cada test nombra el hallazgo que cubre.
 */
class ProgramaTejidoInvariantTest extends TestCase
{
    use ProgramaTejidoFixtures;

    /** @var list<string> Log::warning emitidos durante el test */
    private array $avisos = [];

    /** @var list<string> Log::error emitidos durante el test */
    private array $errores = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararSuperficies();
        $this->sembrarFixtures();

        Log::listen(function ($evento) {
            if ($evento->level === 'warning') {
                $this->avisos[] = $evento->message;
            }
            if ($evento->level === 'error') {
                $this->errores[] = $evento->message;
            }
        });
    }

    private function gateSano(): void
    {
        $this->assertSame(0, Artisan::call('planeacion:programa-tejido-health', ['--json' => true]), Artisan::output());
    }

    public function test_crear_un_programa_genera_lineas_que_cuadran_con_la_cabecera(): void
    {
        $this->usarSuperficie('programa');

        $nuevo = ReqProgramaTejido::create([
            'SalonTejidoId' => 'SMIT', 'NoTelarId' => '206', 'Posicion' => 1, 'EnProceso' => 0,
            'SaldoPedido' => 600, 'PesoCrudo' => 450,
            'FechaInicio' => '2026-09-10 06:30:00', 'FechaFinal' => '2026-09-12 18:00:00',
        ]);

        $lineas = DB::table('ReqProgramaTejidoLine')->where('ProgramaId', $nuevo->Id)->orderBy('Fecha')->get();

        // 59.5 h de producción: 17.5 h el primer día, 24 h el segundo, 18 h el último;
        // 600 / 59.5 = 10.0840336 piezas/h repartidas por horas de cada día.
        $this->assertSame(['2026-09-10', '2026-09-11', '2026-09-12'], $lineas->map(fn ($l) => substr($l->Fecha, 0, 10))->all());
        $this->assertEqualsWithDelta([176.470588, 242.016807, 181.512605], $lineas->pluck('Cantidad')->map(fn ($v) => (float) $v)->all(), 1e-6);
        $this->assertEqualsWithDelta(600.0, (float) $lineas->sum('Cantidad'), 1e-6);
        $this->assertSame(0, DB::table('MuestrasProgramaLine')->where('ProgramaId', $nuevo->Id)->count());
        $this->gateSano();
    }

    public function test_editar_no_toca_fecha_finaliza_y_prioridad_sigue_siendo_texto(): void
    {
        $this->usarSuperficie('programa');

        $registro = ReqProgramaTejido::find(1);
        $registro->SaldoPedido = 750;
        $registro->FechaInicio = '2026-09-01 08:00:00';
        $registro->save();

        $fila = DB::table('ReqProgramaTejido')->where('Id', 1)->first();
        $this->assertNull($fila->FechaFinaliza);
        $this->assertSame('SALDAR 123', $fila->Prioridad);
        $this->gateSano();
    }

    public function test_editar_pedido_recalcula_produccion_y_sincroniza_cat_codificados_solo_de_su_telar(): void
    {
        $this->usarSuperficie('programa');

        $registro = ReqProgramaTejido::find(3); // orden 30003 repartida en telares 202 y 203
        $registro->TotalPedido = 1100;
        $registro->PesoCrudo = 450;
        $registro->NoTiras = 2;
        $registro->LargoCrudo = 70;
        $registro->save();

        // Sin PesoRollo capturado ni maestro ReqPesosRollosTejido (la tabla ni existe en el
        // fixture) → respaldo de 41.5 kg. Repeticiones = TRUNC(41.5/450/2*1000) = 46.
        // Hallazgo 6 (invertido en PT-02): el respaldo ya no es silencioso.
        $this->assertContains('ReqProgramaTejidoObserver::obtenerPesoRolloMaestro error', $this->errores);
        $fila = DB::table('ReqProgramaTejido')->where('Id', 3)->first();
        $this->assertEquals(46, $fila->Repeticiones);
        $this->assertEquals(92, $fila->PzasRollo);
        $this->assertEqualsWithDelta(32.2, $fila->MtsRollo, 1e-6);
        $this->assertEquals(12, $fila->TotalRollos); // CEIL(1100 / 92)
        $this->assertEquals(1104, $fila->TotalPzas);
        $this->assertEquals(12, $fila->RollosProgramados);

        $cat202 = DB::table('CatCodificados')->where('OrdenTejido', '30003')->where('TelarId', 202)->first();
        $cat203 = DB::table('CatCodificados')->where('OrdenTejido', '30003')->where('TelarId', 203)->first();
        $this->assertEquals(1100, $cat202->Pedido);
        $this->assertEquals(12, $cat202->TotalRollos);
        $this->assertEquals(400, $cat203->Pedido, 'El sync pisó el otro telar de la misma orden');
        $this->assertNull($cat203->TotalRollos);
    }

    public function test_en_muestras_el_recalculo_de_produccion_persiste_las_formulas(): void
    {
        // Hallazgo 1, invertido en PT-02 (antes: ..._falla_en_silencio). El UPDATE se filtra a
        // las columnas físicas de MuestrasPrograma, que todavía no tiene RollosProgramados.
        $this->usarSuperficie('muestras');

        $registro = ReqProgramaTejido::find(1);
        $registro->TotalPedido = 1100;
        $this->assertTrue($registro->save());

        $fila = DB::table('MuestrasPrograma')->where('Id', 1)->first();
        $this->assertEquals(1100, $fila->TotalPedido);
        $this->assertEquals(46, $fila->Repeticiones);
        $this->assertEquals(92, $fila->PzasRollo);
        $this->assertEquals(12, $fila->TotalRollos);
        $this->assertEquals(1104, $fila->TotalPzas);
        $this->assertObjectNotHasProperty('RollosProgramados', $fila);
        $this->assertNotContains('ReqProgramaTejidoObserver::recalcularFormulasProduccion error', $this->errores);
        // Programa no se tocó (mismos Id en ambas tablas).
        $this->assertNull(DB::table('ReqProgramaTejido')->where('Id', 1)->value('Repeticiones'));
    }

    public function test_si_falla_insertar_lineas_se_conservan_las_previas_y_el_save_reporta_el_fallo(): void
    {
        // Hallazgo 4, invertido en PT-02 (antes: ..._y_el_save_reporta_exito).
        $this->usarSuperficie('programa');
        Schema::table('ReqProgramaTejidoLine', fn (Blueprint $t) => $t->dropColumn('MtsPie'));

        $registro = ReqProgramaTejido::find(1);
        $registro->SaldoPedido = 700;

        $fallo = null;
        try {
            // Como lo hacen los controllers: la edición va en una transacción.
            DB::transaction(fn () => $registro->save());
        } catch (\Throwable $e) {
            $fallo = $e;
        }

        $this->assertNotNull($fallo, 'El save debe reportar el fallo de las líneas');
        // La línea previa sobrevive (DELETE + INSERT atómicos) y la cabecera tampoco cambió:
        // ya no queda "cabecera nueva con líneas viejas".
        $this->assertSame(1, DB::table('ReqProgramaTejidoLine')->where('ProgramaId', 1)->count());
        $this->assertEquals(10, DB::table('ReqProgramaTejidoLine')->where('ProgramaId', 1)->value('Cantidad'));
        $this->assertEquals(800, DB::table('ReqProgramaTejido')->where('Id', 1)->value('SaldoPedido'));
        $this->assertContains('ReqProgramaTejidoObserver::generarLineasDiarias error', $this->errores);
    }

    public function test_si_falta_cat_codificados_la_cabecera_no_queda_recalculada_y_se_registra(): void
    {
        // Hallazgo 5, invertido en PT-02 (antes: ..._la_cabecera_queda_recalculada_y_cat_sin_sincronizar).
        $this->usarSuperficie('programa');
        Schema::drop('CatCodificados');

        $registro = ReqProgramaTejido::find(1);
        $registro->TotalPedido = 1100;
        $this->assertTrue($registro->save());

        // Cabecera y CatCodificados van en una transacción: si CatCodificados falla, la cabecera
        // conserva sus fórmulas previas en vez de quedar desalineada.
        $this->assertNull(DB::table('ReqProgramaTejido')->where('Id', 1)->value('Repeticiones'));
        // Sin la tabla, el sync ya no se salta en silencio.
        $this->assertContains('ReqProgramaTejidoObserver::sincronizarCatCodificados error', $this->errores);
        $this->assertContains('ReqProgramaTejidoObserver::recalcularFormulasProduccion error', $this->errores);
    }
}
