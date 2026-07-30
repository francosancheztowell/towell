<?php

declare(strict_types=1);

namespace App\Livewire\Crudo;

use App\Contracts\Crudo\CrudoDashboardProvider;
use App\Services\Crudo\CrudoAccess;
use App\Services\Crudo\CrudoFloorLayout;
use App\Services\Crudo\CrudoStatusResolver;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

class Dashboard extends Component
{
    #[Url(except: '')]
    public string $fecha = '';

    #[Url(except: '')]
    public string $fechaInicio = '';

    #[Url(except: '')]
    public string $fechaFin = '';

    #[Url(except: 'dia')]
    public string $modo = 'dia';

    #[Url(except: 'todos')]
    public string $turno = 'todos';

    public ?string $selectedTelar = null;

    public ?string $dataError = null;

    #[Locked]
    public ?array $selectedMachineDetail = null;

    #[Locked]
    public ?string $selectedMachineDetailError = null;

    /**
     * Evita forzar una reconstrucción síncrona del snapshot (que implica varias
     * consultas a SQL Server) cuando la acción solo abre/cierra el modal de detalle
     * — en ese caso basta con lo que ya haya en caché, aunque no esté "fresco".
     */
    private bool $skipRebuildOnNextRender = false;

    private bool $forceRefreshOnNextRender = false;

    private CrudoDashboardProvider $provider;

    private CrudoAccess $access;

    private CrudoFloorLayout $floorLayout;

    private CrudoStatusResolver $statusResolver;

    public function boot(
        CrudoDashboardProvider $provider,
        CrudoAccess $access,
        CrudoFloorLayout $floorLayout,
        CrudoStatusResolver $statusResolver,
    ): void {
        $this->provider = $provider;
        $this->access = $access;
        $this->floorLayout = $floorLayout;
        $this->statusResolver = $statusResolver;
        $this->authorizeAccess();
    }

    public function mount(): void
    {
        $this->fecha = $this->normalizeDate($this->fecha);
        $this->fechaInicio = $this->normalizeDate($this->fechaInicio !== '' ? $this->fechaInicio : $this->fecha);
        $this->fechaFin = $this->normalizeDate($this->fechaFin !== '' ? $this->fechaFin : $this->fecha);
        $this->modo = $this->modo === 'rango' ? 'rango' : 'dia';
        $this->turno = $this->normalizeShift($this->turno);
    }

    public function updatedFecha(): void
    {
        $this->fecha = $this->normalizeDate($this->fecha);
        $this->clearSelectedMachine();
        $this->dataError = null;
    }

    public function updatedFechaInicio(): void
    {
        $this->fechaInicio = $this->normalizeDate($this->fechaInicio);
        $this->clearSelectedMachine();
        $this->dataError = null;
    }

    public function updatedFechaFin(): void
    {
        $this->fechaFin = $this->normalizeDate($this->fechaFin);
        $this->clearSelectedMachine();
        $this->dataError = null;
    }

    public function updatedModo(): void
    {
        $this->modo = $this->modo === 'rango' ? 'rango' : 'dia';
        $this->clearSelectedMachine();
        $this->dataError = null;
    }

    public function updatedTurno(): void
    {
        $this->turno = $this->normalizeShift($this->turno);
        $this->clearSelectedMachine();
        $this->dataError = null;
    }

    public function refreshDashboard(): void
    {
        if ($this->selectedTelar !== null) {
            $this->loadSelectedMachineDetail();
        }

        $this->dispatch('crudo-refrescado');
    }

    public function refreshNow(): void
    {
        $this->forceRefreshOnNextRender = true;

        if ($this->selectedTelar !== null) {
            $this->loadSelectedMachineDetail();
        }

        $this->dispatch('crudo-refrescado');
    }

    public function selectMachine(string $telar): void
    {
        $telar = mb_substr(trim($telar), 0, 20);
        abort_unless($telar !== '', 422, 'Selecciona una máquina válida.');

        $this->selectedTelar = $telar;
        $this->skipRebuildOnNextRender = true;
        $this->loadSelectedMachineDetail();
    }

    public function closeMachine(): void
    {
        $this->clearSelectedMachine();
        $this->skipRebuildOnNextRender = true;
    }

    public function render(): View
    {
        $data = $this->loadDashboard(
            forceRefresh: $this->forceRefreshOnNextRender,
            allowRebuild: ! $this->skipRebuildOnNextRender,
        );
        $this->skipRebuildOnNextRender = false;
        $this->forceRefreshOnNextRender = false;
        $machines = is_array($data['machines'] ?? null) ? $data['machines'] : [];
        $selectedMachine = null;

        if ($this->selectedTelar !== null) {
            foreach ($machines as $machine) {
                if (($machine['telar'] ?? null) === $this->selectedTelar) {
                    $selectedMachine = $machine;
                    break;
                }
            }

            if ($selectedMachine === null) {
                $this->clearSelectedMachine();
            } elseif ($this->selectedMachineDetail !== null) {
                $selectedMachine = $this->overlaySelectedMachineDetail($selectedMachine);
            }
        }

        return view('livewire.crudo.dashboard', [
            'machines' => $machines,
            'summary' => $data['summary'] ?? $this->emptySummary(),
            'areas' => $data['areas'] ?? [],
            'generatedAt' => $data['generatedAt'] ?? null,
            'cacheState' => $data['cacheState'] ?? 'unavailable',
            'sourceError' => $data['sourceError'] ?? null,
            'selectedMachine' => $selectedMachine,
            'floorLayouts' => $this->floorLayout->arrange($machines),
            'shouldPoll' => $this->modo === 'dia'
                && $this->rangeTo()->format('Y-m-d') === now(config('app.timezone'))->format('Y-m-d'),
            'pollSeconds' => (int) config('crudo.poll_seconds', 15),
            'badQualityThreshold' => (float) config('crudo.bad_quality_percent', 10),
            'modo' => $this->modo,
            'maxRangeDays' => (int) config('crudo.max_range_days', 31),
        ]);
    }

    protected function authorizeAccess(): void
    {
        $this->access->authorize();
    }

    /**
     * @return array<string, mixed>
     */
    private function loadDashboard(bool $forceRefresh = false, bool $allowRebuild = true): array
    {
        try {
            $data = $this->provider->get(
                $this->rangeFrom(),
                $this->turno,
                $forceRefresh,
                $this->rangeTo(),
                $allowRebuild,
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
     * En modo "día" el rango es un único día; en modo "rango" se acota a
     * crudo.max_range_days para no disparar cientos de días de captura en
     * una sola consulta (headers crecen ~linear con los días pedidos).
     */
    private function rangeFrom(): DateTimeImmutable
    {
        $timezone = new DateTimeZone((string) config('app.timezone'));

        if ($this->modo !== 'rango') {
            return new DateTimeImmutable($this->normalizeDate($this->fecha), $timezone);
        }

        $from = new DateTimeImmutable($this->normalizeDate($this->fechaInicio), $timezone);
        $to = new DateTimeImmutable($this->normalizeDate($this->fechaFin), $timezone);

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $maxDays = max(1, (int) config('crudo.max_range_days', 31));
        $earliestAllowed = $to->sub(new DateInterval('P'.($maxDays - 1).'D'));

        return $from < $earliestAllowed ? $earliestAllowed : $from;
    }

    private function rangeTo(): DateTimeImmutable
    {
        $timezone = new DateTimeZone((string) config('app.timezone'));

        if ($this->modo !== 'rango') {
            return new DateTimeImmutable($this->normalizeDate($this->fecha), $timezone);
        }

        $from = new DateTimeImmutable($this->normalizeDate($this->fechaInicio), $timezone);
        $to = new DateTimeImmutable($this->normalizeDate($this->fechaFin), $timezone);

        return $from > $to ? $from : $to;
    }

    private function normalizeDate(string $date): string
    {
        $date = trim($date);
        $timezone = new DateTimeZone((string) config('app.timezone'));
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $timezone);
        $errors = DateTimeImmutable::getLastErrors();
        $valid = $parsed !== false
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
            && $parsed->format('Y-m-d') === $date;

        return $valid ? $date : now($timezone)->format('Y-m-d');
    }

    private function normalizeShift(string $shift): string
    {
        return in_array($shift, ['todos', '1', '2', '3', '4'], true) ? $shift : 'todos';
    }

    private function loadSelectedMachineDetail(): void
    {
        if ($this->selectedTelar === null) {
            return;
        }

        try {
            $this->selectedMachineDetail = $this->provider->detail(
                $this->selectedTelar,
                $this->rangeFrom(),
                $this->rangeTo(),
                $this->turno,
            );
            $this->selectedMachineDetailError = null;
        } catch (Throwable $exception) {
            report($exception);
            $this->selectedMachineDetailError = 'No fue posible actualizar el detalle. El resumen puede seguir mostrando el último dato disponible.';
        }
    }

    /**
     * @param  array<string, mixed>  $machine
     * @return array<string, mixed>
     */
    private function overlaySelectedMachineDetail(array $machine): array
    {
        $detailKeys = [
            'captureCount',
            'pieces',
            'seconds',
            'kilos',
            'qualityPercent',
            'secondsPercent',
            'orders',
            'operators',
            'lastUpdatedAt',
            'defectLineCount',
            'defects',
            'captures',
        ];

        foreach ($detailKeys as $key) {
            if (array_key_exists($key, $this->selectedMachineDetail)) {
                $machine[$key] = $this->selectedMachineDetail[$key];
            }
        }

        $state = $this->statusResolver->resolve(
            captureCount: (int) ($machine['captureCount'] ?? 0),
            pieces: (float) ($machine['pieces'] ?? 0),
            secondsPercent: (float) ($machine['secondsPercent'] ?? 0),
            kilos: (float) ($machine['kilos'] ?? 0),
            expectedKilos: (float) ($machine['expectedKilos'] ?? 0),
            hasActiveParo: $machine['paro'] !== null,
        );

        $machine['state'] = $state->value;
        $machine['stateLabel'] = $state->label();
        $machine['stateIcon'] = $state->icon();
        $machine['defectLineCount'] ??= 0;

        return $machine;
    }

    private function clearSelectedMachine(): void
    {
        $this->selectedTelar = null;
        $this->selectedMachineDetail = null;
        $this->selectedMachineDetailError = null;
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
