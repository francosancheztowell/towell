<?php

namespace Tests\Feature\Planeacion\Rigor;

use App\Models\Planeacion\ReqProgramaTejido;
use App\Services\Planeacion\ProgramaTejido\OrdCompartida;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Planeacion\Concerns\ConPermisosPlaneacion;
use Tests\Feature\Planeacion\Concerns\ProgramaTejidoFixtures;
use Tests\TestCase;

/**
 * Eliminar dentro de un grupo OrdCompartida (EliminarTejido::eliminarConOrdCompartida)
 * y destroyEnProceso. Tests de CARACTERIZACIÓN: fijan la conducta de hoy.
 *
 * Escenario propio (Programa, salón SMIT, now = 2026-09-10 08:00, sin calendario):
 *   telar 301: Id 20 cabeza EnProceso (24 h) · Id 10 A líder  (24 h) · Id 21 cola (12 h)
 *   telar 302: Id 22 cabeza EnProceso (36 h) · Id 11 B miembro (24 h)
 *   telar 303: Id 23 cabeza EnProceso (48 h) · Id 12 C miembro (24 h, sin NoProduccion)
 * Grupo OrdCompartida 50010: A (NoProd 50010, FechaInicio 09-11 08:00) es el más antiguo,
 * B (NoProd 50011, 09-11 20:00), C (sin NoProd, 09-12 08:00). %seg = 10 en todos.
 * Los fixtures base (telares 201-204 y toda la superficie Muestras) no deben tocarse.
 */
class EliminarGrupoOrdCompartidaTest extends TestCase
{
    use ConPermisosPlaneacion;
    use ProgramaTejidoFixtures;

    private const GRUPO = 50010;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-10 08:00:00');
        $this->prepararSuperficies();
        $this->sembrarFixtures();
        $this->createTablaDbo('ReqCalendarioLine', ['CalendarioId' => 'text', 'FechaInicio' => 'text', 'FechaFin' => 'text']);

        $base = ['SalonTejidoId' => 'SMIT', 'PorcentajeSegundos' => 10, 'Produccion' => 0, 'OrdCompartida' => null, 'OrdCompartidaLider' => null, 'NoProduccion' => null];
        $filas = [
            ['Id' => 20, 'NoTelarId' => '301', 'Posicion' => 1, 'EnProceso' => 1, 'HorasProd' => 24, 'TotalPedido' => 100, 'SaldoPedido' => 110,
                'FechaInicio' => '2026-09-10 08:00:00', 'FechaFinal' => '2026-09-11 08:00:00', 'Ultimo' => '0'],
            ['Id' => 10, 'NoTelarId' => '301', 'Posicion' => 2, 'EnProceso' => 0, 'HorasProd' => 24, 'TotalPedido' => 1000, 'SaldoPedido' => 1000, 'Produccion' => 100,
                'NoProduccion' => '50010', 'OrdCompartida' => self::GRUPO, 'OrdCompartidaLider' => 1, 'OrdPrincipal' => 50010,
                'FechaInicio' => '2026-09-11 08:00:00', 'FechaFinal' => '2026-09-12 08:00:00', 'Ultimo' => '0'],
            ['Id' => 21, 'NoTelarId' => '301', 'Posicion' => 3, 'EnProceso' => 0, 'HorasProd' => 12, 'TotalPedido' => 50, 'SaldoPedido' => 55,
                'FechaInicio' => '2026-09-12 08:00:00', 'FechaFinal' => '2026-09-12 20:00:00', 'Ultimo' => '1'],
            ['Id' => 22, 'NoTelarId' => '302', 'Posicion' => 1, 'EnProceso' => 1, 'HorasProd' => 36, 'TotalPedido' => 100, 'SaldoPedido' => 110,
                'FechaInicio' => '2026-09-10 08:00:00', 'FechaFinal' => '2026-09-11 20:00:00', 'Ultimo' => '0'],
            ['Id' => 11, 'NoTelarId' => '302', 'Posicion' => 2, 'EnProceso' => 0, 'HorasProd' => 24, 'TotalPedido' => 400, 'SaldoPedido' => 390, 'Produccion' => 50,
                'NoProduccion' => '50011', 'OrdCompartida' => self::GRUPO, 'OrdCompartidaLider' => 0, 'OrdPrincipal' => 50010,
                'FechaInicio' => '2026-09-11 20:00:00', 'FechaFinal' => '2026-09-12 20:00:00', 'Ultimo' => '1'],
            ['Id' => 23, 'NoTelarId' => '303', 'Posicion' => 1, 'EnProceso' => 1, 'HorasProd' => 48, 'TotalPedido' => 100, 'SaldoPedido' => 110,
                'FechaInicio' => '2026-09-10 08:00:00', 'FechaFinal' => '2026-09-12 08:00:00', 'Ultimo' => '0'],
            ['Id' => 12, 'NoTelarId' => '303', 'Posicion' => 2, 'EnProceso' => 0, 'HorasProd' => 24, 'TotalPedido' => 300, 'SaldoPedido' => 330,
                'OrdCompartida' => self::GRUPO, 'OrdCompartidaLider' => 0, 'OrdPrincipal' => 50010,
                'FechaInicio' => '2026-09-12 08:00:00', 'FechaFinal' => '2026-09-13 08:00:00', 'Ultimo' => '1'],
        ];
        foreach ($filas as $fila) {
            DB::table('ReqProgramaTejido')->insert(array_merge($base, $fila));
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ---- helpers --------------------------------------------------------------------

    private function como(array $permisos = [2 => ['eliminar']])
    {
        return $this->actingAs($this->usuarioConPermisos($permisos));
    }

    private function borrar(int $id)
    {
        return $this->como()->deleteJson("/planeacion/programa-tejido/{$id}");
    }

    /** Deja el grupo en 2 miembros (A y B): C sale del grupo. */
    private function grupoDeDos(): void
    {
        DB::table('ReqProgramaTejido')->where('Id', 12)->update(['OrdCompartida' => null, 'OrdCompartidaLider' => null, 'OrdPrincipal' => null]);
    }

    /** @return array<string, mixed> Columnas que fija cada test, normalizadas. */
    private function estado(int $id): ?array
    {
        $r = DB::table('ReqProgramaTejido')->where('Id', $id)->first();
        if ($r === null) {
            return null;
        }

        return [
            'NoTelarId' => (string) $r->NoTelarId,
            'Posicion' => (int) $r->Posicion,
            'FechaInicio' => (string) $r->FechaInicio,
            'FechaFinal' => (string) $r->FechaFinal,
            'EnProceso' => (int) $r->EnProceso,
            'Ultimo' => (string) $r->Ultimo,
            'OrdCompartida' => $r->OrdCompartida === null ? null : (int) $r->OrdCompartida,
            'OrdCompartidaLider' => $r->OrdCompartidaLider === null ? null : (int) $r->OrdCompartidaLider,
            'OrdPrincipal' => $r->OrdPrincipal === null ? null : (int) $r->OrdPrincipal,
            'TotalPedido' => (float) $r->TotalPedido,
            'SaldoPedido' => (float) $r->SaldoPedido,
        ];
    }

    private function idsTelar(string $telar): array
    {
        return DB::table('ReqProgramaTejido')->where('NoTelarId', $telar)->orderBy('Posicion')->pluck('Id')->map(fn ($id) => (int) $id)->all();
    }

    /** Filas de Programa fuera de los telares del escenario + toda Muestras + CatCodificados. */
    private function fotoAjena(): array
    {
        $foto = $this->fotoSuperficies();
        $foto['ReqProgramaTejido'] = array_values(array_filter(
            $foto['ReqProgramaTejido'],
            fn ($r) => ! in_array((string) $r['NoTelarId'], ['301', '302', '303'], true)
        ));
        unset($foto['ReqProgramaTejidoLine']);

        return $foto;
    }

    private function fotoCompleta(): array
    {
        return $this->fotoSuperficies();
    }

    // ---- borrar al líder ------------------------------------------------------------

    public function test_borrar_al_lider_en_grupo_de_dos_transfiere_el_pedido_al_otro_y_lo_hace_lider(): void
    {
        $this->grupoDeDos();

        $this->borrar(10)->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('telares_afectados', 2)
            ->assertJsonStructure(['success', 'message', 'cascaded_records', 'telares_afectados']);

        $this->assertNull($this->estado(10));
        $this->assertSame([
            'NoTelarId' => '302', 'Posicion' => 2,
            'FechaInicio' => '2026-09-11 20:00:00', 'FechaFinal' => '2026-09-12 20:00:00',
            'EnProceso' => 0, 'Ultimo' => '1',
            'OrdCompartida' => self::GRUPO, 'OrdCompartidaLider' => 1, 'OrdPrincipal' => 50010,
            // CARACTERIZA: Saldo = (400 + 1000) - Produccion(50) = 1350, ignora %seg=10 (regla: Pedido×1.1−Prod = 1490).
            'TotalPedido' => 1400.0, 'SaldoPedido' => 1350.0,
        ], $this->estado(11));
    }

    public function test_borrar_al_lider_compacta_su_telar_y_encadena_la_cola(): void
    {
        $this->grupoDeDos();

        $this->borrar(10)->assertOk();

        $this->assertSame([20, 21], $this->idsTelar('301'));
        $this->assertSame([
            'NoTelarId' => '301', 'Posicion' => 1,
            'FechaInicio' => '2026-09-10 08:00:00', 'FechaFinal' => '2026-09-11 08:00:00',
            'EnProceso' => 1, 'Ultimo' => '0',
            'OrdCompartida' => null, 'OrdCompartidaLider' => null, 'OrdPrincipal' => null,
            'TotalPedido' => 100.0, 'SaldoPedido' => 110.0,
        ], $this->estado(20));
        $this->assertSame([
            'NoTelarId' => '301', 'Posicion' => 2,
            'FechaInicio' => '2026-09-11 08:00:00', 'FechaFinal' => '2026-09-11 20:00:00',
            'EnProceso' => 0, 'Ultimo' => '1',
            'OrdCompartida' => null, 'OrdCompartidaLider' => null, 'OrdPrincipal' => null,
            'TotalPedido' => 50.0, 'SaldoPedido' => 55.0,
        ], $this->estado(21));
    }

    public function test_borrar_al_lider_en_grupo_de_tres_transfiere_al_de_fecha_inicio_mas_reciente(): void
    {
        $this->borrar(10)->assertOk()->assertJsonPath('success', true)->assertJsonPath('telares_afectados', 2);

        // CARACTERIZA: el receptor es el ÚLTIMO por FechaInicio (C, Id 12, sin NoProduccion),
        // no el que elegiría OrdCompartida::seleccionarLider (B, Id 11: más antiguo con NoProduccion).
        $this->assertSame([
            'NoTelarId' => '303', 'Posicion' => 2,
            'FechaInicio' => '2026-09-12 08:00:00', 'FechaFinal' => '2026-09-13 08:00:00',
            'EnProceso' => 0, 'Ultimo' => '1',
            'OrdCompartida' => self::GRUPO, 'OrdCompartidaLider' => 1, 'OrdPrincipal' => 50010,
            // CARACTERIZA: 300 + 1000 − 0 = 1300, ignora %seg (con %seg sería 1430).
            'TotalPedido' => 1300.0, 'SaldoPedido' => 1300.0,
        ], $this->estado(12));

        // B queda intacto (sigue no-líder) y el grupo conserva OrdCompartida/OrdPrincipal del líder borrado.
        $this->assertSame([
            'NoTelarId' => '302', 'Posicion' => 2,
            'FechaInicio' => '2026-09-11 20:00:00', 'FechaFinal' => '2026-09-12 20:00:00',
            'EnProceso' => 0, 'Ultimo' => '1',
            'OrdCompartida' => self::GRUPO, 'OrdCompartidaLider' => 0, 'OrdPrincipal' => 50010,
            'TotalPedido' => 400.0, 'SaldoPedido' => 390.0,
        ], $this->estado(11));
    }

    public function test_el_receptor_al_borrar_al_lider_no_coincide_con_seleccionar_lider(): void
    {
        $miembros = ReqProgramaTejido::query()->whereIn('Id', [11, 12])->get();
        $esperadoPorRegla = OrdCompartida::seleccionarLider($miembros);
        $this->assertSame(11, (int) $esperadoPorRegla->Id);

        $this->borrar(10)->assertOk();

        $lideres = DB::table('ReqProgramaTejido')->where('OrdCompartida', self::GRUPO)->where('OrdCompartidaLider', 1)->pluck('Id')->map(fn ($i) => (int) $i)->all();
        // CARACTERIZA: queda líder Id 12 (sin NoProduccion: no podría ser líder según seleccionarLider).
        $this->assertSame([12], $lideres);
    }

    // ---- borrar a un no-líder -------------------------------------------------------

    public function test_borrar_un_no_lider_suma_su_pedido_al_lider_y_no_cambia_el_liderazgo(): void
    {
        $this->borrar(11)->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('telares_afectados', 2);

        $this->assertNull($this->estado(11));
        $this->assertSame([
            'NoTelarId' => '301', 'Posicion' => 2,
            'FechaInicio' => '2026-09-11 08:00:00', 'FechaFinal' => '2026-09-12 08:00:00',
            'EnProceso' => 0, 'Ultimo' => '0',
            'OrdCompartida' => self::GRUPO, 'OrdCompartidaLider' => 1, 'OrdPrincipal' => 50010,
            // CARACTERIZA: (1000 + 400) − Produccion(100) = 1300, ignora %seg (con %seg: 1440).
            'TotalPedido' => 1400.0, 'SaldoPedido' => 1300.0,
        ], $this->estado(10));
        $this->assertSame([22], $this->idsTelar('302'));
        $this->assertSame([12], DB::table('ReqProgramaTejido')->where('OrdCompartida', self::GRUPO)->where('OrdCompartidaLider', 0)->pluck('Id')->map(fn ($i) => (int) $i)->all());
    }

    public function test_borrar_un_no_lider_deja_su_telar_con_la_cabeza_como_ultimo(): void
    {
        $this->borrar(12)->assertOk();

        $this->assertSame([23], $this->idsTelar('303'));
        $this->assertSame([
            'NoTelarId' => '303', 'Posicion' => 1,
            'FechaInicio' => '2026-09-10 08:00:00', 'FechaFinal' => '2026-09-12 08:00:00',
            'EnProceso' => 1, 'Ultimo' => '1',
            'OrdCompartida' => null, 'OrdCompartidaLider' => null, 'OrdPrincipal' => null,
            'TotalPedido' => 100.0, 'SaldoPedido' => 110.0,
        ], $this->estado(23));
    }

    public function test_grupo_que_queda_de_uno_conserva_ord_compartida_y_liderazgo(): void
    {
        $this->grupoDeDos();

        $this->borrar(11)->assertOk();

        // CARACTERIZA: un "grupo" de un solo miembro sigue con OrdCompartida y OrdCompartidaLider=1.
        $this->assertSame(['Id' => 10, 'OrdCompartida' => self::GRUPO, 'OrdCompartidaLider' => 1], (function () {
            $r = DB::table('ReqProgramaTejido')->where('OrdCompartida', self::GRUPO)->first();

            return ['Id' => (int) $r->Id, 'OrdCompartida' => (int) $r->OrdCompartida, 'OrdCompartidaLider' => (int) $r->OrdCompartidaLider];
        })());
        $this->assertSame(1, DB::table('ReqProgramaTejido')->where('OrdCompartida', self::GRUPO)->count());
    }

    public function test_borrar_el_unico_miembro_restante_no_transfiere_nada(): void
    {
        DB::table('ReqProgramaTejido')->whereIn('Id', [11, 12])->update(['OrdCompartida' => null, 'OrdCompartidaLider' => null]);
        $totalesAntes = DB::table('ReqProgramaTejido')->orderBy('Id')->pluck('TotalPedido', 'Id')->except([10])->all();

        $this->borrar(10)->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonMissingPath('telares_afectados');

        $this->assertNull($this->estado(10));
        $this->assertSame([20, 21], $this->idsTelar('301'));
        $this->assertEquals($totalesAntes, DB::table('ReqProgramaTejido')->orderBy('Id')->pluck('TotalPedido', 'Id')->all());
    }

    // ---- aislamiento, permisos y rollback -------------------------------------------

    public function test_borrar_en_grupo_no_toca_otros_telares_ni_la_superficie_muestras(): void
    {
        $antes = $this->fotoAjena();

        $this->borrar(10)->assertOk();
        $this->borrar(11)->assertOk();

        $this->assertSame($antes, $this->fotoAjena());
    }

    public function test_borrar_en_grupo_sin_permiso_eliminar_es_403_y_no_cambia_nada(): void
    {
        $antes = $this->fotoCompleta();

        $this->como([2 => ['acceso', 'modificar']])->deleteJson('/planeacion/programa-tejido/10')->assertForbidden();
        // Permiso de eliminar en Muestras (rol 5) no sirve para Programa.
        $this->como([5 => ['eliminar']])->deleteJson('/planeacion/programa-tejido/10')->assertForbidden();

        $this->assertSame($antes, $this->fotoCompleta());
    }

    public function test_grupo_sin_lider_marcado_responde_422_y_no_cambia_nada(): void
    {
        DB::table('ReqProgramaTejido')->where('Id', 10)->update(['OrdCompartidaLider' => 0]);
        $antes = $this->fotoCompleta();

        $this->borrar(11)->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'No se encontró el registro líder del grupo compartido.');

        $this->assertSame($antes, $this->fotoCompleta());
    }

    public function test_fallo_al_borrar_despues_de_transferir_revierte_todo(): void
    {
        // El DELETE falla DESPUÉS de que el receptor ya recibió el pedido y se recalcularon fechas.
        DB::statement("CREATE TRIGGER no_borrar_10 BEFORE DELETE ON ReqProgramaTejido WHEN OLD.Id = 10 BEGIN SELECT RAISE(ABORT, 'falla simulada'); END");
        $antes = $this->fotoCompleta();

        $r = $this->borrar(10)->assertJsonPath('success', false);

        // CARACTERIZA: QueryException hereda de RuntimeException, así que un error de BD sale como
        // 422 (no 500) y el mensaje SQL crudo llega al usuario.
        $r->assertStatus(422);
        $this->assertStringContainsString('falla simulada', (string) $r->json('message'));

        $this->assertSame($antes, $this->fotoCompleta(), 'rollback total: receptor, fechas y líneas intactos');
    }

    public function test_borrar_en_grupo_un_registro_en_proceso_por_la_ruta_normal_es_422(): void
    {
        DB::table('ReqProgramaTejido')->where('Id', 10)->update(['EnProceso' => 1]);
        $antes = $this->fotoCompleta();

        $this->borrar(10)->assertStatus(422)->assertJsonPath('success', false);

        $this->assertSame($antes, $this->fotoCompleta());
    }

    public function test_borrar_inexistente_es_404_con_codigo(): void
    {
        $this->borrar(9999)->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('codigo', 'registro_no_encontrado');
    }

    // ---- destroyEnProceso -----------------------------------------------------------

    public function test_eliminar_en_proceso_promueve_al_siguiente_que_arranca_en_now_y_encadena(): void
    {
        $antesAjena = $this->fotoAjena();

        $r = $this->como()->deleteJson('/planeacion/programa-tejido/20/en-proceso')
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('cascaded_records', 2);
        $this->assertSame([10, 21], $r->json('registros_ids'));

        $this->assertNull($this->estado(20));
        $this->assertSame([10, 21], $this->idsTelar('301'));
        $this->assertSame([
            'NoTelarId' => '301', 'Posicion' => 1,
            'FechaInicio' => '2026-09-10 08:00:00', 'FechaFinal' => '2026-09-11 08:00:00',
            'EnProceso' => 1, 'Ultimo' => '0',
            'OrdCompartida' => self::GRUPO, 'OrdCompartidaLider' => 1, 'OrdPrincipal' => 50010,
            'TotalPedido' => 1000.0, 'SaldoPedido' => 1000.0,
        ], $this->estado(10));
        $this->assertSame([
            'NoTelarId' => '301', 'Posicion' => 2,
            'FechaInicio' => '2026-09-11 08:00:00', 'FechaFinal' => '2026-09-11 20:00:00',
            'EnProceso' => 0, 'Ultimo' => '1',
            'OrdCompartida' => null, 'OrdCompartidaLider' => null, 'OrdPrincipal' => null,
            'TotalPedido' => 50.0, 'SaldoPedido' => 55.0,
        ], $this->estado(21));
        // No toca a los demás miembros del grupo ni a otros telares.
        $this->assertSame(400.0, $this->estado(11)['TotalPedido']);
        $this->assertSame(300.0, $this->estado(12)['TotalPedido']);
        $this->assertSame($antesAjena, $this->fotoAjena());
    }

    public function test_eliminar_en_proceso_del_unico_registro_deja_el_telar_vacio(): void
    {
        $this->borrar(11)->assertOk(); // telar 302 queda solo con la cabeza

        $this->como()->deleteJson('/planeacion/programa-tejido/22/en-proceso')
            ->assertOk()->assertJsonPath('success', true)->assertJsonMissingPath('detalles');

        $this->assertSame([], $this->idsTelar('302'));
    }

    public function test_eliminar_en_proceso_sin_permiso_es_403(): void
    {
        $this->como([2 => ['modificar']])->deleteJson('/planeacion/programa-tejido/20/en-proceso')->assertForbidden();

        $this->assertNotNull($this->estado(20));
    }
}
