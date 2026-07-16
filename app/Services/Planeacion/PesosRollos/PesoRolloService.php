<?php

declare(strict_types=1);

namespace App\Services\Planeacion\PesosRollos;

use App\DTO\Planeacion\PesosRollos\PesoRolloData;
use App\DTO\Planeacion\PesosRollos\PesoRolloFilters;
use App\Models\Planeacion\Catalogos\ReqPesosRollosTejido;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PesoRolloService
{
    private const SORT_COLUMNS = [
        'item_id' => 'ItemId',
        'item_name' => 'ItemName',
        'invent_size_id' => 'InventSizeId',
        'peso_rollo' => 'PesoRollo',
    ];

    /** @return LengthAwarePaginator<int, ReqPesosRollosTejido> */
    public function paginate(PesoRolloFilters $criteria): LengthAwarePaginator
    {
        $query = ReqPesosRollosTejido::query();

        if ($criteria->search !== null) {
            $search = $criteria->search;
            $query->where(function (Builder $nested) use ($search): void {
                $nested
                    ->where('ItemId', 'like', "%{$search}%")
                    ->orWhere('ItemName', 'like', "%{$search}%")
                    ->orWhere('InventSizeId', 'like', "%{$search}%");
            });
        }

        $this->applyFilters($query, $criteria->filters);

        return $query
            ->orderBy(self::SORT_COLUMNS[$criteria->sort], $criteria->direction)
            ->orderBy('Id')
            ->paginate($criteria->perPage, ['*'], 'page', $criteria->page);
    }

    public function create(PesoRolloData $data): ReqPesosRollosTejido
    {
        $model = new ReqPesosRollosTejido;

        try {
            return DB::connection($model->getConnectionName())->transaction(function () use ($data): ReqPesosRollosTejido {
                $this->assertUnique($data);
                $now = now();

                return ReqPesosRollosTejido::create([
                    ...$data->toDatabaseAttributes(),
                    'FechaCreacion' => $now->toDateString(),
                    'HoraCreacion' => $now->format('H:i:s'),
                    'UsuarioCrea' => $this->userName(),
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            $this->throwDuplicateValidation();
        }
    }

    public function update(ReqPesosRollosTejido $pesoRollo, PesoRolloData $data): ReqPesosRollosTejido
    {
        try {
            return DB::connection($pesoRollo->getConnectionName())->transaction(
                function () use ($pesoRollo, $data): ReqPesosRollosTejido {
                    $pesoRollo->newQuery()->whereKey($pesoRollo->getKey())->lockForUpdate()->firstOrFail();
                    $this->assertUnique($data, $pesoRollo->Id);
                    $now = now();

                    $pesoRollo->update([
                        ...$data->toDatabaseAttributes(),
                        'FechaModificacion' => $now->toDateString(),
                        'HoraModificacion' => $now->format('H:i:s'),
                        'UsuarioModifica' => $this->userName(),
                    ]);

                    return $pesoRollo->refresh();
                }
            );
        } catch (UniqueConstraintViolationException) {
            $this->throwDuplicateValidation();
        }
    }

    public function delete(ReqPesosRollosTejido $pesoRollo): void
    {
        DB::connection($pesoRollo->getConnectionName())->transaction(function () use ($pesoRollo): void {
            $pesoRollo->delete();
        });
    }

    /**
     * @param  Builder<ReqPesosRollosTejido>  $query
     * @param  array{item_id?: string|null, item_name?: string|null, invent_size_id?: string|null, peso_min?: numeric-string|int|float|null, peso_max?: numeric-string|int|float|null}  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        $pesoMin = $filters['peso_min'] ?? null;
        $pesoMax = $filters['peso_max'] ?? null;

        $query
            ->when($this->filterString($filters, 'item_id'), fn (Builder $q, string $value) => $q->where('ItemId', 'like', "%{$value}%"))
            ->when($this->filterString($filters, 'item_name'), fn (Builder $q, string $value) => $q->where('ItemName', 'like', "%{$value}%"))
            ->when($this->filterString($filters, 'invent_size_id'), fn (Builder $q, string $value) => $q->where('InventSizeId', 'like', "%{$value}%"))
            ->when(
                $pesoMin !== null,
                fn (Builder $q) => $q->where('PesoRollo', '>=', (float) $pesoMin)
            )
            ->when(
                $pesoMax !== null,
                fn (Builder $q) => $q->where('PesoRollo', '<=', (float) $pesoMax)
            );
    }

    private function assertUnique(PesoRolloData $data, ?int $ignoreId = null): void
    {
        $duplicate = ReqPesosRollosTejido::query()
            ->where('ItemId', $data->itemId)
            ->where('InventSizeId', $data->inventSizeId)
            ->when($ignoreId !== null, fn (Builder $query) => $query->where('Id', '!=', $ignoreId))
            ->lockForUpdate()
            ->exists();

        if ($duplicate) {
            $this->throwDuplicateValidation();
        }
    }

    private function throwDuplicateValidation(): never
    {
        throw ValidationException::withMessages([
            'item_id' => ['Ya existe un registro con el mismo codigo de articulo y tamano.'],
        ]);
    }

    /** @param array<string, mixed> $filters */
    private function filterString(array $filters, string $key): ?string
    {
        $value = $filters[$key] ?? null;
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    private function userName(): string
    {
        $user = Auth::user();
        if (! $user instanceof Model) {
            return 'Sistema';
        }

        $name = $user->getAttribute('nombre') ?? $user->getAttribute('name');

        return is_string($name) && $name !== '' ? $name : 'Sistema';
    }
}
