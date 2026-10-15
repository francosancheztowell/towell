<?php

declare(strict_types=1);

namespace App\Services\Trazabilidad;

use App\Models\Trazabilidad\TrazaProduccion;
use App\ValueObjects\Trazabilidad\TrazabilidadFilters;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class TrazabilidadFilterOptionsService
{
    /**
     * @return array{
     *     flog: Collection<int, mixed>,
     *     articulo: Collection<int, array{codigo:mixed,label:string}>,
     *     tamano: Collection<int, mixed>,
     *     cliente: Collection<int, mixed>,
     *     anio: Collection<int, mixed>
     * }
     */
    public function build(TrazabilidadFilters $filters, bool $sinExcepcion = false): array
    {
        // $sinExcepcion: cada faceta con TODOS los filtros, incluido el suyo. Es lo que el
        // resumen necesita para saber qué Flogs/artículos/tamaños quedaron de verdad
        // (con rangos y comodines no basta con buscar el valor en la lista).
        $except = static fn (string $campo): ?string => $sinExcepcion ? null : $campo;

        $facet = fn (string $column, ?string $except): Collection => TrazaProduccion::query()
            ->filtrados($filters->toArray(), $except)
            ->whereNotNull($column)
            ->whereRaw('['.$column.'] <> CAST(? AS varchar(100))', [''])
            ->distinct()
            ->orderBy($column)
            ->pluck($column);

        $combo = function (
            string $codeColumn,
            string $nameColumn,
            ?string $except
        ) use ($filters): Collection {
            return TrazaProduccion::query()->filtrados($filters->toArray(), $except)
                ->whereNotNull($codeColumn)
                ->whereRaw('['.$codeColumn.'] <> CAST(? AS varchar(100))', [''])
                ->selectRaw(
                    "{$codeColumn} as codigo, "
                    ."MAX(NULLIF(LTRIM(RTRIM({$nameColumn})), '')) as nombre"
                )
                ->groupBy($codeColumn)
                ->orderBy($codeColumn)
                // Filas agregadas (codigo/nombre), no modelos.
                ->toBase()
                ->get()
                ->map(static fn (object $row): array => [
                    'codigo' => $row->codigo,
                    'label' => trim($row->codigo.(filled($row->nombre) ? ' / '.$row->nombre : '')),
                ])
                ->values();
        };

        // ponytail: TTL fijo en vez de versionar con MAX(Id): cada fila nueva del ETL
        // invalidaba las 3 facetas y el siguiente usuario pagaba ~570ms de scan.
        // 15 min de catálogo ligeramente viejo a cambio de que la página abra en ~120ms.
        // Si se necesita al instante: Cache::forget desde el proceso que carga la tabla.
        // La clave son solo los filtros que acotan la faceta (todos menos el suyo): con un
        // Flog elegido, la lista de Flogs es la misma que sin filtros y no se vuelve a
        // consultar en cada render de Livewire (medido 240 ms; artículos 359 ms).
        $remember = static fn (string $key, ?string $except, callable $callback): mixed => Cache::remember(
            'traza_opt_v2_'.$key.':'.md5((string) json_encode(array_diff_key(
                $filters->toArray(),
                // Año acota también al mes: su faceta ignora los dos.
                $except === 'anio' ? ['anio' => true, 'mes' => true] : [(string) $except => true]
            ))),
            now()->addMinutes(15),
            $callback
        );

        return [
            'flog' => $remember('flog', $except('flog'), fn (): Collection => $facet('Flogs', $except('flog'))),
            'articulo' => $remember(
                'articulo_combo',
                $except('articulo'),
                fn (): Collection => $combo('Articulo', 'NombreArticulo', $except('articulo'))
            ),
            'tamano' => $remember('tamano', $except('tamano'), fn (): Collection => $facet('Tamano', $except('tamano'))),
            'cliente' => $sinExcepcion ? collect() : $remember('cliente', $except('cliente'), fn (): Collection => $facet('Cliente', $except('cliente'))),
            // Años entre la primera y la última fecha: un MIN/MAX en vez de agrupar 266k filas por YEAR().
            'anio' => $sinExcepcion ? collect() : $remember('anio', 'anio', fn (): Collection => TrazaProduccion::query()
                ->filtrados($filters->toArray(), 'anio')
                ->whereNotNull('Fecha')
                ->selectRaw('MIN(Fecha) AS desde, MAX(Fecha) AS hasta')
                ->toBase()
                ->get()
                ->flatMap(static fn (object $fila): array => $fila->desde === null ? [] : range(
                    (int) substr((string) $fila->hasta, 0, 4),
                    (int) substr((string) $fila->desde, 0, 4),
                ))
                ->values()),
        ];
    }

    /**
     * Opciones de Flog para la búsqueda remota del selector (Tom Select).
     *
     * El término se busca literal (sin comodines), sin distinguir mayúsculas.
     *
     * @return Collection<int, string>
     */
    public function searchFlogs(TrazabilidadFilters $filters, string $term = '', int $limit = 50): Collection
    {
        // ponytail: la lista completa de Flogs por (articulo, tamano) es chica (~342 sin
        // filtros) y se cachea con el mismo TTL que las facetas; cada tecla filtra en PHP en
        // vez de un LIKE '%x%' sobre 266k filas (medido 430–870 ms por tecla). Sin LIKE no
        // hay comodines que escapar. Techo: si Flogs distintos llega a decenas de miles,
        // volver a SQL con LIKE escapado (ESCAPE) y CAST del patrón a varchar.
        $flogs = Cache::remember(
            'traza_flog_lista:'.md5($filters->articulo."\0".$filters->tamano),
            now()->addMinutes(15),
            fn (): array => $this->cleanValues(
                TrazaProduccion::query()->filtrados($filters->toArray(), 'flog')
                    ->whereNotNull('Flogs')
                    // CAST: pdo_sqlsrv manda NVARCHAR y anularía el índice (ver filtrados()).
                    ->whereRaw('[Flogs] <> CAST(? AS varchar(100))', [''])
                    ->distinct()
                    ->orderBy('Flogs')
                    ->pluck('Flogs')
            )->all()
        );

        return collect($flogs)
            ->when($term !== '', static fn (Collection $list): Collection => $list->filter(
                static fn (string $flog): bool => mb_stripos($flog, $term) !== false
            ))
            ->take($limit)
            ->values();
    }

    /**
     * Valores que de verdad quedaron con los filtros, para los textos del resumen y para
     * AX/Ventas. Con un campo vacío, su faceta ya es eso; con valor (lista, rango o
     * comodín) se consulta con todos los filtros. La clave de caché coincide con la de la
     * faceta cuando el campo está vacío, así que no se consulta dos veces.
     *
     * @param  array<string, Collection<int, mixed>>|null  $resueltas  build($filters, true); null = consultar.
     * @return array{
     *     flogs: Collection<int, string>,
     *     articulos: Collection<int, string>,
     *     tamanos: Collection<int, string>
     * }
     */
    public function summaryValues(TrazabilidadFilters $filters, ?array $resueltas = null): array
    {
        $resueltas ??= $this->build($filters, true);

        /** @var Collection<int, string> $articulos */
        $articulos = collect($resueltas['articulo'])
            ->map(static fn (array $option): string => preg_replace(
                '/\s+\/\s+/', ' · ', trim((string) ($option['label'] ?? $option['codigo'] ?? '')), 1
            ) ?: trim((string) ($option['codigo'] ?? '')))
            ->filter()
            ->values();

        return [
            'flogs' => $this->cleanValues($resueltas['flog']),
            'articulos' => $articulos,
            'tamanos' => $this->cleanValues($resueltas['tamano']),
        ];
    }

    /**
     * @param  Collection<int, mixed>  $values
     * @return Collection<int, string>
     */
    private function cleanValues(Collection $values): Collection
    {
        return $values
            ->map(static fn (mixed $value): string => trim((string) $value))
            ->filter()
            ->values();
    }
}
