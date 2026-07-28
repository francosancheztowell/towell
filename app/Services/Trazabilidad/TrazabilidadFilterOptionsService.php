<?php

declare(strict_types=1);

namespace App\Services\Trazabilidad;

use App\Models\Trazabilidad\TrazaProduccion;
use App\ValueObjects\Trazabilidad\TrazabilidadFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TrazabilidadFilterOptionsService
{
    /**
     * @return array{
     *     flog: Collection<int, mixed>,
     *     articulo: Collection<int, array{codigo:mixed,label:string}>,
     *     tamano: Collection<int, mixed>,
     *     color: Collection<int, array{codigo:mixed,label:string}>,
     *     meses: Collection<int, array{mes:int,nombre:string,registros:int}>
     * }
     */
    public function build(TrazabilidadFilters $filters, bool $includeHidden = true): array
    {
        $facet = fn (string $column, string $except): Collection => $this
            ->applyFilters(TrazaProduccion::query(), $filters, $except)
            ->whereNotNull($column)
            ->where($column, '<>', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column);

        $combo = function (
            string $codeColumn,
            string $nameColumn,
            string $except,
            ?string $area = null
        ) use ($filters): Collection {
            return $this->applyFilters(TrazaProduccion::query(), $filters, $except)
                ->whereNotNull($codeColumn)
                ->where($codeColumn, '<>', '')
                ->when($area, static fn (Builder $query, string $areaName): Builder => $query
                    ->where('NombreAlmacen', $areaName))
                ->selectRaw(
                    "{$codeColumn} as codigo, "
                    ."MAX(NULLIF(LTRIM(RTRIM({$nameColumn})), '')) as nombre"
                )
                ->groupBy($codeColumn)
                ->orderBy($codeColumn)
                ->get()
                ->map(static fn (object $row): array => [
                    'codigo' => $row->codigo,
                    'label' => trim($row->codigo.(filled($row->nombre) ? ' / '.$row->nombre : '')),
                ])
                ->values();
        };

        $withoutFilters = ! $filters->hasAny();
        $remember = static fn (string $key, callable $callback): mixed => Cache::remember(
            $key,
            now()->addHour(),
            $callback
        );

        $flogs = $withoutFilters
            ? $remember('traza_opt_flog', fn (): Collection => $facet('Flogs', 'flog'))
            : $facet('Flogs', 'flog');
        $articles = $withoutFilters
            ? $remember(
                'traza_opt_articulo_combo',
                fn (): Collection => $combo('Articulo', 'NombreArticulo', 'articulo')
            )
            : $combo('Articulo', 'NombreArticulo', 'articulo');
        $sizes = $withoutFilters
            ? $remember('traza_opt_tamano', fn (): Collection => $facet('Tamano', 'tamano'))
            : $facet('Tamano', 'tamano');

        if (! $includeHidden) {
            return [
                'flog' => $flogs,
                'articulo' => $articles,
                'tamano' => $sizes,
                'color' => collect(),
                'meses' => collect(),
            ];
        }

        $colors = $withoutFilters
            ? $remember(
                'traza_opt_color_combo_v2',
                fn (): Collection => $combo('Color', 'NombreColor', 'color', 'Rollos Teñido')
            )
            : $combo('Color', 'NombreColor', 'color', 'Rollos Teñido');

        return [
            'flog' => $flogs,
            'articulo' => $articles,
            'tamano' => $sizes,
            'color' => $colors,
            'meses' => $this->availableMonths($filters),
        ];
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

    private function applyFilters(
        Builder $query,
        TrazabilidadFilters $filters,
        string $except
    ): Builder {
        return $query
            ->when(
                $except !== 'flog' && $filters->flog !== '',
                static fn (Builder $builder): Builder => $builder->where('Flogs', $filters->flog)
            )
            ->when(
                $except !== 'articulo' && $filters->articulo !== '',
                static fn (Builder $builder): Builder => $builder->where('Articulo', $filters->articulo)
            )
            ->when(
                $except !== 'tamano' && $filters->tamano !== '',
                static fn (Builder $builder): Builder => $builder->where('Tamano', $filters->tamano)
            )
            ->when(
                $except !== 'mes' && $filters->months() !== [],
                static fn (Builder $builder): Builder => $builder
                    ->whereIn(DB::raw('MONTH(Fecha)'), $filters->months())
            );
    }

    /**
     * @return Collection<int, array{mes:int,nombre:string,registros:int}>
     */
    private function availableMonths(TrazabilidadFilters $filters): Collection
    {
        $monthNames = [
            '',
            'Enero',
            'Febrero',
            'Marzo',
            'Abril',
            'Mayo',
            'Junio',
            'Julio',
            'Agosto',
            'Septiembre',
            'Octubre',
            'Noviembre',
            'Diciembre',
        ];

        return $this->applyFilters(TrazaProduccion::query(), $filters, 'mes')
            ->whereNotNull('Fecha')
            ->selectRaw('MONTH(Fecha) as mes, COUNT(*) as registros')
            ->groupByRaw('MONTH(Fecha)')
            ->orderByRaw('MONTH(Fecha)')
            ->get()
            ->map(static fn (object $row): array => [
                'mes' => (int) $row->mes,
                'nombre' => $monthNames[(int) $row->mes] ?? (string) $row->mes,
                'registros' => (int) $row->registros,
            ]);
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
