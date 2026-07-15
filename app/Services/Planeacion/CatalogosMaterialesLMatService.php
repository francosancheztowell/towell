<?php

declare(strict_types=1);

namespace App\Services\Planeacion;

use Illuminate\Support\Facades\DB;

final class CatalogosMaterialesLMatService
{
    /**
     * Obtiene Config, Tamaño y Color para varios artículos usando solo tres
     * consultas a AX, en lugar de tres consultas HTTP por cada fila del modal.
     *
     * @param  list<string>  $itemIds
     * @return array<string, array{
     *     configs: list<string>,
     *     tamanos: list<array{InventSizeId: string, Name: string}>,
     *     colores: list<array{InventColorId: string, Name: string}>
     * }>
     */
    public function obtener(array $itemIds): array
    {
        $articulos = collect($itemIds)
            ->map(fn (mixed $itemId): string => trim((string) $itemId))
            ->filter()
            ->unique()
            ->values();

        if ($articulos->isEmpty()) {
            return [];
        }

        $resultado = $articulos
            ->mapWithKeys(fn (string $itemId): array => [
                $itemId => [
                    'configs' => [],
                    'tamanos' => [],
                    'colores' => [],
                ],
            ])
            ->all();

        $configs = DB::connection('sqlsrv_ti')
            ->table('ConfigTable')
            ->select('ItemId', 'ConfigId')
            ->whereIn('ItemId', $articulos->all())
            ->where('DATAAREAID', 'PRO')
            ->orderBy('ItemId')
            ->orderBy('ConfigId')
            ->get();

        foreach ($configs as $config) {
            $itemId = trim((string) $config->ItemId);
            $configId = trim((string) $config->ConfigId);
            if (isset($resultado[$itemId]) && $configId !== '' && mb_strtoupper($configId) !== 'HILO') {
                $resultado[$itemId]['configs'][] = $configId;
            }
        }

        $tamanos = DB::connection('sqlsrv_ti')
            ->table('InventSize')
            ->select('ItemId', 'InventSizeId', 'NAME')
            ->whereIn('ItemId', $articulos->all())
            ->where('DATAAREAID', 'PRO')
            ->orderBy('ItemId')
            ->orderBy('InventSizeId')
            ->get();

        foreach ($tamanos as $tamano) {
            $itemId = trim((string) $tamano->ItemId);
            $inventSizeId = trim((string) $tamano->InventSizeId);
            if (isset($resultado[$itemId]) && $inventSizeId !== '') {
                $resultado[$itemId]['tamanos'][] = [
                    'InventSizeId' => $inventSizeId,
                    'Name' => trim((string) ($tamano->NAME ?? '')),
                ];
            }
        }

        $colores = DB::connection('sqlsrv_ti')
            ->table('InventColor')
            ->select('ItemId', 'InventColorId', 'Name')
            ->whereIn('ItemId', $articulos->all())
            ->where('DATAAREAID', 'PRO')
            ->orderBy('ItemId')
            ->orderBy('InventColorId')
            ->get();

        foreach ($colores as $color) {
            $itemId = trim((string) $color->ItemId);
            $inventColorId = trim((string) $color->InventColorId);
            if (isset($resultado[$itemId]) && $inventColorId !== '') {
                $resultado[$itemId]['colores'][] = [
                    'InventColorId' => $inventColorId,
                    'Name' => trim((string) ($color->Name ?? '')),
                ];
            }
        }

        return $resultado;
    }
}
