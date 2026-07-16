<?php

declare(strict_types=1);

namespace App\Services\Planeacion\ProgramaTejido;

use App\DTO\Planeacion\ProgramaTejido\ProgramaTejidoFilters;
use App\Models\Planeacion\ReqProgramaTejido;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class ProgramaTejidoReadService
{
    private const SELECT_COLUMNS = [
        'Id',
        'EnProceso',
        'SalonTejidoId',
        'NoTelarId',
        'Posicion',
        'NoProduccion',
        'NombreProducto',
        'ItemId',
        'InventSizeId',
        'FlogsId',
        'TotalPedido',
        'Produccion',
        'SaldoPedido',
        'FechaInicio',
        'FechaFinal',
        'Prioridad',
    ];

    private const SORT_COLUMNS = [
        'en_proceso' => 'EnProceso',
        'salon' => 'SalonTejidoId',
        'posicion' => 'Posicion',
        'orden_produccion' => 'NoProduccion',
        'producto' => 'NombreProducto',
        'item_id' => 'ItemId',
        'total_pedido' => 'TotalPedido',
        'produccion' => 'Produccion',
        'saldo_pedido' => 'SaldoPedido',
        'fecha_inicio' => 'FechaInicio',
        'fecha_final' => 'FechaFinal',
        'prioridad' => 'Prioridad',
    ];

    /** @return LengthAwarePaginator<int, ReqProgramaTejido> */
    public function paginate(ProgramaTejidoFilters $criteria): LengthAwarePaginator
    {
        $query = ReqProgramaTejido::query()->select(self::SELECT_COLUMNS);

        $this->applySearch($query, $criteria->search);
        $this->applyFilters($query, $criteria);
        $this->applySort($query, $criteria->sort, $criteria->direction);

        return $query->paginate($criteria->perPage, ['*'], 'page', $criteria->page);
    }

    /** @param Builder<ReqProgramaTejido> $query */
    private function applySearch(Builder $query, ?string $search): void
    {
        if ($search === null) {
            return;
        }

        $query->where(function (Builder $nested) use ($search): void {
            $like = "%{$search}%";

            $nested
                ->where('NoProduccion', 'like', $like)
                ->orWhere('NombreProducto', 'like', $like)
                ->orWhere('ItemId', 'like', $like)
                ->orWhere('InventSizeId', 'like', $like)
                ->orWhere('FlogsId', 'like', $like);
        });
    }

    /** @param Builder<ReqProgramaTejido> $query */
    private function applyFilters(Builder $query, ProgramaTejidoFilters $criteria): void
    {
        $query
            ->when($criteria->salon !== null, fn (Builder $builder) => $builder->where('SalonTejidoId', $criteria->salon))
            ->when($criteria->telar !== null, fn (Builder $builder) => $builder->where('NoTelarId', $criteria->telar))
            ->when($criteria->enProceso !== null, fn (Builder $builder) => $builder->where('EnProceso', $criteria->enProceso ? 1 : 0));
    }

    /** @param Builder<ReqProgramaTejido> $query */
    private function applySort(Builder $query, string $sort, string $direction): void
    {
        if ($sort === 'telar') {
            $this->applyTelarSort($query, $direction);
            $query->orderBy('Posicion');
        } else {
            $query->orderBy(self::SORT_COLUMNS[$sort], $direction);
        }

        $query->orderBy('Id');
    }

    /** @param Builder<ReqProgramaTejido> $query */
    private function applyTelarSort(Builder $query, string $direction): void
    {
        $connectionName = $query->getModel()->getConnectionName() ?? DB::getDefaultConnection();
        $driver = config("database.connections.{$connectionName}.driver");

        if ($driver !== 'sqlsrv') {
            $query->orderBy('NoTelarId', $direction);

            return;
        }

        $query
            ->orderByRaw(
                "CASE WHEN LTRIM(RTRIM(NoTelarId)) <> '' "
                ."AND LTRIM(RTRIM(NoTelarId)) NOT LIKE '%[^0-9]%' "
                ."THEN CAST(LTRIM(RTRIM(NoTelarId)) AS int) ELSE 2147483647 END {$direction}"
            )
            ->orderBy('NoTelarId', $direction);
    }
}
