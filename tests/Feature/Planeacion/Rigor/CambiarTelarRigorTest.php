<?php

namespace Tests\Feature\Planeacion\Rigor;

use App\Models\Planeacion\ReqModelosCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use Carbon\Carbon;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Planeacion\Concerns\ConPermisosPlaneacion;
use Tests\Feature\Planeacion\Concerns\ProgramaTejidoFixtures;
use Tests\TestCase;

/**
 * Rigor de cambiar-telar / verificar-cambio-telar (ProgramaTejidoOperacionesController).
 *
 * Telares sembrados (Programa; Muestras tiene la misma forma):
 *  - SMIT 201: Id 1 (EnProceso, 48 h) · Id 2 (24 h) · Id 6 (12 h, Ultimo)
 *  - SMIT 204: Id 5 (24 h, sin EnProceso)
 *  - JACQUARD 202: Id 3 (líder OrdCompartida 7, EnProceso) · JACQUARD 203: Id 4 (miembro)
 *  - SMIT 301: Id 7, 8, 9 sin EnProceso (para el inicio del origen)
 * Sin CalendarioId ni EficienciaSTD: FechaFinal = FechaInicio + HorasProd en línea recta.
 */
class CambiarTelarRigorTest extends TestCase
{
    use ConPermisosPlaneacion;
    use ProgramaTejidoFixtures;

    private const COLUMNAS = [
        'Id', 'SalonTejidoId', 'NoTelarId', 'Posicion', 'FechaInicio', 'FechaFinal', 'EnProceso', 'Ultimo',
        'OrdCompartida', 'OrdCompartidaLider', 'TotalPedido', 'SaldoPedido',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-10 08:00:00');
        $this->prepararSuperficies();
        $this->sembrarFixtures();
        $this->createTablaDesdeModelo(ReqModelosCodificados::class);
        $this->createTablaDbo('ReqCalendarioLine', ['CalendarioId' => 'text', 'FechaInicio' => 'text', 'FechaFin' => 'text']);

        foreach (['ReqProgramaTejido', 'MuestrasPrograma'] as $tabla) {
            DB::table($tabla)->update(['CalendarioId' => null, 'TamanoClave' => 'MOD1']);
            DB::table($tabla)->where('Id', 1)->update(['HorasProd' => 48]);
            DB::table($tabla)->where('Id', 2)->update(['Ultimo' => '0', 'HorasProd' => 24]);
            DB::table($tabla)->where('Id', 5)->update([
                'HorasProd' => 24, 'TotalPedido' => 100, 'SaldoPedido' => 100, 'Ultimo' => '1',
                'FechaInicio' => '2026-09-01 06:30:00', 'FechaFinal' => '2026-09-02 06:30:00',
            ]);
            DB::table($tabla)->insert([
                [
                    'Id' => 6, 'SalonTejidoId' => 'SMIT', 'NoTelarId' => '201', 'Posicion' => 3, 'EnProceso' => 0,
                    'TotalPedido' => 300, 'SaldoPedido' => 300, 'HorasProd' => 12, 'TamanoClave' => 'MOD1',
                    'FechaInicio' => '2026-09-05 06:00:00', 'FechaFinal' => '2026-09-05 18:00:00', 'Ultimo' => '1',
                ],
                [
                    'Id' => 7, 'SalonTejidoId' => 'SMIT', 'NoTelarId' => '301', 'Posicion' => 1, 'EnProceso' => 0,
                    'TotalPedido' => 10, 'SaldoPedido' => 10, 'HorasProd' => 24, 'TamanoClave' => 'MOD1',
                    'FechaInicio' => '2026-09-20 06:00:00', 'FechaFinal' => '2026-09-21 06:00:00', 'Ultimo' => '0',
                ],
                [
                    'Id' => 8, 'SalonTejidoId' => 'SMIT', 'NoTelarId' => '301', 'Posicion' => 2, 'EnProceso' => 0,
                    'TotalPedido' => 10, 'SaldoPedido' => 10, 'HorasProd' => 24, 'TamanoClave' => 'MOD1',
                    'FechaInicio' => '2026-09-21 06:00:00', 'FechaFinal' => '2026-09-22 06:00:00', 'Ultimo' => '0',
                ],
                [
                    'Id' => 9, 'SalonTejidoId' => 'SMIT', 'NoTelarId' => '301', 'Posicion' => 3, 'EnProceso' => 0,
                    'TotalPedido' => 10, 'SaldoPedido' => 10, 'HorasProd' => 24, 'TamanoClave' => 'MOD1',
                    'FechaInicio' => '2026-09-25 06:00:00', 'FechaFinal' => '2026-09-26 06:00:00', 'Ultimo' => '1',
                ],
            ]);
        }

        DB::table('ReqModelosCodificados')->insert([
            ['TamanoClave' => 'MOD1', 'SalonTejidoId' => 'SMIT', 'Total' => 50],
            ['TamanoClave' => 'MOD1', 'SalonTejidoId' => 'JACQUARD', 'Total' => 50],
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function como(array $permisos = [2 => ['acceso', 'modificar'], 5 => ['acceso', 'modificar']])
    {
        return $this->actingAs($this->usuarioConPermisos($permisos));
    }

    private function cambiar(int $id, string $salon, string $telar, int $pos, string $superficie = 'programa-tejido')
    {
        return $this->como()->postJson("/planeacion/{$superficie}/{$id}/cambiar-telar", [
            'nuevo_salon' => $salon, 'nuevo_telar' => $telar, 'target_position' => $pos,
        ]);
    }

    /** Estado de las columnas de negocio de una fila, normalizado (sqlite devuelve todo como string/float). */
    private function fila(int $id, string $tabla = 'ReqProgramaTejido'): array
    {
        $r = (array) DB::table($tabla)->where('Id', $id)->first(self::COLUMNAS);

        return [
            'Id' => (int) $r['Id'],
            'Salon' => $r['SalonTejidoId'],
            'Telar' => (string) $r['NoTelarId'],
            'Posicion' => $r['Posicion'] === null ? null : (int) $r['Posicion'],
            'FechaInicio' => $r['FechaInicio'] === null ? null : Carbon::parse($r['FechaInicio'])->format('Y-m-d H:i:s'),
            'FechaFinal' => $r['FechaFinal'] === null ? null : Carbon::parse($r['FechaFinal'])->format('Y-m-d H:i:s'),
            'EnProceso' => $r['EnProceso'] === null ? null : (int) $r['EnProceso'],
            'Ultimo' => $r['Ultimo'] === null ? null : (string) $r['Ultimo'],
            'OrdCompartida' => $r['OrdCompartida'] === null ? null : (int) $r['OrdCompartida'],
            'OrdCompartidaLider' => $r['OrdCompartidaLider'] === null ? null : (int) $r['OrdCompartidaLider'],
            'TotalPedido' => $r['TotalPedido'] === null ? null : (float) $r['TotalPedido'],
            'SaldoPedido' => $r['SaldoPedido'] === null ? null : (float) $r['SaldoPedido'],
        ];
    }

    private function crudo(string $tabla, ?callable $filtro = null): array
    {
        $q = DB::table($tabla)->orderBy('Id');
        if ($filtro) {
            $filtro($q);
        }

        return $q->get()->map(fn ($r) => (array) $r)->all();
    }

    // ---- cambiar-telar: fechas encadenadas ---------------------------------------------

    public function test_destino_encadena_fechas_y_el_movido_queda_ultimo(): void
    {
        $this->cambiar(2, 'SMIT', '204', 1)->assertOk()->assertJsonPath('success', true);

        $this->assertSame([
            'Id' => 5, 'Salon' => 'SMIT', 'Telar' => '204', 'Posicion' => 1,
            'FechaInicio' => '2026-09-01 06:30:00', 'FechaFinal' => '2026-09-02 06:30:00',
            // CARACTERIZA: recalcular() marca EnProceso=1 al primero del destino aunque nunca arrancó.
            'EnProceso' => 1, 'Ultimo' => '0',
            'OrdCompartida' => null, 'OrdCompartidaLider' => null, 'TotalPedido' => 100.0, 'SaldoPedido' => 100.0,
        ], $this->fila(5));

        $this->assertSame([
            'Id' => 2, 'Salon' => 'SMIT', 'Telar' => '204', 'Posicion' => 2,
            'FechaInicio' => '2026-09-02 06:30:00', 'FechaFinal' => '2026-09-03 06:30:00',
            'EnProceso' => 0, 'Ultimo' => '1',
            'OrdCompartida' => null, 'OrdCompartidaLider' => null, 'TotalPedido' => 500.0, 'SaldoPedido' => 500.0,
        ], $this->fila(2), 'arranca donde termina el anterior y dura sus 24 h');
    }

    public function test_origen_se_compacta_y_reencadena_desde_el_en_proceso(): void
    {
        $this->cambiar(2, 'SMIT', '204', 1)->assertOk();

        $this->assertSame([
            'Id' => 1, 'Salon' => 'SMIT', 'Telar' => '201', 'Posicion' => 1,
            // CARACTERIZA: el EnProceso del origen se reancla a now() (arrancó el 09-01, pasa a 09-10 08:00)
            // solo porque se movió otro registro de su telar.
            'FechaInicio' => '2026-09-10 08:00:00', 'FechaFinal' => '2026-09-12 08:00:00',
            'EnProceso' => 1, 'Ultimo' => '0',
            'OrdCompartida' => null, 'OrdCompartidaLider' => null, 'TotalPedido' => 1000.0, 'SaldoPedido' => 800.0,
        ], $this->fila(1));

        $this->assertSame([
            'Id' => 6, 'Salon' => 'SMIT', 'Telar' => '201', 'Posicion' => 2,
            'FechaInicio' => '2026-09-12 08:00:00', 'FechaFinal' => '2026-09-12 20:00:00',
            'EnProceso' => 0, 'Ultimo' => '1',
            'OrdCompartida' => null, 'OrdCompartidaLider' => null, 'TotalPedido' => 300.0, 'SaldoPedido' => 300.0,
        ], $this->fila(6));
    }

    public function test_origen_sin_en_proceso_reancla_al_inicio_del_registro_movido(): void
    {
        // Mover el ÚLTIMO de 301 (Id 9, inicia 09-25) a 204.
        $this->cambiar(9, 'SMIT', '204', 1)->assertOk()->assertJsonPath('success', true);

        // CARACTERIZA: el movido se marca con Posicion = -Id antes de leer el origen, así que queda
        // PRIMERO en la consulta y $inicioOrigen toma SU FechaInicio (09-25) en vez de la del primero
        // que se queda (Id 7, 09-20). El telar origen se corre 5 días hacia adelante.
        $this->assertSame('2026-09-25 06:00:00', $this->fila(7)['FechaInicio']);
        $this->assertSame('2026-09-26 06:00:00', $this->fila(7)['FechaFinal']);
        $this->assertSame('2026-09-26 06:00:00', $this->fila(8)['FechaInicio']);
        $this->assertSame([1, 2], [$this->fila(7)['Posicion'], $this->fila(8)['Posicion']]);
        $this->assertSame(['1', '0'], [(string) $this->fila(7)['EnProceso'], $this->fila(7)['Ultimo']]);
        $this->assertSame('1', $this->fila(8)['Ultimo']);
    }

    public function test_destino_vacio_arranca_en_la_fecha_previa_del_movido(): void
    {
        $this->cambiar(6, 'SMIT', '999', 0)->assertOk();

        $this->assertSame([
            'Id' => 6, 'Salon' => 'SMIT', 'Telar' => '999', 'Posicion' => 1,
            'FechaInicio' => '2026-09-05 06:00:00', 'FechaFinal' => '2026-09-05 18:00:00',
            // CARACTERIZA: un pedido que nunca arrancó queda EnProceso=1 al caer en un telar vacío.
            'EnProceso' => 1, 'Ultimo' => '1',
            'OrdCompartida' => null, 'OrdCompartidaLider' => null, 'TotalPedido' => 300.0, 'SaldoPedido' => 300.0,
        ], $this->fila(6));
        $this->assertSame('1', $this->fila(2)['Ultimo'], 'el nuevo último del origen se marca Ultimo=1');
    }

    public function test_target_position_mayor_al_destino_se_coloca_al_final(): void
    {
        $this->cambiar(2, 'SMIT', '204', 50)->assertOk();

        $this->assertSame([5, 2], DB::table('ReqProgramaTejido')->where('NoTelarId', '204')->orderBy('Posicion')->pluck('Id')->map(fn ($i) => (int) $i)->all());
        $this->assertSame(2, $this->fila(2)['Posicion']);
    }

    public function test_destino_con_en_proceso_acepta_la_posicion_despues_de_el(): void
    {
        DB::table('ReqProgramaTejido')->where('Id', 3)->update(['HorasProd' => 10]);

        $this->cambiar(2, 'JACQUARD', '202', 1)->assertOk()->assertJsonPath('success', true);

        $lider = $this->fila(3);
        $this->assertSame([1, 1, '0', 7, 1], [$lider['Posicion'], $lider['EnProceso'], $lider['Ultimo'], $lider['OrdCompartida'], $lider['OrdCompartidaLider']]);
        // EnProceso del destino también se reancla a now().
        $this->assertSame('2026-09-10 08:00:00', $lider['FechaInicio']);
        $this->assertSame('2026-09-10 18:00:00', $lider['FechaFinal']);

        $movido = $this->fila(2);
        $this->assertSame(['JACQUARD', '202', 2, '2026-09-10 18:00:00', '2026-09-11 18:00:00', 0, '1'],
            [$movido['Salon'], $movido['Telar'], $movido['Posicion'], $movido['FechaInicio'], $movido['FechaFinal'], $movido['EnProceso'], $movido['Ultimo']]);
    }

    // ---- aislamiento --------------------------------------------------------------------

    public function test_no_toca_telares_ajenos_ni_la_superficie_muestras(): void
    {
        $antes = $this->fotoSuperficies();

        $this->cambiar(2, 'SMIT', '204', 1)->assertOk();

        $despues = $this->fotoSuperficies();
        foreach (['MuestrasPrograma', 'MuestrasProgramaLine', 'CatCodificados'] as $tabla) {
            $this->assertNotEmpty($antes[$tabla], "{$tabla} sembrada");
            $this->assertSame($antes[$tabla], $despues[$tabla], "{$tabla} no debe cambiar");
        }
        $ajenos = fn ($q) => $q->whereNotIn('NoTelarId', ['201', '204']);
        $this->assertSame(
            array_values(array_filter($antes['ReqProgramaTejido'], fn ($r) => ! in_array((string) $r['NoTelarId'], ['201', '204'], true))),
            $this->crudo('ReqProgramaTejido', $ajenos),
            'telares 202, 203 y 301 intactos'
        );
    }

    public function test_muestras_cambia_telar_solo_en_muestras(): void
    {
        $antesPrograma = $this->crudo('ReqProgramaTejido');
        $antesCat = $this->crudo('CatCodificados');

        $this->cambiar(2, 'SMIT', '204', 1, 'muestras')->assertOk()->assertJsonPath('success', true);

        $this->usarSuperficie('muestras');
        $this->assertSame(['204', 2, '1'], [$this->fila(2, 'MuestrasPrograma')['Telar'], $this->fila(2, 'MuestrasPrograma')['Posicion'], $this->fila(2, 'MuestrasPrograma')['Ultimo']]);
        $this->assertSame([1, 6], DB::table('MuestrasPrograma')->where('NoTelarId', '201')->orderBy('Posicion')->pluck('Id')->map(fn ($i) => (int) $i)->all());
        $this->assertSame($antesPrograma, $this->crudo('ReqProgramaTejido'), 'Programa no se toca desde Muestras');
        $this->assertSame($antesCat, $this->crudo('CatCodificados'));
    }

    public function test_muestras_sin_permiso_de_muestras_es_403_aunque_tenga_programa(): void
    {
        $antes = $this->fotoSuperficies();

        $this->como([2 => ['acceso', 'modificar']])->postJson('/planeacion/muestras/2/cambiar-telar', [
            'nuevo_salon' => 'SMIT', 'nuevo_telar' => '204', 'target_position' => 1,
        ])->assertForbidden();

        $this->assertSame($antes, $this->fotoSuperficies());
    }

    // ---- OrdCompartida ------------------------------------------------------------------

    public function test_miembro_de_ord_compartida_conserva_grupo_y_el_lider_no_se_toca(): void
    {
        DB::table('ReqProgramaTejido')->where('Id', 4)->update(['EnProceso' => 0, 'HorasProd' => 16]);
        $liderAntes = $this->crudo('ReqProgramaTejido', fn ($q) => $q->where('Id', 3));

        $this->cambiar(4, 'JACQUARD', '210', 0)->assertOk()->assertJsonPath('success', true);

        $miembro = $this->fila(4);
        $this->assertSame(['JACQUARD', '210', 1, 7, 0, 400.0, 400.0],
            [$miembro['Salon'], $miembro['Telar'], $miembro['Posicion'], $miembro['OrdCompartida'], $miembro['OrdCompartidaLider'], $miembro['TotalPedido'], $miembro['SaldoPedido']]);
        $this->assertSame($liderAntes, $this->crudo('ReqProgramaTejido', fn ($q) => $q->where('Id', 3)), 'el líder no cambia');
        $this->assertSame(0, DB::table('ReqProgramaTejido')->where('NoTelarId', '203')->count(), 'el telar origen queda vacío');
    }

    public function test_miembro_compartido_reescribe_cat_codificados_de_todo_el_grupo(): void
    {
        DB::table('ReqProgramaTejido')->where('Id', 4)->update(['EnProceso' => 0, 'HorasProd' => 16]);

        $this->cambiar(4, 'SMIT', '210', 0)->assertOk();

        $cat = DB::table('CatCodificados')->where('OrdenTejido', '30003')->orderBy('Id')->get();
        // CARACTERIZA: sincronizarTelarCatCodificados filtra solo por OrdenTejido; la fila del líder
        // (telar 202) también pasa a 210/SMIT, aunque el líder sigue en JACQUARD 202.
        $this->assertSame([[210, 'SMIT'], [210, 'SMIT']], $cat->map(fn ($c) => [(int) $c->TelarId, $c->Departamento])->all());
        $this->assertSame('202', (string) DB::table('ReqProgramaTejido')->where('Id', 3)->value('NoTelarId'));
        $this->assertSame([1000, 'SMIT'], [(int) DB::table('CatCodificados')->where('OrdenTejido', '30001')->value('Pedido'), DB::table('CatCodificados')->where('OrdenTejido', '30001')->value('Departamento')]);
    }

    // ---- fallos y transacción ----------------------------------------------------------

    public function test_fallo_a_mitad_de_la_transaccion_revierte_todo_y_restaura_observers(): void
    {
        $antes = $this->fotoSuperficies();
        $dispatcherAntes = ReqProgramaTejido::getEventDispatcher();
        $this->assertNotNull($dispatcherAntes);

        DB::listen(function (QueryExecuted $q) {
            if (str_contains($q->sql, '1000000')) {
                throw new \RuntimeException('falla simulada en el destino');
            }
        });

        $this->cambiar(2, 'SMIT', '204', 1)
            ->assertStatus(500)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Error al cambiar de telar: falla simulada en el destino');

        $this->assertSame($antes, $this->fotoSuperficies(), 'rollback total: origen, destino, líneas y CatCodificados');
        $this->assertSame($dispatcherAntes, ReqProgramaTejido::getEventDispatcher(), 'los observers vuelven');
    }

    public function test_si_regenerar_lineas_falla_tras_el_commit_responde_500_con_el_registro_ya_movido(): void
    {
        $commit = false;
        Event::listen(TransactionCommitted::class, function () use (&$commit) {
            $commit = true;
        });
        DB::listen(function (QueryExecuted $q) use (&$commit) {
            if ($commit && str_starts_with(strtolower($q->sql), 'select')) {
                throw new \RuntimeException('lineas caidas');
            }
        });

        $r = $this->cambiar(2, 'SMIT', '204', 1);

        // CARACTERIZA: el catch de cambiarTelar envuelve también el post-commit; rollBack() ya no
        // revierte nada y el cliente ve "Error" aunque el cambio quedó guardado (y reintenta).
        $r->assertStatus(500)->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Error al cambiar de telar: lineas caidas');
        $commit = false;
        $this->assertSame(['204', 2], [$this->fila(2)['Telar'], $this->fila(2)['Posicion']]);
        $this->assertSame([1, 6], DB::table('ReqProgramaTejido')->where('NoTelarId', '201')->orderBy('Posicion')->pluck('Id')->map(fn ($i) => (int) $i)->all());
    }

    public function test_respuesta_exitosa_trae_afectados_detalles_y_updates_por_id(): void
    {
        $json = $this->cambiar(2, 'SMIT', '204', 1)->assertOk()->json();

        $this->assertSame(['success', 'message', 'registros_afectados', 'detalles', 'updates', 'registro_id'], array_keys($json));
        $this->assertSame('Telar actualizado correctamente', $json['message']);
        $this->assertSame(4, $json['registros_afectados'], 'origen (1, 6) + destino (5, 2)');
        $this->assertEqualsCanonicalizing(['1', '6', '5', '2'], array_map('strval', array_keys($json['updates'])));
        $this->assertSame(2, $json['updates']['2']['Posicion']);
        $this->assertSame('204', (string) $json['updates']['2']['NoTelarId']);
        $this->assertCount(4, $json['detalles']);
        $this->assertSame([1, 6, 5, 2], array_column($json['detalles'], 'Id'));
    }

    public function test_cambiar_telar_inexistente_es_404_sin_tocar_nada(): void
    {
        $antes = $this->fotoSuperficies();

        $this->cambiar(9999, 'SMIT', '204', 0)->assertNotFound();

        $this->assertSame($antes, $this->fotoSuperficies());
    }
}
