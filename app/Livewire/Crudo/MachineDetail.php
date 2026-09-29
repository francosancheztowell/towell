<?php

declare(strict_types=1);

namespace App\Livewire\Crudo;

use App\Contracts\Crudo\CrudoDashboardProvider;
use App\Http\Controllers\Planeacion\Alineacion\AlineacionController;
use App\Services\Crudo\CrudoAccess;
use App\Services\Crudo\CrudoParosHistoryService;
use App\Services\Crudo\CrudoProductionTargetService;
use App\Services\Crudo\CrudoStatusResolver;
use App\Support\Crudo\CrudoDefectTurnShare;
use App\Support\Crudo\ResolvesCrudoPeriod;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

class MachineDetail extends Component
{
    use ResolvesCrudoPeriod;

    public string $fecha = '';

    public string $fechaInicio = '';

    public string $fechaFin = '';

    public string $modo = 'dia';

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

    /** Se consulta la alineación solo cuando el usuario la pide, no en cada render del modal. */
    public bool $alineacionAbierta = false;

    /**
     * Karl Mayer no tiene rizo, pie, trama ni cenefas: esas tarjetas se cambian por la de
     * barras (la pinta la vista) y "Rizo y plano" se queda solo con el plano.
     *
     * @return array<string, array<string, string>>
     */
    public static function seccionesAlineacion(bool $esKarlMayer): array
    {
        $secciones = self::SECCIONES_ALINEACION;
        if (! $esKarlMayer) {
            return $secciones;
        }

        unset($secciones['Hilos'], $secciones['Cenefa trama']);
        $plano = array_diff_key($secciones['Rizo y plano'], array_flip(['TipoRizo', 'CalibreRizo']));
        unset($secciones['Rizo y plano']);

        return ['Crudo' => $secciones['Crudo'], 'Peso y muestra' => $secciones['Peso y muestra'], 'Plano' => $plano]
            + $secciones;
    }

    /**
     * Columnas de Planeación > Alineación agrupadas para leerse de arriba abajo en el andón.
     * Las llaves son las de AlineacionController::$columnas.
     */
    /** Franja de arriba: lo que identifica la orden (el modelo va aparte, como título). */
    public const RESUMEN_ALINEACION = [
        'NoProduccion' => 'No. orden', 'ItemId' => 'Clave AX', 'Tolerancia' => 'Tolerancia', 'RazSN' => 'Razurada',
        'FechaCambio' => 'Fecha de cambio', 'FechaCompromiso' => 'Fecha compromiso',
    ];

    public const SECCIONES_ALINEACION = [
        'Crudo' => [
            'Ancho' => 'Ancho', 'LargoCrudo' => 'Largo', 'PesoCrudo' => 'Peso',
        ],
        'Peso y muestra' => [
            'PesoMin' => 'Peso mín.', 'PesoMax' => 'Peso máx.', 'PesoGRM2' => 'Peso muestra',
            'MuestraMin' => 'Muestra mín.', 'MuestraMax' => 'Muestra máx.',
        ],
        'Rizo y plano' => [
            'TipoRizo' => 'Tipo rizo', 'CalibreRizo' => 'Altura rizo', 'Luchaje' => 'Luchaje',
            'TipoPlano' => 'Tipo plano', 'MedidaPlano' => 'Medida plano', 'NoTiras' => 'Tiras',
        ],
        'Hilos' => [
            'FibraRizo' => 'Rizo', 'FibraPie' => 'Pie', 'CalibreTrama' => 'Trama',
        ],
        'Cenefa trama' => [
            'PasadasComb1' => 'Cenefa 1', 'PasadasComb2' => 'Cenefa 2', 'PasadasComb3' => 'Cenefa 3',
            'PasadasComb4' => 'Cenefa 4', 'AnchoToalla' => 'Medida cenefa',
        ],
        'Producción' => [
            'TotalPedido' => 'Pedido', 'Produccion' => 'Producción', 'SaldoPedido' => 'Saldo',
            'DiasEficiencia' => 'Días de prod.', 'ProdKgDia' => 'Prom. por día', 'DiasPorEjecutar' => 'Días por ejecutar',
        ],
    ];

    /**
     * Se consulta siempre el mes; la vista filtra a 2 días o semana con CSS, sin
     * volver al servidor (cada paro trae la ventana a la que pertenece).
     */
    private const PAROS_DIAS = 30;

    private CrudoDashboardProvider $provider;

    private CrudoAccess $access;

    private CrudoStatusResolver $statusResolver;

    private CrudoParosHistoryService $parosHistory;

    public function boot(
        CrudoDashboardProvider $provider,
        CrudoAccess $access,
        CrudoStatusResolver $statusResolver,
        CrudoParosHistoryService $parosHistory,
    ): void {
        $this->provider = $provider;
        $this->access = $access;
        $this->statusResolver = $statusResolver;
        $this->parosHistory = $parosHistory;
    }

    public function mount(): void
    {
        $this->fecha = $this->normalizeDate($this->fecha);
        $this->fechaInicio = $this->normalizeDate($this->fechaInicio !== '' ? $this->fechaInicio : $this->fecha);
        $this->fechaFin = $this->normalizeDate($this->fechaFin !== '' ? $this->fechaFin : $this->fecha);
        $this->modo = $this->modo === 'rango' ? 'rango' : 'dia';
    }

    #[On('open-crudo-detail')]
    public function open(
        string $telar,
        array $machine,
        ?string $fecha = null,
        ?string $fechaInicio = null,
        ?string $fechaFin = null,
        ?string $modo = null,
    ): void {
        $wasOpen = $this->selectedTelar !== null;
        $this->applyFilterContext($fecha, $fechaInicio, $fechaFin, $modo);
        $this->selectedTelar = mb_substr(trim($telar), 0, 20);
        $this->machine = $machine;
        $this->detailError = null;
        $this->detailLoaded = false;
        $this->auditModalOpen = false;
        $this->alineacionAbierta = false;

        if (! $wasOpen) {
            $this->dispatch('crudo-interaction-opened');
        }
    }

    public function close(): void
    {
        $wasOpen = $this->selectedTelar !== null;
        $this->selectedTelar = null;
        $this->machine = null;
        $this->detailError = null;
        $this->detailLoaded = false;
        $this->auditModalOpen = false;
        $this->alineacionAbierta = false;

        if ($wasOpen) {
            $this->dispatch('crudo-interaction-closed');
        }
    }

    #[On('crudo-filtros-cambiados')]
    public function closeForFilterChange(
        ?string $fecha = null,
        ?string $fechaInicio = null,
        ?string $fechaFin = null,
        ?string $modo = null,
    ): void {
        $hasFilterContext = $fecha !== null
            || $fechaInicio !== null
            || $fechaFin !== null
            || $modo !== null;
        $nextFecha = $this->normalizedFilterDate($fecha, $this->fecha);
        $nextFechaInicio = $this->normalizedFilterDate($fechaInicio, $this->fechaInicio);
        $nextFechaFin = $this->normalizedFilterDate($fechaFin, $this->fechaFin);
        $nextModo = $this->normalizedFilterMode($modo, $this->modo);
        $filterContextChanged = $nextFecha !== $this->fecha
            || $nextFechaInicio !== $this->fechaInicio
            || $nextFechaFin !== $this->fechaFin
            || $nextModo !== $this->modo;

        $this->fecha = $nextFecha;
        $this->fechaInicio = $nextFechaInicio;
        $this->fechaFin = $nextFechaFin;
        $this->modo = $nextModo;

        if ($hasFilterContext && ! $filterContextChanged) {
            return;
        }

        $this->close();
    }

    #[On('crudo-auditoria-guardada')]
    public function closeAfterAuditSave(): void
    {
        // El formulario vive dentro del modal de detalle: al guardar se vuelve a
        // "Auditorías de hoy" y "Paros del telar" en vez de cerrar todo.
        $this->closeAudit();
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

    public function closeAudit(): void
    {
        $this->auditModalOpen = false;
    }

    public function verAlineacion(): void
    {
        $this->alineacionAbierta = $this->selectedTelar !== null;
    }

    public function cerrarAlineacion(): void
    {
        $this->alineacionAbierta = false;
    }

    /**
     * Renglón de Planeación > Alineación del telar abierto; si el telar trae varias
     * órdenes en proceso, gana la del programa que muestra el modal.
     *
     * @return array{item: array<string, mixed>|null, error: bool}
     */
    #[Computed]
    public function alineacion(): array
    {
        if (! $this->alineacionAbierta || $this->selectedTelar === null) {
            return ['item' => null, 'error' => false];
        }

        try {
            $items = app(AlineacionController::class)->obtenerItemsAlineacion();
        } catch (Throwable $exception) {
            report($exception);

            return ['item' => null, 'error' => true];
        }

        $telar = trim($this->selectedTelar);
        $delTelar = array_values(array_filter(
            $items,
            static fn (array $item): bool => trim((string) ($item['NoTelarId'] ?? '')) === $telar,
        ));
        $orden = trim((string) ($this->machine['programa']['orden'] ?? ''));
        foreach ($delTelar as $item) {
            if ($orden !== '' && trim((string) ($item['NoProduccion'] ?? '')) === $orden) {
                return ['item' => $item, 'error' => false];
            }
        }

        return ['item' => $delTelar[0] ?? null, 'error' => false];
    }

    public function loadDetail(): void
    {
        if ($this->selectedTelar === null || $this->detailLoaded) {
            return;
        }

        $this->detailLoaded = true;
    }

    #[On('crudo-refrescado')]
    public function refreshDetail(): void
    {
        if ($this->selectedTelar === null) {
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
            'piecesByTurn',
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

        $states = $this->statusResolver->resolveAll(
            captureCount: (int) ($machine['captureCount'] ?? 0),
            pieces: (float) ($machine['pieces'] ?? 0),
            secondsPercent: (float) ($machine['secondsPercent'] ?? 0),
            kilos: (float) ($machine['kilos'] ?? 0),
            expectedKilos: ($machine['productionStandardStatus'] ?? CrudoProductionTargetService::MISSING)
                === CrudoProductionTargetService::COMPLETE
                    ? (float) ($machine['expectedKilos'] ?? 0)
                    : 0.0,
            hasActiveParo: ($machine['paro'] ?? null) !== null,
        );

        $state = $states[0];
        $machine['state'] = $state->value;
        $machine['stateLabel'] = $state->label();
        $machine['stateIcon'] = $state->icon();
        // El color solo muestra el estado más grave; la tarjeta los lista todos.
        $machine['states'] = array_map(static fn ($estado): array => [
            'value' => $estado->value,
            'label' => $estado->label(),
            'icon' => $estado->icon(),
        ], $states);
        $machine['defectLineCount'] ??= 0;
        $machine['piecesByTurn'] = self::normalizedPiecesByTurn(
            $machine['piecesByTurn'] ?? null,
            $machine['captures'],
        );
        $machine['defectTurnPercents'] = CrudoDefectTurnShare::percents(
            $machine['defects'],
            $machine['piecesByTurn'],
        );

        return $machine;
    }

    /**
     * @param  array<string, mixed>|null  $piecesByTurn
     * @param  list<array<string, mixed>>  $captures
     * @return array{1: float, 2: float, 3: float, 4: float}
     */
    private static function normalizedPiecesByTurn(?array $piecesByTurn, array $captures): array
    {
        if (is_array($piecesByTurn) && $piecesByTurn !== []) {
            return [
                '1' => (float) ($piecesByTurn['1'] ?? $piecesByTurn[1] ?? 0),
                '2' => (float) ($piecesByTurn['2'] ?? $piecesByTurn[2] ?? 0),
                '3' => (float) ($piecesByTurn['3'] ?? $piecesByTurn[3] ?? 0),
                '4' => (float) ($piecesByTurn['4'] ?? $piecesByTurn[4] ?? 0),
            ];
        }

        return CrudoDefectTurnShare::piecesFromCaptures($captures);
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
        if ($this->selectedTelar === null || ! $this->detailLoaded) {
            return null;
        }

        $fallbackKey = $this->detailFallbackKey();

        try {
            $detail = $this->provider->detail(
                $this->selectedTelar,
                $this->rangeFrom(),
                $this->rangeTo(),
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

    /**
     * Paros del telar (activos y terminados) del último mes que termina en la
     * fecha del tablero. Va aparte del detalle: si Mantenimiento falla, el resto
     * del modal debe seguir funcionando.
     *
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function paros(): array
    {
        if ($this->selectedTelar === null || ! $this->detailLoaded) {
            return [];
        }

        try {
            return $this->parosHistory->forMachine(
                $this->selectedTelar,
                $this->rangeFrom(),
                $this->rangeTo(),
                self::PAROS_DIAS,
            );
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }
    }

    private function detailFallbackKey(): string
    {
        return sprintf(
            'crudo:detail-fallback:%s:%s:%s',
            sha1(trim($this->selectedTelar ?? '')),
            $this->rangeFrom()->format('Y-m-d'),
            $this->rangeTo()->format('Y-m-d'),
        );
    }

    private function applyFilterContext(
        ?string $fecha,
        ?string $fechaInicio,
        ?string $fechaFin,
        ?string $modo,
    ): void {
        $this->fecha = $this->normalizedFilterDate($fecha, $this->fecha);
        $this->fechaInicio = $this->normalizedFilterDate($fechaInicio, $this->fechaInicio);
        $this->fechaFin = $this->normalizedFilterDate($fechaFin, $this->fechaFin);
        $this->modo = $this->normalizedFilterMode($modo, $this->modo);
    }

    private function normalizedFilterDate(?string $value, string $current): string
    {
        return $value !== null ? $this->normalizeDate($value) : $current;
    }

    private function normalizedFilterMode(?string $value, string $current): string
    {
        return $value !== null ? ($value === 'rango' ? 'rango' : 'dia') : $current;
    }
}
