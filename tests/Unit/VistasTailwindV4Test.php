<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Guardas de Tailwind v4 en las vistas (ERP-F0-04, ERP-F0-05).
 *
 * - `bg-opacity-*` es de Tailwind v3: v4 no la genera y el fondo del modal sale opaco.
 *   La forma v4 es `bg-black/50`.
 * - `max-md` sola no es una clase (es el prefijo de la variante `max-md:`); lo que se
 *   queria era `max-w-md`.
 * - app.js no debe importar app.css: el layout ya lo carga con @vite desde
 *   x-layout-styles, e importarlo desde JS descargaba Tailwind dos veces.
 */
class VistasTailwindV4Test extends TestCase
{
    public function test_ninguna_vista_usa_bg_opacity(): void
    {
        $hallazgos = $this->buscarEnVistas('/\bbg-opacity-\d+/');

        $this->assertSame([], $hallazgos, "Vistas con bg-opacity-* (Tailwind v3):\n".implode("\n", $hallazgos));
    }

    public function test_ninguna_vista_usa_max_md_suelto(): void
    {
        // Token completo: ni `max-md:` (variante) ni `max-w-md` cuentan.
        $hallazgos = $this->buscarEnVistas('/(?<![\w:-])max-md(?![\w:-])/');

        $this->assertSame([], $hallazgos, "Vistas con la clase inexistente max-md:\n".implode("\n", $hallazgos));
    }

    public function test_app_js_no_importa_app_css(): void
    {
        $this->assertStringNotContainsString(
            'css/app.css',
            file_get_contents(resource_path('js/app.js')),
            'app.css ya lo carga x-layout-styles con @vite; importarlo en app.js descarga Tailwind dos veces.'
        );
    }

    /**
     * @return array<int, string> archivo:linea
     */
    private function buscarEnVistas(string $patron): array
    {
        $hallazgos = [];

        foreach (File::allFiles(resource_path('views')) as $archivo) {
            if (! str_ends_with($archivo->getFilename(), '.blade.php')) {
                continue;
            }

            foreach (file($archivo->getPathname()) as $i => $linea) {
                if (preg_match($patron, $linea)) {
                    $hallazgos[] = $archivo->getRelativePathname().':'.($i + 1);
                }
            }
        }

        return $hallazgos;
    }
}
