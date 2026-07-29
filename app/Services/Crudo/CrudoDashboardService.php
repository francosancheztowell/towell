<?php

declare(strict_types=1);

namespace App\Services\Crudo;

use App\Contracts\Crudo\CrudoReadRepository;
use App\DTOs\Crudo\CrudoDashboardData;
use App\DTOs\Crudo\CrudoMachineMetrics;
use App\Enums\Crudo\CrudoMachineState;
use Carbon\CarbonImmutable;
use DateTimeImmutable;

final readonly class CrudoDashboardService
{
    public function __construct(
        private CrudoReadRepository $repository,
        private CrudoStatusResolver $statusResolver,
    ) {}

    public function build(DateTimeImmutable $date, string $shift): CrudoDashboardData
    {
        $shift = $this->normalizeShift($shift);
        $headers = $this->repository->headersForDate($date);
        $headerIds = array_map(
            static fn (object $row): int|string => $row->RECID,
            $headers,
        );
        $defects = $this->repository->defectsForHeaders($headerIds);
        $catalog = $this->repository->machines();
        $now = new DateTimeImmutable('now', $date->getTimezone());

        $defectsByHeader = $this->groupDefectsByHeader($defects);
        $metricsByTelar = $this->aggregateHeaders($headers, $defectsByHeader, $shift);
        $machines = $this->buildMachines($catalog, $metricsByTelar, $date, $shift, $now);

        return new CrudoDashboardData(
            date: $date->format('Y-m-d'),
            shift: $shift,
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
     * @param  list<object>  $headers
     * @param  array<string, list<object>>  $defectsByHeader
     * @return array<string, array<string, mixed>>
     */
    private function aggregateHeaders(array $headers, array $defectsByHeader, string $shift): array
    {
        $metrics = [];

        foreach ($headers as $header) {
            $telar = trim((string) ($header->TELAR ?? ''));
            if ($telar === '') {
                continue;
            }

            $headerDefects = $defectsByHeader[trim((string) $header->RECID)] ?? [];
            $pieces = $this->headerPieces($header, $shift);
            $seconds = $shift === 'todos'
                ? $this->number($header->SEGUNDASTOTAL ?? 0)
                : $this->defectQuantity($headerDefects, $shift);

            if ($shift !== 'todos' && $pieces <= 0 && $seconds <= 0) {
                continue;
            }

            $weight = $this->number($header->PESO ?? 0);
            $metrics[$telar] ??= $this->emptyMetrics();
            $metrics[$telar]['captureCount']++;
            $metrics[$telar]['pieces'] += $pieces;
            $metrics[$telar]['seconds'] += $seconds;
            $metrics[$telar]['kilos'] += ($pieces * $weight) / 1000;

            $order = trim((string) ($header->PRODID ?? ''));
            if ($order !== '') {
                $metrics[$telar]['orders'][$order] = true;
            }

            $operator = trim((string) ($header->NAMEEMPLE ?? ''));
            if ($operator !== '') {
                $metrics[$telar]['operators'][$operator] = true;
            }

            foreach ($headerDefects as $defect) {
                if (! $this->defectMatchesShift($defect, $shift)) {
                    continue;
                }

                $code = trim((string) ($defect->CODDEFECTOID ?? '')) ?: '—';
                $description = trim((string) ($defect->DESCRIP ?? '')) ?: 'Sin descripción';
                $defectKey = $code.'|'.$description;
                $metrics[$telar]['defects'][$defectKey] ??= [
                    'code' => $code,
                    'description' => $description,
                    'quantity' => 0.0,
                ];
                $metrics[$telar]['defects'][$defectKey]['quantity'] += $this->number($defect->CANTIDAD ?? 0);
            }

            $updatedAt = $this->modifiedAt($header);
            if ($updatedAt !== null && ($metrics[$telar]['lastUpdatedAt'] === null || $updatedAt > $metrics[$telar]['lastUpdatedAt'])) {
                $metrics[$telar]['lastUpdatedAt'] = $updatedAt;
            }

            $metrics[$telar]['captures'][] = [
                'recId' => trim((string) $header->RECID),
                'order' => $order ?: 'Sin orden',
                'operator' => $operator ?: 'Sin operador',
                'weight' => round($weight, 2),
                'piecesT1' => round($this->number($header->PIEZAST1 ?? 0)),
                'piecesT2' => round($this->number($header->PIEZAST2 ?? 0)),
                'piecesT3' => round($this->number($header->PIEZAST3 ?? 0)),
                'piecesT4' => round($this->number($header->PIEZAST4 ?? 0)),
                'pieces' => round($pieces),
                'seconds' => round($seconds),
                'observations' => trim((string) ($header->OBSERVACIONES ?? '')),
            ];
        }

        return $metrics;
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
            'defects' => [],
            'captures' => [],
            'lastUpdatedAt' => null,
        ];
    }

    /**
     * @param  list<array<string, int|string|null>>  $catalog
     * @param  array<string, array<string, mixed>>  $metricsByTelar
     * @return list<CrudoMachineMetrics>
     */
    private function buildMachines(
        array $catalog,
        array $metricsByTelar,
        DateTimeImmutable $date,
        string $shift,
        DateTimeImmutable $now,
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
            $expectedKilos = $this->statusResolver->expectedKilos($salon, $date, $shift, $now);
            $state = $this->statusResolver->resolve(
                captureCount: (int) $raw['captureCount'],
                pieces: $pieces,
                secondsPercent: $secondsPercent,
                kilos: (float) $raw['kilos'],
                expectedKilos: $expectedKilos,
            );

            $defects = array_values($raw['defects']);
            usort(
                $defects,
                static fn (array $left, array $right): int => $right['quantity'] <=> $left['quantity'],
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
                state: $state,
                orders: array_keys($raw['orders']),
                operators: array_keys($raw['operators']),
                defects: $defects,
                captures: $raw['captures'],
                lastUpdatedAt: $raw['lastUpdatedAt'],
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
     * @param  list<CrudoMachineMetrics>  $machines
     * @return array<string, int|float>
     */
    private function buildSummary(array $machines): array
    {
        $summary = [
            'bad_quality' => 0,
            'low_kilos' => 0,
            'operating' => 0,
            'no_data' => 0,
            'total' => count($machines),
            'pieces' => 0.0,
            'seconds' => 0.0,
            'kilos' => 0.0,
            'qualityPercent' => 0.0,
        ];

        foreach ($machines as $machine) {
            $summary[$machine->state->value]++;
            $summary['pieces'] += $machine->pieces;
            $summary['seconds'] += $machine->seconds;
            $summary['kilos'] += $machine->kilos;
        }

        $summary['qualityPercent'] = $summary['pieces'] > 0
            ? max(0, 100 - (($summary['seconds'] / $summary['pieces']) * 100))
            : 0.0;

        $summary['pieces'] = round($summary['pieces']);
        $summary['seconds'] = round($summary['seconds']);
        $summary['kilos'] = round($summary['kilos'], 1);
        $summary['qualityPercent'] = round($summary['qualityPercent'], 1);

        return $summary;
    }

    /**
     * @param  list<CrudoMachineMetrics>  $machines
     * @return list<array<string, int|string>>
     */
    private function buildAreas(array $machines): array
    {
        $areas = [];

        foreach ($machines as $machine) {
            $areas[$machine->salon] ??= [
                'name' => $machine->salon,
                'badQuality' => 0,
                'lowKilos' => 0,
                'operating' => 0,
                'noData' => 0,
                'total' => 0,
            ];

            $areas[$machine->salon]['total']++;

            match ($machine->state) {
                CrudoMachineState::BadQuality => $areas[$machine->salon]['badQuality']++,
                CrudoMachineState::LowKilos => $areas[$machine->salon]['lowKilos']++,
                CrudoMachineState::Operating => $areas[$machine->salon]['operating']++,
                CrudoMachineState::NoData => $areas[$machine->salon]['noData']++,
            };
        }

        return array_values($areas);
    }

    private function headerPieces(object $header, string $shift): float
    {
        if ($shift === 'todos') {
            return $this->number($header->PIEZASTOTAL ?? 0);
        }

        $field = 'PIEZAST'.$shift;

        return $this->number($header->{$field} ?? 0);
    }

    /**
     * @param  list<object>  $defects
     */
    private function defectQuantity(array $defects, string $shift): float
    {
        $total = 0.0;

        foreach ($defects as $defect) {
            if ($this->defectMatchesShift($defect, $shift)) {
                $total += $this->number($defect->CANTIDAD ?? 0);
            }
        }

        return $total;
    }

    private function defectMatchesShift(object $defect, string $shift): bool
    {
        if ($shift === 'todos') {
            return true;
        }

        return preg_replace('/\D+/', '', (string) ($defect->TURNO ?? '')) === $shift;
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

    private function normalizeShift(string $shift): string
    {
        return in_array($shift, ['todos', '1', '2', '3', '4'], true) ? $shift : 'todos';
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
