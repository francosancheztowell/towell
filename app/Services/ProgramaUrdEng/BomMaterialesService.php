<?php

declare(strict_types=1);

namespace App\Services\ProgramaUrdEng;

use App\Models\Engomado\EngAnchoBalonaCuenta;
use App\Models\Urdido\URDCatalogoMaquina;
use App\Models\Urdido\UrdConsumoHilo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
                $sub->where('bt.BOMID', 'LIKE', '%'.$query.'%')
                    ->orWhere('bt.NAME', 'LIKE', '%'.$query.'%');
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
                $sub->where('bt.BOMID', 'LIKE', '%'.$query.'%')
                    ->orWhere('bt.NAME', 'LIKE', '%'.$query.'%');
            });
        }

        return $q->select('bt.BOMID as BOMID', 'bt.NAME as NAME')
            ->distinct()->orderBy('bt.BOMID')->limit(20)->get()->toArray();
    }

    public function getMaterialesUrdido(string $bomId): array
    {
        if (empty(trim($bomId))) {
            return [];
        }

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
                DB::raw('MAX(it.ITEMNAME) as ItemName'),
            ])
            ->groupBy('b.ITEMID', 'id.CONFIGID')
            ->orderBy('b.ITEMID')
            ->get()
            ->toArray();
    }

    /**
     * Obtiene resumen (materiales BOM) e inventario detalle para Karl Mayer.
     * Resumen: Articulo, Config, Consumo, Kilos.
     * Detalle: inventario por serie/lote con Conos, Kilos, etc.
     */
    public function getMaterialesUrdidoCompleto(string $bomId, ?float $kilosTotal = null): array
    {
        $resumen = $this->getMaterialesUrdido($bomId);
        if (empty($resumen)) {
            return ['resumen' => [], 'detalle' => []];
        }

        $itemIds = array_values(array_unique(array_map(fn ($r) => trim((string) ($r->ItemId ?? '')), $resumen)));
        $configIds = array_values(array_unique(array_map(fn ($r) => trim((string) ($r->ConfigId ?? '')), $resumen)));
        $itemIds = array_filter($itemIds);
        $configIds = array_filter($configIds);

        $detalleRaw = $this->getInventarioPorMaterialesUrdidoRaw($itemIds, $configIds);

        $kilosTotal = $kilosTotal > 0 ? (float) $kilosTotal : 1;
        $resumenMapped = [];
        foreach ($resumen as $r) {
            $bomQty = (float) ($r->BomQty ?? 0);
            $resumenMapped[] = [
                'articulo' => trim($r->ItemId ?? ''),
                'config' => trim($r->ConfigId ?? ''),
                'consumo' => $bomQty,
                'kilos' => number_format($bomQty * $kilosTotal, 2),
            ];
        }

        return ['resumen' => $resumenMapped, 'detalle' => $detalleRaw];
    }

    /**
     * Inventario físico disponible (formato raw, igual que getMaterialesEngomado).
     * Para Karl Mayer: mismo formato que creacion-ordenes para UrdConsumoHilo.
     */
    private function getInventarioPorMaterialesUrdidoRaw(array $itemIds, array $configIds): array
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

        $rows = $q->select([
            'sum.ITEMID as ItemId',
            'sum.PHYSICALINVENT as PhysicalInvent',
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

        $filtered = $this->excluirMaterialesConsumidos($rows, $consumidosKeys);

        return array_map(function ($row) {
            $row = is_array($row) ? (object) $row : $row;
            $prodDate = $row->ProdDate ?? null;
            $prodDateStr = null;
            if ($prodDate instanceof \DateTimeInterface) {
                $prodDateStr = $prodDate->format('Y-m-d');
            } elseif ($prodDate !== null && $prodDate !== '') {
                $prodDateStr = (string) $prodDate;
            }

            return [
                'ItemId' => trim($row->ItemId ?? ''),
                'ConfigId' => trim($row->ConfigId ?? ''),
                'InventSizeId' => trim($row->InventSizeId ?? '') ?: 'ENTERO',
                'InventColorId' => trim($row->InventColorId ?? ''),
                'InventLocationId' => trim($row->InventLocationId ?? ''),
                'InventBatchId' => trim($row->InventBatchId ?? ''),
                'WMSLocationId' => trim($row->WMSLocationId ?? ''),
                'InventSerialId' => trim($row->InventSerialId ?? ''),
                'ProdDate' => $prodDateStr,
                'TwTiras' => isset($row->TwTiras) ? (int) $row->TwTiras : 0,
                'TwCalidadFlog' => trim($row->TwCalidadFlog ?? ''),
                'TwClienteFlog' => trim($row->TwClienteFlog ?? ''),
                'PhysicalInvent' => (float) ($row->PhysicalInvent ?? 0),
            ];
        }, $filtered);
    }

    /**
     * Inventario físico disponible por ItemId/ConfigId (para tabla detalle Karl Mayer).
     *
     * @deprecated Usar getInventarioPorMaterialesUrdidoRaw para formato creacion-ordenes
     */
    private function getInventarioPorMaterialesUrdido(array $itemIds, array $configIds): array
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

        $rows = $q->select([
            'sum.ITEMID as ItemId',
            'sum.PHYSICALINVENT as PhysicalInvent',
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

        $filtered = $this->excluirMaterialesConsumidos($rows, $consumidosKeys);

        return array_map(function ($row) {
            $row = is_array($row) ? (object) $row : $row;
            $physical = (float) ($row->PhysicalInvent ?? 0);
            $twTiras = isset($row->TwTiras) ? (int) $row->TwTiras : 0;
            $fecha = $row->ProdDate ?? null;
            if ($fecha instanceof \DateTimeInterface) {
                $fecha = $fecha->format('d/m/Y');
            } elseif ($fecha !== null && $fecha !== '') {
                $fecha = (string) $fecha;
            } else {
                $fecha = '';
            }

            return [
                'articulo' => trim($row->ItemId ?? ''),
                'config' => trim($row->ConfigId ?? ''),
                'tamano' => trim($row->InventSizeId ?? '') ?: 'ENTERO',
                'color' => trim($row->InventColorId ?? ''),
                'almacen' => trim($row->InventLocationId ?? ''),
                'lote' => trim($row->InventBatchId ?? ''),
                'localidad' => trim($row->WMSLocationId ?? ''),
                'serie' => trim($row->InventSerialId ?? ''),
                'loteProveedor' => trim($row->TwCalidadFlog ?? ''),
                'noProveedor' => trim($row->TwClienteFlog ?? ''),
                'fecha' => $fecha,
                'conos' => $twTiras,
                'kilos' => $physical,
                'id' => ($row->ItemId ?? '').'|'.($row->InventSerialId ?? ''),
            ];
        }, $filtered);
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
        $query = UrdConsumoHilo::query()
            ->whereIn('ItemId', $itemIds);

        if (Schema::connection('sqlsrv')->hasColumn('UrdConsumoHilo', 'Registrado')) {
            $query->where('Registrado', 1);
        }

        $consumidos = $query->select('ItemId', 'InventSerialId')
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
        return $itemId.'|'.$inventSerialId;
    }

    /**
     * Filtra los materiales de inventario excluyendo los que ya están en UrdConsumoHilo.
     *
     * @param  \Illuminate\Support\Collection  $results
     * @param  array<string, true>  $consumidosKeys
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
        if ($cuenta) {
            $query->whereRaw('LTRIM(RTRIM(Cuenta)) = ?', [$cuenta]);
        }
        if ($tipoNorm) {
            $query->whereRaw('LTRIM(RTRIM(RizoPie)) = ?', [$tipoNorm]);
        }
        $resultados = $query->orderBy('AnchoBalona')->get();

        if ($resultados->isEmpty() && ($cuenta || $tipoNorm)) {
            $resultados = EngAnchoBalonaCuenta::orderBy('AnchoBalona')->get();
        }

        return $resultados->map(fn ($i) => [
            'id' => $i->Id, 'anchoBalona' => $i->AnchoBalona, 'cuenta' => $i->Cuenta, 'rizoPie' => $i->RizoPie,
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

    /**
     * Resuelve el BomId de AX a partir del ITEMID de una fórmula (misma lógica que enlaza BOMVersion con componentes).
     */
    public function resolveBomIdFromFormulaItem(?string $formulaItemId): ?string
    {
        if (empty(trim((string) ($formulaItemId ?? '')))) {
            return null;
        }

        $itemId = trim((string) $formulaItemId);

        try {
            $bomId = DB::connection(self::CONN)
                ->table('BOMVersion')
                ->where('ItemId', $itemId)
                ->where('DATAAREAID', self::DATAAREA)
                ->orderBy('BomId')
                ->value('BomId');

            if ($bomId === null || $bomId === '') {
                return null;
            }

            return trim((string) $bomId);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Lista TE-PD-ENF%: por BomEng se agrupan todos los BOM de engomado (ENG %) que en AX comparten el mismo
     * artículo en {@see BOMVersion} (alternativas de lista); si no aplica, una sola lista vía {@see getBomFormulas()}.
     * Sin bom id, intenta resolver BomId vía {@see resolveBomIdFromFormulaItem()}.
     *
     * @return list<string>
     */
    public function getBomFormulasWithFallback(?string $bomEngId, ?string $formulaItemId): array
    {
        $bomKey = trim((string) ($bomEngId ?? ''));
        if ($bomKey !== '') {
            $aggregated = $this->getBomFormulasAggregatedForEngProgram($bomKey);
            if ($aggregated !== []) {
                return $aggregated;
            }
        }

        $resolved = $this->resolveBomIdFromFormulaItem($formulaItemId);
        if ($resolved === null || $resolved === '') {
            return [];
        }

        return $this->getBomFormulasAggregatedForEngProgram($resolved);
    }

    /**
     * Todas las fórmulas TE-PD-ENF% del programa de engomado: une las de cada BOM ENG % que comparte ItemId
     * en BOMVersion con el BomEng actual (mismas alternativas de lista que en AX para el mismo producto).
     *
     * @return list<string>
     */
    public function getBomFormulasAggregatedForEngProgram(?string $bomEngId): array
    {
        if (empty(trim((string) ($bomEngId ?? '')))) {
            return [];
        }

        $key = trim((string) $bomEngId);

        try {
            $parentItems = $this->resolveParentItemIdsForEngBom($key);
            $bomIds = $parentItems !== []
                ? $this->resolveEngBomIdsForParentItems($parentItems)
                : [];

            if ($bomIds === []) {
                return $this->getBomFormulas($key);
            }

            $seen = [];
            foreach ($bomIds as $bid) {
                foreach ($this->getBomFormulas($bid) as $f) {
                    $seen[$f] = true;
                }
            }

            $result = array_keys($seen);
            sort($result);

            return $result;
        } catch (\Throwable $e) {
            return $this->getBomFormulas($key);
        }
    }

    /**
     * ItemId de producto enlazados al BomId en BOMVersion (un engomado puede tener varias filas de versión).
     *
     * @return list<string>
     */
    private function resolveParentItemIdsForEngBom(string $bomEngId): array
    {
        try {
            return DB::connection(self::CONN)
                ->table('BOMVersion')
                ->whereRaw('RTRIM(BomId) = ?', [trim($bomEngId)])
                ->where('DATAAREAID', self::DATAAREA)
                ->distinct()
                ->pluck('ItemId')
                ->map(fn ($id) => trim((string) $id))
                ->filter(fn (string $id) => $id !== '')
                ->values()
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Todos los BomId de listas de materiales de engomado (JUL-ENG, ENG %) que comparten el mismo ItemId padre.
     *
     * @param  list<string>  $parentItemIds
     * @return list<string>
     */
    private function resolveEngBomIdsForParentItems(array $parentItemIds): array
    {
        if ($parentItemIds === []) {
            return [];
        }

        try {
            return DB::connection(self::CONN)
                ->table('BOMVersion as BV')
                ->join('BOMTABLE as BT', function ($j) {
                    $j->on('BT.BOMID', '=', 'BV.BomId')
                        ->on('BT.DATAAREAID', '=', 'BV.DATAAREAID');
                })
                ->where('BV.DATAAREAID', self::DATAAREA)
                ->whereIn('BV.ItemId', $parentItemIds)
                ->where('BT.ITEMGROUPID', 'JUL-ENG')
                ->where('BT.BOMID', 'like', 'ENG %')
                ->distinct()
                ->pluck('BV.BomId')
                ->map(fn ($id) => trim((string) $id))
                ->filter(fn (string $id) => $id !== '')
                ->values()
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Todos los ITEMID de fórmula (TE-PD-ENF%) distintos de **un** BOM de engomado en AX, ordenados.
     *
     * @return list<string>
     */
    public function getBomFormulas(?string $bomEngId): array
    {
        if (empty(trim($bomEngId ?? ''))) {
            return [];
        }

        $bomKey = trim((string) $bomEngId);

        try {
            $rows = DB::connection(self::CONN)
                ->table('BOM')
                ->select('ITEMID')
                ->whereRaw('RTRIM(BOM.BOMID) = ?', [$bomKey])
                ->where('DATAAREAID', self::DATAAREA)
                ->where('ITEMID', 'like', 'TE-PD-ENF%')
                ->orderBy('ITEMID')
                ->distinct()
                ->pluck('ITEMID');

            return $rows
                ->map(fn ($id) => (string) $id)
                ->map(fn (string $id) => trim($id))
                ->filter(fn (string $id) => $id !== '')
                ->values()
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Compatibilidad Programa URD/ENG: primera fórmula de {@see getBomFormulas()} (mismo criterio y orden).
     *
     * @return string|null ITEMID de la fórmula o null si no existe
     */
    public function getBomFormula(?string $bomEngId): ?string
    {
        $formulas = $this->getBomFormulas($bomEngId);

        return $formulas[0] ?? null;
    }
}
