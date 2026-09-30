<?php

namespace Tests\Feature\Tejido;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Guardián de 19-02: ninguna vista de Tejido vuelve a meter JS inline.
 * Se revisa el FUENTE Blade (como el ratchet): x-ui.modal-base emite su propio onclick en el HTML.
 */
class VistasSinJsInlineTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function vistas(): array
    {
        $base = __DIR__.'/../../../resources/views';
        $finder = Finder::create()->files()->name('*.blade.php')
            ->in([
                $base.'/modulos/tejido',
                $base.'/modulos/cortes-eficiencia',
                $base.'/modulos/inventario-trama',
                $base.'/modulos/marcas-finales',
                $base.'/livewire/inventario-trama',
            ])
            ->append([$base.'/modulos/produccion-reenconado-cabezuela.blade.php']);

        $casos = [];
        foreach ($finder as $archivo) {
            $real = (string) realpath((string) $archivo);
            $casos[substr($real, strlen((string) realpath($base)) + 1)] = [$real];
        }

        return $casos;
    }

    /** @dataProvider vistas */
    public function test_sin_script_inline_ni_handlers_on(string $ruta): void
    {
        $fuente = (string) file_get_contents($ruta);

        $this->assertDoesNotMatchRegularExpression('/<script\b(?![^>]*\bsrc\s*=)[^>]*>/i', $fuente, 'Mover el <script> a resources/js/modulos/tejido/** (19-00-RECETA §1).');
        $this->assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=\s*["\']/i', $fuente, 'Usar data-accion + delegate() (19-00-RECETA §3).');
        $this->assertStringNotContainsString('csrf_token()', $fuente, 'http ya manda el CSRF (19-00-RECETA §2).');
    }

    /** @dataProvider vistas */
    public function test_texto_minimo_12px_y_sin_h1_propio(string $ruta): void
    {
        $fuente = (string) file_get_contents($ruta);
        // Las vistas de dompdf son documentos sueltos (no llevan el layout ni su <h1>).
        if (str_contains($ruta, 'pdf')) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->assertDoesNotMatchRegularExpression('/text-\[(8|9|10|11)px\]/', $fuente, 'UX-07: text-xs como mínimo.');
        $this->assertDoesNotMatchRegularExpression('/<h1\b/i', $fuente, 'UX-03: el <h1> lo pone el navbar; en el contenido va <h2>.');
    }
}
