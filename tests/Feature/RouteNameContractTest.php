<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Todo route('nombre') literal del codigo tiene que apuntar a una ruta registrada.
 *
 * Un nombre inexistente no falla al compilar: lanza RouteNotFoundException cuando se
 * ejecuta la linea. Asi vivieron tres rotos (auditoria ERP §2.7, ERP-F0-11): la vista de
 * cargar catalogos daba 500 al renderizar, y duplicar un modelo codificado o borrar un
 * usuario inexistentes reventaban en vez de volver al listado.
 *
 * Solo se capturan nombres literales. Los concatenados o en variable quedan fuera a
 * proposito: no se pueden resolver sin ejecutar. El lookbehind excluye
 * $request->route('id') (parametro de ruta) y Route::.
 */
final class RouteNameContractTest extends TestCase
{
    private const PATRON = '/(?:(?<![\w>:$])route|redirect\(\)->route|redirectRoute|to_route)\(\s*[\'"]([A-Za-z0-9_.\-]+)[\'"]\s*[,)]/';

    public function test_todo_route_literal_apunta_a_una_ruta_registrada(): void
    {
        $rotos = [];

        foreach (['app', 'resources/views'] as $dir) {
            foreach ($this->archivosPhp(base_path($dir)) as $archivo) {
                $codigo = (string) file_get_contents($archivo);

                if (! preg_match_all(self::PATRON, $codigo, $m)) {
                    continue;
                }

                foreach (array_unique($m[1]) as $nombre) {
                    if (! Route::has($nombre)) {
                        $relativo = str_replace('\\', '/', substr($archivo, strlen(base_path()) + 1));
                        $rotos[] = "{$relativo} => {$nombre}";
                    }
                }
            }
        }

        sort($rotos);

        $this->assertSame([], $rotos, "route() a nombres inexistentes:\n".implode("\n", $rotos));
    }

    /**
     * @return iterable<string>
     */
    private function archivosPhp(string $dir): iterable
    {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));

        foreach ($it as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
                yield $file->getPathname();
            }
        }
    }
}
