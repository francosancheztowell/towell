<?php

namespace Tests\Feature\Planeacion;

use App\Models\Planeacion\ReqModelosCodificados;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Planeacion\Concerns\ConPermisosPlaneacion;
use Tests\Feature\Planeacion\Concerns\ProgramaTejidoFixtures;
use Tests\TestCase;

/**
 * Comportamiento (no solo "la ruta existe") de mover, vincular, desvincular y
 * balancearAutomatico. Fixtures: telar 201 = Id 1 (EnProceso) + Id 2 + Id 6; grupo
 * OrdCompartida 7 = Id 3 (líder, telar 202) + Id 4 (telar 203); Id 7 se suma al grupo en
 * los tests de 3 miembros.
 */
class ProgramaTejidoMoverVincularTest extends TestCase
{
    use ConPermisosPlaneacion;
    use ProgramaTejidoFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-10 08:00:00');
        $this->prepararSuperficies();
        $this->sembrarFixtures();
        $this->createTablaDesdeModelo(ReqModelosCodificados::class);
        $this->createTablaDbo('ReqCalendarioLine', ['CalendarioId' => 'text', 'FechaInicio' => 'text', 'FechaFin' => 'text']);

        // Tercer registro del telar 201 para poder reordenar sin tocar al EnProceso.
        DB::table('ReqProgramaTejido')->where('Id', 2)->update(['Ultimo' => '0']);
        DB::table('ReqProgramaTejido')->insert([
            'Id' => 6, 'SalonTejidoId' => 'SMIT', 'NoTelarId' => '201', 'Posicion' => 3, 'EnProceso' => 0,
            'TotalPedido' => 300, 'SaldoPedido' => 300, 'HorasProd' => 12,
            'FechaInicio' => '2026-09-05 06:00:00', 'FechaFinal' => '2026-09-05 18:00:00', 'Ultimo' => '1',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function como(array $permisos = [2 => ['modificar']])
    {
        return $this->actingAs($this->usuarioConPermisos($permisos));
    }

    private function posiciones(string $telar): array
    {
        return DB::table('ReqProgramaTejido')->where('NoTelarId', $telar)->orderBy('Posicion')->pluck('Id')->all();
    }

    private function agregarMiembroAlGrupo(): void
    {
        DB::table('ReqProgramaTejido')->insert([
            'Id' => 7, 'SalonTejidoId' => 'JACQUARD', 'NoTelarId' => '205', 'Posicion' => 1, 'EnProceso' => 0,
            'NoProduccion' => '30007', 'OrdCompartida' => 7, 'OrdCompartidaLider' => 0,
            'TotalPedido' => 200, 'SaldoPedido' => 200,
            'FechaInicio' => '2026-09-02 06:30:00', 'FechaFinal' => '2026-09-03 06:30:00', 'Ultimo' => '1',
        ]);
    }

    // ---- mover ----------------------------------------------------------------------

    public function test_mover_un_registro_en_proceso_se_rechaza_y_no_cambia_nada(): void
    {
        $antes = $this->posiciones('201');

        $this->como()->postJson('/planeacion/programa-tejido/1/prioridad/mover', ['new_position' => 2])
            ->assertStatus(422)->assertJsonPath('success', false);

        $this->assertSame($antes, $this->posiciones('201'));
    }

    public function test_mover_antes_del_registro_en_proceso_se_rechaza(): void
    {
        $this->como()->postJson('/planeacion/programa-tejido/6/prioridad/mover', ['new_position' => 0])
            ->assertStatus(422)->assertJsonPath('success', false);

        $this->assertSame([1, 2, 6], $this->posiciones('201'));
    }

    public function test_mover_fuera_de_rango_se_rechaza(): void
    {
        $this->como()->postJson('/planeacion/programa-tejido/6/prioridad/mover', ['new_position' => 9])
            ->assertStatus(422)->assertJsonPath('success', false);

        $this->assertSame([1, 2, 6], $this->posiciones('201'));
    }

    public function test_mover_a_la_misma_posicion_se_rechaza(): void
    {
        $this->como()->postJson('/planeacion/programa-tejido/6/prioridad/mover', ['new_position' => 2])
            ->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_mover_un_telar_con_un_solo_registro_se_rechaza(): void
    {
        $this->como()->postJson('/planeacion/programa-tejido/5/prioridad/mover', ['new_position' => 0])
            ->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_mover_exige_new_position_valida(): void
    {
        $this->como()->postJson('/planeacion/programa-tejido/6/prioridad/mover', ['new_position' => -1])
            ->assertStatus(422);
        $this->como()->postJson('/planeacion/programa-tejido/6/prioridad/mover', [])
            ->assertStatus(422);
    }

    public function test_mover_un_id_inexistente_es_404(): void
    {
        $this->como()->postJson('/planeacion/programa-tejido/9999/prioridad/mover', ['new_position' => 1])
            ->assertStatus(404);
    }

    public function test_mover_reordena_posiciones_sin_tocar_otros_telares(): void
    {
        $otros = fn () => DB::table('ReqProgramaTejido')->where('NoTelarId', '!=', '201')->orderBy('Id')->get()->map(fn ($r) => (array) $r)->all();
        $antes = $otros();

        $this->como()->postJson('/planeacion/programa-tejido/6/prioridad/mover', ['new_position' => 1])
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('registro_id', 6);

        $this->assertSame([1, 6, 2], $this->posiciones('201'));
        $this->assertSame($antes, $otros());
    }

    public function test_mover_encadena_las_fechas_arrancando_el_en_proceso_en_now(): void
    {
        $this->como()->postJson('/planeacion/programa-tejido/6/prioridad/mover', ['new_position' => 1])->assertOk();

        $filas = DB::table('ReqProgramaTejido')->where('NoTelarId', '201')->orderBy('Posicion')->get();
        $this->assertSame('2026-09-10 08:00:00', $filas[0]->FechaInicio, 'el EnProceso (primero) arranca en now() (DateHelpers::recalcularFechasSecuencia)');
        for ($i = 1; $i < $filas->count(); $i++) {
            $this->assertGreaterThanOrEqual(
                Carbon::parse($filas[$i - 1]->FechaFinal)->timestamp,
                Carbon::parse($filas[$i]->FechaInicio)->timestamp,
                'el registro en posición '.($i + 1).' no puede empezar antes de que termine el anterior'
            );
        }
    }

    public function test_mover_sin_permiso_modificar_es_403(): void
    {
        $this->como([2 => ['acceso']])->postJson('/planeacion/programa-tejido/6/prioridad/mover', ['new_position' => 1])
            ->assertForbidden();

        $this->assertSame([1, 2, 6], $this->posiciones('201'));
    }

    // ---- vincular -------------------------------------------------------------------

    public function test_vincular_asigna_la_ord_compartida_del_primero_a_todos(): void
    {
        $this->como()->postJson('/planeacion/programa-tejido/vincular-registros-existentes', ['registros_ids' => [1, 6]])
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('ord_compartida', 30001);

        $this->assertSame(
            [30001, 30001],
            DB::table('ReqProgramaTejido')->whereIn('Id', [1, 6])->orderBy('Id')->pluck('OrdCompartida')->map(fn ($v) => (int) $v)->all()
        );
        $this->assertSame(1, (int) DB::table('ReqProgramaTejido')->where('Id', 1)->value('OrdCompartidaLider'), 'el que tiene NoProduccion queda de líder');
    }

    public function test_vincular_rechaza_si_el_primero_no_tiene_no_produccion(): void
    {
        $this->como()->postJson('/planeacion/programa-tejido/vincular-registros-existentes', ['registros_ids' => [2, 1]])
            ->assertStatus(422)->assertJsonPath('success', false);

        $this->assertNull(DB::table('ReqProgramaTejido')->whereIn('Id', [1, 2])->whereNotNull('OrdCompartida')->value('Id'));
    }

    public function test_vincular_exige_al_menos_dos_registros_existentes(): void
    {
        $this->como()->postJson('/planeacion/programa-tejido/vincular-registros-existentes', ['registros_ids' => [1]])->assertStatus(422);
        $this->como()->postJson('/planeacion/programa-tejido/vincular-registros-existentes', ['registros_ids' => [1, 9999]])->assertStatus(422);
    }

    public function test_vincular_a_un_miembro_de_un_grupo_usa_el_no_produccion_del_primero(): void
    {
        // El grupo se identifica por el NoProduccion del líder propuesto (Id 3 = 30003).
        $this->como()->postJson('/planeacion/programa-tejido/vincular-registros-existentes', ['registros_ids' => [3, 6]])
            ->assertOk()->assertJsonPath('ord_compartida', 30003);

        $this->assertSame(30003, (int) DB::table('ReqProgramaTejido')->where('Id', 6)->value('OrdCompartida'));
    }

    // ---- desvincular ----------------------------------------------------------------

    public function test_desvincular_en_grupo_de_dos_libera_a_ambos(): void
    {
        $this->como()->postJson('/planeacion/programa-tejido/4/desvincular')
            ->assertOk()->assertJsonPath('success', true);

        foreach ([3, 4] as $id) {
            $fila = DB::table('ReqProgramaTejido')->where('Id', $id)->first();
            $this->assertNull($fila->OrdCompartida, "Id {$id} debe quedar sin orden compartida");
            $this->assertNull($fila->OrdCompartidaLider, "Id {$id} debe quedar sin líder");
        }
    }

    public function test_desvincular_en_grupo_de_tres_solo_saca_al_seleccionado(): void
    {
        $this->agregarMiembroAlGrupo();

        $this->como()->postJson('/planeacion/programa-tejido/7/desvincular')->assertOk();

        $this->assertNull(DB::table('ReqProgramaTejido')->where('Id', 7)->value('OrdCompartida'));
        $this->assertSame(7, (int) DB::table('ReqProgramaTejido')->where('Id', 3)->value('OrdCompartida'));
        $this->assertSame(7, (int) DB::table('ReqProgramaTejido')->where('Id', 4)->value('OrdCompartida'));
        $this->assertSame(
            1,
            (int) DB::table('ReqProgramaTejido')->where('OrdCompartida', 7)->sum('OrdCompartidaLider'),
            'queda exactamente un líder entre los que siguen vinculados'
        );
    }

    public function test_desvincular_al_lider_de_un_grupo_de_tres_reasigna_lider(): void
    {
        $this->agregarMiembroAlGrupo();

        $this->como()->postJson('/planeacion/programa-tejido/3/desvincular')->assertOk();

        $this->assertNull(DB::table('ReqProgramaTejido')->where('Id', 3)->value('OrdCompartida'));
        $this->assertSame(
            1,
            (int) DB::table('ReqProgramaTejido')->where('OrdCompartida', 7)->sum('OrdCompartidaLider'),
            'el grupo no se queda sin líder'
        );
    }

    public function test_desvincular_registro_sin_grupo_es_400_y_inexistente_404(): void
    {
        $this->como()->postJson('/planeacion/programa-tejido/1/desvincular')->assertStatus(400)->assertJsonPath('success', false);
        $this->como()->postJson('/planeacion/programa-tejido/9999/desvincular')->assertStatus(404);
    }

    // ---- balancear automático (casos de rechazo del servidor) -------------------------

    private function balancear(array $extra = [])
    {
        return $this->como()->postJson('/planeacion/programa-tejido/balancear-automatico', $extra + [
            'ord_compartida' => 7,
            'fecha_fin_objetivo' => '2026-09-30',
        ]);
    }

    public function test_balancear_automatico_exige_fecha_objetivo_y_orden(): void
    {
        $this->como()->postJson('/planeacion/programa-tejido/balancear-automatico', ['ord_compartida' => 7])->assertStatus(422);
        $this->como()->postJson('/planeacion/programa-tejido/balancear-automatico', ['fecha_fin_objetivo' => '2026-09-30'])->assertStatus(422);
    }

    public function test_balancear_automatico_orden_sin_registros_es_422(): void
    {
        $this->balancear(['ord_compartida' => 424242])->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_balancear_automatico_sin_fecha_de_inicio_es_422(): void
    {
        DB::table('ReqProgramaTejido')->whereIn('Id', [3, 4])->update(['FechaInicio' => null]);

        $this->balancear()->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_balancear_automatico_con_produccion_igual_al_pedido_no_tiene_saldo(): void
    {
        DB::table('ReqProgramaTejido')->where('Id', 3)->update(['Produccion' => 600]);
        DB::table('ReqProgramaTejido')->where('Id', 4)->update(['Produccion' => 400]);

        $r = $this->balancear()->assertStatus(422)->assertJsonPath('success', false);
        $this->assertStringContainsString('saldo', mb_strtolower($r->json('message')));
    }

    public function test_balancear_automatico_fecha_antes_del_inicio_mas_tardio_es_422(): void
    {
        DB::table('ReqProgramaTejido')->where('Id', 4)->update(['FechaInicio' => '2026-09-20 06:30:00']);

        $this->balancear(['fecha_fin_objetivo' => '2026-09-10'])->assertStatus(422)->assertJsonPath('success', false);
    }
}
