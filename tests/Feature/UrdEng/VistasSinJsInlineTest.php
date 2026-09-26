<?php

namespace Tests\Feature\UrdEng;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Guardián de 19-01: ninguna vista de Urdido/Engomado vuelve a meter JS inline.
 * Se revisa el FUENTE Blade (como el ratchet): x-ui.modal-base emite su propio onclick en el HTML.
 * Fuera: programar-* (tableros de 19-05).
 */
class VistasSinJsInlineTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function vistas(): array
    {
        $finder = Finder::create()->files()->name('*.blade.php')->notName('programar-*')
            ->in([
                __DIR__.'/../../../resources/views/modulos/urdido',
                __DIR__.'/../../../resources/views/modulos/engomado',
                __DIR__.'/../../../resources/views/catalogosurdido',
            ]);

        $casos = [];
        foreach ($finder as $archivo) {
            $casos[$archivo->getRelativePathname().' ('.basename(dirname($archivo->getPath())).')'] = [$archivo->getRealPath()];
        }

        return $casos;
    }

    /** @dataProvider vistas */
    public function test_sin_script_inline_ni_handlers_on(string $ruta): void
    {
        $fuente = (string) file_get_contents($ruta);

        $this->assertDoesNotMatchRegularExpression('/<script\b(?![^>]*\bsrc\s*=)[^>]*>/i', $fuente, 'Mover el <script> a resources/js/modulos/** (19-00-RECETA §1).');
        $this->assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=\s*["\']/i', $fuente, 'Usar data-accion + delegate() (19-00-RECETA §3).');
        $this->assertStringNotContainsString('csrf_token()', $fuente, 'http ya manda el CSRF (19-00-RECETA §2).');
    }
}
