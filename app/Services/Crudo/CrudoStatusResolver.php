<?php

declare(strict_types=1);

namespace App\Services\Crudo;

use App\Enums\Crudo\CrudoMachineState;
use DateInterval;
use DateTimeImmutable;

final class CrudoStatusResolver
{
    public function resolve(
        int $captureCount,
        float $pieces,
        float $secondsPercent,
        float $kilos,
        float $expectedKilos,
        bool $hasActiveParo = false,
    ): CrudoMachineState {
        if ($hasActiveParo) {
            return CrudoMachineState::Paro;
        }

        if ($captureCount === 0 || $pieces <= 0) {
            return CrudoMachineState::NoData;
        }

        if ($secondsPercent >= (float) config('crudo.bad_quality_percent', 10)) {
            return CrudoMachineState::BadQuality;
        }

        if ($expectedKilos > 0 && $kilos < $expectedKilos) {
            return CrudoMachineState::LowKilos;
        }

        return CrudoMachineState::Operating;
    }

    /**
     * Meta esperada entre $from y $to (inclusive). Para un solo día, deja $to en null.
     * Los días futuros no cuentan; el día de hoy se prorratea por hora transcurrida
     * solo cuando el turno es "todos" (un turno específico siempre pesa el día completo,
     * ya que corresponde a un bloque de horas ya cerrado o en curso, no fraccionable por reloj).
     */
    public function expectedKilos(
        string $salon,
        DateTimeImmutable $from,
        string $shift,
        DateTimeImmutable $now,
        ?DateTimeImmutable $to = null,
    ): float {
        /** @var array<string, int|float> $targets */
        $targets = config('crudo.daily_kg_target', []);
        $dailyTarget = (float) ($targets[$salon] ?? $targets['Sin clasificar'] ?? 0);

        if ($dailyTarget <= 0) {
            return 0.0;
        }

        $today = $now->setTime(0, 0);
        $cursor = $from->setTime(0, 0);
        $lastDay = min(($to ?? $from)->setTime(0, 0), $today);

        if ($cursor > $lastDay) {
            return 0.0;
        }

        if ($shift !== 'todos') {
            $days = (int) $cursor->diff($lastDay)->days + 1;

            return ($dailyTarget / max(1, (int) config('crudo.turns_per_day', 4))) * $days;
        }

        $hoursElapsed = (int) $now->format('H')
            + ((int) $now->format('i') / 60)
            + ((int) $now->format('s') / 3600);
        $hourlyTarget = $dailyTarget / 24;

        $expectedKilos = 0.0;
        while ($cursor <= $lastDay) {
            $expectedKilos += $cursor->format('Y-m-d') === $today->format('Y-m-d')
                ? min($dailyTarget, max(0, $hourlyTarget * $hoursElapsed))
                : $dailyTarget;

            $cursor = $cursor->add(new DateInterval('P1D'));
        }

        return $expectedKilos;
    }
}
