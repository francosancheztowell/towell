<?php

declare(strict_types=1);

namespace App\Services\Crudo;

use App\Contracts\Crudo\CrudoReadRepository;
use App\DTOs\Crudo\CrudoDashboardData;
use App\DTOs\Crudo\CrudoMachineMetrics;
use App\Enums\Crudo\CrudoMachineState;
use App\Support\Crudo\CrudoProductionDay;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;

final readonly class CrudoDashboardService
{
    public function __construct(
        private CrudoReadRepository $repository,
        private CrudoStatusResolver $statusResolver,
        private CrudoProductionTargetService $productionTargetService,
    ) {}

    public function build(DateTimeImmutable $date): CrudoDashboardData
    {
        return $this->buildForRange($date, $date);
    }

    public function buildRange(DateTimeImmutable $from, DateTimeImmutable $to): CrudoDashboardData
    {
        return $this->buildForRange($from, $to);
    }

    /**
     * Defectos y capturas individuales de UN telar. El proveedor decide si
     * conserva este resultado unos segundos para absorber aperturas repetidas;
     * no se carga para los 39 telares dentro del snapshot compartido.
     *
     * @return array<string, mixed>
     */
    public function machineDetail(string $telar, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $headers = $this->repository->headersForTelarInRange($telar, $from, $to);
        $headerIds = array_map(
            static fn (object $row): int|string => $row->RECID,
            $headers,
        );
        $defects = $this->repository->defectsForHeaders($headerIds);
        $defectsByHeader = $this->groupDefectsByHeader($defects);
        $metricsByTelar = $this->aggregateHeaders($headers, $defectsByHeader);
        $raw = $metricsByTelar[$telar] ?? $this->emptyMetrics();

        $defectsList = array_values($raw['defects']);
        usort(
            $defectsList,
            static fn (array $left, array $right): int => $right['quantity'] <=> $left['quantity'],
        );

        $captureLimit = max(5, (int) config('crudo.detail_capture_limit', 25));
        $visibleCaptures = $this->attachSupplierLots(array_slice($raw['captures'], -$captureLimit));

        return [
            'captureCount' => (int) $raw['captureCount'],
            'pieces' => (float) $raw['pieces'],
            'seconds' => (float) $raw['seconds'],
            'kilos' => (float) $raw['kilos'],
            'qualityPercent' => $raw['pieces'] > 0
                ? max(0, 100 - (($raw['seconds'] / $raw['pieces']) * 100))
                : 0.0,
            'secondsPercent' => $raw['pieces'] > 0
                ? min(100, max(0, ($raw['seconds'] / $raw['pieces']) * 100))
                : 0.0,
            'orders' => array_keys($raw['orders']),
            'operators' => array_keys($raw['operators']),
            'lastUpdatedAt' => $raw['lastUpdatedAt'],
            'defectLineCount' => (int) $raw['defectLineCount'],
            'defects' => $defectsList,
            'captures' => $visibleCaptures,
        ];
    }

    /**
     * El ORDENURDIDO de la captura es el Folio del programa de urdido, de donde
     * sale el lote del proveedor. Se resuelve en una sola consulta para las
     * capturas ya recortadas al límite del modal, no para todo el histórico.
     *
     * @param  list<array<string, mixed>>  $captures
     * @return list<array<string, mixed>>
     */
    private function attachSupplierLots(array $captures): array
    {
        $warpingOrders = array_values(array_filter(array_map(
            static fn (array $capture): string => trim((string) ($capture['warpingOrder'] ?? '')),
            $captures,
        )));

        $lotsByFolio = $warpingOrders === []
            ? []
            : $this->repository->supplierLotsByWarpingOrder($warpingOrders);

        foreach ($captures as $index => $capture) {
            $folio = trim((string) ($capture['warpingOrder'] ?? ''));
            $captures[$index]['supplierLot'] = $lotsByFolio[$folio] ?? '';
        }

        return $captures;
    }

    private function buildForRange(DateTimeImmutable $from, DateTimeImmutable $to): CrudoDashboardData
    {
        $catalog = $this->machineCatalog();
        $now = CarbonImmutable::now($from->getTimezone());

        // El paro y los datos descriptivos del programa solo se muestran en
        // periodos que incluyen hoy. ProdKgDia sí se consulta siempre porque
        // negocio definió el programa EnProceso como estándar único, incluso
        // al consultar fechas históricas.
        //
        // "Hoy" se compara contra el día de producción (06:30-06:30), no el
        // calendario: de madrugada (00:00-06:30) el día de producción sigue
        // siendo el de ayer, y comparar contra el calendario apagaba los
        // paros activos y el programa en ese tramo.
        $today = CrudoProductionDay::forInstant($now);
        $includesToday = $from->format('Y-m-d') <= $today && $to->format('Y-m-d') >= $today;

        $parosByTelar = $includesToday ? $this->groupParosByTelar($this->repository->activeParos()) : [];
        $metricsByTelar = $this->metricsFromAggregateRows(
            $this->cachedProductionRows($from, $to),
        );

        $catalogTelares = array_map(static fn (array $row): string => (string) $row['telar'], $catalog);
        $telares = array_values(array_unique([...$catalogTelares, ...array_keys($metricsByTelar)]));
        $activePrograms = $telares !== [] ? $this->repository->activePrograms($telares) : [];
        $programsByTelar = $includesToday ? $this->groupProgramsByTelar($activePrograms) : [];
        $productionTargetsByTelar = $this->productionTargetService->expectedByTelar(
            $from,
            $to,
            $now,
            $activePrograms,
        );

        $machines = $this->buildMachines(
            $catalog,
            $metricsByTelar,
            $parosByTelar,
            $programsByTelar,
            $productionTargetsByTelar,
            $this->efficiencyByTelar($this->cachedEfficiencyLines($from, $to), $from, $to),
        );

        $isSingleDay = $from->format('Y-m-d') === $to->format('Y-m-d');

        return new CrudoDashboardData(
            date: $isSingleDay ? $from->format('Y-m-d') : $from->format('Y-m-d').'_'.$to->format('Y-m-d'),
            machines: $machines,
            summary: $this->buildSummary($machines),
            areas: $this->buildAreas($machines),
            generatedAt: $now->format(DATE_ATOM),
        );
    }

    /**
     * @param  list<object>  $rows
     * @return array<string, list<object>>
     */
    private function groupDefectsByHeader(array $rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $key = trim((string) $row->REFRECID);
            $grouped[$key] ??= [];
            $grouped[$key][] = $row;
        }

        return $grouped;
    }

    /**
     * @param  list<object>  $rows
     * @return array<string, array<string, string|null>>
     */
    private function groupParosByTelar(array $rows): array
    {
        $paros = [];

        foreach ($rows as $row) {
            $telar = trim((string) ($row->MaquinaId ?? ''));
            if ($telar === '') {
                continue;
            }

            $faultCode = trim((string) ($row->Falla ?? '')) ?: null;
            $faultName = trim((string) ($row->Descripcion ?? '')) ?: $faultCode;
            $detalle = [
                'reportedBy' => trim((string) ($row->NomEmpl ?? '')) ?: null,
                'depto' => trim((string) ($row->Depto ?? '')) ?: null,
                'faultCode' => $faultCode,
                'falla' => $faultName,
                'tipo' => trim((string) ($row->TipoFallaId ?? '')) ?: null,
                'since' => $this->formatParoSince($row->Fecha ?? null, $row->Hora ?? null),
            ];

            // Un telar puede acumular varios paros activos (eléctrico y mecánico,
            // por ejemplo). El encabezado sigue siendo el más reciente —las filas
            // vienen ordenadas por fecha y hora descendente— y el resto se lista.
            if (! isset($paros[$telar])) {
                $paros[$telar] = $detalle + [
                    'descripcion' => $faultName,
                    'count' => 0,
                    'todos' => [],
                ];
            }

            $paros[$telar]['count']++;
            $paros[$telar]['todos'][] = $detalle;
        }

        return $paros;
    }

    private function formatParoSince(mixed $date, mixed $time): ?string
    {
        $dateText = trim((string) ($date ?? ''));
        $timeText = trim((string) ($time ?? ''));

        if ($dateText === '' && $timeText === '') {
            return null;
        }

        try {
            $datePart = $dateText !== ''
                ? CarbonImmutable::parse($dateText)->format('d/m/Y')
                : '';
        } catch (\Throwable) {
            $datePart = preg_replace('/\s+\d{1,2}:\d{2}(?::\d{2})?.*$/', '', $dateText) ?? $dateText;
        }

        $timePart = '';
        if (preg_match('/(\d{1,2}):(\d{2})/', $timeText, $matches) === 1) {
            $timePart = str_pad($matches[1], 2, '0', STR_PAD_LEFT).':'.$matches[2];
        }

        return trim($datePart.' '.$timePart) ?: null;
    }

    /**
     * @param  list<object>  $rows
     * @return array<string, array<string, string|null>>
     */
    private function groupProgramsByTelar(array $rows): array
    {
        $programs = [];

        foreach ($rows as $row) {
            $telar = trim((string) ($row->NoTelarId ?? ''));
            if ($telar === '' || isset($programs[$telar])) {
                continue;
            }

            $programs[$telar] = [
                'orden' => trim((string) ($row->NoProduccion ?? '')) ?: null,
                'claveModelo' => trim((string) ($row->TamanoClave ?? '')) ?: null,
                'itemId' => trim((string) ($row->ItemId ?? '')) ?: null,
                'inventSizeId' => trim((string) ($row->InventSizeId ?? '')) ?: null,
                'flogId' => trim((string) ($row->FlogsId ?? '')) ?: null,
                'nombreProducto' => trim((string) ($row->NombreProducto ?? '')) ?: null,
                // PesoCrudo es texto en ReqProgramaTejido y viene en gramos por pieza.
                'pesoCrudo' => is_numeric(trim((string) ($row->PesoCrudo ?? '')))
                    ? (float) trim((string) $row->PesoCrudo)
                    : null,
                'marbetes' => $this->numberOrNull($row->SaldoMarbete ?? null),
                'totalRollos' => $this->numberOrNull($row->TotalRollos ?? null),
                'totalPedido' => $this->numberOrNull($row->TotalPedido ?? null),
                'saldoPedido' => $this->numberOrNull($row->SaldoPedido ?? null),
            ];
        }

        return $programs;
    }

    private function numberOrNull(mixed $value): ?float
    {
        $text = trim((string) ($value ?? ''));

        return is_numeric($text) ? (float) $text : null;
    }

    /**
     * @param  list<object>  $headers
     * @param  array<string, list<object>>  $defectsByHeader
     * @return array<string, array<string, mixed>>
     */
    private function aggregateHeaders(array $headers, array $defectsByHeader, bool $includeDetail = true): array
    {
        $metrics = [];

        foreach ($headers as $header) {
            $telar = trim((string) ($header->TELAR ?? ''));
            if ($telar === '') {
                continue;
            }

            $headerDefects = $defectsByHeader[trim((string) $header->RECID)] ?? [];
            $pieces = $this->number($header->PIEZASTOTAL ?? 0);
            $seconds = $this->number($header->SEGUNDASTOTAL ?? 0);
            // PESO ya es el kg total de la captura, no por pieza.
            $kilos = $this->number($header->PESO ?? 0);

            $metrics[$telar] ??= $this->emptyMetrics();
            $metrics[$telar]['captureCount']++;
            $metrics[$telar]['pieces'] += $pieces;
            $metrics[$telar]['seconds'] += $seconds;
            $metrics[$telar]['kilos'] += $kilos;

            $order = trim((string) ($header->PRODID ?? ''));
            if ($order !== '') {
                $metrics[$telar]['orders'][$order] = true;
            }

            $operator = trim((string) ($header->NAMEEMPLE ?? ''));
            if ($operator !== '') {
                $metrics[$telar]['operators'][$operator] = true;
            }

            $updatedAt = $this->modifiedAt($header);
            if ($updatedAt !== null && ($metrics[$telar]['lastUpdatedAt'] === null || $updatedAt > $metrics[$telar]['lastUpdatedAt'])) {
                $metrics[$telar]['lastUpdatedAt'] = $updatedAt;
            }

            // El detalle (lista de defectos y capturas individuales) solo se arma para
            // el snapshot pequeño de un telar (modal), no para el tablero completo
            // cacheado — evita cargar/serializar esas listas para los 39 telares.
            if (! $includeDetail) {
                continue;
            }

            $captureDefectLineCount = 0;
            foreach ($headerDefects as $defect) {
                $captureDefectLineCount++;
                $metrics[$telar]['defectLineCount']++;
                $code = trim((string) ($defect->CODDEFECTOID ?? '')) ?: '—';
                $description = trim((string) ($defect->DESCRIP ?? '')) ?: 'Sin descripción';
                $defectKey = $code.'|'.$description;
                $metrics[$telar]['defects'][$defectKey] ??= [
                    'code' => $code,
                    'description' => $description,
                    'quantity' => 0.0,
                    'turns' => [
                        '1' => 0.0,
                        '2' => 0.0,
                        '3' => 0.0,
                        '4' => 0.0,
                        'other' => 0.0,
                    ],
                ];
                $quantity = $this->number($defect->CANTIDAD ?? 0);
                $turn = $this->defectTurn($defect) ?? 'other';
                $metrics[$telar]['defects'][$defectKey]['quantity'] += $quantity;
                $metrics[$telar]['defects'][$defectKey]['turns'][$turn] += $quantity;
            }

            $metrics[$telar]['captures'][] = [
                'recId' => trim((string) $header->RECID),
                'order' => $order ?: 'Sin orden',
                'date' => $this->formatCaptureDate($header->TRANSDATE ?? null),
                'purchBarcode' => trim((string) ($header->PURCHBARCODE ?? '')),
                'weavingOrder' => trim((string) ($header->ORDENTEJIDO ?? '')),
                'warpingOrder' => trim((string) ($header->ORDENURDIDO ?? '')),
                'operator' => $operator ?: 'Sin operador',
                'weight' => round($kilos, 2),
                'piecesT1' => round($this->number($header->PIEZAST1 ?? 0)),
                'piecesT2' => round($this->number($header->PIEZAST2 ?? 0)),
                'piecesT3' => round($this->number($header->PIEZAST3 ?? 0)),
                'piecesT4' => round($this->number($header->PIEZAST4 ?? 0)),
                'pieces' => round($pieces),
                'seconds' => round($seconds),
                'defectLineCount' => $captureDefectLineCount,
                'observations' => trim((string) ($header->OBSERVACIONES ?? '')),
            ];
        }

        return $metrics;
    }

    private function formatCaptureDate(mixed $date): string
    {
        if ($date === null || $date === '') {
            return '';
        }

        try {
            return CarbonImmutable::parse($date)->format('d/m/Y');
        } catch (\Throwable) {
            return is_scalar($date) ? trim((string) $date) : '';
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyMetrics(): array
    {
        return [
            'captureCount' => 0,
            'pieces' => 0.0,
            'seconds' => 0.0,
            'kilos' => 0.0,
            'orders' => [],
            'operators' => [],
            'defectLineCount' => 0,
            'defects' => [],
            'captures' => [],
            'lastUpdatedAt' => null,
        ];
    }

    /**
     * @param  list<object>  $rows
     * @return array<string, array<string, mixed>>
     */
    private function metricsFromAggregateRows(array $rows): array
    {
        $metrics = [];

        foreach ($rows as $row) {
            $telar = trim((string) ($row->TELAR ?? ''));
            if ($telar === '') {
                continue;
            }

            $metrics[$telar] = [
                ...$this->emptyMetrics(),
                'captureCount' => (int) $this->number($row->captureCount ?? 0),
                'pieces' => $this->number($row->pieces ?? 0),
                'seconds' => $this->number($row->seconds ?? 0),
                'kilos' => $this->number($row->kilos ?? 0),
            ];
        }

        return $metrics;
    }

    /**
     * @return list<array<string, int|string|null>>
     */
    /**
     * La agregación de producción es la única consulta cara del tablero (750k
     * filas en TI_PRO). Vive en su propio caché de 3 minutos para que el pulso
     * de 15 s pueda seguir refrescando paros y programa sin volver a pedirla.
     *
     * @return list<object>
     */
    private function cachedProductionRows(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $seconds = max(0, (int) config('crudo.production_cache_seconds', 180));
        if ($seconds === 0) {
            return $this->repository->aggregateHeadersForRange($from, $to);
        }

        $key = sprintf(
            'crudo:production:%s:%s',
            $from->format('Y-m-d'),
            $to->format('Y-m-d'),
        );

        return Cache::remember(
            $key,
            now()->addSeconds($seconds),
            fn (): array => $this->repository->aggregateHeadersForRange($from, $to),
        );
    }

    /**
     * El corte de eficiencia arrastra hasta 30 días hacia atrás para los telares
     * sin captura del día; se cachea con la misma vida que la producción porque
     * cambia 3 veces al día, no cada pulso de 15 s.
     *
     * @return list<object>
     */
    private function cachedEfficiencyLines(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $seconds = max(0, (int) config('crudo.production_cache_seconds', 180));
        if ($seconds === 0) {
            return $this->repository->efficiencyLinesForRange($from, $to);
        }

        return Cache::remember(
            sprintf('crudo:efficiency:%s:%s', $from->format('Y-m-d'), $to->format('Y-m-d')),
            now()->addSeconds($seconds),
            fn (): array => $this->repository->efficiencyLinesForRange($from, $to),
        );
    }

    private function machineCatalog(): array
    {
        $seconds = max(0, (int) config('crudo.catalog_cache_seconds', 300));
        if ($seconds === 0) {
            return $this->repository->machines();
        }

        $key = 'crudo:machine-catalog:'.sha1(json_encode([
            config('crudo.connections.catalog'),
            config('crudo.tables.machines'),
            config('crudo.tables.sequence'),
            config('crudo.catalog_salons'),
        ], JSON_THROW_ON_ERROR));

        return Cache::remember(
            $key,
            now()->addSeconds($seconds),
            fn (): array => $this->repository->machines(),
        );
    }

    /**
     * @param  list<array<string, int|string|null>>  $catalog
     * @param  array<string, array<string, mixed>>  $metricsByTelar
     * @param  array<string, array{expectedKilos: float, dailyKilos: float, standardStatus: string}>  $productionTargetsByTelar
     * @return list<CrudoMachineMetrics>
     */
    private function buildMachines(
        array $catalog,
        array $metricsByTelar,
        array $parosByTelar,
        array $programsByTelar,
        array $productionTargetsByTelar,
        array $efficiencyByTelar = [],
    ): array {
        $catalogByTelar = [];
        foreach ($catalog as $row) {
            $catalogByTelar[(string) $row['telar']] = $row;
        }

        foreach (array_keys($metricsByTelar) as $telar) {
            $catalogByTelar[$telar] ??= [
                'telar' => $telar,
                'name' => 'Telar '.$telar,
                'salon' => 'Sin clasificar',
                'group' => '',
                'sequence' => null,
            ];
        }

        $machines = [];
        foreach ($catalogByTelar as $telarKey => $catalogRow) {
            $telar = (string) $telarKey;
            $raw = $metricsByTelar[$telar] ?? $this->emptyMetrics();
            $pieces = (float) $raw['pieces'];
            $seconds = (float) $raw['seconds'];
            $secondsPercent = $pieces > 0 ? min(100, max(0, ($seconds / $pieces) * 100)) : 0.0;
            $qualityPercent = $pieces > 0 ? max(0, 100 - $secondsPercent) : 0.0;
            $salon = $this->normalizeSalon((string) ($catalogRow['salon'] ?? ''));
            $productionTarget = $productionTargetsByTelar[$telar] ?? [
                'expectedKilos' => 0.0,
                'dailyKilos' => 0.0,
                'standardStatus' => CrudoProductionTargetService::MISSING,
            ];
            $expectedKilos = (float) $productionTarget['expectedKilos'];
            $dailyTargetKilos = (float) $productionTarget['dailyKilos'];
            $standardStatus = (string) $productionTarget['standardStatus'];
            $paro = $parosByTelar[$telar] ?? null;
            $state = $this->statusResolver->resolve(
                captureCount: (int) $raw['captureCount'],
                pieces: $pieces,
                secondsPercent: $secondsPercent,
                kilos: (float) $raw['kilos'],
                expectedKilos: $standardStatus === CrudoProductionTargetService::COMPLETE
                    ? $expectedKilos
                    : 0.0,
                hasActiveParo: $paro !== null,
            );

            $machines[] = new CrudoMachineMetrics(
                telar: $telar,
                name: (string) ($catalogRow['name'] ?? 'Telar '.$telar),
                salon: $salon,
                group: (string) ($catalogRow['group'] ?? ''),
                sequence: (int) ($catalogRow['sequence'] ?? 99999),
                captureCount: (int) $raw['captureCount'],
                pieces: $pieces,
                seconds: $seconds,
                kilos: (float) $raw['kilos'],
                qualityPercent: $qualityPercent,
                secondsPercent: $secondsPercent,
                expectedKilos: $expectedKilos,
                dailyTargetKilos: $dailyTargetKilos,
                productionStandardStatus: $standardStatus,
                state: $state,
                paro: $paro,
                programa: $programsByTelar[$telar] ?? null,
                efficiencyPercent: (float) ($efficiencyByTelar[$telar]['percent'] ?? 0.0),
                efficiencyObs: (string) ($efficiencyByTelar[$telar]['obs'] ?? ''),
                efficiencyDate: (string) ($efficiencyByTelar[$telar]['date'] ?? ''),
                efficiencyStale: (bool) ($efficiencyByTelar[$telar]['stale'] ?? false),
            );
        }

        usort($machines, function (CrudoMachineMetrics $left, CrudoMachineMetrics $right): int {
            $salonOrder = ['Karl Mayer' => 0, 'Jacquard' => 1, 'Smith' => 2, 'Sin clasificar' => 3];

            return [
                $salonOrder[$left->salon] ?? 99,
                $left->sequence,
                $this->numericTelar($left->telar),
                $left->telar,
            ] <=> [
                $salonOrder[$right->salon] ?? 99,
                $right->sequence,
                $this->numericTelar($right->telar),
                $right->telar,
            ];
        });

        return $machines;
    }

    /**
     * TejEficienciaLine trae una fila por telar y turno con hasta tres
     * revisiones. Se conserva la última revisión con dato del último turno
     * capturado: las filas ya llegan ordenadas por fecha y turno, así que
     * basta con quedarse con la última que traiga eficiencia.
     *
     * La consulta arrastra días previos (efficiency_lookback_days): si el telar
     * no tuvo corte en el periodo se muestra el último que sí tuvo, marcado con
     * su fecha para avisarlo en el modal.
     *
     * @param  list<object>  $rows
     * @return array<string, array{percent: float, obs: string, date: string, stale: bool}>
     */
    private function efficiencyByTelar(array $rows, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $latest = [];

        foreach ($rows as $row) {
            $telar = trim((string) ($row->NoTelarId ?? ''));
            if ($telar === '') {
                continue;
            }

            foreach (['3', '2', '1'] as $revision) {
                $value = $row->{'EficienciaR'.$revision} ?? null;
                if ($value === null || $value === '') {
                    continue;
                }

                $date = $this->efficiencyDate($row->Date ?? null);

                $latest[$telar] = [
                    'percent' => (float) $value,
                    'obs' => trim((string) ($row->{'ObsR'.$revision} ?? '')),
                    'date' => $date,
                    'stale' => $date !== '' && (
                        $date < $from->format('Y-m-d') || $date > $to->format('Y-m-d')
                    ),
                ];

                break;
            }
        }

        return $latest;
    }

    private function efficiencyDate(mixed $date): string
    {
        if ($date === null || $date === '') {
            return '';
        }

        try {
            return CarbonImmutable::parse($date)->format('Y-m-d');
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * @param  list<CrudoMachineMetrics>  $machines
     * @return array<string, int|float>
     */
    private function buildSummary(array $machines): array
    {
        $machines = $this->enOperacion($machines);
        $summary = [
            'paro' => 0,
            'bad_quality' => 0,
            'low_kilos' => 0,
            'operating' => 0,
            'no_data' => 0,
            'total' => count($machines),
            'pieces' => 0.0,
            'seconds' => 0.0,
            'kilos' => 0.0,
            'expectedKilos' => 0.0,
            'dailyTargetKilos' => 0.0,
            'qualityPercent' => 0.0,
            'efficiencyPercent' => 0.0,
        ];
        $standardizedKilos = 0.0;

        foreach ($machines as $machine) {
            // "En operación" = solo el estado verde. Mala calidad y bajos kg ya
            // tienen su propio contador y no deben sumar aquí también.
            $summary[$machine->state->value]++;

            $summary['pieces'] += $machine->pieces;
            $summary['seconds'] += $machine->seconds;
            $summary['kilos'] += $machine->kilos;
            if ($machine->productionStandardStatus === CrudoProductionTargetService::COMPLETE) {
                $summary['expectedKilos'] += $machine->expectedKilos;
                $summary['dailyTargetKilos'] += $machine->dailyTargetKilos;
                $standardizedKilos += $machine->kilos;
            }
        }

        $summary['qualityPercent'] = $summary['pieces'] > 0
            ? max(0, 100 - (($summary['seconds'] / $summary['pieces']) * 100))
            : 0.0;

        $summary['efficiencyPercent'] = $summary['expectedKilos'] > 0
            ? max(0, min(100, ($standardizedKilos / $summary['expectedKilos']) * 100))
            : 0.0;

        $summary['pieces'] = round($summary['pieces']);
        $summary['seconds'] = round($summary['seconds']);
        $summary['kilos'] = round($summary['kilos'], 1);
        $summary['expectedKilos'] = round($summary['expectedKilos'], 1);
        $summary['dailyTargetKilos'] = round($summary['dailyTargetKilos'], 1);
        $summary['qualityPercent'] = round($summary['qualityPercent'], 1);
        $summary['efficiencyPercent'] = round($summary['efficiencyPercent'], 1);

        return $summary;
    }

    /**
     * @param  list<CrudoMachineMetrics>  $machines
     * @return list<array<string, int|string>>
     */
    private function buildAreas(array $machines): array
    {
        $areas = [];

        foreach ($this->enOperacion($machines) as $machine) {
            $areas[$machine->salon] ??= [
                'name' => $machine->salon,
                'paro' => 0,
                'badQuality' => 0,
                'lowKilos' => 0,
                'operating' => 0,
                'noData' => 0,
                'total' => 0,
            ];

            $areas[$machine->salon]['total']++;

            match ($machine->state) {
                CrudoMachineState::Paro => $areas[$machine->salon]['paro']++,
                CrudoMachineState::BadQuality => $areas[$machine->salon]['badQuality']++,
                CrudoMachineState::LowKilos => $areas[$machine->salon]['lowKilos']++,
                CrudoMachineState::Operating => $areas[$machine->salon]['operating']++,
                CrudoMachineState::NoData => $areas[$machine->salon]['noData']++,
            };
        }

        return array_values($areas);
    }

    /**
     * Los telares fuera de operación (config crudo.telares_fuera) solo ocupan su
     * lugar en el plano: no suman en el resumen ni en las alertas por salón.
     *
     * @param  list<CrudoMachineMetrics>  $machines
     * @return list<CrudoMachineMetrics>
     */
    private function enOperacion(array $machines): array
    {
        /** @var list<string> $fuera */
        $fuera = (array) config('crudo.telares_fuera', []);

        return array_values(array_filter(
            $machines,
            static fn (CrudoMachineMetrics $machine): bool => ! in_array($machine->telar, $fuera, true),
        ));
    }

    private function defectTurn(object $defect): ?string
    {
        $turn = preg_replace('/\D+/', '', (string) ($defect->TURNO ?? ''));

        return in_array($turn, ['1', '2', '3', '4'], true) ? $turn : null;
    }

    private function modifiedAt(object $header): ?string
    {
        $date = $header->MODIFIEDDATE ?? $header->TRANSDATE ?? null;
        if ($date === null || $date === '') {
            return null;
        }

        try {
            $seconds = max(0, min(86399, (int) ($header->MODIFIEDTIME ?? 0)));

            return CarbonImmutable::parse($date, config('app.timezone'))
                ->startOfDay()
                ->addSeconds($seconds)
                ->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeSalon(string $salon): string
    {
        $normalized = mb_strtoupper(trim($salon));
        /** @var array<string, string> $salons */
        $salons = config('crudo.salons', []);

        return $salons[$normalized] ?? (trim($salon) ?: 'Sin clasificar');
    }

    private function numericTelar(string $telar): int
    {
        return ctype_digit($telar) ? (int) $telar : PHP_INT_MAX;
    }

    private function number(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }
}
