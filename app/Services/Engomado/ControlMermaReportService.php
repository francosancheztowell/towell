<?php

namespace App\Services\Engomado;

use App\Models\Engomado\EngProduccionEngomado;
use App\Models\Engomado\EngProgramaEngomado;
use App\Models\Urdido\UrdProduccionUrdido;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ControlMermaReportService
{
    private ?string $dateColumn = null;

    public function build(?string $fechaIni, ?string $fechaFin): Collection
    {
        if (blank($fechaIni) || blank($fechaFin)) {
            return collect();
        }

        $dateColumn = $this->resolveDateColumn();

        $programas = EngProgramaEngomado::query()
            ->with([
                'programaUrdido' => function ($query) {
                    $query->select(['Id', 'Folio', 'Cuenta', 'Calibre', 'MaquinaId']);
                },
            ])
            ->where('Status', 'Finalizado')
            ->whereNotNull($dateColumn)
            ->whereBetween($dateColumn, [$fechaIni, $fechaFin])
            ->get();

        [$urdProductionByFolio, $engProductionByFolio] = $this->loadProductionGroupedByFolio($programas);

        return $this->mapProgramas($programas, $dateColumn, $urdProductionByFolio, $engProductionByFolio);
    }

    public function mapProgramas(
        Collection $programas,
        ?string $dateColumn = null,
        ?Collection $urdProductionByFolio = null,
        ?Collection $engProductionByFolio = null
    ): Collection {
        $dateColumn ??= $this->resolveDateColumn();

        if ($urdProductionByFolio === null || $engProductionByFolio === null) {
            [$urdProductionByFolio, $engProductionByFolio] = $this->loadProductionGroupedByFolio($programas);
        }

        $sorted = $programas
            ->sort(fn (EngProgramaEngomado $left, EngProgramaEngomado $right) => $this->compareProgramas($left, $right, $dateColumn))
            ->values();

        $machineCounters = [];

        return $sorted->map(function (EngProgramaEngomado $programa) use (&$machineCounters, $dateColumn, $urdProductionByFolio, $engProductionByFolio) {
            $programaUrdido = $programa->programaUrdido;
            $folio = $this->normalizeText($programa->Folio);
            $maquinaLabel = $this->extractEngomadoWP($programa->MaquinaEng);
            $maquinaUrdidoLabel = $this->extractUrdidoMachineLabel(
                $programaUrdido?->MaquinaId,
                $programa->MaquinaUrd ?? null
            );

            $machineCounters[$maquinaLabel] = ($machineCounters[$maquinaLabel] ?? 0) + 1;
            $maquinaSeq = $machineCounters[$maquinaLabel];

            $maquinaFullLabel = $maquinaUrdidoLabel !== 'OTRO'
                ? sprintf('%s / %s', $maquinaLabel, $maquinaUrdidoLabel)
                : $maquinaLabel;

            return [
                'fecha' => $this->normalizeDate($programa->getAttribute($dateColumn)),
                'maquina_label' => $maquinaLabel,
                'maquina_urdido_label' => $maquinaUrdidoLabel,
                'maquina_full_label' => $maquinaFullLabel,
                'maquina_seq' => $maquinaSeq,
                'maquina_display' => sprintf('%s  %d', $maquinaFullLabel, $maquinaSeq),
                'folio' => $folio,
                'cuenta' => $this->firstFilledValue($programa->Cuenta, $programaUrdido?->Cuenta),
                'hilo' => $this->firstFilledValue($programa->Calibre, $programaUrdido?->Calibre),
                'merma_sin_goma' => $this->normalizeNumeric($programa->Merma),
                'merma_con_goma' => $this->normalizeNumeric($programa->MermaGoma),
                'urd_slots' => $this->buildOfficialSlots($urdProductionByFolio->get($folio, collect()), 'urd'),
                'eng_slots' => $this->buildOfficialSlots($engProductionByFolio->get($folio, collect()), 'eng'),
            ];
        });
    }

    private function loadProductionGroupedByFolio(Collection $programas): array
    {
        $folios = $programas
            ->pluck('Folio')
            ->map(fn (mixed $folio) => $this->normalizeText($folio))
            ->filter(fn (string $folio) => $folio !== '')
            ->unique()
            ->values();

        if ($folios->isEmpty()) {
            return [collect(), collect()];
        }

        $urdProduction = UrdProduccionUrdido::query()
            ->select([
                'Id',
                'Folio',
                'NoJulio',
                'CveEmpl1',
                'NomEmpl1',
                'Metros1',
                'CveEmpl2',
                'NomEmpl2',
                'Metros2',
                'CveEmpl3',
                'NomEmpl3',
                'Metros3',
            ])
            ->whereIn('Folio', $folios)
            ->orderBy('Id')
            ->get()
            ->groupBy(fn (UrdProduccionUrdido $registro) => $this->normalizeText($registro->Folio));

        $engProduction = EngProduccionEngomado::query()
            ->select([
                'Id',
                'Folio',
                'NoJulio',
                'CveEmpl1',
                'NomEmpl1',
                'Metros1',
                'CveEmpl2',
                'NomEmpl2',
                'Metros2',
                'CveEmpl3',
                'NomEmpl3',
                'Metros3',
            ])
            ->whereIn('Folio', $folios)
            ->orderBy('Id')
            ->get()
            ->groupBy(fn (EngProduccionEngomado $registro) => $this->normalizeText($registro->Folio));

        return [$urdProduction, $engProduction];
    }

    private function compareProgramas(EngProgramaEngomado $left, EngProgramaEngomado $right, string $dateColumn): int
    {
        $leftDate = $this->normalizeDate($left->getAttribute($dateColumn))?->format('Y-m-d') ?? '';
        $rightDate = $this->normalizeDate($right->getAttribute($dateColumn))?->format('Y-m-d') ?? '';

        if ($leftDate !== $rightDate) {
            return $leftDate <=> $rightDate;
        }

        $leftMachine = $this->machineSortOrder($left->MaquinaEng);
        $rightMachine = $this->machineSortOrder($right->MaquinaEng);

        if ($leftMachine !== $rightMachine) {
            return $leftMachine <=> $rightMachine;
        }

        return strcmp((string) ($left->Folio ?? ''), (string) ($right->Folio ?? ''));
    }

    private function buildOfficialSlots(Collection $records, string $source): array
    {
        $buckets = [];
        $sequence = 0;
        $seenItemsByLabel = [];

        foreach ($records as $record) {
            $label = $this->extractResponsibleOfficial($record);
            if ($label === null) {
                continue;
            }

            $itemKey = $this->resolveProductionItemKey($record, $source);
            if (isset($seenItemsByLabel[$label][$itemKey])) {
                continue;
            }

            $seenItemsByLabel[$label][$itemKey] = true;

            if (!isset($buckets[$label])) {
                $buckets[$label] = [
                    'label' => $label,
                    'count' => 0,
                    'sequence' => $sequence++,
                ];
            }

            $buckets[$label]['count']++;
        }

        $slots = array_values($buckets);
        usort($slots, function (array $left, array $right): int {
            $countComparison = ($right['count'] ?? 0) <=> ($left['count'] ?? 0);
            if ($countComparison !== 0) {
                return $countComparison;
            }

            $sequenceComparison = ($left['sequence'] ?? 0) <=> ($right['sequence'] ?? 0);
            if ($sequenceComparison !== 0) {
                return $sequenceComparison;
            }

            return strcmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
        });

        if (count($slots) > 3) {
            $overflowTotal = collect(array_slice($slots, 2))
                ->sum(fn (array $slot) => (int) ($slot['count'] ?? 0));

            $slots = [
                $slots[0],
                $slots[1],
                [
                    'label' => 'OTROS',
                    'count' => $overflowTotal,
                ],
            ];
        }

        while (count($slots) < 3) {
            $slots[] = [
                'label' => null,
                'count' => null,
            ];
        }

        return array_map(fn (array $slot) => [
            'label' => $slot['label'] ?? null,
            'count' => $slot['count'] ?? null,
        ], $slots);
    }

    private function extractResponsibleOfficial(object $record): ?string
    {
        $candidates = [];

        foreach ([1, 2, 3] as $slot) {
            $label = $this->normalizeOperatorLabel(
                $record->{"NomEmpl{$slot}"} ?? null,
                $record->{"CveEmpl{$slot}"} ?? null
            );

            if ($label === null) {
                continue;
            }

            $candidates[] = [
                'slot' => $slot,
                'label' => $label,
                'meters' => $this->normalizeNumeric($record->{"Metros{$slot}"} ?? null) ?? 0.0,
            ];
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, function (array $left, array $right): int {
            $metersComparison = ($right['meters'] ?? 0.0) <=> ($left['meters'] ?? 0.0);
            if ($metersComparison !== 0) {
                return $metersComparison;
            }

            return ($left['slot'] ?? 0) <=> ($right['slot'] ?? 0);
        });

        return $candidates[0]['label'] ?? null;
    }

    private function resolveProductionItemKey(object $record, string $source): string
    {
        if ($source === 'urd') {
            $noJulio = $this->normalizeText($record->NoJulio ?? null);
            if ($noJulio !== '') {
                return 'julio:' . $noJulio;
            }
        }

        $id = $this->normalizeText($record->Id ?? null);
        if ($id !== '') {
            return 'row:' . $id;
        }

        return 'row:' . spl_object_id($record);
    }

    private function normalizeOperatorLabel(mixed $name, mixed $code): ?string
    {
        $normalizedName = $this->normalizeText($name);
        if ($normalizedName !== '') {
            return $normalizedName;
        }

        $normalizedCode = $this->normalizeText($code);
        if ($normalizedCode !== '') {
            return $normalizedCode;
        }

        return null;
    }

    private function extractEngomadoWP(?string $maquinaEng): string
    {
        $maquina = trim((string) $maquinaEng);

        if ($maquina === '') {
            return 'OTRO';
        }

        if (preg_match('/west\s*point\s*2|westpoint\s*2|tabla\s*1|izquierda/i', $maquina) || $maquina === '2') {
            return 'WP2';
        }

        if (preg_match('/west\s*point\s*3|westpoint\s*3|tabla\s*2|derecha/i', $maquina) || $maquina === '3') {
            return 'WP3';
        }

        return 'OTRO';
    }

    private function machineSortOrder(?string $maquinaEng): int
    {
        return match ($this->extractEngomadoWP($maquinaEng)) {
            'WP2' => 1,
            'WP3' => 2,
            default => 9,
        };
    }

    private function extractUrdidoMachineLabel(?string $maquinaId, mixed $fallback = null): string
    {
        $maquina = $this->normalizeText($this->firstFilledValue($maquinaId, $fallback));

        if ($maquina === '') {
            return 'OTRO';
        }

        if (preg_match('/karl\s*mayer/i', $maquina)) {
            return 'KARL MAYER';
        }

        if (preg_match('/mc\s*coy\s*(\d+)/i', $maquina, $matches)) {
            return 'MC' . $matches[1];
        }

        if (preg_match('/\bmc\s*(\d+)/i', $maquina, $matches)) {
            return 'MC' . $matches[1];
        }

        return 'OTRO';
    }

    private function normalizeDate(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy()->startOfDay();
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->startOfDay();
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        return Carbon::parse($text)->startOfDay();
    }

    private function firstFilledValue(mixed ...$values): mixed
    {
        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }

            if (is_string($value) && trim($value) === '') {
                continue;
            }

            return $value;
        }

        return null;
    }

    private function normalizeNumeric(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    private function normalizeText(mixed $value): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim((string) $value));

        return $normalized ?? '';
    }

    private function resolveDateColumn(): string
    {
        if ($this->dateColumn !== null) {
            return $this->dateColumn;
        }

        $model = new EngProgramaEngomado();
        $connectionName = $model->getConnectionName();
        $table = $model->getTable();

        foreach (['FechaFinaliza', 'FechaProg', 'FechaReq'] as $candidate) {
            if (Schema::connection($connectionName)->hasColumn($table, $candidate)) {
                $this->dateColumn = $candidate;

                if ($candidate !== 'FechaFinaliza') {
                    Log::warning('ControlMerma: usando columna de fecha alternativa', [
                        'table' => $table,
                        'column' => $candidate,
                    ]);
                }

                return $this->dateColumn;
            }
        }

        $this->dateColumn = 'FechaProg';

        Log::warning('ControlMerma: no se encontro columna de fecha esperada, usando fallback por defecto', [
            'table' => $table,
            'column' => $this->dateColumn,
        ]);

        return $this->dateColumn;
    }
}
