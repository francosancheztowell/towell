<?php

namespace App\Support\Planeacion\CatCodificados;

use Illuminate\Support\Facades\Cache;

/**
 * Llaves de caché de CatCodificados. El import encolado, el endpoint de progreso
 * y getAllFast las comparten: no cambiar los textos.
 */
final class CatCodificadosCache
{
    /**
     * Invalidar cache de getAllFast.
     */
    public static function clearCache(?int $id = null): void
    {
        if ($id !== null) {
            Cache::forget("catcodificacion_fast_id_{$id}");
        }
        Cache::forget('catcodificacion_fast_all');
        Cache::forget('catcodificacion_fast_recientes');
        Cache::forget('catcodificacion_estimated_count');
        Cache::forget('catcodificacion_total');
    }

    public static function progressCacheKey(string $id): string
    {
        return 'excel_import_progress:'.$id;
    }

    public static function cancellationCacheKey(string $id): string
    {
        return 'excel_import_cancelled:'.$id;
    }
}
