<?php

declare(strict_types=1);

namespace Tests\Unit\Calidad;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Producción corre SQL Server 2008 R2: paginate(), simplePaginate() y cursorPaginate()
 * de Laravel emiten OFFSET … FETCH (2012+) y fallan desde la página 2. Los tests
 * corren en SQLite, que sí lo acepta, así que el único guardián posible es este.
 * Usar App\Support\PaginacionCompat::paginar().
 */
final class SinPaginateNativoTest extends TestCase
{
    public function test_app_no_usa_la_paginacion_nativa_de_laravel(): void
    {
        $base = dirname(__DIR__, 3);
        $hallazgos = [];

        $archivos = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base.'/app'));
        foreach ($archivos as $archivo) {
            if ($archivo->getExtension() !== 'php' || $archivo->getFilename() === 'PaginacionCompat.php') {
                continue;
            }
            foreach (file($archivo->getPathname()) as $n => $linea) {
                if (preg_match('/->(paginate|simplePaginate|cursorPaginate)\s*\(/', $linea)) {
                    $hallazgos[] = substr($archivo->getPathname(), strlen($base) + 1).':'.($n + 1);
                }
            }
        }

        $this->assertSame([], $hallazgos, 'Usa PaginacionCompat::paginar() (SQL Server 2008 R2 no tiene OFFSET/FETCH).');
    }
}
