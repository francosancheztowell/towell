<?php

declare(strict_types=1);

namespace App\Services\Crudo;

use DateInterval;
use DateTimeImmutable;

final readonly class CrudoProductionTargetService
{
    public const COMPLETE = 'complete';

    public const MISSING = 'missing';

    /**
     * La meta siempre pertenece al programa actualmente EnProceso del telar.
     * La fecha consultada solo cambia el tiempo contra el que se prorratea ese
     * ProdKgDia; no cambia la fuente del estándar.
     *
     * @param  list<object>  $activePrograms
     * @return array<string, array{expectedKilos: float, dailyKilos: float, standardStatus: string}>
     */
    public function expectedByTelar(
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        string $shift,
        DateTimeImmutable $now,
        array $activePrograms = [],
    ): array {
        $dailyTargets = $this->activeTargetsByTelar($activePrograms);
        if ($dailyTargets === []) {
            return [];
        }

        $eligibleSeconds = $this->eligibleSeconds($from, $to, $shift, $now);
        $targets = [];

        foreach ($dailyTargets as $telar => $dailyTarget) {
            $targets[$telar] = [
                'expectedKilos' => $dailyTarget * ($eligibleSeconds / 86400),
                // En rango es también el promedio diario: se aplica el mismo
                // estándar EnProceso a cada día elegible del periodo.
                'dailyKilos' => $dailyTarget,
                'standardStatus' => self::COMPLETE,
            ];
        }

        return $targets;
    }

    /**
     * activePrograms ya llega ordenado por FechaInicio/Id descendente. Si por
     * inconsistencia hay más de un EnProceso por telar, se conserva el primer
     * ProdKgDia positivo.
     *
     * @param  list<object>  $activePrograms
     * @return array<string, float>
     */
    private function activeTargetsByTelar(array $activePrograms): array
    {
        $targets = [];

        foreach ($activePrograms as $program) {
            $telar = trim((string) ($program->NoTelarId ?? ''));
            if ($telar === '' || array_key_exists($telar, $targets)) {
                continue;
            }

            $target = $this->positiveNumber($program->ProdKgDia ?? null);
            if ($target !== null) {
                $targets[$telar] = $target;
            }
        }

        return $targets;
    }

    private function eligibleSeconds(
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        string $shift,
        DateTimeImmutable $now,
    ): int {
        $day = $from->setTime(0, 0);
        $lastDay = $to->setTime(0, 0);
        if ($lastDay < $day) {
            [$day, $lastDay] = [$lastDay, $day];
        }

        $seconds = 0;
        while ($day <= $lastDay) {
            [$windowStart, $windowEnd] = $this->windowForDay($day, $shift, $now);
            $seconds += max(0, $windowEnd->getTimestamp() - $windowStart->getTimestamp());
            $day = $day->add(new DateInterval('P1D'));
        }

        return $seconds;
    }

    /**
     * @return array{DateTimeImmutable, DateTimeImmutable}
     */
    private function windowForDay(
        DateTimeImmutable $dayStart,
        string $shift,
        DateTimeImmutable $now,
    ): array {
        $dayEnd = $dayStart->add(new DateInterval('P1D'));

        if (in_array($shift, ['1', '2', '3', '4'], true)) {
            $turnsPerDay = max(1, (int) config('crudo.turns_per_day', 4));
            $secondsPerTurn = (int) floor(86400 / $turnsPerDay);
            $offset = ((int) $shift - 1) * $secondsPerTurn;
            $windowStart = $dayStart->modify('+'.$offset.' seconds');
            $windowEnd = min($windowStart->modify('+'.$secondsPerTurn.' seconds'), $dayEnd);
        } else {
            $windowStart = $dayStart;
            $windowEnd = $dayEnd;
        }

        $today = $now->setTime(0, 0);
        if ($dayStart == $today) {
            $windowEnd = min($windowEnd, $now);
        } elseif ($dayStart > $today) {
            $windowEnd = $windowStart;
        }

        return [$windowStart, max($windowStart, $windowEnd)];
    }

    private function positiveNumber(mixed $value): ?float
    {
        return is_numeric($value) && (float) $value > 0
            ? (float) $value
            : null;
    }
}
