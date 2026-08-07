<?php

declare(strict_types=1);

namespace App\Services\Crudo;

use App\Enums\Crudo\CrudoMachineState;

final class CrudoStatusResolver
{
    /**
     * Estado que pinta el color de la tarjeta: el más grave de los activos.
     */
    public function resolve(
        int $captureCount,
        float $pieces,
        float $secondsPercent,
        float $kilos,
        float $expectedKilos,
        bool $hasActiveParo = false,
    ): CrudoMachineState {
        return $this->resolveAll($captureCount, $pieces, $secondsPercent, $kilos, $expectedKilos, $hasActiveParo)[0];
    }

    /**
     * Todas las condiciones activas del telar, de más grave a menos.
     *
     * Un telar puede ir mal por varios motivos a la vez (mala calidad y además
     * bajo en kilos); el color solo alcanza para uno, así que el detalle los
     * muestra todos. Siempre devuelve al menos un estado.
     *
     * @return non-empty-list<CrudoMachineState>
     */
    public function resolveAll(
        int $captureCount,
        float $pieces,
        float $secondsPercent,
        float $kilos,
        float $expectedKilos,
        bool $hasActiveParo = false,
    ): array {
        $states = [];

        if ($hasActiveParo) {
            $states[] = CrudoMachineState::Paro;
        }

        if ($captureCount === 0 || $pieces <= 0) {
            // Sin capturas no se puede juzgar calidad ni kilos.
            return $states === [] ? [CrudoMachineState::NoData] : $states;
        }

        if ($secondsPercent >= (float) config('crudo.bad_quality_percent', 7)) {
            $states[] = CrudoMachineState::BadQuality;
        }

        if ($expectedKilos > 0 && $kilos < $expectedKilos) {
            $states[] = CrudoMachineState::LowKilos;
        }

        return $states === [] ? [CrudoMachineState::Operating] : $states;
    }
}
