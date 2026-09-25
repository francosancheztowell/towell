<?php

namespace Tests\Feature\Planeacion;

use Illuminate\Testing\TestResponse;
use Tests\Feature\Planeacion\Concerns\ConPermisosPlaneacion;
use Tests\Feature\Planeacion\Concerns\ProgramaTejidoFixtures;
use Tests\TestCase;

/**
 * PT 04-perf · HANDOFF B1–B3 de PT-02: en Muestras, el navbar y el menú contextual
 * siguen los permisos del módulo Muestras (idrol 5), que es lo que el servidor exige.
 * Antes los decidían los de Programa Tejido (idrol 2), y Descargar se ocultaba por JS.
 */
class ProgramaTejidoPermisosUiTest extends TestCase
{
    use ConPermisosPlaneacion;
    use ProgramaTejidoFixtures;

    private const TODAS = ['acceso', 'crear', 'modificar', 'eliminar', 'registrar'];

    /** Acciones del menú contextual que dependen de crear/modificar/eliminar. */
    private const MENU = ['contextMenuCrear', 'contextMenuEditar', 'contextMenuEliminar'];

    /** Botones del navbar que dependen de permisos del módulo (button-create / button-report). */
    private const NAVBAR = ['Activar/Desactivar arrastrar filas', 'Liberar órdenes'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararSuperficies();
        $this->sembrarFixtures();
    }

    private function ver(string $ruta, array $permisos): TestResponse
    {
        $response = $this->actingAs($this->usuarioConPermisos($permisos))->get(route($ruta));
        $response->assertOk()->assertViewMissing('error');

        return $response;
    }

    public function test_en_muestras_los_botones_siguen_los_permisos_de_muestras(): void
    {
        $r = $this->ver('muestras.index', [5 => self::TODAS]);

        foreach (self::MENU as $id) {
            $r->assertSee('id="'.$id.'"', false);
        }
        foreach (self::NAVBAR as $titulo) {
            $r->assertSee('title="'.$titulo.'"', false);
        }
    }

    public function test_en_muestras_los_permisos_de_programa_no_habilitan_botones(): void
    {
        // Acceso a Muestras para entrar; todo lo demás solo en Programa.
        $r = $this->ver('muestras.index', [5 => ['acceso'], 2 => self::TODAS]);

        foreach (self::MENU as $id) {
            $r->assertDontSee('id="'.$id.'"', false);
        }
        foreach (self::NAVBAR as $titulo) {
            $r->assertDontSee('title="'.$titulo.'"', false);
        }
    }

    public function test_en_programa_los_botones_siguen_los_permisos_de_programa(): void
    {
        $this->ver('catalogos.req-programa-tejido', [2 => self::TODAS])
            ->assertSee('id="contextMenuCrear"', false)
            ->assertSee('title="Descargar programa"', false);

        $this->ver('catalogos.req-programa-tejido', [2 => ['acceso'], 5 => self::TODAS])
            ->assertDontSee('id="contextMenuCrear"', false)
            ->assertDontSee('title="Descargar programa"', false);
    }

    public function test_descargar_no_se_emite_en_muestras(): void
    {
        // B1: capacidad B, sale del HTML en el servidor (antes lo ocultaba index.js).
        $this->ver('muestras.index', [5 => self::TODAS])
            ->assertDontSee('title="Descargar programa"', false);
    }

    public function test_los_valores_del_servidor_viajan_como_json_y_no_como_codigo(): void
    {
        $html = $this->ver('muestras.index', [5 => self::TODAS])->getContent();

        $this->assertMatchesRegularExpression('#<script type="application/json" id="pt-boot">(.*?)</script>#s', $html);
        preg_match('#<script type="application/json" id="pt-boot">(.*?)</script>#s', $html, $m);
        $boot = json_decode($m[1], true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('/planeacion/muestras', $boot['basePath']);
        $this->assertSame(route('muestras.recalcular-fechas'), $boot['routes']['recalcularFechas']);
        $this->assertFalse($boot['capacidades']['descarga']);
        $this->assertStringNotContainsString('window.PT_BOOT', $html);
    }
}
