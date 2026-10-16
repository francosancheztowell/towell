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

    public const MESES = [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto',
        'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

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

    /** Tarjeta Flog con varios Flogs: cuál se está viendo (0 = el primero). Vuelve a 0 al filtrar. */
    public int $flogPagina = 0;

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
            'opcionesMes' => $options['mes'],
            'resumenFlog' => $hasFilter ? $this->flogCard($filterValues, $summaryValues, $tableProgress) : null,
            'flogsResumen' => $summaryValues['flogs'] ?? collect(),
            'tablaAvancePedido' => $tableProgress,
            // Ventas de los Flogs completos, igual que el pedido de AX (ver TrazabilidadResumenService).
            'ventas' => $hasFilter ? $this->sales->resumen($summaryValues['flogs']->all(), $this->sales->stock($filterValues), $filters->rangos()) : null,
            'meses' => self::MESES,
        ]);
    }

    /**
     * Tarjeta Flog: con varios Flogs muestra uno a la vez (paginador), con sus propios
     * artículos, tamaños, pedido y facturado. Las demás tarjetas siguen con todo el filtro.
     *
     * @param  array<string, mixed>  $filterValues
     * @param  array<string, mixed>  $summaryValues
     * @param  array<int, array<string, mixed>>  $tableProgress
     * @return array<string, mixed>
     */
    private function flogCard(array $filterValues, array $summaryValues, array $tableProgress): array
    {
        $flogs = $summaryValues['flogs']->values();
        if ($flogs->count() <= 1) {
            return $this->summary->build($filterValues, $summaryValues, $tableProgress);
        }

        $this->flogPagina = max(0, min($this->flogPagina, $flogs->count() - 1));
        $flog = (string) $flogs[$this->flogPagina];
        $uno = TrazabilidadFilters::fromArray([...$filterValues, 'flog' => $flog]);

        return [
            ...$this->summary->build(
                $uno->toArray(),
                $this->filterOptions->summaryValues($uno),
                array_values(array_filter($tableProgress, static fn (array $fila): bool => ($fila['flog'] ?? '') === $flog)),
            ),
            'flogVista' => $flog,
        ];
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
        return $this->only(TrazabilidadFilters::CAMPOS);
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
        // Años y meses sin movimiento con los demás filtros (el VO vuelve a quitar los meses si no queda año).
        foreach (['anio' => $filters->anios(), 'mes' => $filters->meses()] as $field => $selected) {
            $values[$field] = array_values(array_intersect($selected, collect($options[$field] ?? [])->map(static fn (mixed $v): int => (int) $v)->all()));
        }

        $cleaned = TrazabilidadFilters::fromArray($values);
        if ($cleaned == $filters) {
            return false;
        }

        $this->applyNormalizedFilters($cleaned);

        // Sin aviso, el valor tecleado desaparecía del filtro y parecía que no se había escrito.
        $quitados = array_diff(
            [...FiltroAx::valores($filters->flog), ...FiltroAx::valores($filters->articulo),
                ...FiltroAx::valores($filters->tamano), ...$filters->cliente, ...$filters->anios(), ...$this->nombresMes($filters)],
            [...FiltroAx::valores($cleaned->flog), ...FiltroAx::valores($cleaned->articulo),
                ...FiltroAx::valores($cleaned->tamano), ...$cleaned->cliente, ...$cleaned->anios(), ...$this->nombresMes($cleaned)],
        );
        if ($quitados !== []) {
            $this->dispatch('aviso', tipo: 'warning', texto: 'Sin producción con los demás filtros, se quitó: '.implode(', ', $quitados));
        }

        return true;
    }

    /** @return list<string> */
    private function nombresMes(TrazabilidadFilters $filters): array
    {
        return array_map(static fn (int $mes): string => self::MESES[$mes], $filters->meses());
    }

    private function applyNormalizedFilters(TrazabilidadFilters $filters): void
    {
        $this->flogPagina = 0;
        $this->flog = $filters->flog;
        $this->articulo = $filters->articulo;
        $this->tamano = $filters->tamano;
        $this->cliente = $filters->cliente;
        $this->anio = $filters->anio;
        $this->mes = $filters->mes;
    }
}
