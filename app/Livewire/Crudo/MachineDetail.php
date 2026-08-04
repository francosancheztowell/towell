<?php

declare(strict_types=1);

namespace App\Livewire\Crudo;

use App\Contracts\Crudo\CrudoDashboardProvider;
use App\Contracts\Crudo\CrudoFlogProvider;
use App\Services\Crudo\CrudoStatusResolver;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

class MachineDetail extends Component
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

    /**
     * Datos básicos de la máquina enviados desde el dashboard al abrir el modal.
     *
     * @var array<string, mixed>|null
     */
    public ?array $machine = null;

    public ?string $detailError = null;

    /** @var array<string, mixed>|null */
    public ?array $flogSummary = null;

    public bool $auditModalOpen = false;

    private CrudoDashboardProvider $provider;

    private CrudoStatusResolver $statusResolver;

    private CrudoFlogProvider $flogProvider;

    public function boot(
        CrudoDashboardProvider $provider,
        CrudoStatusResolver $statusResolver,
        CrudoFlogProvider $flogProvider,
    ): void {
        $this->provider = $provider;
        $this->statusResolver = $statusResolver;
        $this->flogProvider = $flogProvider;
    }

    public function mount(): void
    {
        $this->fecha = $this->normalizeDate($this->fecha);
        $this->fechaInicio = $this->normalizeDate($this->fechaInicio !== '' ? $this->fechaInicio : $this->fecha);
        $this->fechaFin = $this->normalizeDate($this->fechaFin !== '' ? $this->fechaFin : $this->fecha);
        $this->modo = $this->modo === 'rango' ? 'rango' : 'dia';
        $this->turno = $this->normalizeShift($this->turno);
    }

    #[On('open-crudo-detail')]
    public function open(string $telar, array $machine): void
    {
        $this->selectedTelar = mb_substr(trim($telar), 0, 20);
        $this->machine = $machine;
        $this->detailError = null;
        $this->auditModalOpen = false;
        $this->loadFlogSummary();
    }

    public function close(): void
    {
        $this->selectedTelar = null;
        $this->machine = null;
        $this->detailError = null;
        $this->flogSummary = null;
        $this->auditModalOpen = false;
    }

    #[On('crudo-filtros-cambiados')]
    public function closeForFilterChange(): void
    {
        $this->close();
    }

    public function openAudit(): void
    {
        if ($this->selectedTelar === null) {
            $this->auditModalOpen = false;

            return;
        }

        $this->auditModalOpen = true;
    }

    #[On('crudo-refrescado')]
    public function refreshDetail(): void
    {
        // El detalle se resuelve en la computed property `detail` al renderizar;
        // este guard solo evita volver a consultarlo con el modal de auditoría abierto.
        if ($this->selectedTelar === null || $this->auditModalOpen) {
            return;
        }
    }

    public function render(): View
    {
        return view('livewire.crudo.machine-detail', [
            'selectedMachine' => $this->resolvedMachine(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolvedMachine(): array
    {
        if ($this->machine === null) {
            return [];
        }

        $machine = $this->machine;
        $detail = $this->detail ?? [];

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
            if (array_key_exists($key, $detail)) {
                $machine[$key] = $detail[$key];
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

    /**
     * El detalle en vivo del telar (capturas y defectos) no se persiste como
     * propiedad pública: en rangos de varios días las listas de capturas son
     * grandes y viajarían en el snapshot de Livewire en cada petición. La
     * computed property se evalúa al renderizar (memoizada por petición) y, si
     * la consulta falla, devuelve el último detalle exitoso cacheado en servidor.
     *
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function detail(): ?array
    {
        if ($this->selectedTelar === null || $this->auditModalOpen) {
            return null;
        }

        $fallbackKey = $this->detailFallbackKey();

        try {
            $detail = $this->provider->detail(
                $this->selectedTelar,
                $this->rangeFrom(),
                $this->rangeTo(),
                $this->turno,
            );

            Cache::put(
                $fallbackKey,
                $detail,
                now()->addSeconds((int) config('crudo.detail_fallback_seconds', 900)),
            );
            $this->detailError = null;

            return $detail;
        } catch (Throwable $exception) {
            report($exception);
            $this->detailError = 'No fue posible actualizar el detalle. El resumen puede seguir mostrando el último dato disponible.';

            $cached = Cache::get($fallbackKey);

            return is_array($cached) ? $cached : null;
        }
    }

    private function detailFallbackKey(): string
    {
        return sprintf(
            'crudo:detail-fallback:%s:%s:%s:%s',
            sha1(trim($this->selectedTelar ?? '')),
            $this->rangeFrom()->format('Y-m-d'),
            $this->rangeTo()->format('Y-m-d'),
            $this->turno,
        );
    }

    private function loadFlogSummary(): void
    {
        $program = $this->machine['programa'] ?? null;
        $barcodes = array_values(array_filter(array_map(
            static fn (array $capture): string => trim((string) ($capture['purchBarcode'] ?? '')),
            $this->detail['captures'] ?? [],
        )));

        try {
            $this->flogSummary = $this->flogProvider->find(
                is_array($program) ? $program : null,
                $barcodes,
            );
        } catch (Throwable $exception) {
            report($exception);
            $this->flogSummary = [
                'status' => 'error',
                'source' => null,
                'flog' => '',
                'client' => '',
                'itemId' => '',
                'inventSizeId' => '',
                'simulationSalesUrl' => null,
                'simulationDesignUrl' => null,
                'lineMatched' => false,
            ];
        }
    }

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
}
