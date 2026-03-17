<?php

namespace App\Services\Tejido;

use App\Models\Tejido\TejEficiencia;
use App\Models\Tejido\TejEficienciaLine;
use App\Models\Tejido\TejMarcas;
use App\Models\Tejido\TejMarcasLine;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class PromedioParosEficienciaReportService
{
    public function build(string $fechaIni, string $fechaFin): array
    {
        $days = $this->buildDays($fechaIni, $fechaFin);

        $marcasHeaders = $this->selectLatestMarcasHeaders($fechaIni, $fechaFin);
        $cortesHeaders = $this->selectLatestCortesHeaders($fechaIni, $fechaFin);

        $marcasMetrics = $this->fetchMarcasMetrics($marcasHeaders);
        $cortesMetrics = $this->fetchCortesMetrics($cortesHeaders);

        return [
            'days' => $days,
            'metrics' => $this->mergeMetrics($marcasMetrics, $cortesMetrics),
        ];
    }

    private function buildDays(string $fechaIni, string $fechaFin): array
    {
        $days = [];

        foreach (CarbonPeriod::create($fechaIni, $fechaFin) as $date) {
            $carbon = Carbon::parse($date);
            $dayCode = $this->resolveDayCode($carbon);
            $dateKey = $carbon->format('Y-m-d');

            $days[] = [
                'date' => $carbon->copy(),
                'date_key' => $dateKey,
                'day_code' => $dayCode,
                'turn_labels' => [
                    1 => $dayCode . ' 1T',
                    2 => $dayCode . ' 2T',
                    3 => $dayCode . ' 3T',
                ],
            ];
        }

        return $days;
    }

    private function selectLatestMarcasHeaders(string $fechaIni, string $fechaFin): array
    {
        $headers = TejMarcas::query()
            ->where('Status', 'Finalizado')
            ->whereDate('Date', '>=', $fechaIni)
            ->whereDate('Date', '<=', $fechaFin)
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->orderByDesc('Folio')
            ->get(['Folio', 'Date', 'Turno', 'updated_at', 'created_at']);

        return $this->indexLatestHeaders($headers);
    }

    private function selectLatestCortesHeaders(string $fechaIni, string $fechaFin): array
    {
        $headers = TejEficiencia::query()
            ->whereDate('Date', '>=', $fechaIni)
            ->whereDate('Date', '<=', $fechaFin)
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->orderByDesc('Folio')
            ->get(['Folio', 'Date', 'Turno', 'updated_at', 'created_at']);

        return $this->indexLatestHeaders($headers);
    }

    private function indexLatestHeaders(iterable $headers): array
    {
        $selected = [];

        foreach ($headers as $header) {
            $dateKey = $this->normalizeDateValue($header->Date ?? null);
            $turn = $this->normalizeTurn($header->Turno ?? null);

            if ($dateKey === null || $turn === null) {
                continue;
            }

            if (!isset($selected[$dateKey][$turn])) {
                $selected[$dateKey][$turn] = $header;
            }
        }

        return $selected;
    }

    private function fetchMarcasMetrics(array $headersByDateTurn): array
    {
        $folioMeta = $this->flattenHeaderMeta($headersByDateTurn);
        if ($folioMeta === []) {
            return [];
        }

        $lines = TejMarcasLine::query()
            ->whereIn('Folio', array_keys($folioMeta))
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->orderByDesc('Folio')
            ->get([
                'Folio',
                'NoTelarId',
                'Eficiencia',
                'Trama',
                'Pie',
                'Rizo',
                'Otros',
                'Marcas',
                'updated_at',
                'created_at',
            ]);

        $metrics = [];
        foreach ($lines as $line) {
            $folio = trim((string) ($line->Folio ?? ''));
            $telar = $this->normalizeTelar($line->NoTelarId ?? null);

            if ($folio === '' || $telar === '' || !isset($folioMeta[$folio])) {
                continue;
            }

            $dateKey = $folioMeta[$folio]['date_key'];
            $turn = $folioMeta[$folio]['turn'];

            if (isset($metrics[$dateKey][$turn][$telar])) {
                continue;
            }

            $metrics[$dateKey][$turn][$telar] = [
                'eficiencia' => $this->normalizeNumericValue($line->Eficiencia ?? null),
                'paros_trama' => $this->normalizeNumericValue($line->Trama ?? null),
                'paros_urdimbre' => $this->normalizeNumericValue($line->Pie ?? null),
                'paros_rizo' => $this->normalizeNumericValue($line->Rizo ?? null),
                'paros_otros' => $this->normalizeNumericValue($line->Otros ?? null),
                'marcas' => $this->normalizeNumericValue($line->Marcas ?? null),
                'rpm' => null,
            ];
        }

        return $metrics;
    }

    private function fetchCortesMetrics(array $headersByDateTurn): array
    {
        $folioMeta = $this->flattenHeaderMeta($headersByDateTurn);
        if ($folioMeta === []) {
            return [];
        }

        $lines = TejEficienciaLine::query()
            ->whereIn('Folio', array_keys($folioMeta))
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->orderByDesc('Folio')
            ->get([
                'Folio',
                'NoTelarId',
                'RpmR1',
                'RpmR2',
                'RpmR3',
                'updated_at',
                'created_at',
            ]);

        $metrics = [];
        foreach ($lines as $line) {
            $folio = trim((string) ($line->Folio ?? ''));
            $telar = $this->normalizeTelar($line->NoTelarId ?? null);

            if ($folio === '' || $telar === '' || !isset($folioMeta[$folio])) {
                continue;
            }

            $dateKey = $folioMeta[$folio]['date_key'];
            $turn = $folioMeta[$folio]['turn'];

            if (isset($metrics[$dateKey][$turn][$telar])) {
                continue;
            }

            $metrics[$dateKey][$turn][$telar] = [
                'rpm' => $this->averageRpm($line),
            ];
        }

        return $metrics;
    }

    private function flattenHeaderMeta(array $headersByDateTurn): array
    {
        $flat = [];

        foreach ($headersByDateTurn as $dateKey => $headersByTurn) {
            foreach ($headersByTurn as $turn => $header) {
                $folio = trim((string) ($header->Folio ?? ''));
                if ($folio === '') {
                    continue;
                }

                $flat[$folio] = [
                    'date_key' => $dateKey,
                    'turn' => (int) $turn,
                ];
            }
        }

        return $flat;
    }

    private function mergeMetrics(array $marcasMetrics, array $cortesMetrics): array
    {
        $metrics = $marcasMetrics;

        foreach ($cortesMetrics as $dateKey => $turns) {
            foreach ($turns as $turn => $telares) {
                foreach ($telares as $telar => $values) {
                    $metrics[$dateKey][$turn][$telar] = array_merge(
                        $metrics[$dateKey][$turn][$telar] ?? [
                            'paros_trama' => null,
                            'paros_urdimbre' => null,
                            'paros_rizo' => null,
                            'paros_otros' => null,
                            'marcas' => null,
                            'eficiencia' => null,
                            'rpm' => null,
                        ],
                        $values
                    );
                }
            }
        }

        ksort($metrics);

        return $metrics;
    }

    /**
     * @param TejEficienciaLine|object $line
     */
    private function averageRpm(object $line): ?float
    {
        return $this->averageCapturedValues([
            $line->RpmR1 ?? null,
            $line->RpmR2 ?? null,
            $line->RpmR3 ?? null,
        ]);
    }

    private function averageCapturedValues(array $values): ?float
    {
        $capturedValues = [];

        foreach ($values as $value) {
            if ($value === null || $value === '' || !is_numeric($value)) {
                continue;
            }

            $numeric = (float) $value;
            if ($numeric == 0.0) {
                continue;
            }

            $capturedValues[] = $numeric;
        }

        if ($capturedValues === []) {
            return null;
        }

        return round(array_sum($capturedValues) / count($capturedValues), 2);
    }

    private function normalizeNumericValue(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        return (int) round((float) $value, 0);
    }

    private function normalizeDateValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value)->format('Y-m-d');
    }

    private function normalizeTurn(mixed $value): ?int
    {
        if (!is_numeric($value)) {
            return null;
        }

        $turn = (int) $value;

        return in_array($turn, [1, 2, 3], true) ? $turn : null;
    }

    private function normalizeTelar(mixed $value): string
    {
        return trim((string) $value);
    }

    private function resolveDayCode(Carbon $date): string
    {
        return match ($date->dayOfWeekIso) {
            1 => 'LU',
            2 => 'MA',
            3 => 'MI',
            4 => 'JU',
            5 => 'VI',
            6 => 'SA',
            7 => 'DO',
            default => '',
        };
    }
}
