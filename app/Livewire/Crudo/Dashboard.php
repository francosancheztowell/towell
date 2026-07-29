<?php

declare(strict_types=1);

namespace App\Livewire\Crudo;

use App\Contracts\Crudo\CrudoDashboardProvider;
use App\Services\Crudo\CrudoAccess;
use App\Services\Crudo\CrudoFloorLayout;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

class Dashboard extends Component
{
    #[Url(except: '')]
    public string $fecha = '';

    #[Url(except: 'todos')]
    public string $turno = 'todos';

    public ?string $selectedTelar = null;

    public ?string $dataError = null;

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
        $this->turno = $this->normalizeShift($this->turno);
    }

    public function updatedFecha(): void
    {
        $this->fecha = $this->normalizeDate($this->fecha);
        $this->selectedTelar = null;
        $this->dataError = null;
    }

    public function updatedTurno(): void
    {
        $this->turno = $this->normalizeShift($this->turno);
        $this->selectedTelar = null;
        $this->dataError = null;
    }

    public function refreshDashboard(): void
    {
        $this->loadDashboard();
        $this->dispatch('crudo-refrescado');
    }

    public function refreshNow(): void
    {
        $this->loadDashboard(forceRefresh: true);
        $this->dispatch('crudo-refrescado');
    }

    public function selectMachine(string $telar): void
    {
        $telar = mb_substr(trim($telar), 0, 20);
        abort_unless($telar !== '', 422, 'Selecciona una máquina válida.');

        $this->selectedTelar = $telar;
    }

    public function closeMachine(): void
    {
        $this->selectedTelar = null;
    }

    public function render(): View
    {
        $data = $this->loadDashboard();
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
                $this->selectedTelar = null;
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
            'isToday' => $this->fecha === now(config('app.timezone'))->format('Y-m-d'),
            'pollSeconds' => (int) config('crudo.poll_seconds', 10),
            'badQualityThreshold' => (float) config('crudo.bad_quality_percent', 10),
        ]);
    }

    protected function authorizeAccess(): void
    {
        $this->access->authorize();
    }

    /**
     * @return array<string, mixed>
     */
    private function loadDashboard(bool $forceRefresh = false): array
    {
        try {
            $data = $this->provider->get($this->date(), $this->turno, $forceRefresh);
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

    private function date(): DateTimeImmutable
    {
        return new DateTimeImmutable(
            $this->normalizeDate($this->fecha),
            new DateTimeZone((string) config('app.timezone')),
        );
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
            'bad_quality' => 0,
            'low_kilos' => 0,
            'operating' => 0,
            'no_data' => 0,
            'total' => 0,
            'pieces' => 0,
            'seconds' => 0,
            'kilos' => 0,
            'qualityPercent' => 0,
        ];
    }
}
