<?php

namespace Tests\Unit\Planeacion;

use PHPUnit\Framework\TestCase;

/**
 * PT 03: el shell v2 es un wrapper delgado, sin JS inline, y sus assets solo se referencian
 * desde el wrapper (con el canary apagado la vista legacy no los carga).
 */
class ProgramaTejidoShellV2EstructuraTest extends TestCase
{
    private function ruta(string $relativa): string
    {
        return dirname(__DIR__, 3).'/'.$relativa;
    }

    private function leer(string $relativa): string
    {
        return (string) file_get_contents($this->ruta($relativa));
    }

    public function test_wrapper_delgado_sin_script_inline(): void
    {
        $wrapper = $this->leer('resources/views/modulos/programa-tejido/req-programa-tejido-v2.blade.php');

        $this->assertLessThan(40, substr_count($wrapper, "\n"));
        $this->assertStringContainsString('<livewire:planeacion.programa-tejido-board', $wrapper);
        $this->assertStringContainsString("@vite('resources/js/modulos/programa-tejido-v2/index.ts')", $wrapper);
        $this->assertDoesNotMatchRegularExpression('#<script\b#i', $wrapper);
    }

    public function test_vista_livewire_sin_script_y_con_la_grilla_compartida(): void
    {
        $vista = $this->leer('resources/views/livewire/planeacion/programa-tejido-board.blade.php');

        $this->assertDoesNotMatchRegularExpression('#<script\b|@script#i', $vista);
        $this->assertStringContainsString("@include('modulos.programa-tejido.partials.grilla')", $vista);
        $this->assertStringContainsString('wire:ignore', $vista);
    }

    public function test_la_legacy_comparte_partials_y_no_referencia_v2(): void
    {
        $legacy = $this->leer('resources/views/modulos/programa-tejido/req-programa-tejido.blade.php');

        $this->assertStringContainsString("@include('modulos.programa-tejido.partials.grilla')", $legacy);
        $this->assertStringContainsString("@include('modulos.programa-tejido.partials.complementos')", $legacy);
        $this->assertStringNotContainsString('programa-tejido-v2', $legacy);
        $this->assertStringNotContainsString('livewire', $legacy);
    }

    public function test_los_assets_v2_solo_se_cargan_desde_el_wrapper(): void
    {
        $vistas = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->ruta('resources/views')));
        $referencias = [];
        foreach ($vistas as $archivo) {
            if (str_ends_with($archivo->getFilename(), '.blade.php') && str_contains((string) file_get_contents($archivo->getPathname()), 'modulos/programa-tejido-v2')) {
                $referencias[] = str_replace($this->ruta(''), '', $archivo->getPathname());
            }
        }

        $this->assertSame(['resources/views/modulos/programa-tejido/req-programa-tejido-v2.blade.php'], $referencias);
    }
}
