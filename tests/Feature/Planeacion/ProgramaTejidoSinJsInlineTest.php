<?php

namespace Tests\Feature\Planeacion;

use Tests\TestCase;

/**
 * Guardián de PT-TS 1: las pantallas de Programa Tejido que no son la grilla (Liberar, Utilería,
 * Alineación) y los modales del bundle no vuelven a meter JS inline, y el JS de PT que salió a
 * TS no vuelve como .js. Se revisa el FUENTE Blade, como el ratchet.
 */
class ProgramaTejidoSinJsInlineTest extends TestCase
{
    private const RAIZ = __DIR__.'/../../../';

    /** @return array<string, array{string}> */
    public static function vistas(): array
    {
        $vistas = [
            'modulos/programa-tejido/liberar-ordenes/index.blade.php',
            'planeacion/utileria/index.blade.php',
            'planeacion/utileria/finalizar-ordenes.blade.php',
            'planeacion/utileria/mover-ordenes.blade.php',
            'planeacion/alineacion/index.blade.php',
            'modulos/programa-tejido/modal/marbetes.blade.php',
            'modulos/programa-tejido/modal/repaso.blade.php',
            'modulos/programa-tejido/modal/act-calendarios.blade.php',
            'modulos/programa-tejido/modal/redbooth.blade.php',
        ];

        $casos = [];
        foreach ($vistas as $vista) {
            $casos[$vista] = [self::RAIZ.'resources/views/'.$vista];
        }

        return $casos;
    }

    /** @dataProvider vistas */
    public function test_sin_script_inline_ni_handlers_on(string $ruta): void
    {
        $this->assertFileExists($ruta);
        $fuente = (string) file_get_contents($ruta);

        // Ni <script> sin src (tampoco islas JSON: los datos van en data-*='@json(...)').
        $this->assertDoesNotMatchRegularExpression('/<script\b(?![^>]*\bsrc\s*=)[^>]*>/i', $fuente, 'Mover el <script> a resources/js/modulos/programa-tejido/** (19-00-RECETA §1-2).');
        // onclose= es la prop de x-ui.modal-base (su runtime lo cablea), no un handler en línea.
        $this->assertDoesNotMatchRegularExpression('/\son(?!close\b)[a-z]+\s*=\s*["\']/i', $fuente, 'Usar data-accion + delegate() (19-00-RECETA §3).');
        $this->assertStringNotContainsString('csrf_token()', $fuente, 'http ya manda el CSRF.');
        $this->assertStringNotContainsString("asset('js/", $fuente, 'El JS va por Vite, no por public/js.');
    }

    public function test_el_js_migrado_no_vuelve_como_js(): void
    {
        $js = array_map(
            fn (string $f) => str_replace(self::RAIZ, '', $f),
            array_merge(
                glob(self::RAIZ.'resources/js/programa-tejido/*.js') ?: [],
                glob(self::RAIZ.'resources/js/programa-tejido/*/*.js') ?: [],
                glob(self::RAIZ.'resources/js/modulos/redbooth/*.js') ?: [],
                glob(self::RAIZ.'resources/js/modulos/programa-tejido/*/*.js') ?: [],
                glob(self::RAIZ.'public/js/programa-tejido*.js') ?: [],
            ),
        );

        // index.js (13 000 líneas) es de PT-TS 2.
        $this->assertSame(['resources/js/programa-tejido/index.js'], $js);
    }
}
