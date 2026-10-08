<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

/**
 * Schema::getColumnListing con cache estático por tabla (conexión por defecto).
 *
 * La metadata no cambia durante el request; en workers persistentes (queue/octane) dura lo
 * que el proceso. Ojo en la suite: un test que crea una tabla con menos columnas deja ESA
 * lista en cache y el siguiente ve su UPDATE filtrado a cero sin error; por eso
 * Tests\TestCase::setUp() vacía el cache (vía ReqProgramaTejidoObserver::flushCaches()).
 */
final class ColumnasDeTabla
{
    /** @var array<string, list<string>> */
    private static array $cache = [];

    /** @return list<string> */
    public static function de(string $tabla): array
    {
        if (! isset(self::$cache[$tabla])) {
            self::$cache[$tabla] = Schema::getColumnListing($tabla);
        }

        return self::$cache[$tabla];
    }

    public static function flush(): void
    {
        self::$cache = [];
    }
}
