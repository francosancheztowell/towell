<?php

declare(strict_types=1);

namespace App\Services\Crudo;

use App\Enums\Crudo\CrudoMachineState;
use DateTimeImmutable;

final class CrudoStatusResolver
{
    public function resolve(
        int $captureCount,
        float $pieces,
        float $secondsPercent,
        float $kilos,
        float $expectedKilos,
    ): CrudoMachineState {
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

    public function expectedKilos(
        string $salon,
        DateTimeImmutable $date,
        string $shift,
        DateTimeImmutable $now,
    ): float {
        /** @var array<string, int|float> $targets */
        $targets = config('crudo.daily_kg_target', []);
        $dailyTarget = (float) ($targets[$salon] ?? $targets['Sin clasificar'] ?? 0);

        if ($dailyTarget <= 0 || $date > $now->setTime(23, 59, 59)) {
            return 0.0;
        }

        if ($shift !== 'todos') {
            return $dailyTarget / max(1, (int) config('crudo.turns_per_day', 4));
        }

        if ($date->format('Y-m-d') < $now->format('Y-m-d')) {
            return $dailyTarget;
        }

        $secondsElapsed = ((int) $now->format('H') * 3600)
            + ((int) $now->format('i') * 60)
            + (int) $now->format('s');

        return $dailyTarget * min(1, max(0, $secondsElapsed / 86400));
    }
}
