<?php

namespace App\Services\Programas;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProgramaPrioridadService
{
    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     * @param  array<int, string>  $baseColumns
     * @return array{records: Collection<int, object>, has_priority: bool}
     */
    public function loadRecordsWithOptionalPriority(string $modelClass, array $baseColumns, callable $scope): array
    {
        $columns = array_values(array_unique($baseColumns));

        try {
            $records = $scope($modelClass::select([...$columns, 'Prioridad']))->get();

            return [
                'records' => $records,
                'has_priority' => true,
            ];
        } catch (\Throwable) {
            $records = $scope($modelClass::select($columns))->get();

            return [
                'records' => $records,
                'has_priority' => false,
            ];
        }
    }

    public function sortRecords(Collection $records, callable $fallbackResolver): Collection
    {
        return $records->sort(function ($left, $right) use ($fallbackResolver) {
            $priorityComparison = $this->normalizePriority($left->Prioridad ?? null) <=> $this->normalizePriority($right->Prioridad ?? null);
            if ($priorityComparison !== 0) {
                return $priorityComparison;
            }

            return $fallbackResolver($left) <=> $fallbackResolver($right);
        })->values();
    }

    public function displayPriority(object $record, int $position): int
    {
        $priority = $record->Prioridad ?? null;

        return $this->hasPriority($priority) ? (int) $priority : $position + 1;
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     */
    public function swapPriorities(string $modelClass, int $sourceId, int $targetId): void
    {
        DB::transaction(function () use ($modelClass, $sourceId, $targetId) {
            $source = $modelClass::findOrFail($sourceId);
            $target = $modelClass::findOrFail($targetId);

            $nextPriority = (int) ($modelClass::query()->max('Prioridad') ?? 0);

            if (! $this->hasPriority($source->Prioridad ?? null)) {
                $nextPriority++;
                $source->Prioridad = $nextPriority;
            }

            if (! $this->hasPriority($target->Prioridad ?? null)) {
                $nextPriority++;
                $target->Prioridad = $nextPriority;
            }

            $priority = $source->Prioridad;
            $source->Prioridad = $target->Prioridad;
            $target->Prioridad = $priority;

            $source->save();
            $target->save();
        });
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     * @param  array<int, array{id:int, prioridad:int}>  $priorities
     */
    public function bulkUpdatePriorities(string $modelClass, array $priorities): void
    {
        DB::transaction(function () use ($modelClass, $priorities) {
            foreach ($priorities as $item) {
                $model = $modelClass::findOrFail($item['id']);
                $model->Prioridad = $item['prioridad'];
                $model->save();
            }
        });
    }

    public function nextPriority(Builder $query): int
    {
        return (int) ($query->whereNotNull('Prioridad')->max('Prioridad') ?? 0) + 1;
    }

    public function recalculatePriorities(Builder $query, callable $fallbackResolver): void
    {
        $ordered = $this->sortRecords($query->get(), $fallbackResolver);

        DB::transaction(function () use ($ordered) {
            foreach ($ordered as $index => $record) {
                $record->Prioridad = $index + 1;
                $record->save();
            }
        });
    }

    private function hasPriority(mixed $priority): bool
    {
        return $priority !== null && $priority !== '' && (int) $priority > 0;
    }

    private function normalizePriority(mixed $priority): int
    {
        return $this->hasPriority($priority) ? (int) $priority : PHP_INT_MAX;
    }
}
