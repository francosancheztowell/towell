<?php

namespace Tests\Feature\Mantenimiento;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Guardián de 19-08: ninguna vista de Mantenimiento vuelve a meter JS inline.
 * Se revisa el FUENTE Blade, como el ratchet (19-00-RECETA §7).
 */
class VistasSinJsInlineTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function vistas(): array
    {
        $finder = Finder::create()->files()->name('*.blade.php')->in([
            __DIR__.'/../../../resources/views/modulos/mantenimiento',
            __DIR__.'/../../../resources/views/livewire/mantenimiento',
        ]);

        $casos = [];
        foreach ($finder as $archivo) {
            $casos[$archivo->getRelativePathname()] = [$archivo->getRealPath()];
        }

        return $casos;
    }

    #[DataProvider('vistas')]
    public function test_sin_script_inline_ni_handlers_on(string $ruta): void
    {
        $fuente = (string) file_get_contents($ruta);

        $this->assertDoesNotMatchRegularExpression('/<script\b(?![^>]*\bsrc\s*=)[^>]*>/i', $fuente, 'Mover el <script> a resources/js/modulos/mantenimiento/** (19-00-RECETA §1).');
        $this->assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=\s*["\']/i', $fuente, 'Usar data-accion + delegate() (19-00-RECETA §3).');
        $this->assertStringNotContainsString('csrf_token()', $fuente, 'http ya manda el CSRF (19-00-RECETA §2).');
        $this->assertDoesNotMatchRegularExpression('/text-\[(8|9|10|11)px\]/', $fuente, 'Texto mínimo 12 px (17-02 C3).');
        $this->assertStringNotContainsString('<h1', $fuente, 'El navbar pone el único <h1> (17-02 C2).');
    }
}
