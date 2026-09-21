<?php

namespace App\Services\Tejido\InventarioTrama;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CatalogoTramaService
{
    /** @return array<int, array{ItemId: string}> */
    public function calibres(): array
    {
        // ponytail: mismo catálogo para todos, se cachea 10 min en vez de pegar a TI en cada modal
        return Cache::remember('trama_calibres', 600, fn () => DB::connection('sqlsrv_ti')
            ->table('InventTable')
            ->select('ItemId')
            ->where('ItemGroupId', 'HILO DIREC')
            ->where('DATAAREAID', 'PRO')
            ->orderBy('ItemId')
            ->distinct()
            ->pluck('ItemId')
            ->filter(fn ($id) => (string) $id !== '')
            ->map(fn ($id) => ['ItemId' => (string) $id])
            ->values()
            ->all());
    }

    /** @return array<int, array{ConfigId: string}> */
    public function fibras(string $itemId): array
    {
        return DB::connection('sqlsrv_ti')
            ->table('InventSum')
            ->join('InventDim', 'InventDim.InventDimId', '=', 'InventSum.InventDimId')
            ->where('InventSum.ItemId', $itemId)
            ->where('InventSum.PhysicalInvent', '>', 0)
            ->where('InventSum.DATAAREAID', 'PRO')
            ->where('InventDim.DATAAREAID', 'PRO')
            ->whereNotNull('InventDim.ConfigId')
            ->where('InventDim.ConfigId', '!=', '')
            ->distinct()
            ->orderBy('InventDim.ConfigId')
            ->pluck('InventDim.ConfigId')
            ->map(fn ($id) => ['ConfigId' => (string) $id])
            ->values()
            ->all();
    }

    /** @return array<int, array{InventColorId: string, Name: string|null}> */
    public function colores(string $itemId): array
    {
        return DB::connection('sqlsrv_ti')
            ->table('InventSum')
            ->join('InventDim', 'InventDim.InventDimId', '=', 'InventSum.InventDimId')
            ->join('InventColor', function ($join) use ($itemId) {
                $join->on('InventColor.InventColorId', '=', 'InventDim.InventColorId')
                    ->where('InventColor.ItemId', '=', $itemId);
            })
            ->select('InventColor.InventColorId', 'InventColor.Name')
            ->where('InventSum.ItemId', $itemId)
            ->where('InventSum.PhysicalInvent', '>', 0)
            ->where('InventSum.DATAAREAID', 'PRO')
            ->where('InventDim.DATAAREAID', 'PRO')
            ->where('InventColor.DATAAREAID', 'PRO')
            ->whereNotNull('InventColor.InventColorId')
            ->where('InventColor.InventColorId', '!=', '')
            ->groupBy('InventColor.InventColorId', 'InventColor.Name')
            ->orderBy('InventColor.InventColorId')
            ->get()
            ->map(fn ($c) => [
                'InventColorId' => (string) $c->InventColorId,
                'Name' => $c->Name,
            ])
            ->all();
    }

    /** @return array<int, string> */
    public function nombresColor(string $search = ''): array
    {
        $search = trim($search);

        $query = DB::table('TejTramaConsumos')
            ->select('ColorTrama')
            ->whereNotNull('ColorTrama')
            ->where('ColorTrama', '!=', '')
            ->distinct();

        if ($search !== '') {
            $query->whereRaw('ColorTrama LIKE ?', ['%'.$search.'%']);
        }

        return $query->orderBy('ColorTrama')
            ->limit(50)
            ->pluck('ColorTrama')
            ->unique()
            ->values()
            ->all();
    }
}
