<?php

declare(strict_types=1);

namespace App\Livewire\Trazabilidad;

use App\Services\Trazabilidad\TrazabilidadFilterOptionsService;
use App\Services\Trazabilidad\TrazabilidadProduccionService;
use App\Services\Trazabilidad\TrazabilidadResumenService;
use App\ValueObjects\Trazabilidad\TrazabilidadFilters;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

class Index extends Component
{
    /** idrol de Trazabilidad: el mismo que exige el router. /livewire/update no pasa por ese middleware. */
    public const MODULO = 190;

    #[Url(except: '')]
    public string $flog = '';

    #[Url(except: '')]
    public string $articulo = '';

    #[Url(except: '')]
    public string $tamano = '';

    /** Hubo cambio de filtros en esta request: render() avisa al front una sola vez. */
    private bool $filtersChanged = false;

    private TrazabilidadFilterOptionsService $filterOptions;

    private TrazabilidadResumenService $summary;

    private TrazabilidadProduccionService $production;

    public function boot(
        TrazabilidadFilterOptionsService $filterOptions,
        TrazabilidadResumenService $summary,
        TrazabilidadProduccionService $production,
    ): void {
        $this->authorizeAccess();

        $this->filterOptions = $filterOptions;
        $this->summary = $summary;
        $this->production = $production;
    }

    public function mount(): void
    {
        $this->applyNormalizedFilters($this->filters());
    }

    #[On('trazabilidad-actualizar-filtro')]
    public function actualizarFiltro(string $campo, mixed $valor): void
    {
        abort_unless(
            in_array($campo, ['flog', 'articulo', 'tamano'], true),
            422,
            'Filtro de trazabilidad no válido.'
        );
        abort_unless(is_scalar($valor) || $valor === null, 422, 'Valor de filtro no válido.');

        $values = $this->filterValues();
        $values[$campo] = mb_substr(trim((string) ($valor ?? '')), 0, 100);

        $this->applyNormalizedFilters(TrazabilidadFilters::fromArray($values));
        $this->filtersChanged = true;
    }

    public function restablecer(): void
    {
        $this->applyNormalizedFilters(new TrazabilidadFilters);
        $this->filtersChanged = true;
    }

    public function render(): View
    {
        $filters = $this->filters();
        $options = $this->filterOptions->build($filters);

        // Un filtro puede quedar fuera de alcance al cambiar otro (p. ej. elegir un
        // Flog donde el artículo seleccionado no existe): se descarta y se recalcula.
        if ($this->discardOutOfScopeFilters($filters, $options)) {
            $filters = $this->filters();
            $options = $this->filterOptions->build($filters);
            $this->filtersChanged = true;
        }

        // Después del descarte: el front recibe el estado que de verdad quedó aplicado.
        if ($this->filtersChanged) {
            $this->dispatch('trazabilidad-filtros-actualizados', filtros: $filters->toArray());
        }

        $hasFilter = $filters->hasAny();
        $filterValues = $filters->toArray();
        $tableProgress = $hasFilter
            ? $this->production->buildTablaAvance($filterValues)
            : [];
        $summaryValues = $this->filterOptions->summaryValues($filters, $options);

        return view('livewire.trazabilidad.index', [
            'filtros' => $filterValues,
            'hayFiltro' => $hasFilter,
            'hayFlog' => $filters->hasFlog(),
            'opcionesArticulo' => $options['articulo'],
            'opcionesTamano' => $options['tamano'],
            'resumenFlog' => $hasFilter ? $this->summary->build($filterValues, $summaryValues, $tableProgress) : null,
            'tablaAvancePedido' => $tableProgress,
        ]);
    }

    protected function authorizeAccess(): void
    {
        abort_unless(
            userCan('acceso', self::MODULO),
            403,
            'No tienes acceso al módulo de Trazabilidad.'
        );
    }

    private function filters(): TrazabilidadFilters
    {
        return TrazabilidadFilters::fromArray($this->filterValues());
    }

    /**
     * @return array<string, string>
     */
    private function filterValues(): array
    {
        return [
            'flog' => $this->flog,
            'articulo' => $this->articulo,
            'tamano' => $this->tamano,
        ];
    }

    /**
     * @param  array<string, iterable<mixed>>  $options
     */
    private function discardOutOfScopeFilters(TrazabilidadFilters $filters, array $options): bool
    {
        $available = static fn (string $selected, iterable $values): bool => $selected === ''
            || collect($values)
                ->map(static fn (mixed $option): string => trim((string) (is_array($option)
                    ? ($option['codigo'] ?? '')
                    : $option)))
                ->contains(static fn (string $code): bool => strcasecmp($code, $selected) === 0);

        $discarded = false;
        foreach (['flog', 'articulo', 'tamano'] as $field) {
            if (! $available($filters->{$field}, $options[$field] ?? [])) {
                $this->{$field} = '';
                $discarded = true;
            }
        }

        return $discarded;
    }

    private function applyNormalizedFilters(TrazabilidadFilters $filters): void
    {
        $this->flog = $filters->flog;
        $this->articulo = $filters->articulo;
        $this->tamano = $filters->tamano;
    }
}
