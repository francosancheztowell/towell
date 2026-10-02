<?php

namespace Tests\Feature\Planeacion;

use App\Livewire\Planeacion\ProgramaTejidoBoard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Feature\Planeacion\Concerns\ConPermisosPlaneacion;
use Tests\Feature\Planeacion\Concerns\ProgramaTejidoFixtures;
use Tests\TestCase;

/**
 * PT 03 · shell Livewire v2 detrás de canary (PLANEACION_SHELL_V2 / _CANARY).
 * Apagado: la página es la legacy, sin rastro de v2; su único Livewire es el modal Duplicar/Dividir
 * (la grilla sigue siendo HTML + index.js). Encendido: el shell
 * resuelve la superficie en mount() y la conserva en /livewire/update.
 */
class ProgramaTejidoShellV2Test extends TestCase
{
    use ConPermisosPlaneacion;
    use ProgramaTejidoFixtures;

    private const TODAS = ['acceso', 'crear', 'modificar', 'eliminar', 'registrar'];

    private const RUTAS = ['catalogos.req-programa-tejido' => 'programa', 'muestras.index' => 'muestras'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararSuperficies();
        $this->sembrarFixtures();
    }

    private function canary(string $modo, array $ids = []): void
    {
        config()->set('planeacion.shell_v2', ['modo' => $modo, 'usuarios_canary' => $ids]);
    }

    private function html(string $ruta, int $usuario = 999100): string
    {
        $superficie = self::RUTAS[$ruta];
        $this->usarSuperficie($superficie); // lo que hace ProgramaTejidoContext en la ruta
        $response = $this->actingAs($this->usuarioConPermisos([2 => self::TODAS, 5 => self::TODAS], $usuario))->get(route($ruta));
        $response->assertOk();

        return (string) $response->getContent();
    }

    public function test_apagado_sirve_la_legacy_sin_assets_v2_ni_grilla_livewire(): void
    {
        $this->canary('off', [999100]);

        foreach (array_keys(self::RUTAS) as $ruta) {
            $html = $this->html($ruta);
            $this->assertStringContainsString('id="mainTable"', $html);
            $this->assertStringNotContainsString('programa-tejido-v2', $html, $ruta);
            $this->assertStringNotContainsString('data-pt-shell', $html, $ruta);
            $this->assertStringNotContainsString('programa-tejido-board', $html, $ruta);
            preg_match('#<table id="mainTable".*?</table>#s', $html, $tabla);
            $this->assertStringNotContainsString('wire:', $tabla[0] ?? '', $ruta);
        }
    }

    public function test_canary_solo_para_la_allowlist(): void
    {
        $this->canary('canary', [999100]);

        $this->assertStringContainsString('data-pt-shell="v2"', $this->html('catalogos.req-programa-tejido'));
        $this->assertStringNotContainsString('data-pt-shell', $this->html('catalogos.req-programa-tejido', 999101));
    }

    public function test_encendido_cada_ruta_monta_su_superficie(): void
    {
        $this->canary('on');

        foreach (self::RUTAS as $ruta => $superficie) {
            $html = $this->html($ruta);
            $this->assertStringContainsString('data-superficie="'.$superficie.'"', $html);
            $this->assertStringContainsString("PRODUCTO {$superficie}", $html);
            $this->assertStringContainsString('id="pt-boot"', $html);
        }
    }

    public function test_la_grilla_v2_es_la_misma_tabla_que_la_legacy(): void
    {
        foreach (array_keys(self::RUTAS) as $ruta) {
            $this->canary('off');
            $legacy = $this->html($ruta);
            $this->canary('on');
            $v2 = $this->html($ruta);

            // Livewire marca los bloques @if/@foreach para su morph: comentarios HTML, sin efecto
            // visual (y la grilla va en wire:ignore). Fuera de eso, la tabla es la misma.
            $tabla = fn (string $html) => preg_match('#<table id="mainTable".*?</table>#s', $html, $m)
                ? preg_replace('#<!--\[if (?:END)?BLOCK\]><!\[endif\]-->#', '', $m[0]) : '';
            $this->assertNotSame('', $tabla($legacy));
            $this->assertSame($tabla($legacy), $tabla($v2), $ruta);
            // Menús, modales y boot fuera de la grilla: los mismos ids en las dos.
            foreach (['contextMenu', 'contextMenuHeader', 'pt-boot'] as $id) {
                $this->assertStringContainsString('id="'.$id.'"', $v2);
            }
        }
    }

    public function test_muestras_sigue_en_muestras_en_livewire_update(): void
    {
        $this->canary('on');
        $usuario = $this->usuarioConPermisos([5 => self::TODAS]);

        $componente = Livewire::actingAs($usuario)->test(ProgramaTejidoBoard::class, ['superficie' => 'muestras'])
            ->assertSee('PRODUCTO muestras');

        // Un /livewire/update no pasa por ProgramaTejidoContext: config y URL dicen Programa.
        $this->usarSuperficie('programa');
        $this->app->instance('request', Request::create('/livewire/update', 'POST'));

        $componente->call('$refresh')
            ->assertSee('PRODUCTO muestras')
            ->assertDontSee('PRODUCTO programa')
            ->assertSet('superficie', 'muestras');
    }

    public function test_el_dataset_no_viaja_en_el_snapshot(): void
    {
        $this->canary('on');

        $componente = Livewire::actingAs($this->usuarioConPermisos([2 => self::TODAS]))
            ->test(ProgramaTejidoBoard::class, ['superficie' => 'programa', 'ocultas' => ['Maquina']]);

        $datos = $componente->snapshot['data'];
        $this->assertSame(['superficie', 'ocultas', 'error'], array_keys($datos));
        $this->assertStringNotContainsString('PRODUCTO', json_encode($datos));
    }

    public function test_la_superficie_no_se_puede_cambiar_desde_el_cliente(): void
    {
        $this->canary('on');
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($this->usuarioConPermisos([5 => self::TODAS]))
            ->test(ProgramaTejidoBoard::class, ['superficie' => 'muestras'])
            ->set('superficie', 'programa');
    }

    public function test_superficie_invalida_o_canary_apagado_responden_404(): void
    {
        $this->canary('on');
        Livewire::actingAs($this->usuarioConPermisos([2 => self::TODAS]))
            ->test(ProgramaTejidoBoard::class, ['superficie' => 'otra'])
            ->assertStatus(404);

        $this->canary('off');
        Livewire::actingAs($this->usuarioConPermisos([2 => self::TODAS]))
            ->test(ProgramaTejidoBoard::class, ['superficie' => 'programa'])
            ->assertStatus(404);
    }

    public function test_error_de_lectura_pinta_el_estado_de_error_y_avisa(): void
    {
        $this->canary('on');
        Schema::connection('sqlsrv')->drop(config('planeacion.superficies.muestras.tabla'));

        Livewire::actingAs($this->usuarioConPermisos([5 => self::TODAS]))
            ->test(ProgramaTejidoBoard::class, ['superficie' => 'muestras'])
            ->assertSee('No se pudieron cargar los registros', false)
            ->assertSee('data-estado="error"', false)
            ->assertDispatched('programa-tejido-error');
    }
}
