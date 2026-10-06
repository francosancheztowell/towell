<?php

namespace Tests\Feature\Planeacion;

use App\Models\Planeacion\ReqModelosCodificados;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Planeacion\Concerns\ConPermisosPlaneacion;
use Tests\Feature\Planeacion\Concerns\ProgramaTejidoFixtures;
use Tests\TestCase;

/**
 * Eliminar (a mitad de cadena y EnProceso) y cambiar de telar: comportamiento sobre
 * posiciones, fechas y CatCodificados. Fixtures: telar 201 = Id 1 (EnProceso) + Id 2 + Id 6.
 */
class ProgramaTejidoEliminarCambiarTelarTest extends TestCase
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

        DB::table('ReqProgramaTejido')->where('Id', 2)->update(['Ultimo' => '0', 'TamanoClave' => 'MOD1', 'HorasProd' => 24]);
        DB::table('ReqProgramaTejido')->insert([
            'Id' => 6, 'SalonTejidoId' => 'SMIT', 'NoTelarId' => '201', 'Posicion' => 3, 'EnProceso' => 0,
            'TotalPedido' => 300, 'SaldoPedido' => 300, 'HorasProd' => 12,
            'FechaInicio' => '2026-09-05 06:00:00', 'FechaFinal' => '2026-09-05 18:00:00', 'Ultimo' => '1',
        ]);
        // Modelo existente en SMIT y JACQUARD (cambiar telar exige que la clave exista en el destino).
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

    private function como(array $permisos = [2 => ['modificar', 'eliminar']])
    {
        return $this->actingAs($this->usuarioConPermisos($permisos));
    }

    private function posiciones(string $telar): array
    {
        return DB::table('ReqProgramaTejido')->where('NoTelarId', $telar)->orderBy('Posicion')->pluck('Id')->all();
    }

    // ---- eliminar -------------------------------------------------------------------

    public function test_eliminar_a_mitad_de_cadena_recorre_posiciones_y_encadena_fechas(): void
    {
        $this->como()->deleteJson('/planeacion/programa-tejido/2')
            ->assertOk()->assertJsonPath('success', true);

        $this->assertNull(DB::table('ReqProgramaTejido')->where('Id', 2)->value('Id'));
        $this->assertSame([1, 6], $this->posiciones('201'));
        $this->assertSame([1, 2], DB::table('ReqProgramaTejido')->where('NoTelarId', '201')->orderBy('Posicion')->pluck('Posicion')->map(fn ($p) => (int) $p)->all());

        $filas = DB::table('ReqProgramaTejido')->where('NoTelarId', '201')->orderBy('Posicion')->get();
        $this->assertGreaterThanOrEqual(
            Carbon::parse($filas[0]->FechaFinal)->timestamp,
            Carbon::parse($filas[1]->FechaInicio)->timestamp,
            'el que queda después no puede empezar antes de que termine el anterior'
        );
    }

    public function test_eliminar_no_toca_otros_telares(): void
    {
        $otros = fn () => DB::table('ReqProgramaTejido')->where('NoTelarId', '!=', '201')->orderBy('Id')->get()->map(fn ($r) => (array) $r)->all();
        $antes = $otros();

        $this->como()->deleteJson('/planeacion/programa-tejido/2')->assertOk();

        $this->assertSame($antes, $otros());
    }

    public function test_eliminar_un_registro_en_proceso_por_la_ruta_normal_se_rechaza(): void
    {
        $this->como()->deleteJson('/planeacion/programa-tejido/1')
            ->assertStatus(422)->assertJsonPath('success', false);

        $this->assertSame([1, 2, 6], $this->posiciones('201'));
    }

    public function test_eliminar_sin_permiso_eliminar_es_403_y_no_borra(): void
    {
        $this->como([2 => ['modificar']])->deleteJson('/planeacion/programa-tejido/2')->assertForbidden();

        $this->assertNotNull(DB::table('ReqProgramaTejido')->where('Id', 2)->value('Id'));
    }

    public function test_eliminar_en_proceso_promueve_al_siguiente_y_arranca_en_now(): void
    {
        $this->como()->deleteJson('/planeacion/programa-tejido/1/en-proceso')
            ->assertOk()->assertJsonPath('success', true);

        $this->assertNull(DB::table('ReqProgramaTejido')->where('Id', 1)->value('Id'));
        $this->assertSame([2, 6], $this->posiciones('201'));

        $primero = DB::table('ReqProgramaTejido')->where('Id', 2)->first();
        $this->assertSame('2026-09-10 08:00:00', $primero->FechaInicio, 'el nuevo primero arranca desde now()');
    }

    public function test_eliminar_en_proceso_sobre_un_registro_que_no_lo_esta_es_422(): void
    {
        $this->como()->deleteJson('/planeacion/programa-tejido/2/en-proceso')
            ->assertStatus(422)->assertJsonPath('success', false);

        $this->assertNotNull(DB::table('ReqProgramaTejido')->where('Id', 2)->value('Id'));
    }

    public function test_eliminar_en_proceso_inexistente_es_404(): void
    {
        $this->como()->deleteJson('/planeacion/programa-tejido/9999/en-proceso')->assertStatus(404);
    }

    public function test_eliminar_el_unico_registro_de_un_telar_lo_deja_vacio(): void
    {
        DB::table('ReqProgramaTejido')->where('Id', 5)->update(['FechaInicio' => '2026-09-01 06:30:00', 'FechaFinal' => '2026-09-02 06:30:00']);

        $this->como()->deleteJson('/planeacion/programa-tejido/5')->assertOk()->assertJsonPath('success', true);

        $this->assertSame([], $this->posiciones('204'));
    }

    // ---- cambiar telar --------------------------------------------------------------

    public function test_verificar_cambio_telar_mismo_telar_no_requiere_confirmacion(): void
    {
        $this->como()->postJson('/planeacion/programa-tejido/2/verificar-cambio-telar', ['nuevo_salon' => 'SMIT', 'nuevo_telar' => '201'])
            ->assertOk()->assertJsonPath('puede_mover', true)->assertJsonPath('requiere_confirmacion', false);
    }

    public function test_verificar_cambio_telar_sin_modelo_en_destino_no_puede_mover(): void
    {
        DB::table('ReqModelosCodificados')->where('SalonTejidoId', 'JACQUARD')->delete();

        $this->como()->postJson('/planeacion/programa-tejido/2/verificar-cambio-telar', ['nuevo_salon' => 'JACQUARD', 'nuevo_telar' => '210'])
            ->assertOk()->assertJsonPath('puede_mover', false);
    }

    public function test_verificar_cambio_telar_lista_salon_y_telar_como_cambios(): void
    {
        $r = $this->como()->postJson('/planeacion/programa-tejido/2/verificar-cambio-telar', ['nuevo_salon' => 'JACQUARD', 'nuevo_telar' => '210'])
            ->assertOk()->assertJsonPath('puede_mover', true)->assertJsonPath('requiere_confirmacion', true);

        $campos = array_column($r->json('cambios'), 'campo');
        $this->assertContains('Salón', $campos);
        $this->assertContains('Telar', $campos);
        $this->assertContains('Fecha Inicio', $campos);
    }

    public function test_cambiar_telar_mueve_el_registro_y_recorre_el_origen(): void
    {
        $this->como()->postJson('/planeacion/programa-tejido/2/cambiar-telar', [
            'nuevo_salon' => 'SMIT', 'nuevo_telar' => '204', 'target_position' => 1,
        ])->assertOk()->assertJsonPath('success', true)->assertJsonPath('registro_id', 2);

        $movido = DB::table('ReqProgramaTejido')->where('Id', 2)->first();
        $this->assertSame('204', (string) $movido->NoTelarId);
        $this->assertSame([5, 2], $this->posiciones('204'), 'queda en la posición pedida (1 = después del primero)');
        $this->assertSame([1, 6], $this->posiciones('201'), 'el origen se compacta');
        $this->assertSame([1, 2], DB::table('ReqProgramaTejido')->where('NoTelarId', '201')->orderBy('Posicion')->pluck('Posicion')->map(fn ($p) => (int) $p)->all());
    }

    public function test_cambiar_telar_sincroniza_telar_y_salon_en_cat_codificados(): void
    {
        DB::table('ReqProgramaTejido')->where('Id', 2)->update(['NoProduccion' => '30002', 'EnProceso' => 0]);
        DB::table('CatCodificados')->insert(['OrdenTejido' => '30002', 'TelarId' => 201, 'Departamento' => 'SMIT', 'Pedido' => 500]);

        $this->como()->postJson('/planeacion/programa-tejido/2/cambiar-telar', [
            'nuevo_salon' => 'JACQUARD', 'nuevo_telar' => '210', 'target_position' => 0,
        ])->assertOk()->assertJsonPath('success', true);

        $cat = DB::table('CatCodificados')->where('OrdenTejido', '30002')->first();
        $this->assertSame(210, (int) $cat->TelarId);
        $this->assertSame('JACQUARD', $cat->Departamento);
    }

    public function test_cambiar_telar_un_registro_en_proceso_se_rechaza(): void
    {
        $this->como()->postJson('/planeacion/programa-tejido/1/cambiar-telar', [
            'nuevo_salon' => 'SMIT', 'nuevo_telar' => '204', 'target_position' => 0,
        ])->assertStatus(422)->assertJsonPath('success', false);

        $this->assertSame([1, 2, 6], $this->posiciones('201'));
    }

    public function test_cambiar_al_mismo_telar_se_rechaza(): void
    {
        $this->como()->postJson('/planeacion/programa-tejido/2/cambiar-telar', [
            'nuevo_salon' => 'SMIT', 'nuevo_telar' => '201', 'target_position' => 0,
        ])->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_cambiar_telar_sin_modelo_en_destino_se_rechaza_sin_mover(): void
    {
        DB::table('ReqModelosCodificados')->where('SalonTejidoId', 'JACQUARD')->delete();

        $this->como()->postJson('/planeacion/programa-tejido/2/cambiar-telar', [
            'nuevo_salon' => 'JACQUARD', 'nuevo_telar' => '210', 'target_position' => 0,
        ])->assertStatus(422)->assertJsonPath('success', false);

        $this->assertSame([1, 2, 6], $this->posiciones('201'));
    }

    public function test_cambiar_telar_no_permite_colocarse_antes_del_en_proceso_del_destino(): void
    {
        // Telar 202 tiene a Id 3 EnProceso en posición 1: la mínima válida es 1.
        $this->como()->postJson('/planeacion/programa-tejido/2/cambiar-telar', [
            'nuevo_salon' => 'JACQUARD', 'nuevo_telar' => '202', 'target_position' => 0,
        ])->assertStatus(422)->assertJsonPath('success', false);

        $this->assertSame([1, 2, 6], $this->posiciones('201'));
        $this->assertSame([3], $this->posiciones('202'));
    }

    public function test_cambiar_telar_exige_los_tres_campos(): void
    {
        $this->como()->postJson('/planeacion/programa-tejido/2/cambiar-telar', ['nuevo_salon' => 'SMIT'])->assertStatus(422);
    }

    public function test_cambiar_telar_sin_permiso_modificar_es_403(): void
    {
        $this->como([2 => ['acceso']])->postJson('/planeacion/programa-tejido/2/cambiar-telar', [
            'nuevo_salon' => 'SMIT', 'nuevo_telar' => '204', 'target_position' => 1,
        ])->assertForbidden();

        $this->assertSame([1, 2, 6], $this->posiciones('201'));
    }
}
