<?php

declare(strict_types=1);

namespace App\DTOs\Crudo;

use App\Enums\Crudo\CrudoMachineState;
use App\Services\Crudo\CrudoProductionTargetService;

final readonly class CrudoMachineMetrics
{
    /**
     * @param  array<string, string|null>|null  $paro
     * @param  array<string, string|null>|null  $programa
     */
    public function __construct(
        public string $telar,
        public string $name,
        public string $salon,
        public string $group,
        public int $sequence,
        public int $captureCount,
        public float $pieces,
        public float $seconds,
        public float $kilos,
        public float $qualityPercent,
        public float $secondsPercent,
        public float $expectedKilos,
        public float $dailyTargetKilos,
        public string $productionStandardStatus,
        public CrudoMachineState $state,
        public ?array $paro = null,
        public ?array $programa = null,
        // Último corte de tejido con dato: % y observación de esa revisión.
        public float $efficiencyPercent = 0.0,
        public string $efficiencyObs = '',
        // Fecha del corte usado y si viene de un día anterior al consultado.
        public string $efficiencyDate = '',
        public bool $efficiencyStale = false,
        public ?float $rpm = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'telar' => $this->telar,
            'name' => $this->name,
            'salon' => $this->salon,
            'group' => $this->group,
            'sequence' => $this->sequence,
            'captureCount' => $this->captureCount,
            'pieces' => round($this->pieces),
            'seconds' => round($this->seconds),
            'kilos' => round($this->kilos, 1),
            'qualityPercent' => round($this->qualityPercent, 1),
            'secondsPercent' => round($this->secondsPercent, 1),
            'efficiencyPercent' => round($this->efficiencyPercent, 1),
            'efficiencyObs' => $this->efficiencyObs,
            'efficiencyDate' => $this->efficiencyDate,
            'efficiencyStale' => $this->efficiencyStale,
            'rpm' => $this->rpm !== null ? (int) round($this->rpm) : null,
            'expectedKilos' => round($this->expectedKilos, 1),
            'dailyTargetKilos' => round($this->dailyTargetKilos, 1),
            'productionStandardStatus' => $this->productionStandardStatus,
            'hasProductionStandard' => $this->productionStandardStatus === CrudoProductionTargetService::COMPLETE,
            'state' => $this->state->value,
            'stateLabel' => $this->state->label(),
            'stateIcon' => $this->state->icon(),
            'paro' => $this->paro,
            'programa' => $this->programa,
        ];
    }
}
