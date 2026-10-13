<?php

declare(strict_types=1);

namespace App\Livewire\Trazabilidad;

use App\Services\Trazabilidad\TrazabilidadFilterOptionsService;
use App\Services\Trazabilidad\TrazabilidadProduccionService;
use App\Services\Trazabilidad\TrazabilidadResumenService;
use App\Services\Trazabilidad\TrazabilidadVentasService;
use App\Support\FiltroAx;
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

    /** @var list<string> */
    #[Url(except: [])]
    public array $cliente = [];

    #[Url(except: '')]
    public string $anio = '';

    #[Url(except: '')]
    public string $mes = '';

    /** Hubo cambio de filtros en esta request: render() avisa al front una sola vez. */
    private bool $filtersChanged = false;

    private TrazabilidadFilterOptionsService $filterOptions;

    private TrazabilidadResumenService $summary;

    private TrazabilidadProduccionService $production;

    private TrazabilidadVentasService $sales;

    public function boot(
        TrazabilidadFilterOptionsService $filterOptions,
        TrazabilidadResumenService $summary,
        TrazabilidadProduccionService $production,
        TrazabilidadVentasService $sales,
    ): void {
        $this->authorizeAccess();

        $this->filterOptions = $filterOptions;
        $this->summary = $summary;
        $this->production = $production;
        $this->sales = $sales;
    }

    public function mount(): void
    {
        $this->applyNormalizedFilters($this->filters());
    }

    #[On('trazabilidad-actualizar-filtro')]
    public function actualizarFiltro(string $campo, mixed $valor): void
    {
        abort_unless(
            in_array($campo, TrazabilidadFilters::CAMPOS, true),
            422,
            'Filtro de trazabilidad no válido.'
        );
        abort_unless(is_scalar($valor) || is_array($valor) || $valor === null, 422, 'Valor de filtro no válido.');

        // Los selectores múltiples mandan un arreglo; el VO lo normaliza (lista AX o nombres).
        $values = $this->filterValues();
        $values[$campo] = $valor ?? '';

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
        $summaryValues = $hasFilter ? $this->filterOptions->summaryValues($filters) : null;

        return view('livewire.trazabilidad.index', [
            'filtros' => $filterValues,
            'hayFiltro' => $hasFilter,
            'opcionesArticulo' => $options['articulo'],
            'opcionesTamano' => $options['tamano'],
            'opcionesCliente' => $options['cliente'],
            'opcionesAnio' => $options['anio'],
            'resumenFlog' => $hasFilter ? $this->summary->build($filterValues, $summaryValues, $tableProgress) : null,
            'tablaAvancePedido' => $tableProgress,
            'ventas' => $hasFilter ? $this->sales->resumen(
                $summaryValues['flogs']->all(),
                $filters->articulo,
                $filters->tamano,
            ) : null,
            'meses' => ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto',
                'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'],
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
     * @return array<string, mixed>
     */
    private function filterValues(): array
    {
        return [
            'flog' => $this->flog,
            'articulo' => $this->articulo,
            'tamano' => $this->tamano,
            'cliente' => $this->cliente,
            'anio' => $this->anio,
            'mes' => $this->mes,
        ];
    }

    /**
     * Quita los valores exactos que ya no existen con los demás filtros. Rangos y comodines
     * se quedan: no se pueden buscar en la lista.
     *
     * @param  array<string, iterable<mixed>>  $options
     */
    private function discardOutOfScopeFilters(TrazabilidadFilters $filters, array $options): bool
    {
        $codes = static fn (iterable $values): array => collect($values)
            ->map(static fn (mixed $option): string => mb_strtolower(trim((string) (is_array($option)
                ? ($option['codigo'] ?? '')
                : $option))))
            ->all();
        $keep = static fn (array $selected, iterable $values): array => array_values(array_filter(
            $selected,
            static fn (string $value): bool => FiltroAx::esPatron($value)
                || in_array(mb_strtolower($value), $codes($values), true)
        ));

        $values = $filters->toArray();
        foreach (['flog', 'articulo', 'tamano'] as $field) {
            $values[$field] = implode(',', $keep(FiltroAx::valores($filters->{$field}), $options[$field] ?? []));
        }
        $values['cliente'] = $keep($filters->cliente, $options['cliente'] ?? []);
        if ($filters->anio !== '' && ! in_array($filters->anio, array_map('strval', $codes($options['anio'] ?? [])), true)) {
            $values['anio'] = '';
        }

        $cleaned = TrazabilidadFilters::fromArray($values);
        if ($cleaned == $filters) {
            return false;
        }

        $this->applyNormalizedFilters($cleaned);

        return true;
    }

    private function applyNormalizedFilters(TrazabilidadFilters $filters): void
    {
        $this->flog = $filters->flog;
        $this->articulo = $filters->articulo;
        $this->tamano = $filters->tamano;
        $this->cliente = $filters->cliente;
        $this->anio = $filters->anio;
        $this->mes = $filters->mes;
    }
}
