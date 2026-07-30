<?php

declare(strict_types=1);

namespace App\DTOs\Crudo;

use App\Enums\Crudo\CrudoMachineState;

final readonly class CrudoMachineMetrics
{
    /**
     * @param  list<string>  $orders
     * @param  list<string>  $operators
     * @param  list<array<string, int|float|string>>  $defects
     * @param  list<array<string, int|float|string|null>>  $captures
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
        public CrudoMachineState $state,
        public array $orders,
        public array $operators,
        public array $defects,
        public array $captures,
        public ?string $lastUpdatedAt,
        public ?array $paro = null,
        public ?array $programa = null,
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
            'expectedKilos' => round($this->expectedKilos, 1),
            'state' => $this->state->value,
            'stateLabel' => $this->state->label(),
            'stateIcon' => $this->state->icon(),
            'orders' => $this->orders,
            'operators' => $this->operators,
            'defects' => $this->defects,
            'captures' => $this->captures,
            'lastUpdatedAt' => $this->lastUpdatedAt,
            'paro' => $this->paro,
            'programa' => $this->programa,
        ];
    }
}
