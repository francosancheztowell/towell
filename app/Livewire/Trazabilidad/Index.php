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
    #[Url(except: '')]
    public string $flog = '';

    #[Url(except: '')]
    public string $articulo = '';

    #[Url(except: '')]
    public string $tamano = '';

    #[Url(except: '')]
    public string $color = '';

    #[Url(except: '')]
    public string $mes = '';

    #[Url(except: 'cantidad')]
    public string $metrica = 'cantidad';

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
            in_array($campo, ['flog', 'articulo', 'tamano', 'color', 'mes', 'metrica'], true),
            422,
            'Filtro de trazabilidad no válido.'
        );
        abort_unless(is_scalar($valor) || $valor === null, 422, 'Valor de filtro no válido.');

        $text = mb_substr(trim((string) ($valor ?? '')), 0, $campo === 'mes' ? 50 : 100);
        $values = $this->filterValues();
        $values[$campo] = $text;

        $filters = TrazabilidadFilters::fromArray($values);
        $this->applyNormalizedFilters($filters);
        $this->dispatchFiltersChanged($filters);
    }

    #[On('trazabilidad-restablecer')]
    public function restablecer(): void
    {
        $filters = TrazabilidadFilters::fromArray(['metrica' => $this->metrica]);
        $this->applyNormalizedFilters($filters);
        $this->dispatchFiltersChanged($filters);
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
            'resumenFlog' => $hasFilter ? $this->summary->build($filterValues, $summaryValues) : null,
            'tablaAvancePedido' => $tableProgress,
        ]);
    }

    protected function authorizeAccess(): void
    {
        abort_unless(
            userCan('acceso', 'Trazabilidad'),
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
            'color' => $this->color,
            'mes' => $this->mes,
            'metrica' => $this->metrica,
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
        $this->color = $filters->color;
        $this->mes = $filters->mes;
        $this->metrica = $filters->metrica;
    }

    private function dispatchFiltersChanged(TrazabilidadFilters $filters): void
    {
        $this->dispatch(
            'trazabilidad-filtros-actualizados',
            filtros: [
                ...$filters->toArray(),
                'metrica' => $filters->metrica,
            ],
        );
    }
}
