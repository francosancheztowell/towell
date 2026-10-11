<?php

namespace Tests\Feature\Planeacion\Rigor;

use App\Models\Planeacion\ReqModelosCodificados;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Planeacion\Concerns\ConPermisosPlaneacion;
use Tests\Feature\Planeacion\Concerns\ProgramaTejidoFixtures;
use Tests\TestCase;

/**
 * verificar-cambio-telar: el diff que ve el usuario antes de confirmar el cambio de telar.
 * Es de solo lectura: forma exacta del JSON, orden de los campos y que no escriba nada.
 */
class VerificarCambioTelarRigorTest extends TestCase
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

        DB::table('ReqProgramaTejido')->where('Id', 2)->update([
            'TamanoClave' => 'MOD1', 'Ultimo' => '1', 'CambioHilo' => '1', 'Ancho' => 50,
            'EficienciaSTD' => 0.8, 'VelocidadSTD' => 300, 'CalibreRizo2' => 10, 'CalibrePie2' => 8, 'Calc4' => 5,
        ]);
        DB::table('ReqModelosCodificados')->insert([
            ['TamanoClave' => 'MOD1', 'SalonTejidoId' => 'SMIT', 'AnchoToalla' => 50, 'VelocidadSTD' => 300, 'CalibreRizo2' => 10, 'CalibrePie2' => 8],
            ['TamanoClave' => 'MOD1', 'SalonTejidoId' => 'JACQUARD', 'AnchoToalla' => 60, 'VelocidadSTD' => 400, 'CalibreRizo2' => 12, 'CalibrePie2' => 9],
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function verificar(int $id, array $body, array $permisos = [2 => ['acceso', 'modificar']], string $superficie = 'programa-tejido')
    {
        return $this->actingAs($this->usuarioConPermisos($permisos))
            ->postJson("/planeacion/{$superficie}/{$id}/verificar-cambio-telar", $body);
    }

    public function test_diff_completo_lista_cada_campo_en_orden_con_actual_y_nuevo(): void
    {
        $json = $this->verificar(2, ['nuevo_salon' => 'JACQUARD', 'nuevo_telar' => '210'])->assertOk()->json();

        $this->assertSame([
            'puede_mover', 'requiere_confirmacion', 'mensaje', 'clave_modelo',
            'telar_origen', 'salon_origen', 'telar_destino', 'salon_destino', 'cambios',
        ], array_keys($json));
        $this->assertTrue($json['puede_mover']);
        $this->assertTrue($json['requiere_confirmacion']);
        $this->assertSame('MOD1', $json['clave_modelo']);
        $this->assertSame(['201', 'SMIT', '210', 'JACQUARD'], [(string) $json['telar_origen'], $json['salon_origen'], $json['telar_destino'], $json['salon_destino']]);

        $this->assertSame([
            'Salón', 'Telar', 'Último', 'Cambio Hilo', 'Ancho', 'Velocidad STD',
            'Calibre Rizo', 'Calibre Pie', 'Fecha Inicio', 'Fecha Final', 'Cálculos (Calc4, Calc5, Calc6)',
        ], array_column($json['cambios'], 'campo'), 'Eficiencia STD no aparece: sin FibraRizo se conserva la actual');

        $porCampo = array_column($json['cambios'], null, 'campo');
        foreach ($json['cambios'] as $c) {
            $this->assertSame(['campo', 'actual', 'nuevo'], array_keys($c));
        }
        $this->assertSame(['SMIT', 'JACQUARD'], [$porCampo['Salón']['actual'], $porCampo['Salón']['nuevo']]);
        $this->assertSame('0', $porCampo['Último']['nuevo']);
        $this->assertSame('0', $porCampo['Cambio Hilo']['nuevo']);
        $this->assertEquals([50, 60], [$porCampo['Ancho']['actual'], $porCampo['Ancho']['nuevo']]);
        $this->assertEquals([300, 400], [$porCampo['Velocidad STD']['actual'], $porCampo['Velocidad STD']['nuevo']]);
        $this->assertEquals([10, 12], [$porCampo['Calibre Rizo']['actual'], $porCampo['Calibre Rizo']['nuevo']]);
        $this->assertEquals([8, 9], [$porCampo['Calibre Pie']['actual'], $porCampo['Calibre Pie']['nuevo']]);
        $this->assertSame(['03/09/2026 18:00', 'Se recalculará'], [$porCampo['Fecha Inicio']['actual'], $porCampo['Fecha Inicio']['nuevo']]);
        $this->assertSame(['05/09/2026 06:00', 'Se recalculará'], [$porCampo['Fecha Final']['actual'], $porCampo['Fecha Final']['nuevo']]);
    }

    public function test_mismo_modelo_y_estandares_solo_reporta_salon_telar_y_fechas(): void
    {
        DB::table('ReqProgramaTejido')->where('Id', 2)->update(['Ultimo' => '0', 'CambioHilo' => '0', 'Calc4' => null]);

        $json = $this->verificar(2, ['nuevo_salon' => 'SMIT', 'nuevo_telar' => '204'])->assertOk()->json();

        $this->assertSame(['Telar', 'Fecha Inicio', 'Fecha Final'], array_column($json['cambios'], 'campo'),
            'mismo salón: sin Salón; mismo modelo: sin Ancho/Velocidad/Calibres');
    }

    public function test_sin_fechas_muestra_na(): void
    {
        DB::table('ReqProgramaTejido')->where('Id', 5)->update(['TamanoClave' => 'MOD1']);

        $porCampo = array_column(
            $this->verificar(5, ['nuevo_salon' => 'SMIT', 'nuevo_telar' => '201'])->assertOk()->json('cambios'),
            null, 'campo'
        );

        $this->assertSame('N/A', $porCampo['Fecha Inicio']['actual']);
        $this->assertSame('N/A', $porCampo['Fecha Final']['actual']);
        $this->assertSame('N/A', $porCampo['Ancho']['actual']);
    }

    public function test_mismo_telar_responde_sin_confirmacion_y_sin_cambios(): void
    {
        $json = $this->verificar(2, ['nuevo_salon' => 'SMIT', 'nuevo_telar' => '201'])->assertOk()->json();

        $this->assertSame(['puede_mover' => true, 'requiere_confirmacion' => false, 'mensaje' => 'Mismo telar'], $json);
    }

    public function test_sin_modelo_en_destino_no_puede_mover_e_informa_clave_y_destino(): void
    {
        DB::table('ReqModelosCodificados')->where('SalonTejidoId', 'JACQUARD')->delete();

        $json = $this->verificar(2, ['nuevo_salon' => 'JACQUARD', 'nuevo_telar' => '210'])->assertOk()->json();

        $this->assertSame([
            'puede_mover' => false,
            'requiere_confirmacion' => false,
            'mensaje' => 'La clave modelo no existe en el telar destino. No se puede realizar el cambio.',
            'clave_modelo' => 'MOD1',
            'telar_destino' => '210',
            'salon_destino' => 'JACQUARD',
        ], $json);
    }

    public function test_el_modelo_destino_se_encuentra_tambien_por_clave_modelo(): void
    {
        DB::table('ReqModelosCodificados')->where('SalonTejidoId', 'JACQUARD')->update(['TamanoClave' => 'OTRA', 'ClaveModelo' => 'MOD1']);

        $this->verificar(2, ['nuevo_salon' => 'JACQUARD', 'nuevo_telar' => '210'])
            ->assertOk()->assertJsonPath('puede_mover', true)->assertJsonPath('requiere_confirmacion', true);
    }

    public function test_verificar_no_escribe_nada(): void
    {
        $antes = $this->fotoSuperficies();

        $this->verificar(2, ['nuevo_salon' => 'JACQUARD', 'nuevo_telar' => '210'])->assertOk();

        $this->assertSame($antes, $this->fotoSuperficies());
    }

    public function test_verificar_exige_permiso_modificar(): void
    {
        // Mismo permiso que cambiar-telar: sin modificar del módulo no se entrega el diagnóstico.
        $this->verificar(2, ['nuevo_salon' => 'JACQUARD', 'nuevo_telar' => '210'], [])->assertForbidden();
        $this->verificar(2, ['nuevo_salon' => 'JACQUARD', 'nuevo_telar' => '210'], [2 => ['acceso']])->assertForbidden();
    }

    public function test_verificar_exige_salon_y_telar(): void
    {
        $this->verificar(2, ['nuevo_salon' => 'JACQUARD'])->assertStatus(422)->assertJsonValidationErrors('nuevo_telar');
        $this->verificar(2, [])->assertStatus(422)->assertJsonValidationErrors(['nuevo_salon', 'nuevo_telar']);
    }

    public function test_verificar_registro_inexistente_responde_404(): void
    {
        $this->verificar(9999, ['nuevo_salon' => 'JACQUARD', 'nuevo_telar' => '210'])->assertNotFound();
    }

    public function test_verificar_en_muestras_lee_la_tabla_de_muestras(): void
    {
        DB::table('MuestrasPrograma')->where('Id', 2)->update(['TamanoClave' => 'MOD1', 'NoTelarId' => '777']);

        $this->verificar(2, ['nuevo_salon' => 'JACQUARD', 'nuevo_telar' => '210'], [5 => ['acceso', 'modificar']], 'muestras')
            ->assertOk()->assertJsonPath('telar_origen', '777');
    }
}
