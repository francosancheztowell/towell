<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

/**
 * Paginación compatible con SQL Server 2008 R2 (nivel de compatibilidad 100),
 * que es el servidor de producción.
 *
 * `->paginate()` de Laravel emite `OFFSET … FETCH NEXT`, sintaxis que solo existe
 * desde SQL Server 2012 y que aquí revienta con "Incorrect syntax near 'offset'".
 * Aquí se pide `TOP (hasta la página actual)` —válido en 2008— y se recorta el
 * sobrante en PHP, conservando los modelos Eloquent.
 *
 * ponytail: el costo crece con la profundidad de página (la página 40 lee 1000
 * filas para mostrar 25). Sirve de sobra para catálogos y listados operativos; si
 * algún día hay que paginar hasta el fondo de una tabla enorme, la salida es
 * ROW_NUMBER() en subconsulta —o subir el nivel de compatibilidad del servidor.
 */
final class PaginacionCompat
{
    public static function paginar(
        EloquentBuilder|QueryBuilder $query,
        int $porPagina = 25,
        ?int $pagina = null,
        string $nombrePagina = 'page',
    ): LengthAwarePaginatorContract {
        $porPagina = max(1, $porPagina);
        $pagina = max(1, $pagina ?? Paginator::resolveCurrentPage($nombrePagina));

        $total = (clone $query)->toBase()->getCountForPagination();
        $hasta = $pagina * $porPagina;

        $items = $total > 0
            ? (clone $query)->take($hasta)->get()->slice(($pagina - 1) * $porPagina)->values()
            : collect();

        return new LengthAwarePaginator($items, $total, $porPagina, $pagina, [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => $nombrePagina,
        ]);
    }
}
