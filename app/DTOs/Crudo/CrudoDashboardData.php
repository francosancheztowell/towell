<?php

declare(strict_types=1);

namespace App\DTOs\Crudo;

final readonly class CrudoDashboardData
{
    /**
     * @param  list<CrudoMachineMetrics>  $machines
     * @param  array<string, int|float>  $summary
     * @param  list<array<string, int|string>>  $areas
     */
    public function __construct(
        public string $date,
        public string $shift,
        public array $machines,
        public array $summary,
        public array $areas,
        public string $generatedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'shift' => $this->shift,
            'machines' => array_map(
                static fn (CrudoMachineMetrics $machine): array => $machine->toArray(),
                $this->machines,
            ),
            'summary' => $this->summary,
            'areas' => $this->areas,
            'generatedAt' => $this->generatedAt,
            'cacheState' => 'fresh',
            'sourceError' => null,
        ];
    }
}
