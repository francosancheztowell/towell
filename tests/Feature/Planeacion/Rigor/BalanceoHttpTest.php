<?php

namespace Tests\Feature\Planeacion\Rigor;

use App\Models\Planeacion\Catalogos\CatCodificados;
use App\Models\Planeacion\ReqModelosCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Services\Planeacion\ProgramaTejido\CalendarioProduccion;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\Feature\Planeacion\Concerns\ConPermisosPlaneacion;
use Tests\Feature\Planeacion\Concerns\ProgramaTejidoFixtures;
use Tests\TestCase;

/**
 * Balanceo por HTTP (preview-fechas-balanceo, actualizar-pedidos-balanceo, balancear-automatico).
 *
 * Fixture (ambas superficies, mismos Id): grupo OrdCompartida 7 =
 *   Id 3 JACQUARD/202 líder, EnProceso, NoProduccion, TotalPedido 600 (saldo 600)
 *   Id 4 JACQUARD/203 miembro, NO EnProceso, FechaInicio 2026-09-11 06:00, TotalPedido 400
 *   Id 8 JACQUARD/203 Posicion 2, SIN grupo: sólo se mueve por cascada.
 * Producción a 10 piezas/hora exactas (modelo CLV-1: Total 500, Luchaje 254, Repeticiones 50,
 * Velocidad 100, Eficiencia 1, NoTiras 1) y sin CalendarioId: tiempo continuo, fechas exactas.
 * now() = 2026-09-10 08:00 (el EnProceso arranca ahí).
 */
class BalanceoHttpTest extends TestCase
{
    use ConPermisosPlaneacion;
    use ProgramaTejidoFixtures;

    private const PARAMS_PRODUCCION = [
        'TamanoClave' => 'CLV-1', 'VelocidadSTD' => 100, 'EficienciaSTD' => 1,
        'NoTiras' => 1, 'Luchaje' => 254, 'Repeticiones' => 50,
    ];

    private const TOCADOS = [3, 4, 8];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-10 08:00:00');
        CalendarioProduccion::limpiarCache();
        $this->prepararSuperficies();
        $this->sembrarFixtures();
        $this->createTablaDesdeModelo(ReqModelosCodificados::class);
        $this->createTablaDbo('ReqCalendarioLine', ['CalendarioId' => 'text', 'FechaInicio' => 'text', 'FechaFin' => 'text']);
        DB::table('ReqModelosCodificados')->insert(['TamanoClave' => 'CLV-1', 'Total' => 500]);

        foreach (['ReqProgramaTejido', 'MuestrasPrograma'] as $tabla) {
            DB::table($tabla)->whereIn('Id', [3, 4])->update(self::PARAMS_PRODUCCION + ['ItemId' => '7001']);
            DB::table($tabla)->where('Id', 4)->update([
                'Ultimo' => '0', 'EnProceso' => 0,
                'FechaInicio' => '2026-09-11 06:00:00', 'FechaFinal' => '2026-09-12 22:00:00',
            ]);
            DB::table($tabla)->insert(self::PARAMS_PRODUCCION + [
                'Id' => 8, 'SalonTejidoId' => 'JACQUARD', 'NoTelarId' => '203', 'Posicion' => 2, 'EnProceso' => 0,
                'TotalPedido' => 100, 'SaldoPedido' => 100,
                'FechaInicio' => '2026-09-12 22:00:00', 'FechaFinal' => '2026-09-13 08:00:00', 'Ultimo' => '1',
            ]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CalendarioProduccion::limpiarCache();
        parent::tearDown();
    }

    // ---- helpers ------------------------------------------------------------------------

    private function enviar(string $accion, array $payload, array $permisos = [2 => ['modificar']], string $sup = 'programa-tejido'): TestResponse
    {
        return $this->actingAs($this->usuarioConPermisos($permisos))->postJson("/planeacion/{$sup}/{$accion}", $payload);
    }

    private function payload(array $totales, int $ord = 7, ?string $modo = null): array
    {
        $cambios = [];
        foreach ($totales as $id => $total) {
            $cambios[] = ['id' => $id, 'total_pedido' => $total] + ($modo ? ['modo' => $modo] : []);
        }

        return ['cambios' => $cambios, 'ord_compartida' => $ord];
    }

    private function fila(int $id, string $tabla = 'ReqProgramaTejido'): object
    {
        return DB::table($tabla)->where('Id', $id)->first();
    }

    /** Foto sin las filas tocadas a propósito (ni sus líneas ni el CatCodificados de su orden). */
    private function fotoNoTocada(string $tablaTocada = 'ReqProgramaTejido'): array
    {
        $foto = $this->fotoSuperficies();
        $lineas = $tablaTocada === 'ReqProgramaTejido' ? 'ReqProgramaTejidoLine' : 'MuestrasProgramaLine';
        $foto[$tablaTocada] = array_values(array_filter($foto[$tablaTocada], fn ($r) => ! in_array((int) $r['Id'], self::TOCADOS, true)));
        // Las líneas se borran/reinsertan (Id nuevo): se comparan sin Id.
        $foto[$lineas] = array_values(array_map(
            fn ($r) => array_diff_key($r, ['Id' => 1]),
            array_filter($foto[$lineas], fn ($r) => ! in_array((int) $r['ProgramaId'], self::TOCADOS, true))
        ));
        $cat = (new CatCodificados)->getTable();
        $foto[$cat] = array_values(array_filter($foto[$cat], fn ($r) => (string) $r['OrdenTejido'] !== '30003'));

        return $foto;
    }

    private function sumaLineas(int $programaId, string $tabla = 'ReqProgramaTejidoLine'): float
    {
        return (float) DB::table($tabla)->where('ProgramaId', $programaId)->sum('Cantidad');
    }

    private function sumaTotalGrupo(string $tabla = 'ReqProgramaTejido'): float
    {
        return (float) DB::table($tabla)->where('OrdCompartida', 7)->sum('TotalPedido');
    }

    private function assertFila(int $id, array $esperado, string $tabla = 'ReqProgramaTejido'): void
    {
        $fila = (array) $this->fila($id, $tabla);
        foreach ($esperado as $campo => $valor) {
            if (is_float($valor) || is_int($valor)) {
                $this->assertEqualsWithDelta($valor, (float) $fila[$campo], 0.001, "{$tabla}#{$id}.{$campo}");
            } else {
                $this->assertSame($valor, $fila[$campo] === null ? null : (string) $fila[$campo], "{$tabla}#{$id}.{$campo}");
            }
        }
    }

    // ---- actualizar-pedidos-balanceo: éxito ------------------------------------------------

    public function test_actualizar_pedidos_reparte_conserva_el_total_del_grupo_y_recalcula_fechas_y_cascada(): void
    {
        $sumaAntes = $this->sumaTotalGrupo();

        $this->enviar('actualizar-pedidos-balanceo', $this->payload([3 => 700, 4 => 300]))
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Balanceo aplicado correctamente',
                'actualizados' => 3,
                'registros_ids' => [3, 4, 8],
            ]);

        $this->assertEqualsWithDelta($sumaAntes, $this->sumaTotalGrupo(), 0.001, 'la suma de TotalPedido del grupo se conserva');

        // EnProceso: arranca en now(); 700 pz a 10 pz/h = 70 h.
        $this->assertFila(3, [
            'Posicion' => '1', 'EnProceso' => '1', 'Ultimo' => '1', 'OrdCompartida' => '7', 'OrdCompartidaLider' => '1',
            'FechaInicio' => '2026-09-10 08:00:00', 'FechaFinal' => '2026-09-13 06:00:00',
            'TotalPedido' => 700.0, 'SaldoPedido' => 700.0, 'HorasProd' => 70.0, 'OrdPrincipal' => '7001',
        ]);
        // No EnProceso: conserva FechaInicio; 300 pz = 30 h.
        $this->assertFila(4, [
            'Posicion' => '1', 'EnProceso' => '0', 'Ultimo' => '0', 'OrdCompartida' => '7', 'OrdCompartidaLider' => '0',
            'FechaInicio' => '2026-09-11 06:00:00', 'FechaFinal' => '2026-09-12 12:00:00',
            'TotalPedido' => 300.0, 'SaldoPedido' => 300.0, 'HorasProd' => 30.0, 'OrdPrincipal' => '7001',
        ]);
        // Cascada: el siguiente del telar 203 arranca donde termina el 4, mismo pedido.
        $this->assertFila(8, [
            'Posicion' => '2', 'EnProceso' => '0', 'Ultimo' => '1', 'OrdCompartida' => null, 'OrdCompartidaLider' => null,
            'FechaInicio' => '2026-09-12 12:00:00', 'FechaFinal' => '2026-09-12 22:00:00',
            'TotalPedido' => 100.0, 'SaldoPedido' => 100.0, 'OrdPrincipal' => null,
        ]);
    }

    public function test_actualizar_pedidos_regenera_las_lineas_diarias_de_los_afectados_con_su_saldo(): void
    {
        $this->enviar('actualizar-pedidos-balanceo', $this->payload([3 => 700, 4 => 300]))->assertOk();

        $this->assertEqualsWithDelta(700, $this->sumaLineas(3), 0.01);
        $this->assertEqualsWithDelta(300, $this->sumaLineas(4), 0.01);
        $this->assertEqualsWithDelta(100, $this->sumaLineas(8), 0.01, 'la fila en cascada también regenera líneas');
        $this->assertSame(
            ['2026-09-10', '2026-09-11', '2026-09-12', '2026-09-13'],
            DB::table('ReqProgramaTejidoLine')->where('ProgramaId', 3)->orderBy('Fecha')->pluck('Fecha')->map(fn ($f) => substr((string) $f, 0, 10))->all(),
            'las líneas cubren exactamente los días de las nuevas fechas (la línea vieja del 2026-09-01 desaparece)'
        );
        $this->assertSame([10.0], DB::table('ReqProgramaTejidoLine')->where('ProgramaId', 1)->pluck('Cantidad')->map(fn ($c) => (float) $c)->all());
    }

    public function test_actualizar_pedidos_no_toca_otros_telares_ni_la_superficie_muestras(): void
    {
        $antes = $this->fotoNoTocada();

        $this->enviar('actualizar-pedidos-balanceo', $this->payload([3 => 700, 4 => 300]))->assertOk();

        $this->assertEquals($antes, $this->fotoNoTocada());
    }

    public function test_actualizar_pedidos_no_reasigna_el_lider_aunque_otro_miembro_quede_con_inicio_mas_antiguo(): void
    {
        // Id 4 (NoProduccion) arranca antes que el now() al que se mueve el líder EnProceso:
        // seleccionarLider elegiría al 4, pero balancear no debe reasignar el líder.
        DB::table('ReqProgramaTejido')->where('Id', 4)->update(['FechaInicio' => '2026-09-08 06:00:00']);

        $this->enviar('actualizar-pedidos-balanceo', $this->payload([3 => 500, 4 => 500]))->assertOk();

        $this->assertSame(1, (int) $this->fila(3)->OrdCompartidaLider);
        $this->assertSame(0, (int) $this->fila(4)->OrdCompartidaLider);
        $this->assertSame(1, (int) DB::table('ReqProgramaTejido')->where('OrdCompartida', 7)->sum('OrdCompartidaLider'), 'exactamente un líder');
        $this->assertSame('2026-09-08 06:00:00', (string) $this->fila(4)->FechaInicio);
    }

    public function test_actualizar_pedidos_en_modo_saldo_suma_la_produccion_al_total(): void
    {
        DB::table('ReqProgramaTejido')->where('Id', 3)->update(['Produccion' => 200, 'SaldoPedido' => 400]);

        $this->enviar('actualizar-pedidos-balanceo', $this->payload([3 => 500], 7, 'saldo'))->assertOk();

        // TotalPedido = Produccion + saldo capturado; 500 pz restantes = 50 h desde now().
        $this->assertFila(3, ['TotalPedido' => 700.0, 'SaldoPedido' => 500.0, 'Produccion' => 200.0, 'FechaFinal' => '2026-09-12 10:00:00']);
    }

    public function test_actualizar_pedidos_incluye_el_porcentaje_de_segundas_en_el_saldo(): void
    {
        DB::table('ReqProgramaTejido')->where('Id', 4)->update(['PorcentajeSegundos' => 10]);

        $this->enviar('actualizar-pedidos-balanceo', $this->payload([4 => 400]))->assertOk();

        // Regla del owner: Saldo = Pedido × (1 + %seg/100) − Producción = 400 × 1.10 = 440 (44 h, no 40 h).
        $this->assertFila(4, ['TotalPedido' => 400.0, 'SaldoPedido' => 440.0, 'FechaFinal' => '2026-09-13 02:00:00']);
    }

    public function test_actualizar_pedidos_no_exige_que_se_conserve_la_suma_del_grupo(): void
    {
        $this->enviar('actualizar-pedidos-balanceo', $this->payload([3 => 5000]))->assertOk();

        // CARACTERIZA: el servidor no valida que Σ TotalPedido del grupo se mantenga; un payload
        // parcial cambia el pedido total de la orden (1000 → 5400) sin aviso. Hoy lo cuida sólo el modal.
        $this->assertEqualsWithDelta(5400, $this->sumaTotalGrupo(), 0.001);
    }

    private function insertarTercerMiembroEnTelar203(): void
    {
        // Tercer miembro del grupo en el telar 203, después del Id 8.
        DB::table('ReqProgramaTejido')->insert(self::PARAMS_PRODUCCION + [
            'Id' => 9, 'SalonTejidoId' => 'JACQUARD', 'NoTelarId' => '203', 'Posicion' => 3, 'EnProceso' => 0,
            'OrdCompartida' => 7, 'OrdCompartidaLider' => 0, 'TotalPedido' => 100, 'SaldoPedido' => 100,
            'FechaInicio' => '2026-09-13 08:00:00', 'FechaFinal' => '2026-09-13 18:00:00', 'Ultimo' => '1',
        ]);
        DB::table('ReqProgramaTejido')->where('Id', 8)->update(['Ultimo' => '0']);
    }

    public function test_actualizar_pedidos_reencadena_a_un_miembro_del_grupo_que_va_despues_en_el_mismo_telar(): void
    {
        $this->insertarTercerMiembroEnTelar203();

        $this->enviar('actualizar-pedidos-balanceo', $this->payload([3 => 500, 4 => 500, 9 => 100]))->assertOk();

        $this->assertSame('2026-09-13 08:00:00', (string) $this->fila(4)->FechaFinal);
        $this->assertSame('2026-09-13 08:00:00', (string) $this->fila(8)->FechaInicio);
        $this->assertSame('2026-09-13 18:00:00', (string) $this->fila(8)->FechaFinal);
        // El 9 (Posicion 3) también viene en cambios: arranca donde termina el 8, sin traslape (100 pz = 10 h).
        $this->assertSame('2026-09-13 18:00:00', (string) $this->fila(9)->FechaInicio);
        $this->assertSame('2026-09-14 04:00:00', (string) $this->fila(9)->FechaFinal);
    }

    public function test_preview_y_guardado_coinciden_cuando_un_miembro_en_cambios_va_despues_de_uno_que_no(): void
    {
        $this->insertarTercerMiembroEnTelar203();
        $payload = $this->payload([3 => 500, 4 => 600, 9 => 100]);

        $preview = $this->enviar('preview-fechas-balanceo', $payload)->assertOk()->json('data');
        $this->enviar('actualizar-pedidos-balanceo', $payload)->assertOk();

        foreach ($preview as $p) {
            $f = $this->fila($p['id']);
            $this->assertSame($p['fecha_inicio'], (string) $f->FechaInicio, "FechaInicio #{$p['id']}");
            $this->assertSame($p['fecha_final'], (string) $f->FechaFinal, "FechaFinal #{$p['id']}");
        }
        // 4: 600 pz = 60 h → 09-13 18:00; 8: 10 h → 09-14 04:00; 9: 10 h → 09-14 14:00.
        $this->assertSame(['2026-09-14 04:00:00', '2026-09-14 14:00:00'], [$preview[2]['fecha_inicio'], $preview[2]['fecha_final']]);
    }

    // ---- preview == guardado ----------------------------------------------------------------

    public function test_preview_devuelve_exactamente_lo_que_luego_se_guarda_para_el_mismo_payload(): void
    {
        DB::table('ReqProgramaTejido')->where('Id', 3)->update(['Produccion' => 150]);
        $payload = $this->payload([3 => 750, 4 => 250]);
        $antes = $this->fotoSuperficies();

        $preview = $this->enviar('preview-fechas-balanceo', $payload)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => [['id', 'fecha_inicio', 'fecha_final', 'horas_prod', 'saldo', 'total', 'calendario_id']]])
            ->json('data');

        $this->assertEquals($antes, $this->fotoSuperficies(), 'el preview no escribe nada');

        $this->enviar('actualizar-pedidos-balanceo', $payload)->assertOk();

        foreach ($preview as $p) {
            $f = $this->fila($p['id']);
            $this->assertSame($p['fecha_inicio'], (string) $f->FechaInicio, "FechaInicio #{$p['id']}");
            $this->assertSame($p['fecha_final'], (string) $f->FechaFinal, "FechaFinal #{$p['id']}");
            $this->assertEqualsWithDelta($p['total'], (float) $f->TotalPedido, 0.001);
            $this->assertEqualsWithDelta($p['saldo'], (float) $f->SaldoPedido, 0.001);
            $this->assertEqualsWithDelta($p['horas_prod'], (float) $f->HorasProd, 0.001);
        }
        // Saldo = 750 − 150 producidas = 600 pz = 60 h desde now().
        $this->assertSame(['id' => 3, 'fecha_inicio' => '2026-09-10 08:00:00', 'fecha_final' => '2026-09-12 20:00:00'], array_intersect_key($preview[0], array_flip(['id', 'fecha_inicio', 'fecha_final'])));
    }

    // ---- 422 de pertenencia, sin escribir -------------------------------------------------

    public function test_registro_de_otra_ord_compartida_es_422_y_no_escribe_nada_aunque_venga_despues_de_uno_valido(): void
    {
        $antes = $this->fotoSuperficies();

        foreach (['actualizar-pedidos-balanceo', 'preview-fechas-balanceo'] as $accion) {
            $this->enviar($accion, $this->payload([3 => 900, 1 => 100]))
                ->assertStatus(422)
                ->assertExactJson(['success' => false, 'message' => 'Todos los registros deben pertenecer al OrdCompartida indicado.', 'id' => 1]);
        }

        $this->assertEquals($antes, $this->fotoSuperficies());
    }

    public function test_ord_compartida_que_no_corresponde_al_grupo_o_id_inexistente_es_422_sin_escribir(): void
    {
        $antes = $this->fotoSuperficies();

        $this->enviar('actualizar-pedidos-balanceo', $this->payload([3 => 600, 4 => 400], 30003))
            ->assertStatus(422)->assertJsonPath('success', false)->assertJsonPath('id', 3);
        $this->enviar('actualizar-pedidos-balanceo', $this->payload([3 => 600, 9999 => 400]))
            ->assertStatus(422)->assertJsonPath('message', 'Uno o más registros no existen.')->assertJsonPath('id', 9999);

        $this->assertEquals($antes, $this->fotoSuperficies());
    }

    // ---- 403 ---------------------------------------------------------------------------------

    public function test_sin_permiso_modificar_actualizar_y_balancear_automatico_son_403_sin_escribir(): void
    {
        $antes = $this->fotoSuperficies();
        $sinModificar = [2 => ['acceso', 'crear', 'eliminar', 'registrar']];

        $this->enviar('actualizar-pedidos-balanceo', $this->payload([3 => 700, 4 => 300]), $sinModificar)->assertForbidden();
        $this->enviar('balancear-automatico', ['ord_compartida' => 7, 'fecha_fin_objetivo' => '2026-09-11'], $sinModificar)->assertForbidden();
        // Permiso de Programa no abre Muestras.
        $this->enviar('actualizar-pedidos-balanceo', $this->payload([3 => 700, 4 => 300]), [2 => ['modificar']], 'muestras')->assertForbidden();

        $this->assertEquals($antes, $this->fotoSuperficies());
    }

    public function test_preview_exige_permiso_modificar(): void
    {
        // Mismo permiso que actualizar-pedidos-balanceo: con sólo "acceso" o sin permisos es 403.
        $this->enviar('preview-fechas-balanceo', $this->payload([3 => 700, 4 => 300]), [2 => ['acceso']])->assertForbidden();
        $this->enviar('preview-fechas-balanceo', $this->payload([3 => 700, 4 => 300]), [])->assertForbidden();
    }

    // ---- payload inválido ----------------------------------------------------------------------

    public function test_payload_invalido_a_actualizar_pedidos_responde_422_como_el_preview(): void
    {
        $antes = $this->fotoSuperficies();

        foreach (['actualizar-pedidos-balanceo', 'preview-fechas-balanceo'] as $accion) {
            $this->enviar($accion, ['cambios' => 'x', 'ord_compartida' => 7])
                ->assertStatus(422)->assertJsonValidationErrors(['cambios']);
            $this->enviar($accion, ['cambios' => [['id' => 3]], 'ord_compartida' => 7])
                ->assertStatus(422)->assertJsonValidationErrors(['cambios.0.total_pedido']);
        }

        $this->assertEquals($antes, $this->fotoSuperficies());
    }

    // ---- rollback ---------------------------------------------------------------------------------

    public function test_fallo_a_mitad_de_la_transaccion_revierte_todo_y_restaura_observers(): void
    {
        $antes = $this->fotoSuperficies();
        $dispatcher = ReqProgramaTejido::getEventDispatcher();

        // Falla el UPDATE del segundo registro (después de guardar el primero).
        DB::listen(function ($q) {
            if (str_starts_with(strtolower($q->sql), 'update') && in_array(4, $q->bindings, true)) {
                throw new RuntimeException('falla simulada');
            }
        });

        $this->enviar('actualizar-pedidos-balanceo', $this->payload([3 => 700, 4 => 300]))
            ->assertStatus(500)
            ->assertExactJson(['success' => false, 'message' => 'Error al actualizar los pedidos: falla simulada']);

        $this->assertEquals($antes, $this->fotoSuperficies(), 'el Id 3 ya guardado vuelve atrás');
        $this->assertSame($dispatcher, ReqProgramaTejido::getEventDispatcher(), 'los observers quedan restaurados');
    }

    // ---- Muestras -----------------------------------------------------------------------------

    public function test_muestras_balancea_su_propia_tabla_y_no_toca_programa(): void
    {
        $antes = $this->fotoNoTocada('MuestrasPrograma');

        $this->enviar('actualizar-pedidos-balanceo', $this->payload([3 => 700, 4 => 300]), [5 => ['modificar']], 'muestras')
            ->assertOk()->assertJsonPath('registros_ids', [3, 4, 8]);

        $this->assertFila(3, ['TotalPedido' => 700.0, 'FechaFinal' => '2026-09-13 06:00:00', 'OrdCompartidaLider' => '1'], 'MuestrasPrograma');
        $this->assertFila(4, ['TotalPedido' => 300.0, 'FechaFinal' => '2026-09-12 12:00:00'], 'MuestrasPrograma');
        $this->assertFila(8, ['FechaInicio' => '2026-09-12 12:00:00'], 'MuestrasPrograma');
        $this->assertEqualsWithDelta(700, $this->sumaLineas(3, 'MuestrasProgramaLine'), 0.01);
        $this->assertEquals($antes, $this->fotoNoTocada('MuestrasPrograma'), 'Programa y los demás telares de Muestras intactos');
    }

    // ---- balancear-automatico ------------------------------------------------------------------

    public function test_balancear_automatico_calcula_sin_escribir_y_cierra_el_total_en_el_ultimo_telar(): void
    {
        $antes = $this->fotoSuperficies();

        $r = $this->enviar('balancear-automatico', ['ord_compartida' => 7, 'fecha_fin_objetivo' => '2026-09-11'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Balanceo calculado correctamente')
            ->assertJsonMissingPath('advertencia_total')
            ->assertJsonMissingPath('total_diferencia_vs_objetivo');

        $this->assertEquals($antes, $this->fotoSuperficies(), 'balancear-automatico sólo calcula');

        // Id 3 (EnProceso, desde now) cabe 40 h hasta 23:59:59 → 400; Id 4 cabe 18 h → 180,
        // y el último (4) absorbe el faltante: 180 + 420 = 600. Σ = 1000.
        $this->assertSame([
            ['id' => 3, 'total_pedido' => 400, 'modo' => 'total'],
            ['id' => 4, 'total_pedido' => 600, 'modo' => 'total'],
        ], array_map(fn ($c) => ['id' => $c['id'], 'total_pedido' => (int) $c['total_pedido'], 'modo' => $c['modo']], $r->json('cambios')));
        $this->assertEqualsWithDelta(100.0, $r->json('horas_necesarias_originales'), 0.001);
        $this->assertEqualsWithDelta(1000, array_sum(array_column($r->json('cambios'), 'total_pedido')), 0.001);
    }

    public function test_aplicar_lo_que_propone_balancear_automatico_conserva_el_total_y_el_lider(): void
    {
        $cambios = $this->enviar('balancear-automatico', ['ord_compartida' => 7, 'fecha_fin_objetivo' => '2026-09-11'])
            ->assertOk()->json('cambios');

        $this->enviar('actualizar-pedidos-balanceo', ['cambios' => $cambios, 'ord_compartida' => 7])
            ->assertOk()->assertJsonPath('registros_ids', [3, 4, 8]);

        $this->assertEqualsWithDelta(1000, $this->sumaTotalGrupo(), 0.001);
        $this->assertSame(1, (int) $this->fila(3)->OrdCompartidaLider);
        $this->assertSame(0, (int) $this->fila(4)->OrdCompartidaLider);
        $this->assertFila(3, ['TotalPedido' => 400.0, 'FechaInicio' => '2026-09-10 08:00:00', 'FechaFinal' => '2026-09-12 00:00:00']);
        $this->assertFila(4, ['TotalPedido' => 600.0, 'FechaInicio' => '2026-09-11 06:00:00', 'FechaFinal' => '2026-09-13 18:00:00']);
        $this->assertFila(8, ['FechaInicio' => '2026-09-13 18:00:00', 'FechaFinal' => '2026-09-14 04:00:00']);
        $this->assertEqualsWithDelta(600, $this->sumaLineas(4), 0.01);
    }

    public function test_balancear_automatico_con_exceso_solo_recorta_el_ultimo_telar_y_avisa_que_no_cuadra(): void
    {
        $r = $this->enviar('balancear-automatico', ['ord_compartida' => 7, 'fecha_fin_objetivo' => '2026-09-14'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('total_diferencia_vs_objetivo', -121);

        $this->assertStringContainsString('no coincide exactamente', $r->json('advertencia_total'));
        // CARACTERIZA: Id 3 recibe 1120 (todo lo que cabe) y sólo el último (Id 4) se recorta, hasta
        // el mínimo de 1; el grupo queda en 1121 contra 1000 aunque el Id 3 podía recortarse.
        $this->assertSame(
            [[3, 1120], [4, 1]],
            array_map(fn ($c) => [$c['id'], (int) $c['total_pedido']], $r->json('cambios'))
        );
    }
}
