<?php

namespace Tests\Feature\Planeacion\Rigor;

use App\Models\Planeacion\Catalogos\CatCodificados;
use App\Models\Planeacion\ReqModelosCodificados;
use App\Services\Planeacion\ProgramaTejido\CalendarioProduccion;
use App\Services\Planeacion\ProgramaTejido\FormulasSecuenciaTelar;
use App\Support\ColumnasDeTabla;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Planeacion\Concerns\ConPermisosPlaneacion;
use Tests\Feature\Planeacion\Concerns\ProgramaTejidoFixtures;
use Tests\TestCase;

/**
 * POST recalcular-fechas (ProgramaTejidoCalendariosController::recalcularFechas →
 * Artisan programa-tejido:recalcular-fechas-produccion --all).
 *
 * Fixture propio (no sembrarFixtures) para que las horas sean exactas: modelo 'TC-RIGOR'
 * con Total=99, Luchaje=254, Repeticiones=5000, VelocidadSTD=100, NoTiras=1, Eficiencia=1
 * → StdToaHra = 60 piezas/hora. Sin CalendarioId el fin es continuo: horas = Saldo / 60.
 *
 *   Telar SMIT/201:     Id 1 (Pos1, EnProceso, saldo 600 → 10 h), Id 2 (Pos2, 120 → 2 h),
 *                       Id 3 (Pos3, 300 → 5 h, Ultimo)
 *   Telar JACQUARD/202: Id 4 (Pos1, EnProceso, 240 → 4 h, OrdCompartida 7 líder),
 *                       Id 5 (Pos2, 60 → 1 h, Ultimo)
 *   Telar SMIT/204:     Id 6 (sin FechaInicio: el comando lo ignora)
 *
 * Las dos superficies se siembran con los mismos Id (Muestras con FechaInicio distinta) para
 * detectar escrituras cruzadas.
 */
class RecalcularFechasTest extends TestCase
{
    use ConPermisosPlaneacion;
    use ProgramaTejidoFixtures;

    private const AHORA = '2026-09-10 08:00:00';

    private const URL_PROGRAMA = '/planeacion/programa-tejido/recalcular-fechas';

    private const URL_MUESTRAS = '/planeacion/muestras/recalcular-fechas';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(self::AHORA);
        $this->limpiarCachesEstaticas();

        $this->prepararSuperficies();
        $this->createTablaDesdeModelo(ReqModelosCodificados::class);
        $this->createTablaDbo('ReqCalendarioLine', ['CalendarioId' => 'text', 'FechaInicio' => 'text', 'FechaFin' => 'text']);

        DB::table((new ReqModelosCodificados)->getTable())->insert([
            'TamanoClave' => 'TC-RIGOR', 'Total' => 99, 'NoTiras' => 1, 'Luchaje' => 254, 'Repeticiones' => 5000,
        ]);

        $this->sembrarTelares('ReqProgramaTejido', '2026-09-01');
        $this->sembrarTelares('MuestrasPrograma', '2026-08-01');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->limpiarCachesEstaticas();
        parent::tearDown();
    }

    // ---- autorización y forma de la respuesta ----------------------------------------

    public function test_recalcular_responde_ok_true_con_la_salida_del_comando_como_mensaje(): void
    {
        $res = $this->como()->postJson(self::URL_PROGRAMA)->assertOk();

        $res->assertJsonStructure(['ok', 'message']);
        $this->assertSame(['ok', 'message'], array_keys($res->json()));
        $this->assertTrue($res->json('ok'));
        $this->assertStringContainsString('Procesando 5 registro(s)...', $res->json('message'));
        $this->assertStringContainsString('Listo: 5 recalculados, 0 omitidos/error.', $res->json('message'));
    }

    public function test_sin_permiso_modificar_es_403_y_no_toca_ninguna_tabla(): void
    {
        $antes = $this->fotoSuperficies();

        $this->como([2 => ['acceso', 'crear', 'eliminar']])->postJson(self::URL_PROGRAMA)->assertForbidden();

        $this->assertSame($antes, $this->fotoSuperficies());
    }

    public function test_sin_sesion_es_401_y_no_toca_ninguna_tabla(): void
    {
        $antes = $this->fotoSuperficies();

        $this->postJson(self::URL_PROGRAMA)->assertUnauthorized();

        $this->assertSame($antes, $this->fotoSuperficies());
    }

    public function test_el_permiso_de_programa_no_autoriza_recalcular_muestras(): void
    {
        $antes = $this->fotoSuperficies();

        $this->como([2 => ['acceso', 'modificar']])->postJson(self::URL_MUESTRAS)->assertForbidden();

        $this->assertSame($antes, $this->fotoSuperficies());
    }

    public function test_recalcular_por_get_no_existe(): void
    {
        $antes = $this->fotoSuperficies();

        $this->como()->getJson(self::URL_PROGRAMA)->assertStatus(405);

        $this->assertSame($antes, $this->fotoSuperficies());
    }

    // ---- cálculo de fechas -----------------------------------------------------------

    public function test_el_en_proceso_arranca_en_now_y_termina_al_consumir_sus_horas(): void
    {
        $this->como()->postJson(self::URL_PROGRAMA)->assertOk();

        $this->assertFechas(1, self::AHORA, '2026-09-10 18:00:00');
        $this->assertFechas(4, self::AHORA, '2026-09-10 12:00:00');
    }

    public function test_cada_registro_del_telar_empieza_exactamente_donde_termina_el_anterior(): void
    {
        $this->como()->postJson(self::URL_PROGRAMA)->assertOk();

        // SMIT/201: 10 h + 2 h + 5 h encadenadas desde now().
        $this->assertFechas(1, '2026-09-10 08:00:00', '2026-09-10 18:00:00');
        $this->assertFechas(2, '2026-09-10 18:00:00', '2026-09-10 20:00:00');
        $this->assertFechas(3, '2026-09-10 20:00:00', '2026-09-11 01:00:00');

        foreach (['201', '202'] as $telar) {
            $filas = DB::table('ReqProgramaTejido')->where('NoTelarId', $telar)->orderBy('Posicion')->get();
            for ($i = 1; $i < $filas->count(); $i++) {
                $this->assertSame($filas[$i - 1]->FechaFinal, $filas[$i]->FechaInicio, "telar {$telar}, posición ".($i + 1));
            }
        }
    }

    public function test_las_cadenas_de_telares_distintos_no_se_cruzan(): void
    {
        $this->como()->postJson(self::URL_PROGRAMA)->assertOk();

        // JACQUARD/202 encadena desde su propio EnProceso (4 h), no desde el fin de SMIT/201.
        $this->assertFechas(4, '2026-09-10 08:00:00', '2026-09-10 12:00:00');
        $this->assertFechas(5, '2026-09-10 12:00:00', '2026-09-10 13:00:00');
    }

    public function test_conserva_posicion_en_proceso_ultimo_orden_compartida_y_cantidades(): void
    {
        $campos = ['Id', 'SalonTejidoId', 'NoTelarId', 'Posicion', 'EnProceso', 'Ultimo', 'OrdCompartida',
            'OrdCompartidaLider', 'TotalPedido', 'SaldoPedido', 'Produccion', 'NoProduccion', 'TamanoClave'];
        $foto = fn () => DB::table('ReqProgramaTejido')->orderBy('Id')->get($campos)->map(fn ($r) => (array) $r)->all();
        $antes = $foto();

        $this->como()->postJson(self::URL_PROGRAMA)->assertOk();

        $this->assertEquals($antes, $foto());
    }

    public function test_un_registro_sin_fecha_inicio_no_se_toca(): void
    {
        $antes = (array) DB::table('ReqProgramaTejido')->where('Id', 6)->first();

        $this->como()->postJson(self::URL_PROGRAMA)->assertOk();

        $this->assertSame($antes, (array) DB::table('ReqProgramaTejido')->where('Id', 6)->first());
    }

    public function test_si_el_en_proceso_no_es_el_primero_lo_mueve_al_de_menor_posicion(): void
    {
        DB::table('ReqProgramaTejido')->where('Id', 1)->update(['EnProceso' => 0]);
        DB::table('ReqProgramaTejido')->where('Id', 2)->update(['EnProceso' => 1]);

        $res = $this->como()->postJson(self::URL_PROGRAMA)->assertOk();

        $this->assertStringContainsString('Telar SMIT/201: EnProceso corregido → Id=1 (Pos=1)', $res->json('message'));
        $this->assertSame([1 => 1, 2 => 0, 3 => 0], $this->enProcesoDe('201'));
        // Con el EnProceso corregido, la cadena vuelve a arrancar en now().
        $this->assertFechas(1, self::AHORA, '2026-09-10 18:00:00');
        $this->assertFechas(2, '2026-09-10 18:00:00', '2026-09-10 20:00:00');
        // El otro telar no se reporta como corregido.
        $this->assertStringNotContainsString('JACQUARD/202', $res->json('message'));
    }

    public function test_un_telar_sin_en_proceso_recibe_uno_en_el_de_menor_posicion(): void
    {
        DB::table('ReqProgramaTejido')->where('NoTelarId', '202')->update(['EnProceso' => 0]);

        $res = $this->como()->postJson(self::URL_PROGRAMA)->assertOk();

        $this->assertStringContainsString('no había ninguno', $res->json('message'));
        $this->assertSame([4 => 1, 5 => 0], $this->enProcesoDe('202'));
        $this->assertFechas(4, self::AHORA, '2026-09-10 12:00:00');
    }

    public function test_es_idempotente_con_el_mismo_reloj(): void
    {
        $this->como()->postJson(self::URL_PROGRAMA)->assertOk();
        $primera = DB::table('ReqProgramaTejido')->orderBy('Id')->get(['Id', 'FechaInicio', 'FechaFinal', 'EnProceso', 'Ultimo', 'Posicion'])->map(fn ($r) => (array) $r)->all();

        $this->como()->postJson(self::URL_PROGRAMA)->assertOk();
        $segunda = DB::table('ReqProgramaTejido')->orderBy('Id')->get(['Id', 'FechaInicio', 'FechaFinal', 'EnProceso', 'Ultimo', 'Posicion'])->map(fn ($r) => (array) $r)->all();

        $this->assertSame($primera, $segunda);
    }

    public function test_el_inicio_no_en_proceso_se_ajusta_a_la_siguiente_linea_del_calendario(): void
    {
        // Turnos de 06:00 a 14:00: el EnProceso no hace snap (arranca en now() = 08:00, 10 h
        // consumen 6 h del día 10 y 4 h del día 11 → 10:00). El siguiente arranca ahí, dentro de línea.
        DB::table('ReqProgramaTejido')->whereIn('Id', [1, 2, 3])->update(['CalendarioId' => 'CAL-RIGOR']);
        foreach (['2026-09-10', '2026-09-11', '2026-09-12', '2026-09-13'] as $dia) {
            DB::table('dbo.ReqCalendarioLine')->insert([
                'CalendarioId' => 'CAL-RIGOR', 'FechaInicio' => "{$dia} 06:00:00", 'FechaFin' => "{$dia} 14:00:00",
            ]);
        }

        $this->como()->postJson(self::URL_PROGRAMA)->assertOk();

        $this->assertFechas(1, self::AHORA, '2026-09-11 10:00:00');
        $this->assertFechas(2, '2026-09-11 10:00:00', '2026-09-11 12:00:00');
        // 5 h desde las 12:00: 2 h el día 11 y 3 h el 12 desde las 06:00.
        $this->assertFechas(3, '2026-09-11 12:00:00', '2026-09-12 09:00:00');
    }

    public function test_programa_no_toca_las_tablas_de_muestras(): void
    {
        $muestras = fn () => [
            DB::table('MuestrasPrograma')->orderBy('Id')->get()->map(fn ($r) => (array) $r)->all(),
            DB::table('MuestrasProgramaLine')->orderBy('Id')->get()->map(fn ($r) => (array) $r)->all(),
        ];
        $antes = $muestras();

        $this->como()->postJson(self::URL_PROGRAMA)->assertOk();

        $this->assertSame($antes, $muestras());
    }

    public function test_muestras_recalcula_solo_muestras_con_su_propio_permiso(): void
    {
        $programa = fn () => [
            DB::table('ReqProgramaTejido')->orderBy('Id')->get()->map(fn ($r) => (array) $r)->all(),
            DB::table('ReqProgramaTejidoLine')->orderBy('Id')->get()->map(fn ($r) => (array) $r)->all(),
        ];
        $antes = $programa();

        $this->como([5 => ['acceso', 'modificar']])->postJson(self::URL_MUESTRAS)
            ->assertOk()->assertJsonPath('ok', true);

        $this->assertSame($antes, $programa());
        $fila = DB::table('MuestrasPrograma')->where('Id', 2)->first();
        $this->assertSame('2026-09-10 18:00:00', $fila->FechaInicio);
        $this->assertSame('2026-09-10 20:00:00', $fila->FechaFinal);
    }

    public function test_regenera_las_lineas_diarias_de_los_registros_recalculados(): void
    {
        $this->como()->postJson(self::URL_PROGRAMA)->assertOk();

        // Las líneas sembradas (Fecha 2026-09-01) se reemplazan por las del nuevo rango.
        $fechas = DB::table('ReqProgramaTejidoLine')->where('ProgramaId', 3)->pluck('Fecha')->map(fn ($f) => substr((string) $f, 0, 10))->all();
        $this->assertNotContains('2026-09-01', $fechas);
        $this->assertContains('2026-09-10', $fechas);
        $this->assertContains('2026-09-11', $fechas);
        // El registro sin FechaInicio conserva su línea vieja.
        $this->assertSame(1, DB::table('ReqProgramaTejidoLine')->where('ProgramaId', 6)->count());
    }

    // ---- caracterizaciones -----------------------------------------------------------

    public function test_saldo_negativo_en_proceso_termina_en_now(): void
    {
        DB::table('ReqProgramaTejido')->where('Id', 1)->update(['SaldoPedido' => -50]);

        $this->como()->postJson(self::URL_PROGRAMA)->assertOk();

        $this->assertFechas(1, self::AHORA, self::AHORA);
        $this->assertFechas(2, self::AHORA, '2026-09-10 10:00:00');
    }

    public function test_sin_parametros_de_modelo_el_comando_usa_horas_prod_guardadas_como_la_cascada(): void
    {
        // Id 2 trae HorasProd=2 pero sin TamanoClave no hay horas calculables: tanto la cascada
        // como el propio recálculo del registro usan las 2 h guardadas (no 30 días por defecto).
        DB::table('ReqProgramaTejido')->where('Id', 2)->update(['TamanoClave' => null, 'HorasProd' => 2]);

        $this->como()->postJson(self::URL_PROGRAMA)->assertOk();

        $this->assertFechas(2, '2026-09-10 18:00:00', '2026-09-10 20:00:00');
        $this->assertFechas(3, '2026-09-10 20:00:00', '2026-09-11 01:00:00');
        $this->assertEquals(2.0, (float) DB::table('ReqProgramaTejido')->where('Id', 2)->value('HorasProd'));
    }

    public function test_un_registro_con_fecha_inicio_ilegible_cuenta_como_error_y_aun_asi_responde_ok_true(): void
    {
        // CARACTERIZA: el controller responde 200 + ok:true aunque el comando reporte errores;
        // la UI muestra "completado". '!!' ordena antes que las fechas, así que se procesa antes
        // que el EnProceso del telar, falla el Carbon::parse y luego la cascada del EnProceso
        // lo repara con fechas válidas.
        DB::table('ReqProgramaTejido')->where('Id', 5)->update(['FechaInicio' => '!!']);

        $res = $this->como()->postJson(self::URL_PROGRAMA)->assertOk();

        $this->assertTrue($res->json('ok'));
        $this->assertStringContainsString('Listo: 4 recalculados, 1 omitidos/error.', $res->json('message'));
        $this->assertFechas(4, self::AHORA, '2026-09-10 12:00:00');
        $this->assertFechas(5, '2026-09-10 12:00:00', '2026-09-10 13:00:00');
    }

    public function test_un_en_proceso_marcado_ultimo_por_error_igual_encadena_a_sus_posteriores(): void
    {
        // La cascada se decide por si hay posteriores reales en el telar (Posicion mayor), no por
        // el flag Ultimo: con Ultimo='1' mal puesto en el EnProceso la cadena sigue encadenada.
        DB::table('ReqProgramaTejido')->where('Id', 1)->update(['Ultimo' => '1']);

        $this->como()->postJson(self::URL_PROGRAMA)->assertOk();

        $this->assertFechas(1, self::AHORA, '2026-09-10 18:00:00');
        $this->assertFechas(2, '2026-09-10 18:00:00', '2026-09-10 20:00:00');
        $this->assertFechas(3, '2026-09-10 20:00:00', '2026-09-11 01:00:00');
    }

    public function test_al_terminar_restaura_el_dispatcher_de_eventos_eloquent(): void
    {
        // El comando suprime los observers y en el finally restaura el MISMO dispatcher:
        // lo que corra después en el mismo proceso sigue disparando observers y eventos.
        $antes = Model::getEventDispatcher();
        $this->assertNotNull($antes);

        $this->como()->postJson(self::URL_PROGRAMA)->assertOk();

        $this->assertSame($antes, Model::getEventDispatcher());
    }

    public function test_programa_propaga_formulas_de_rollos_a_cat_codificados_de_su_orden(): void
    {
        $this->conPesoCrudo('ReqProgramaTejido');
        $cat = (new CatCodificados)->getTable();

        $this->como()->postJson(self::URL_PROGRAMA)->assertOk();

        $this->assertNotNull(DB::table($cat)->where('OrdenTejido', '30001')->value('TotalRollos'));
        $this->assertNotNull(DB::table('ReqProgramaTejido')->where('Id', 1)->value('TotalRollos'));
    }

    public function test_muestras_escribe_en_cat_codificados_si_su_orden_coincide_con_una_de_programa(): void
    {
        // CARACTERIZA: CatCodificados es de Programa ("Muestras no libera hoy"), pero
        // SincronizadorCatCodificados::propagarFormulas no distingue superficie: un registro de
        // Muestras con el mismo NoProduccion+telar pisa los rollos de la orden de Programa.
        $this->conPesoCrudo('MuestrasPrograma');
        $cat = (new CatCodificados)->getTable();
        $antes = (array) DB::table($cat)->where('OrdenTejido', '30001')->first();

        $this->como([5 => ['acceso', 'modificar']])->postJson(self::URL_MUESTRAS)->assertOk();

        $despues = (array) DB::table($cat)->where('OrdenTejido', '30001')->first();
        $this->assertNull($antes['TotalRollos']);
        $this->assertNotNull($despues['TotalRollos']);
        $this->assertNotSame($antes, $despues);
    }

    private function conPesoCrudo(string $tabla): void
    {
        DB::table($tabla)->where('Id', 1)->update(['PesoCrudo' => 450, 'LargoCrudo' => 70]);
        DB::table((new CatCodificados)->getTable())->insert(['OrdenTejido' => '30001', 'TelarId' => 201, 'Departamento' => 'SMIT', 'Pedido' => 800]);
    }

    // ---- helpers ---------------------------------------------------------------------

    private function como(array $permisos = [2 => ['acceso', 'modificar']])
    {
        return $this->actingAs($this->usuarioConPermisos($permisos));
    }

    private function sembrarTelares(string $tabla, string $dia): void
    {
        $base = ['TamanoClave' => 'TC-RIGOR', 'VelocidadSTD' => 100, 'EficienciaSTD' => 1, 'NoTiras' => 1,
            'Luchaje' => 254, 'Repeticiones' => 5000];
        $filas = [
            ['Id' => 1, 'SalonTejidoId' => 'SMIT', 'NoTelarId' => '201', 'Posicion' => 1, 'EnProceso' => 1, 'Ultimo' => '0',
                'NoProduccion' => '30001', 'TotalPedido' => 800, 'SaldoPedido' => 600, 'Produccion' => 200,
                'FechaInicio' => "{$dia} 06:30:00", 'FechaFinal' => "{$dia} 16:30:00"],
            ['Id' => 2, 'SalonTejidoId' => 'SMIT', 'NoTelarId' => '201', 'Posicion' => 2, 'EnProceso' => 0, 'Ultimo' => '0',
                'NoProduccion' => '30002', 'TotalPedido' => 120, 'SaldoPedido' => 120,
                'FechaInicio' => "{$dia} 16:30:00", 'FechaFinal' => "{$dia} 18:30:00"],
            ['Id' => 3, 'SalonTejidoId' => 'SMIT', 'NoTelarId' => '201', 'Posicion' => 3, 'EnProceso' => 0, 'Ultimo' => '1',
                'NoProduccion' => null, 'TotalPedido' => 300, 'SaldoPedido' => 300,
                'FechaInicio' => "{$dia} 18:30:00", 'FechaFinal' => "{$dia} 23:30:00"],
            ['Id' => 4, 'SalonTejidoId' => 'JACQUARD', 'NoTelarId' => '202', 'Posicion' => 1, 'EnProceso' => 1, 'Ultimo' => '0',
                'NoProduccion' => '30004', 'OrdCompartida' => 7, 'OrdCompartidaLider' => 1, 'TotalPedido' => 240, 'SaldoPedido' => 240,
                'FechaInicio' => "{$dia} 06:30:00", 'FechaFinal' => "{$dia} 10:30:00"],
            ['Id' => 5, 'SalonTejidoId' => 'JACQUARD', 'NoTelarId' => '202', 'Posicion' => 2, 'EnProceso' => 0, 'Ultimo' => '1',
                'NoProduccion' => '30005', 'TotalPedido' => 60, 'SaldoPedido' => 60,
                'FechaInicio' => "{$dia} 10:30:00", 'FechaFinal' => "{$dia} 11:30:00"],
        ];
        foreach ($filas as $fila) {
            DB::table($tabla)->insert(array_merge($base, $fila));
        }
        DB::table($tabla)->insert(['Id' => 6, 'SalonTejidoId' => 'SMIT', 'NoTelarId' => '204', 'Posicion' => 1, 'TotalPedido' => 50]);

        $lineas = $tabla === 'ReqProgramaTejido' ? 'ReqProgramaTejidoLine' : 'MuestrasProgramaLine';
        foreach ([1, 2, 3, 4, 5, 6] as $id) {
            DB::table($lineas)->insert(['ProgramaId' => $id, 'Fecha' => "{$dia} 00:00:00", 'Cantidad' => $id * 10]);
        }
    }

    private function assertFechas(int $id, string $inicio, string $fin, string $tabla = 'ReqProgramaTejido'): void
    {
        $fila = DB::table($tabla)->where('Id', $id)->first(['FechaInicio', 'FechaFinal']);
        $this->assertSame([$inicio, $fin], [$fila->FechaInicio, $fila->FechaFinal], "fechas del Id {$id} en {$tabla}");
    }

    /** @return array<int, int> Id => EnProceso */
    private function enProcesoDe(string $telar): array
    {
        return DB::table('ReqProgramaTejido')->where('NoTelarId', $telar)->orderBy('Id')
            ->pluck('EnProceso', 'Id')->map(fn ($v) => (int) $v)->all();
    }

    private function limpiarCachesEstaticas(): void
    {
        CalendarioProduccion::limpiarCache();
        ColumnasDeTabla::flush();
        $prop = new \ReflectionProperty(FormulasSecuenciaTelar::class, 'totalModeloCache');
        $prop->setValue(null, []);
    }
}
