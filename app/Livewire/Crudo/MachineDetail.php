<?php

declare(strict_types=1);

namespace App\Livewire\Crudo;

use App\Contracts\Crudo\CrudoDashboardProvider;
use App\Services\Crudo\CrudoAccess;
use App\Services\Crudo\CrudoStatusResolver;
use App\Support\Crudo\ResolvesCrudoPeriod;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

class MachineDetail extends Component
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

    public bool $detailLoaded = false;

    public bool $auditModalOpen = false;

    private CrudoDashboardProvider $provider;

    private CrudoAccess $access;

    private CrudoStatusResolver $statusResolver;

    public function boot(
        CrudoDashboardProvider $provider,
        CrudoAccess $access,
        CrudoStatusResolver $statusResolver,
    ): void {
        $this->provider = $provider;
        $this->access = $access;
        $this->statusResolver = $statusResolver;
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
        $this->detailLoaded = false;
        $this->auditModalOpen = false;
    }

    public function close(): void
    {
        $this->selectedTelar = null;
        $this->machine = null;
        $this->detailError = null;
        $this->detailLoaded = false;
        $this->auditModalOpen = false;
    }

    #[On('crudo-filtros-cambiados')]
    public function closeForFilterChange(
        ?string $fecha = null,
        ?string $fechaInicio = null,
        ?string $fechaFin = null,
        ?string $modo = null,
        ?string $turno = null,
    ): void {
        if ($fecha !== null) {
            $this->fecha = $this->normalizeDate($fecha);
        }

        if ($fechaInicio !== null) {
            $this->fechaInicio = $this->normalizeDate($fechaInicio);
        }

        if ($fechaFin !== null) {
            $this->fechaFin = $this->normalizeDate($fechaFin);
        }

        if ($modo !== null) {
            $this->modo = $modo === 'rango' ? 'rango' : 'dia';
        }

        if ($turno !== null) {
            $this->turno = $this->normalizeShift($turno);
        }

        $this->close();
    }

    #[On('crudo-auditoria-guardada')]
    public function closeAfterAuditSave(): void
    {
        $this->close();
    }

    public function openAudit(): void
    {
        $this->authorizeRegisterAudit();

        if ($this->selectedTelar === null) {
            $this->auditModalOpen = false;

            return;
        }

        $this->auditModalOpen = true;
    }

    public function loadDetail(): void
    {
        if ($this->selectedTelar === null || $this->auditModalOpen || $this->detailLoaded) {
            return;
        }

        $this->detailLoaded = true;
    }

    #[On('crudo-refrescado')]
    public function refreshDetail(): void
    {
        // El detalle se resuelve en la computed property `detail` al renderizar;
        // este guard solo evita volver a consultarlo con el modal de auditoría abierto.
        if ($this->selectedTelar === null || $this->auditModalOpen) {
            return;
        }

        $this->detailLoaded = true;
    }

    public function render(): View
    {
        return view('livewire.crudo.machine-detail', [
            'selectedMachine' => $this->resolvedMachine(),
            'canRegisterAudit' => $this->canRegisterAudit(),
        ]);
    }

    protected function canRegisterAudit(): bool
    {
        return $this->access->canRegister();
    }

    protected function authorizeRegisterAudit(): void
    {
        $this->access->authorizeRegister();
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

        $machine['orders'] = is_array($machine['orders'] ?? null) ? $machine['orders'] : [];
        $machine['operators'] = is_array($machine['operators'] ?? null) ? $machine['operators'] : [];
        $machine['defects'] = is_array($machine['defects'] ?? null) ? $machine['defects'] : [];
        $machine['captures'] = is_array($machine['captures'] ?? null) ? $machine['captures'] : [];

        $state = $this->statusResolver->resolve(
            captureCount: (int) ($machine['captureCount'] ?? 0),
            pieces: (float) ($machine['pieces'] ?? 0),
            secondsPercent: (float) ($machine['secondsPercent'] ?? 0),
            kilos: (float) ($machine['kilos'] ?? 0),
            expectedKilos: (float) ($machine['expectedKilos'] ?? 0),
            hasActiveParo: ($machine['paro'] ?? null) !== null,
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
        if ($this->selectedTelar === null || $this->auditModalOpen || ! $this->detailLoaded) {
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
}
