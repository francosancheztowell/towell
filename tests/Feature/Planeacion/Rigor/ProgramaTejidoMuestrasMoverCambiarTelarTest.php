<?php

namespace Tests\Feature\Planeacion\Rigor;

use App\Models\Planeacion\ReqModelosCodificados;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Planeacion\Concerns\ConPermisosPlaneacion;
use Tests\Feature\Planeacion\Concerns\ProgramaTejidoFixtures;
use Tests\TestCase;

/**
 * Prioridad/mover (DragAndDropTejido) y cambiar-telar en la superficie MUESTRAS.
 *
 * Las dos superficies comparten los Id 1..6 a propósito: una escritura cruzada por Id cae
 * en una fila real de la otra tabla y la foto lo detecta. Telar 201 en ambas = Id 1
 * (EnProceso) + Id 2 + Id 6. El permiso de Muestras es el idrol 5; el de Programa, el 2.
 *
 * Ojo: en tests la app es una sola instancia, así que la config que pone
 * ProgramaTejidoContext para una URI de Muestras sobrevive al request. Cada request a
 * Programa va precedido de usarSuperficie('programa') (en producción cada request nace limpio).
 */
class ProgramaTejidoMuestrasMoverCambiarTelarTest extends TestCase
{
    use ConPermisosPlaneacion;
    use ProgramaTejidoFixtures;

    private const COLUMNAS_ESTADO = [
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
            DB::table($tabla)->where('Id', 2)->update(['Ultimo' => '0', 'TamanoClave' => 'MOD1', 'HorasProd' => 24]);
            DB::table($tabla)->insert([
                'Id' => 6, 'SalonTejidoId' => 'SMIT', 'NoTelarId' => '201', 'Posicion' => 3, 'EnProceso' => 0,
                'TotalPedido' => 300, 'SaldoPedido' => 300, 'HorasProd' => 12,
                'FechaInicio' => '2026-09-05 06:00:00', 'FechaFinal' => '2026-09-05 18:00:00', 'Ultimo' => '1',
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

    private function como(array $permisos = [5 => ['modificar']])
    {
        return $this->actingAs($this->usuarioConPermisos($permisos));
    }

    private function posiciones(string $tabla, string $telar): array
    {
        return DB::table($tabla)->where('NoTelarId', $telar)->orderBy('Posicion')->pluck('Id')->map(fn ($id) => (int) $id)->all();
    }

    private function valoresPosicion(string $tabla, string $telar): array
    {
        return DB::table($tabla)->where('NoTelarId', $telar)->orderBy('Posicion')->pluck('Posicion')->map(fn ($p) => (int) $p)->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function estado(string $tabla, array $ids): array
    {
        return DB::table($tabla)->whereIn('Id', $ids)->orderBy('Id')->get(self::COLUMNAS_ESTADO)
            ->map(fn ($r) => (array) $r)->keyBy('Id')->all();
    }

    /** Foto de todo lo que NO es MuestrasPrograma/MuestrasProgramaLine. */
    private function fotoSinMuestras(): array
    {
        return array_diff_key($this->fotoSuperficies(), ['MuestrasPrograma' => 1, 'MuestrasProgramaLine' => 1]);
    }

    /** Foto de lo que NO es ReqProgramaTejido/ReqProgramaTejidoLine (CatCodificados incluido). */
    private function fotoSinPrograma(): array
    {
        return array_diff_key($this->fotoSuperficies(), ['ReqProgramaTejido' => 1, 'ReqProgramaTejidoLine' => 1]);
    }

    // ---- mover --------------------------------------------------------------------------

    public function test_mover_en_muestras_reordena_muestras_programa_y_deja_programa_intacto(): void
    {
        $programaAntes = $this->fotoSinMuestras();
        $otrosTelares = fn () => DB::table('MuestrasPrograma')->where('NoTelarId', '!=', '201')->orderBy('Id')->get()->map(fn ($r) => (array) $r)->all();
        $otrosAntes = $otrosTelares();

        $this->como()->postJson('/planeacion/muestras/6/prioridad/mover', ['new_position' => 1])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('registro_id', 6)
            ->assertJsonPath('cascaded_records', 3);

        $this->assertSame([1, 6, 2], $this->posiciones('MuestrasPrograma', '201'));
        $this->assertSame([1, 2, 3], $this->valoresPosicion('MuestrasPrograma', '201'));

        $e = $this->estado('MuestrasPrograma', [1, 2, 6]);
        $this->assertSame([1, 0, 0], [(int) $e[1]['EnProceso'], (int) $e[6]['EnProceso'], (int) $e[2]['EnProceso']]);
        $this->assertSame(['0', '0', '1'], [$e[1]['Ultimo'], $e[6]['Ultimo'], $e[2]['Ultimo']], 'Ultimo solo en el último del telar');
        $this->assertSame('2026-09-10 08:00:00', $e[1]['FechaInicio'], 'el EnProceso arranca en now()');
        $this->assertGreaterThanOrEqual(strtotime($e[1]['FechaFinal']), strtotime($e[6]['FechaInicio']));
        $this->assertGreaterThanOrEqual(strtotime($e[6]['FechaFinal']), strtotime($e[2]['FechaInicio']));
        // Totales y grupos no se tocan al reordenar.
        $this->assertSame([1000.0, 500.0, 300.0], [(float) $e[1]['TotalPedido'], (float) $e[2]['TotalPedido'], (float) $e[6]['TotalPedido']]);
        $this->assertSame([800.0, 500.0, 300.0], [(float) $e[1]['SaldoPedido'], (float) $e[2]['SaldoPedido'], (float) $e[6]['SaldoPedido']]);

        $this->assertSame($otrosAntes, $otrosTelares(), 'los demás telares de Muestras no se tocan');
        $this->assertSame($programaAntes, $this->fotoSinMuestras(), 'ni ReqProgramaTejido, ni sus líneas, ni CatCodificados cambian');
    }

    public function test_mover_en_programa_deja_muestras_intacto(): void
    {
        $muestrasAntes = $this->fotoSinPrograma();

        $this->usarSuperficie('programa');
        $this->como([2 => ['modificar']])->postJson('/planeacion/programa-tejido/6/prioridad/mover', ['new_position' => 1])
            ->assertOk()->assertJsonPath('success', true);

        $this->assertSame([1, 6, 2], $this->posiciones('ReqProgramaTejido', '201'));
        $this->assertSame($muestrasAntes, $this->fotoSinPrograma());
    }

    public function test_mover_en_muestras_exige_modificar_del_modulo_5_no_del_2(): void
    {
        $antes = $this->fotoSuperficies();

        $this->como([2 => ['acceso', 'crear', 'modificar', 'eliminar', 'registrar'], 5 => ['acceso']])
            ->postJson('/planeacion/muestras/6/prioridad/mover', ['new_position' => 1])
            ->assertForbidden();

        $this->assertSame($antes, $this->fotoSuperficies());
    }

    public function test_mover_en_programa_con_solo_permiso_de_muestras_es_403(): void
    {
        $antes = $this->fotoSuperficies();

        $this->usarSuperficie('programa');
        $this->como([5 => ['acceso', 'modificar']])
            ->postJson('/planeacion/programa-tejido/6/prioridad/mover', ['new_position' => 1])
            ->assertForbidden();

        $this->assertSame($antes, $this->fotoSuperficies());
    }

    public function test_mover_en_muestras_un_id_que_solo_existe_en_programa_es_404(): void
    {
        DB::table('ReqProgramaTejido')->insert([
            'Id' => 50, 'SalonTejidoId' => 'SMIT', 'NoTelarId' => '201', 'Posicion' => 4, 'EnProceso' => 0,
            'FechaInicio' => '2026-09-06 06:00:00', 'FechaFinal' => '2026-09-06 18:00:00', 'Ultimo' => '0',
        ]);
        $antes = $this->fotoSuperficies();

        $this->como()->postJson('/planeacion/muestras/50/prioridad/mover', ['new_position' => 1])->assertNotFound();

        $this->assertSame($antes, $this->fotoSuperficies());
    }

    public function test_mover_en_muestras_un_registro_en_proceso_es_422_sin_cambios(): void
    {
        $antes = $this->fotoSuperficies();

        $this->como()->postJson('/planeacion/muestras/1/prioridad/mover', ['new_position' => 2])
            ->assertStatus(422)
            ->assertExactJson([
                'success' => false,
                'message' => 'No se puede mover un registro en proceso. Debe finalizar el proceso antes de moverlo.',
            ]);

        $this->assertSame($antes, $this->fotoSuperficies());
    }

    public function test_mover_en_muestras_antes_del_en_proceso_es_422_sin_cambios(): void
    {
        $antes = $this->fotoSuperficies();

        $r = $this->como()->postJson('/planeacion/muestras/6/prioridad/mover', ['new_position' => 0])
            ->assertStatus(422)->assertJsonPath('success', false);

        $this->assertStringContainsString('posición mínima permitida es 2', $r->json('message'));
        $this->assertSame($antes, $this->fotoSuperficies());
    }

    public function test_mover_en_muestras_ante_fallo_a_mitad_revierte_todo(): void
    {
        // Sin CambioHilo en MuestrasPrograma: el "Posicion + 10000" pasa, el update por fila
        // (que sí escribe CambioHilo) truena dentro de la transacción.
        Schema::table('MuestrasPrograma', fn (Blueprint $t) => $t->dropColumn('CambioHilo'));
        $antes = $this->fotoSuperficies();

        // Un fallo de BD es 500 con mensaje fijo: ni 422 "de negocio" ni el SQL crudo.
        $this->como()->postJson('/planeacion/muestras/6/prioridad/mover', ['new_position' => 1])
            ->assertStatus(500)->assertJsonPath('success', false)
            ->assertJsonPath('message', 'No se pudo mover el registro. Intente de nuevo.');

        $this->assertSame($antes, $this->fotoSuperficies(), 'ni el +10000 temporal ni ninguna fecha deben quedar');
        $this->assertSame([1, 2, 3], $this->valoresPosicion('MuestrasPrograma', '201'));
    }

    // ---- cambiar telar --------------------------------------------------------------------

    public function test_cambiar_telar_en_muestras_mueve_en_muestras_y_deja_programa_intacto(): void
    {
        $programaAntes = $this->fotoSinMuestras();

        $this->como()->postJson('/planeacion/muestras/2/cambiar-telar', [
            'nuevo_salon' => 'SMIT', 'nuevo_telar' => '204', 'target_position' => 1,
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('registro_id', 2)
            ->assertJsonStructure(['success', 'message', 'registros_afectados', 'detalles', 'updates', 'registro_id']);

        $movido = DB::table('MuestrasPrograma')->where('Id', 2)->first();
        $this->assertSame(['SMIT', '204'], [$movido->SalonTejidoId, (string) $movido->NoTelarId]);
        $this->assertSame([5, 2], $this->posiciones('MuestrasPrograma', '204'));
        $this->assertSame([1, 2], $this->valoresPosicion('MuestrasPrograma', '204'));
        $this->assertSame([1, 6], $this->posiciones('MuestrasPrograma', '201'), 'el origen se compacta');
        $this->assertSame([1, 2], $this->valoresPosicion('MuestrasPrograma', '201'));
        $this->assertSame('1', (string) DB::table('MuestrasPrograma')->where('Id', 6)->value('Ultimo'));
        $this->assertSame('1', (string) $movido->Ultimo);

        $this->assertSame($programaAntes, $this->fotoSinMuestras());
    }

    public function test_cambiar_telar_en_programa_deja_muestras_intacto(): void
    {
        $muestrasAntes = $this->fotoSinPrograma();

        $this->usarSuperficie('programa');
        $this->como([2 => ['modificar']])->postJson('/planeacion/programa-tejido/2/cambiar-telar', [
            'nuevo_salon' => 'SMIT', 'nuevo_telar' => '204', 'target_position' => 1,
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertSame('204', (string) DB::table('ReqProgramaTejido')->where('Id', 2)->value('NoTelarId'));
        $this->assertSame($muestrasAntes, $this->fotoSinPrograma());
    }

    public function test_cambiar_telar_en_muestras_exige_modificar_del_modulo_5(): void
    {
        $antes = $this->fotoSuperficies();

        $this->como([2 => ['modificar'], 5 => ['acceso']])->postJson('/planeacion/muestras/2/cambiar-telar', [
            'nuevo_salon' => 'SMIT', 'nuevo_telar' => '204', 'target_position' => 1,
        ])->assertForbidden();

        $this->assertSame($antes, $this->fotoSuperficies());
    }

    public function test_cambiar_telar_en_muestras_con_orden_que_coincide_con_programa_reescribe_cat_codificados(): void
    {
        // CARACTERIZA: CatCodificados es compartido y sincronizarTelarCatCodificados filtra solo
        // por OrdenTejido. Una muestra con el mismo NoProduccion que una orden de Programa
        // (30001, telar 201) le cambia telar y departamento a la orden de Programa.
        DB::table('MuestrasPrograma')->where('Id', 2)->update(['NoProduccion' => '30001']);

        $this->como()->postJson('/planeacion/muestras/2/cambiar-telar', [
            'nuevo_salon' => 'JACQUARD', 'nuevo_telar' => '210', 'target_position' => 0,
        ])->assertOk()->assertJsonPath('success', true);

        $cat = DB::table('CatCodificados')->where('OrdenTejido', '30001')->first();
        $this->assertSame([210, 'JACQUARD'], [(int) $cat->TelarId, $cat->Departamento]);
        $this->assertSame('201', (string) DB::table('ReqProgramaTejido')->where('Id', 1)->value('NoTelarId'), 'la orden de Programa sigue en 201');
    }

    public function test_cambiar_telar_en_muestras_ante_fallo_a_mitad_revierte_origen_y_destino(): void
    {
        // Sin Maquina en MuestrasPrograma: los updates del origen (Posicion=-Id, +10000, fechas)
        // pasan, y el del registro movido (que escribe Maquina) truena dentro de la transacción.
        Schema::table('MuestrasPrograma', fn (Blueprint $t) => $t->dropColumn('Maquina'));
        $antes = $this->fotoSuperficies();

        $r = $this->como()->postJson('/planeacion/muestras/2/cambiar-telar', [
            'nuevo_salon' => 'SMIT', 'nuevo_telar' => '204', 'target_position' => 1,
        ])->assertStatus(500)->assertJsonPath('success', false);

        // Sin el texto de la excepción SQL: mensaje fijo, el detalle va al log.
        $this->assertSame('Error al cambiar de telar.', $r->json('message'));
        $this->assertSame($antes, $this->fotoSuperficies());
        $this->assertSame([1, 2, 6], $this->posiciones('MuestrasPrograma', '201'));
        $this->assertSame([1, 2, 3], $this->valoresPosicion('MuestrasPrograma', '201'));
    }

    public function test_verificar_cambio_telar_en_muestras_lee_el_registro_de_muestras(): void
    {
        // Misma Id, distinta clave: Programa no tiene modelo en JACQUARD, Muestras sí.
        DB::table('ReqProgramaTejido')->where('Id', 2)->update(['TamanoClave' => 'SOLO-PROGRAMA']);

        $this->como()->postJson('/planeacion/muestras/2/verificar-cambio-telar', ['nuevo_salon' => 'JACQUARD', 'nuevo_telar' => '210'])
            ->assertOk()->assertJsonPath('puede_mover', true)->assertJsonPath('requiere_confirmacion', true);

        $this->usarSuperficie('programa');
        $this->como([2 => ['modificar']])->postJson('/planeacion/programa-tejido/2/verificar-cambio-telar', ['nuevo_salon' => 'JACQUARD', 'nuevo_telar' => '210'])
            ->assertOk()->assertJsonPath('puede_mover', false)->assertJsonPath('clave_modelo', 'SOLO-PROGRAMA');
    }

    public function test_verificar_cambio_telar_en_muestras_exige_modificar_de_muestras(): void
    {
        // Sin permisos, o con modificar sólo de Programa, Muestras no entrega el diagnóstico.
        $antes = $this->fotoSuperficies();

        $this->como([])->postJson('/planeacion/muestras/2/verificar-cambio-telar', ['nuevo_salon' => 'JACQUARD', 'nuevo_telar' => '210'])
            ->assertForbidden();
        $this->como([2 => ['modificar']])->postJson('/planeacion/muestras/2/verificar-cambio-telar', ['nuevo_salon' => 'JACQUARD', 'nuevo_telar' => '210'])
            ->assertForbidden();

        $this->assertSame($antes, $this->fotoSuperficies());
    }
}
