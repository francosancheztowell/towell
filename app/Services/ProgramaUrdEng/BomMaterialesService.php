<?php

declare(strict_types=1);

namespace App\Services\ProgramaUrdEng;

use App\Models\Engomado\EngAnchoBalonaCuenta;
use App\Models\Urdido\URDCatalogoMaquina;
use App\Models\Urdido\UrdConsumoHilo;
use Illuminate\Support\Facades\DB;

class BomMaterialesService
{
    private const CONN = 'sqlsrv_ti';
    private const DATAAREA = 'PRO';

    public function buscarBomUrdido(string $query): array
    {
        $q = DB::connection(self::CONN)->table('BOMTABLE as bt')
            ->where('bt.DATAAREAID', self::DATAAREA)
            ->where('bt.ITEMGROUPID', 'JUL-URD')
            ->where('bt.BOMID', 'LIKE', 'URD %');

        if (strlen($query) >= 1) {
            $q->where(function ($sub) use ($query) {
                $sub->where('bt.BOMID', 'LIKE', '%' . $query . '%')
                    ->orWhere('bt.NAME', 'LIKE', '%' . $query . '%');
            });
        }

        return $q->select('bt.BOMID as BOMID', 'bt.NAME as NAME')
            ->distinct()->orderBy('bt.BOMID')->limit(20)->get()->toArray();
    }

    public function buscarBomEngomado(string $query): array
    {
        $q = DB::connection(self::CONN)->table('BOMTABLE as bt')
            ->where('bt.ITEMGROUPID', 'JUL-ENG')
            ->where('bt.DATAAREAID', self::DATAAREA)
            ->where('bt.BOMID', 'LIKE', 'ENG %');

        if (strlen($query) >= 1) {
            $q->where(function ($sub) use ($query) {
                $sub->where('bt.BOMID', 'LIKE', '%' . $query . '%')
                    ->orWhere('bt.NAME', 'LIKE', '%' . $query . '%');
            });
        }

        return $q->select('bt.BOMID as BOMID', 'bt.NAME as NAME')
            ->distinct()->orderBy('bt.BOMID')->limit(20)->get()->toArray();
    }

    public function getMaterialesUrdido(string $bomId): array
    {
        if (empty(trim($bomId))) return [];

        return DB::connection(self::CONN)
            ->table('BOM as b')
            ->join('BOMTABLE as bt', function ($j) {
                $j->on('bt.BOMID', '=', 'b.BOMID')->on('bt.DATAAREAID', '=', 'b.DATAAREAID');
            })
            ->join('INVENTDIM as id', 'id.INVENTDIMID', '=', 'b.INVENTDIMID')
            ->join('INVENTTABLE as it', function ($j) {
                $j->on('it.ITEMID', '=', 'b.ITEMID')->on('it.DATAAREAID', '=', 'b.DATAAREAID');
            })
            ->where('b.BOMID', $bomId)
            ->where('b.DATAAREAID', self::DATAAREA)
            ->where('id.DATAAREAID', self::DATAAREA)
            ->where('bt.ITEMGROUPID', 'JUL-URD')
            ->select([
                'b.ITEMID as ItemId',
                DB::raw('SUM(CAST(b.BOMQTY AS DECIMAL(18,6))) as BomQty'),
                'id.CONFIGID as ConfigId',
                DB::raw('MAX(it.ITEMNAME) as ItemName')
            ])
            ->groupBy('b.ITEMID', 'id.CONFIGID')
            ->orderBy('b.ITEMID')
            ->get()
            ->toArray();
    }

    public function getMaterialesEngomado(array $itemIds, array $configIds = []): array
    {
        $itemIds = array_values(array_filter(array_map(fn ($id) => trim((string) $id) ?: null, $itemIds)));
        if (empty($itemIds)) {
            return [];
        }

        $configIds = array_values(array_filter(array_map(fn ($id) => trim((string) $id) ?: null, $configIds)));

        $consumidosKeys = $this->obtenerMaterialesConsumidosKeys($itemIds);

        $q = DB::connection(self::CONN)
            ->table('InventSum as sum')
            ->join('InventDim as dim', 'dim.INVENTDIMID', '=', 'sum.INVENTDIMID')
            ->join('InventSerial as ser', function ($j) {
                $j->on('sum.ITEMID', '=', 'ser.ITEMID')->on('ser.INVENTSERIALID', '=', 'dim.INVENTSERIALID');
            })
            ->whereIn('sum.ITEMID', $itemIds)
            ->whereRaw('sum.PhysicalInvent <> 0')
            ->where('sum.DATAAREAID', self::DATAAREA)
            ->where('dim.DATAAREAID', self::DATAAREA)
            ->whereIn('dim.INVENTLOCATIONID', ['A-MP', 'A-MPBB'])
            ->where('ser.DATAAREAID', self::DATAAREA);

        if (! empty($configIds)) {
            $q->whereIn('dim.CONFIGID', $configIds);
        }

        $results = $q->select([
            'sum.ITEMID as ItemId',
            'sum.PHYSICALINVENT as PhysicalInvent',
            'sum.RESERVPHYSICAL as ReservPhysical',
            'dim.CONFIGID as ConfigId',
            'dim.INVENTSIZEID as InventSizeId',
            'dim.INVENTCOLORID as InventColorId',
            'dim.INVENTLOCATIONID as InventLocationId',
            'dim.INVENTBATCHID as InventBatchId',
            'dim.WMSLOCATIONID as WMSLocationId',
            'dim.INVENTSERIALID as InventSerialId',
            'ser.PRODDATE as ProdDate',
            'ser.TWTIRAS as TwTiras',
            'ser.TWCALIDADFLOG as TwCalidadFlog',
            'ser.TWCLIENTEFLOG as TwClienteFlog',
        ])->orderBy('sum.ITEMID')->get();

        return $this->excluirMaterialesConsumidos($results, $consumidosKeys);
    }

    /**
     * Obtiene las claves (ItemId|InventSerialId) de materiales ya consumidos en UrdConsumoHilo.
     * Sirve para no mostrar en la tabla materiales que ya fueron asignados a órdenes anteriores.
     *
     * @param  array<string>  $itemIds
     * @return array<string, true> Map de claves consumidas para lookup O(1)
     */
    private function obtenerMaterialesConsumidosKeys(array $itemIds): array
    {
        $consumidos = UrdConsumoHilo::query()
            ->whereIn('ItemId', $itemIds)
            ->select('ItemId', 'InventSerialId')
            ->distinct()
            ->get();

        $keys = [];
        foreach ($consumidos as $row) {
            $key = self::claveMaterialConsumido(
                trim($row->ItemId ?? ''),
                trim($row->InventSerialId ?? '')
            );
            $keys[$key] = true;
        }

        return $keys;
    }

    /**
     * Genera la clave única para identificar un material consumido (ItemId + InventSerialId).
     */
    private static function claveMaterialConsumido(string $itemId, string $inventSerialId): string
    {
        return $itemId . '|' . $inventSerialId;
    }

    /**
     * Filtra los materiales de inventario excluyendo los que ya están en UrdConsumoHilo.
     *
     * @param  \Illuminate\Support\Collection  $results
     * @param  array<string, true>  $consumidosKeys
     * @return array
     */
    private function excluirMaterialesConsumidos($results, array $consumidosKeys): array
    {
        if (empty($consumidosKeys)) {
            return $results->toArray();
        }

        return $results
            ->filter(function ($row) use ($consumidosKeys) {
                $key = self::claveMaterialConsumido(
                    trim($row->ItemId ?? ''),
                    trim($row->InventSerialId ?? '')
                );

                return ! isset($consumidosKeys[$key]);
            })
            ->values()
            ->toArray();
    }

    public function getAnchosBalona(?string $cuenta, ?string $tipo): array
    {
        $svc = new InventarioTelaresService;
        $tipoNorm = $svc->normalizeTipo($tipo);
        $cuenta = $cuenta && trim($cuenta) !== '' ? trim($cuenta) : null;

        $query = EngAnchoBalonaCuenta::query();
        if ($cuenta) $query->whereRaw('LTRIM(RTRIM(Cuenta)) = ?', [$cuenta]);
        if ($tipoNorm) $query->whereRaw('LTRIM(RTRIM(RizoPie)) = ?', [$tipoNorm]);
        $resultados = $query->orderBy('AnchoBalona')->get();

        if ($resultados->isEmpty() && ($cuenta || $tipoNorm)) {
            $resultados = EngAnchoBalonaCuenta::orderBy('AnchoBalona')->get();
        }

        return $resultados->map(fn ($i) => [
            'id' => $i->Id, 'anchoBalona' => $i->AnchoBalona, 'cuenta' => $i->Cuenta, 'rizoPie' => $i->RizoPie
        ])->toArray();
    }

    public function getMaquinasEngomado(): array
    {
        return URDCatalogoMaquina::where('Departamento', 'Engomado')
            ->orderBy('Nombre')
            ->get()
            ->map(fn ($m) => ['maquinaId' => $m->MaquinaId, 'nombre' => $m->Nombre, 'departamento' => $m->Departamento])
            ->toArray();
    }

    public function obtenerHilos(): array
    {
        return DB::connection(self::CONN)
            ->table('ConfigTable')
            ->select('ConfigId')
            ->where('ItemId', 'JULIO-URDIDO')
            ->orderBy('ConfigId')
            ->distinct()
            ->get()
            ->toArray();
    }

    public function obtenerTamanos(): array
    {
        return DB::connection(self::CONN)
            ->table('InventSize')
            ->select('InventSizeId')
            ->where('ItemId', 'JULIO-URDIDO')
            ->where('DATAAREAID', self::DATAAREA)
            ->orderBy('InventSizeId')
            ->distinct()
            ->get()
            ->toArray();
    }
}
