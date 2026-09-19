<?php

declare(strict_types=1);

namespace App\Services\Planeacion\Liberar;

use Illuminate\Support\Facades\DB;

/**
 * Catálogo de tipos de hilo (AX) al liberar.
 *
 * Extraído de LiberarOrdenesController. Misma entrada → misma salida:
 * TwTipoHiloId por item desde INVENTTABLE (ITEMID con sufijo -1),
 * y lista de TipoHilo distintos de TwTipoHilo para el select.
 */
final class LiberarHilosCatalogo
{
    /**
     * Mapa itemId (sin sufijo) → TwTipoHiloId. El front manda CSV de ItemId
     * del programa; AX guarda ITEMID con '-1'.
     *
     * @return array<string, string|null>
     */
    public function mapaTipoHilo(string $itemIdsParam): array
    {
        $itemIds = array_filter(array_map('trim', explode(',', $itemIdsParam)));
        if ($itemIds === []) {
            return [];
        }

        $itemIdsWithSuffix = array_map(fn ($id) => $id.'-1', $itemIds);

        $results = DB::connection('sqlsrv_ti')
            ->table('INVENTTABLE')
            ->select('ITEMID', 'TwTipoHiloId')
            ->whereIn('ITEMID', $itemIdsWithSuffix)
            ->get();

        $map = [];
        foreach ($results as $result) {
            $itemIdOriginal = LiberarBomCrudoResolver::itemIdSinSufijo((string) $result->ITEMID);
            $map[$itemIdOriginal] = $result->TwTipoHiloId ?? null;
        }

        return $map;
    }

    /**
     * Opciones del select: TipoHilo distintos de TwTipoHilo, trim, unique, sort.
     *
     * @return list<string>
     */
    public function opciones(): array
    {
        return DB::connection('sqlsrv_ti')
            ->table('TwTipoHilo')
            ->select('TipoHilo')
            ->where('TipoHilo', '!=', '')
            ->distinct()
            ->pluck('TipoHilo')
            ->filter(function ($value) {
                return ! empty(trim((string) $value));
            })
            ->map(function ($value) {
                return trim((string) $value);
            })
            ->unique()
            ->sort()
            ->values()
            ->toArray();
    }
}
