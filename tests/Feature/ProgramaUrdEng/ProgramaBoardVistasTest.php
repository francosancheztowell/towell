<?php

declare(strict_types=1);

namespace Tests\Feature\ProgramaUrdEng;

use Tests\TestCase;

/**
 * Guardián de las vistas de los tableros Programar Urdido/Engomado y de la edición de órdenes
 * (19-05 p2.2): sin JS inline, sin on*=, íconos accesibles (UX-18) y texto ≥ 12 px en Blade.
 */
class ProgramaBoardVistasTest extends TestCase
{
    private const VISTAS = [
        'livewire/urd-eng/program-board.blade.php',
        'livewire/urd-eng/edicion-ordenes.blade.php',
        'livewire/urd-eng/edicion-orden.blade.php',
        'modulos/urdido/programar-urdido-livewire.blade.php',
        'modulos/urdido/editar-orden-programada.blade.php',
        'modulos/engomado/programar-engomado-livewire.blade.php',
        'modulos/engomado/editar-orden-engomado.blade.php',
    ];

    /** @return array<string, array{string}> */
    public static function vistas(): array
    {
        return array_combine(self::VISTAS, array_map(fn (string $v): array => [$v], self::VISTAS));
    }

    /**
     * @dataProvider vistas
     */
    public function test_vista_sin_js_inline_y_accesible(string $vista): void
    {
        $fuente = (string) file_get_contents(resource_path('views/'.$vista));

        $this->assertDoesNotMatchRegularExpression('/<script\b(?![^>]*\bsrc\s*=)[^>]*>/i', $fuente, 'sin <script> inline');
        $this->assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=/i', $fuente, 'sin on*= (usar data-accion / wire:)');
        $this->assertDoesNotMatchRegularExpression('/text-\[(9|10|11)px\]/', $fuente, 'texto ≥ 12 px');
        $this->assertStringNotContainsString('X-CSRF-TOKEN', $fuente);

        preg_match_all('/<i\s[^>]*>/', $fuente, $iconos);
        foreach ($iconos[0] as $icono) {
            $this->assertMatchesRegularExpression('/aria-(hidden|label)=/', $icono, "ícono sin aria: {$icono}");
        }
    }

    public function test_bundles_de_urd_eng_sin_fetch_swal_ni_alert(): void
    {
        foreach (glob(resource_path('js/urd-eng/*.ts')) ?: [] as $archivo) {
            $fuente = (string) file_get_contents($archivo);
            $nombre = basename($archivo);

            $this->assertDoesNotMatchRegularExpression('/\bfetch\(/', $fuente, $nombre);
            $this->assertStringNotContainsString('Swal', $fuente, $nombre);
            $this->assertStringNotContainsString('window.alert', $fuente, $nombre);
            $this->assertDoesNotMatchRegularExpression('/\.innerHTML\s*\+?=/', $fuente, $nombre);
            $this->assertStringNotContainsString('X-CSRF-TOKEN', $fuente, $nombre);
        }
    }

    public function test_calificar_julios_sin_puente_window(): void
    {
        $fuente = (string) file_get_contents(resource_path('js/modulos/urdido/comun/calificar-julios/index.ts'));

        $this->assertStringNotContainsString('abrirModalCalificarJuliosEng', $fuente);
        $this->assertStringContainsString('export function abrirCalificarJulios', $fuente);
        $this->assertStringContainsString(
            "from '../modulos/urdido/comun/calificar-julios/index.ts'",
            (string) file_get_contents(resource_path('js/urd-eng/edicion-ordenes.ts'))
        );
    }
}
