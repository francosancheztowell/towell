<?php

namespace App\Services\Engomado;

use App\Models\Engomado\EngProgramaEngomado;
use App\Models\Urdido\UrdJuliosOrden;
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
                    $query->select(['Id', 'Folio', 'Cuenta', 'Calibre']);
                },
                'programaUrdido.julios' => function ($query) {
                    $query->select(['Id', 'Folio', 'Julios', 'Obs'])
                        ->orderBy('Id');
                },
            ])
            ->where('Status', 'Finalizado')
            ->whereNotNull($dateColumn)
            ->whereBetween($dateColumn, [$fechaIni, $fechaFin])
            ->get();

        return $this->mapProgramas($programas, $dateColumn);
    }

    public function mapProgramas(Collection $programas, ?string $dateColumn = null): Collection
    {
        $dateColumn ??= $this->resolveDateColumn();

        $sorted = $programas
            ->sort(fn (EngProgramaEngomado $left, EngProgramaEngomado $right) => $this->compareProgramas($left, $right, $dateColumn))
            ->values();

        $machineCounters = [];

        return $sorted->map(function (EngProgramaEngomado $programa) use (&$machineCounters, $dateColumn) {
            $maquinaLabel = $this->extractEngomadoWP($programa->MaquinaEng);
            $machineCounters[$maquinaLabel] = ($machineCounters[$maquinaLabel] ?? 0) + 1;
            $maquinaSeq = $machineCounters[$maquinaLabel];

            $programaUrdido = $programa->programaUrdido;
            $julios = $programaUrdido?->julios instanceof Collection
                ? $programaUrdido->julios
                : collect();

            return [
                'fecha' => $this->normalizeDate($programa->getAttribute($dateColumn)),
                'maquina_label' => $maquinaLabel,
                'maquina_seq' => $maquinaSeq,
                'maquina_display' => $maquinaSeq === 1
                    ? sprintf('%s  %d', $maquinaLabel, $maquinaSeq)
                    : (string) $maquinaSeq,
                'folio' => trim((string) ($programa->Folio ?? '')),
                'cuenta' => $this->firstFilledValue($programa->Cuenta, $programaUrdido?->Cuenta),
                'hilo' => $this->firstFilledValue($programa->Calibre, $programaUrdido?->Calibre),
                'merma_sin_goma' => $this->normalizeNumeric($programa->Merma),
                'merma_con_goma' => $this->normalizeNumeric($programa->MermaGoma),
                'urd_slots' => $this->buildUrdSlots($julios),
                'eng_slots' => $this->buildEmptySlots(),
            ];
        });
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

    private function buildUrdSlots(Collection $julios): array
    {
        $grouped = [];
        $groupOrder = [];

        foreach ($julios as $julio) {
            if (!$julio instanceof UrdJuliosOrden) {
                continue;
            }

            $label = $this->normalizeObsLabel($julio->Obs ?? null);

            if (!array_key_exists($label, $grouped)) {
                $grouped[$label] = 0;
                $groupOrder[] = $label;
            }

            $grouped[$label] += (int) ($julio->Julios ?? 0);
        }

        $slots = [];
        foreach ($groupOrder as $label) {
            $slots[] = [
                'label' => $label,
                'count' => $grouped[$label],
            ];
        }

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

        return $slots;
    }

    private function buildEmptySlots(): array
    {
        return [
            ['label' => null, 'count' => null],
            ['label' => null, 'count' => null],
            ['label' => null, 'count' => null],
        ];
    }

    private function normalizeObsLabel(?string $value): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim((string) $value));

        return $normalized !== '' ? $normalized : 'SIN OBS';
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
