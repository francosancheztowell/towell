<?php

declare(strict_types=1);

namespace App\Livewire\Crudo;

use App\Contracts\Crudo\CrudoDashboardProvider;
use App\Enums\Crudo\CrudoMachineState;
use App\Services\Crudo\CrudoAccess;
use App\Services\Crudo\CrudoFloorLayout;
use App\Support\Crudo\ResolvesCrudoPeriod;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

class Dashboard extends Component
{
    use ResolvesCrudoPeriod;

    #[Url(except: '')]
    public string $fecha = '';

    #[Url(except: '')]
    public string $fechaInicio = '';

    #[Url(except: '')]
    public string $fechaFin = '';

    #[Url(except: 'dia')]
    public string $modo = 'dia';

    public ?string $dataError = null;

    /** Estado cuyo desglose está abierto (paro, bad_quality, low_kilos, …); null = cerrado. */
    public ?string $estadoDetalle = null;

    public bool $interactionPaused = false;

    private bool $forceRefreshOnNextRender = false;

    private CrudoDashboardProvider $provider;

    private CrudoAccess $access;

    private CrudoFloorLayout $floorLayout;

    public function boot(
        CrudoDashboardProvider $provider,
        CrudoAccess $access,
        CrudoFloorLayout $floorLayout,
    ): void {
        $this->provider = $provider;
        $this->access = $access;
        $this->floorLayout = $floorLayout;
        $this->authorizeAccess();
    }

    public function mount(): void
    {
        $this->fecha = $this->normalizeDate($this->fecha);
        $this->fechaInicio = $this->normalizeDate($this->fechaInicio !== '' ? $this->fechaInicio : $this->fecha);
        $this->fechaFin = $this->normalizeDate($this->fechaFin !== '' ? $this->fechaFin : $this->fecha);
        $this->modo = $this->modo === 'rango' ? 'rango' : 'dia';
    }

    public function updatedFecha(): void
    {
        $this->fecha = $this->normalizeDate($this->fecha);
        $this->filtersChanged();
    }

    public function updatedFechaInicio(): void
    {
        $this->fechaInicio = $this->normalizeDate($this->fechaInicio);
        $this->filtersChanged();
    }

    public function updatedFechaFin(): void
    {
        $this->fechaFin = $this->normalizeDate($this->fechaFin);
        $this->filtersChanged();
    }

    public function updatedModo(): void
    {
        $this->modo = $this->modo === 'rango' ? 'rango' : 'dia';
        $this->filtersChanged();
    }

    /**
     * Desglose del contador: qué telares están en ese estado, desde cuándo y
     * quién reportó. Sigue refrescándose con el poll mientras está abierto.
     */
    public function abrirEstado(string $estado): void
    {
        $this->estadoDetalle = in_array($estado, CrudoMachineState::values(), true)
            ? $estado
            : null;
    }

    public function cerrarEstado(): void
    {
        $this->estadoDetalle = null;
    }

    public function refreshDashboard(): void
    {
        // El render del poll pide siempre el snapshot no bloqueante. El proveedor
        // agenda la renovación si el dato ya venció.
    }

    #[On('crudo-interaction-opened')]
    public function pauseInteraction(): void
    {
        $this->interactionPaused = true;
    }

    #[On('crudo-interaction-closed')]
    public function resumeInteraction(): void
    {
        $this->interactionPaused = false;
    }

    public function refreshNow(): void
    {
        $this->forceRefreshOnNextRender = true;
        $this->dispatch('crudo-refrescado');
    }

    #[On('crudo-paro-guardado')]
    public function refreshAfterStop(): void
    {
        $this->forceRefreshOnNextRender = true;
        $this->dispatch('crudo-refrescado');
    }

    public function render(): View
    {
        $data = $this->loadDashboard(forceRefresh: $this->forceRefreshOnNextRender);
        $this->forceRefreshOnNextRender = false;
        $machines = is_array($data['machines'] ?? null) ? $data['machines'] : [];
        $cacheState = (string) ($data['cacheState'] ?? 'unavailable');

        return view('livewire.crudo.dashboard', [
            'machines' => $machines,
            'summary' => $data['summary'] ?? $this->emptySummary(),
            'areas' => $data['areas'] ?? [],
            'generatedAt' => $data['generatedAt'] ?? null,
            'cacheState' => $cacheState,
            'sourceError' => $data['sourceError'] ?? null,
            'floorLayouts' => $this->floorLayout->arrange($machines),
            // Entre 00:00 y 06:30 el día de producción vigente es el de ayer:
            // se sigue refrescando esa fecha, y también la de hoy si el usuario
            // la eligió a mano, para no dejar el tablero congelado de madrugada.
            'shouldPoll' => $this->modo === 'dia'
                && ! $this->interactionPaused
                && in_array($this->rangeTo()->format('Y-m-d'), [
                    $this->crudoProductionDay(),
                    now(config('app.timezone'))->format('Y-m-d'),
                ], true),
            // Mantener una cadencia estable evita una tormenta de requests de 2 s
            // mientras SQL Server sigue renovando un snapshot vencido.
            'pollSeconds' => (int) config('crudo.poll_seconds', 15),
            'modo' => $this->modo,
            'machinesDetalle' => $this->machinesPorEstado($machines),
        ]);
    }

    protected function authorizeAccess(): void
    {
        $this->access->authorize();
    }

    /**
     * Telares del estado abierto, en orden de número de telar (201, 202, …) y no
     * por salón: en el desglose se busca un telar concreto, no su ubicación.
     *
     * @param  list<array<string, mixed>>  $machines
     * @return list<array<string, mixed>>
     */
    private function machinesPorEstado(array $machines): array
    {
        if ($this->estadoDetalle === null) {
            return [];
        }

        $delEstado = array_values(array_filter(
            $machines,
            fn (array $machine): bool => ($machine['state'] ?? null) === $this->estadoDetalle,
        ));

        usort($delEstado, static function (array $left, array $right): int {
            $numero = static fn (array $machine): int => (int) preg_replace('/\D/', '', (string) ($machine['telar'] ?? ''));

            return [$numero($left), (string) $left['telar']] <=> [$numero($right), (string) $right['telar']];
        });

        return $delEstado;
    }

    private function filtersChanged(): void
    {
        $this->dataError = null;
        $this->dispatch(
            'crudo-filtros-cambiados',
            fecha: $this->fecha,
            fechaInicio: $this->fechaInicio,
            fechaFin: $this->fechaFin,
            modo: $this->modo,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function loadDashboard(bool $forceRefresh = false): array
    {
        try {
            $data = $this->provider->get(
                $this->rangeFrom(),
                $forceRefresh,
                $this->rangeTo(),
                false,
            );
            $this->dataError = null;

            return $data;
        } catch (Throwable $exception) {
            report($exception);
            $this->dataError = $exception->getMessage();

            return [
                'machines' => [],
                'summary' => $this->emptySummary(),
                'areas' => [],
                'generatedAt' => null,
                'cacheState' => 'unavailable',
                'sourceError' => null,
            ];
        }
    }

    /**
     * @return array<string, int|float>
     */
    private function emptySummary(): array
    {
        return [
            'paro' => 0,
            'bad_quality' => 0,
            'low_kilos' => 0,
            'operating' => 0,
            'no_data' => 0,
            'total' => 0,
            'pieces' => 0,
            'seconds' => 0,
            'kilos' => 0,
            'expectedKilos' => 0,
            'qualityPercent' => 0,
            'efficiencyPercent' => 0,
        ];
    }
}
