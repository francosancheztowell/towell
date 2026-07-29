<?php

declare(strict_types=1);

namespace App\Services\Crudo;

final class CrudoFloorLayout
{
    /**
     * @param  list<array<string, mixed>>  $machines
     * @return array<string, array{
     *     count: int,
     *     physical: bool,
     *     columns: list<list<array<string, mixed>>>
     * }>
     */
    public function arrange(array $machines): array
    {
        $machinesBySalon = [];

        foreach ($machines as $machine) {
            $salon = trim((string) ($machine['salon'] ?? '')) ?: 'Sin clasificar';
            $machinesBySalon[$salon] ??= [];
            $machinesBySalon[$salon][] = $machine;
        }

        $result = [];

        foreach ($machinesBySalon as $salon => $salonMachines) {
            $configuredColumns = $this->configuredColumns($salon);

            if ($configuredColumns === []) {
                $result[$salon] = [
                    'count' => count($salonMachines),
                    'physical' => false,
                    'columns' => [array_values($salonMachines)],
                ];

                continue;
            }

            $result[$salon] = [
                'count' => count($salonMachines),
                'physical' => true,
                'columns' => $this->arrangePhysicalColumns($salonMachines, $configuredColumns),
            ];
        }

        return $result;
    }

    /**
     * @return list<list<string>>
     */
    private function configuredColumns(string $salon): array
    {
        $columns = config("crudo.floor_layout.{$salon}", []);

        if (! is_array($columns)) {
            return [];
        }

        return array_values(array_map(
            static fn (array $column): array => array_values(array_map(
                static fn (mixed $telar): string => trim((string) $telar),
                $column,
            )),
            array_filter($columns, 'is_array'),
        ));
    }

    /**
     * @param  list<array<string, mixed>>  $machines
     * @param  list<list<string>>  $configuredColumns
     * @return list<list<array<string, mixed>>>
     */
    private function arrangePhysicalColumns(array $machines, array $configuredColumns): array
    {
        $machinesByTelar = [];

        foreach ($machines as $machine) {
            $machinesByTelar[trim((string) ($machine['telar'] ?? ''))] = $machine;
        }

        $columns = [];
        $placed = [];

        foreach ($configuredColumns as $configuredColumn) {
            $column = [];

            foreach ($configuredColumn as $telar) {
                if (! isset($machinesByTelar[$telar])) {
                    continue;
                }

                $column[] = $machinesByTelar[$telar];
                $placed[$telar] = true;
            }

            $columns[] = $column;
        }

        foreach ($machines as $machine) {
            $telar = trim((string) ($machine['telar'] ?? ''));
            if (isset($placed[$telar])) {
                continue;
            }

            $columns[$this->shortestColumnIndex($columns)][] = $machine;
        }

        return $columns;
    }

    /**
     * @param  list<list<array<string, mixed>>>  $columns
     */
    private function shortestColumnIndex(array $columns): int
    {
        $shortestIndex = 0;
        $shortestCount = PHP_INT_MAX;

        foreach ($columns as $index => $column) {
            if (count($column) < $shortestCount) {
                $shortestIndex = $index;
                $shortestCount = count($column);
            }
        }

        return $shortestIndex;
    }
}
