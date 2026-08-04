<?php

declare(strict_types=1);

namespace App\Livewire\Crudo;

use App\Contracts\Crudo\CrudoDashboardProvider;
use App\Services\Crudo\CrudoAccess;
use App\Services\Crudo\CrudoFloorLayout;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
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

    public ?string $dataError = null;

    private bool $forceRefreshOnNextRender = false;

    private bool $allowSynchronousRebuildOnNextRender = false;

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
        $this->turno = $this->normalizeShift($this->turno);
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

    public function updatedTurno(): void
    {
        $this->turno = $this->normalizeShift($this->turno);
        $this->filtersChanged();
    }

    public function refreshDashboard(): void
    {
        // El poll nunca debe bloquear la interfaz esperando SQL Server. Sirve el
        // último snapshot y agenda la renovación después de enviar la respuesta.
        $this->allowSynchronousRebuildOnNextRender = false;
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
        $data = $this->loadDashboard(
            forceRefresh: $this->forceRefreshOnNextRender,
            allowRebuild: $this->allowSynchronousRebuildOnNextRender,
        );
        $this->forceRefreshOnNextRender = false;
        $this->allowSynchronousRebuildOnNextRender = false;
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
            'shouldPoll' => $this->modo === 'dia'
                && $this->rangeTo()->format('Y-m-d') === now(config('app.timezone'))->format('Y-m-d'),
            'pollSeconds' => in_array($cacheState, ['stale', 'refreshing'], true)
                ? 2
                : (int) config('crudo.poll_seconds', 15),
            'badQualityThreshold' => (float) config('crudo.bad_quality_percent', 10),
            'modo' => $this->modo,
            'maxRangeDays' => (int) config('crudo.max_range_days', 31),
        ]);
    }

    protected function authorizeAccess(): void
    {
        $this->access->authorize();
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
            turno: $this->turno,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function loadDashboard(bool $forceRefresh = false, bool $allowRebuild = false): array
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
