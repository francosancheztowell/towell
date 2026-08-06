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
        DateTimeImmutable $now,
        array $activePrograms = [],
    ): array {
        $dailyTargets = $this->activeTargetsByTelar($activePrograms);
        if ($dailyTargets === []) {
            return [];
        }

        $eligibleSeconds = $this->eligibleSeconds($from, $to, $now);
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

    /**
     * Segundos ya transcurridos del periodo. Cada día de producción dura 24 h
     * completas (06:30 a 06:30); solo el día en curso se prorratea.
     */
    private function eligibleSeconds(
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        DateTimeImmutable $now,
    ): int {
        $day = $from->setTime(0, 0);
        $lastDay = $to->setTime(0, 0);
        if ($lastDay < $day) {
            [$day, $lastDay] = [$lastDay, $day];
        }

        // El día de producción corre de 06:30 a 06:30, así que lo transcurrido
        // del día en curso se mide desde ese arranque, no desde medianoche. Con
        // el corte anterior la meta se inflaba 6.5 h y marcaba "bajos kg" de más.
        $startMinutes = (int) config('crudo.production_day_start_minutes', 390);
        $currentDay = $now->modify('-'.$startMinutes.' minutes')->setTime(0, 0);

        $seconds = 0;
        while ($day <= $lastDay) {
            if ($day < $currentDay) {
                $seconds += 86400;
            } elseif ($day == $currentDay) {
                $elapsed = $now->getTimestamp()
                    - $day->modify('+'.$startMinutes.' minutes')->getTimestamp();
                $seconds += min(86400, max(0, $elapsed));
            }
            // Un día de producción que aún no arranca no aporta meta.

            $day = $day->add(new DateInterval('P1D'));
        }

        return $seconds;
    }

    private function positiveNumber(mixed $value): ?float
    {
        return is_numeric($value) && (float) $value > 0
            ? (float) $value
            : null;
    }
}
