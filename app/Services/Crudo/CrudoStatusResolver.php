<?php

declare(strict_types=1);

namespace App\Services\Crudo;

use App\Enums\Crudo\CrudoMachineState;

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
}
