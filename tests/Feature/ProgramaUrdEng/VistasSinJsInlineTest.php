<?php

namespace Tests\Feature\ProgramaUrdEng;

use Tests\TestCase;

/**
 * Guardián de 19-05: ninguna vista de Programa Urdido-Engomado (reservar/programar, creación de
 * órdenes, Karl Mayer, tableros y edición de órdenes) vuelve a meter JS inline ni public/js.
 * Se revisa el FUENTE Blade, como el ratchet (x-ui.modal-base emite su propio onclick en el HTML).
 */
class VistasSinJsInlineTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function vistas(): array
    {
        $raiz = __DIR__.'/../../../resources/views/';
        $archivos = array_merge(
            glob($raiz.'modulos/programa_urd_eng/*.blade.php') ?: [],
            glob($raiz.'modulos/programa_urd_eng/*/*.blade.php') ?: [],
            glob($raiz.'livewire/urd-eng/*.blade.php') ?: [],
            [
                $raiz.'modulos/urdido/programar-urdido-livewire.blade.php',
                $raiz.'modulos/urdido/editar-orden-programada.blade.php',
                $raiz.'modulos/engomado/programar-engomado-livewire.blade.php',
                $raiz.'modulos/engomado/editar-orden-engomado.blade.php',
            ],
        );

        $casos = [];
        foreach ($archivos as $archivo) {
            $casos[str_replace($raiz, '', $archivo)] = [$archivo];
        }

        return $casos;
    }

    /** @dataProvider vistas */
    public function test_sin_script_inline_ni_handlers_on(string $ruta): void
    {
        $this->assertFileExists($ruta);
        $fuente = (string) file_get_contents($ruta);

        $this->assertDoesNotMatchRegularExpression('/<script\b(?![^>]*\bsrc\s*=)[^>]*>/i', $fuente, 'Mover el <script> a resources/js/modulos/** (19-00-RECETA §1).');
        $this->assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=\s*["\']/i', $fuente, 'Usar data-accion + delegate() (19-00-RECETA §3).');
        $this->assertStringNotContainsString('csrf_token()', $fuente, 'http ya manda el CSRF (19-00-RECETA §2).');
        $this->assertStringNotContainsString("asset('js/", $fuente, 'El JS va por Vite, no por public/js (BUG-033).');
    }

    public function test_creacion_ordenes_ya_no_vive_en_public_js(): void
    {
        $this->assertFileDoesNotExist(__DIR__.'/../../../public/js/modulos/programa_urd_eng/creacion-ordenes.js');
    }
}
