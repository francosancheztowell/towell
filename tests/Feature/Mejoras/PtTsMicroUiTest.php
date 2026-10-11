<?php

declare(strict_types=1);

namespace Tests\Feature\Mejoras;

use Tests\TestCase;

/**
 * Ramas claude/mejora-micro-ui-kit y claude/mejora-ts-programa-tejido-charts.
 *
 * El estado "ocupado" de x-ui.button ya no lo pinta Blade: depende de que Livewire 4 ponga
 * data-loading en quien dispara la petición y de que app.css lo atrape por [data-ui-button].
 * Son tres piezas en tres lugares (componente, CSS, vendor): si una cambia, el botón vuelve a
 * aceptar doble clic sin que ningún test de Blade lo note. Esto amarra las tres.
 */
class PtTsMicroUiTest extends TestCase
{
    private function css(): string
    {
        return (string) file_get_contents(resource_path('css/app.css'));
    }

    public function test_livewire_sigue_marcando_data_loading_en_quien_dispara(): void
    {
        $dist = (string) file_get_contents(base_path('vendor/livewire/livewire/dist/livewire.esm.js'));

        // supportDataLoading.js: si Livewire lo renombra o lo quita, el CSS de x-ui.button queda muerto.
        $this->assertStringContainsString('el.setAttribute("data-loading", "true")', $dist);
        $this->assertStringContainsString('el.removeAttribute("data-loading")', $dist);
    }

    public function test_button_y_anchor_llevan_data_ui_button_una_sola_vez(): void
    {
        $boton = (string) $this->blade('<x-ui.button variant="create" icon="fa-plus" wire:click="guardar">Guardar</x-ui.button>');
        $this->assertSame(1, substr_count($boton, 'data-ui-button'));
        $this->assertMatchesRegularExpression('/<button[^>]*\sdata-ui-button[\s>]/', $boton);
        $this->assertStringContainsString('wire:click="guardar"', $boton);
        $this->assertStringContainsString('type="button"', $boton);
        // El ícono va como hijo directo: es lo que app.css esconde (> :is(i, svg)) mientras carga.
        $this->assertMatchesRegularExpression('/data-ui-button[^>]*>\s*<i class="fa-solid fa-plus/', $boton);

        $enlace = (string) $this->blade('<x-ui.button variant="ghost" href="/x">Volver</x-ui.button>');
        $this->assertSame(1, substr_count($enlace, 'data-ui-button'));
        $this->assertStringStartsWith('<a', ltrim($enlace));
    }

    public function test_submit_dentro_de_wire_submit_queda_cubierto_por_el_css(): void
    {
        $html = (string) $this->blade('<form wire:submit="guardar"><x-ui.button type="submit">Guardar</x-ui.button></form>');
        $this->assertMatchesRegularExpression('/<button[^>]*type="submit"[^>]*data-ui-button/', $html);

        $css = $this->css();
        $selector = ":is([data-ui-button][data-loading], form[data-loading] [data-ui-button][type='submit'])";
        // Bloquea el doble clic, esconde el ícono y dibuja el spinner, los tres con el mismo selector.
        $this->assertStringContainsString($selector.' { pointer-events: none;', $css);
        $this->assertStringContainsString($selector.' > :is(i, svg) { display: none; }', $css);
        $this->assertStringContainsString($selector.'::before', $css);
    }

    public function test_el_spinner_de_dialogo_data_ocupado_sigue_en_la_misma_regla(): void
    {
        // Se juntó [data-ocupado]::before con el selector nuevo: si se separa o se pierde,
        // los botones de notify.form/confirm dejan de girar mientras esperan.
        $css = $this->css();
        $this->assertMatchesRegularExpression(
            '/\[data-ocupado\]::before,\s*:is\(\[data-ui-button\]\[data-loading\][^{]*::before\s*\{[^}]*animation:\s*loader-giro/',
            $css
        );
    }

    public function test_skeleton_sin_movimiento_si_se_pidio_menos_y_con_fondo_siempre(): void
    {
        $css = $this->css();
        // El color de fondo va fuera del media query (se ve aunque no haya animación)...
        $this->assertMatchesRegularExpression('/\.ui-skeleton \{ background-color: var\(--color-slate-200\); \}/', $css);
        // ...y la animación infinita solo dentro de prefers-reduced-motion: no-preference.
        $this->assertMatchesRegularExpression(
            '/@media \(prefers-reduced-motion: no-preference\) \{\s*\.ui-skeleton \{[^}]*animation: ui-skeleton-brillo/',
            $css
        );
        $this->assertSame(1, preg_match_all('/animation: ui-skeleton-brillo/', $css));

        $bloque = (string) $this->blade('<x-ui.skeleton shape="block" class="h-20" />');
        $this->assertStringContainsString('class="ui-skeleton rounded-lg h-20"', $bloque);
        $circulo = (string) $this->blade('<x-ui.skeleton shape="circle" class="size-12" />');
        $this->assertStringContainsString('ui-skeleton rounded-full', $circulo);
        $this->assertStringNotContainsString('animate-pulse', $bloque.$circulo);
    }

    public function test_tabla_cargando_usa_el_skeleton_nuevo(): void
    {
        $html = (string) $this->blade('<x-ui.table :loading="true" :loading-rows="2" :columns="3"><x-slot:head><th>A</th></x-slot:head></x-ui.table>');
        $this->assertGreaterThanOrEqual(2, substr_count($html, 'ui-skeleton'));
    }

    public function test_programa_tejido_carga_el_bundle_ts_y_vite_lo_conoce(): void
    {
        $main = (string) file_get_contents(resource_path('views/modulos/programa-tejido/scripts/main.blade.php'));
        $this->assertStringContainsString("@vite('resources/js/programa-tejido/index.ts')", $main);
        $this->assertFileDoesNotExist(resource_path('js/programa-tejido/index.js'));

        $manifiesto = public_path('build/manifest.json');
        if (! is_file($manifiesto)) {
            $this->markTestSkipped('sin npm run build no hay manifest que revisar');
        }
        $entradas = json_decode((string) file_get_contents($manifiesto), true);
        $this->assertArrayHasKey('resources/js/programa-tejido/index.ts', $entradas);
        $this->assertTrue($entradas['resources/js/programa-tejido/index.ts']['isEntry'] ?? false);
        $this->assertArrayNotHasKey('resources/js/charts.js', $entradas, 'el build trae una entrada charts.js que ya no existe');
    }
}
