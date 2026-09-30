<?php

namespace Tests\Feature\CatalogosPlaneacion;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Guardián de 19-06b: las vistas de los catálogos de Planeación (salvo Codificación y L.Mat, de
 * 19-06a) y el componente catalog-actions no vuelven a meter JS inline ni public/js.
 * Se revisa el FUENTE Blade, como el ratchet (x-ui.modal-base emite su propio onclick en el HTML).
 */
class VistasCatalogosSinJsInlineTest extends TestCase
{
    private const DE_19_06A = ['catalogoCodificacion', 'codificacion-form', 'lmat-lista', '_duplicar-importar-codificacion'];

    /** @return array<string, array{string}> */
    public static function vistas(): array
    {
        $raiz = __DIR__.'/../../../resources/views/';
        $archivos = array_merge(
            glob($raiz.'catalagos/*.blade.php') ?: [],
            glob($raiz.'catalagos/*/*.blade.php') ?: [],
            [$raiz.'components/buttons/catalog-actions.blade.php'],
        );

        $casos = [];
        foreach ($archivos as $archivo) {
            if (collect(self::DE_19_06A)->contains(fn ($n) => str_contains($archivo, $n))) {
                continue;
            }
            $casos[str_replace($raiz, '', $archivo)] = [$archivo];
        }

        return $casos;
    }

    #[DataProvider('vistas')]
    public function test_sin_script_inline_ni_handlers_on(string $ruta): void
    {
        $this->assertFileExists($ruta);
        $fuente = (string) file_get_contents($ruta);

        $this->assertDoesNotMatchRegularExpression('/<script\b(?![^>]*\bsrc\s*=)[^>]*>/i', $fuente, 'Mover el <script> a resources/js/modulos/catalogos-planeacion/** (19-00-RECETA §1).');
        $this->assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=\s*["\']/i', $fuente, 'Usar data-accion + delegate() (19-00-RECETA §3).');
        $this->assertStringNotContainsString('csrf_token()', $fuente, 'http ya manda el CSRF (19-00-RECETA §2).');
        $this->assertStringNotContainsString("asset('js/", $fuente, 'El JS va por Vite, no por public/js.');
    }

    public function test_el_js_legado_de_catalogos_ya_no_existe(): void
    {
        $this->assertDirectoryDoesNotExist(__DIR__.'/../../../public/js/catalogs');
    }
}
