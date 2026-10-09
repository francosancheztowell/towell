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
     *     tamano: Collection<int, mixed>
     * }
     */
    public function build(TrazabilidadFilters $filters): array
    {
        $facet = fn (string $column, string $except): Collection => TrazaProduccion::query()
            ->filtrados($filters->toArray(), $except)
            ->whereNotNull($column)
            ->where($column, '<>', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column);

        $combo = function (
            string $codeColumn,
            string $nameColumn,
            string $except
        ) use ($filters): Collection {
            return TrazaProduccion::query()->filtrados($filters->toArray(), $except)
                ->whereNotNull($codeColumn)
                ->where($codeColumn, '<>', '')
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

        $withoutFilters = ! $filters->hasAny();
        // ponytail: TTL fijo en vez de versionar con MAX(Id): cada fila nueva del ETL
        // invalidaba las 3 facetas y el siguiente usuario pagaba ~570ms de scan.
        // 15 min de catálogo ligeramente viejo a cambio de que la página abra en ~120ms.
        // Si se necesita al instante: Cache::forget desde el proceso que carga la tabla.
        $remember = static fn (string $key, callable $callback): mixed => Cache::remember(
            $key,
            now()->addMinutes(15),
            $callback
        );

        return [
            'flog' => $withoutFilters
                ? $remember('traza_opt_flog', fn (): Collection => $facet('Flogs', 'flog'))
                : $facet('Flogs', 'flog'),
            'articulo' => $withoutFilters
                ? $remember(
                    'traza_opt_articulo_combo',
                    fn (): Collection => $combo('Articulo', 'NombreArticulo', 'articulo')
                )
                : $combo('Articulo', 'NombreArticulo', 'articulo'),
            'tamano' => $withoutFilters
                ? $remember('traza_opt_tamano', fn (): Collection => $facet('Tamano', 'tamano'))
                : $facet('Tamano', 'tamano'),
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
     * Reutiliza las opciones facetadas para los textos del resumen.
     *
     * Si una faceta está seleccionada, el resumen muestra únicamente esa
     * selección. Si no lo está, su catálogo ya representa el alcance actual.
     *
     * @param  array{
     *     flog: Collection<int, mixed>,
     *     articulo: Collection<int, array{codigo:mixed,label:string}>,
     *     tamano: Collection<int, mixed>
     * }  $options
     * @return array{
     *     flogs: Collection<int, string>,
     *     articulos: Collection<int, string>,
     *     tamanos: Collection<int, string>
     * }
     */
    public function summaryValues(TrazabilidadFilters $filters, array $options): array
    {
        $flogs = $this->selectedOrAll($filters->flog, $options['flog']);
        $sizes = $this->selectedOrAll($filters->tamano, $options['tamano']);

        $articles = collect($options['articulo'])
            ->map(static function (array $option): array {
                $code = trim((string) ($option['codigo'] ?? ''));
                $label = trim((string) ($option['label'] ?? $code));

                return [
                    'codigo' => $code,
                    'resumen' => preg_replace('/\s+\/\s+/', ' · ', $label, 1) ?: $code,
                ];
            })
            ->filter(static fn (array $option): bool => $option['codigo'] !== '');

        if ($filters->articulo !== '') {
            $selected = $articles->first(
                static fn (array $option): bool => strcasecmp($option['codigo'], $filters->articulo) === 0
            );
            $articleValues = $selected ? collect([$selected['resumen']]) : collect();
        } else {
            $articleValues = $articles->pluck('resumen')->values();
        }

        return [
            'flogs' => $flogs,
            'articulos' => $articleValues,
            'tamanos' => $sizes,
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

    /**
     * @param  Collection<int, mixed>  $values
     * @return Collection<int, string>
     */
    private function selectedOrAll(string $selected, Collection $values): Collection
    {
        $clean = $this->cleanValues($values);
        if ($selected === '') {
            return $clean;
        }

        $match = $clean->first(
            static fn (string $value): bool => strcasecmp($value, $selected) === 0
        );

        return $match !== null ? collect([$match]) : collect();
    }
}
