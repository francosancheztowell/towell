<?php

namespace Tests\Unit\Planeacion;

use App\Support\Planeacion\CatCodificados\CatCodificadosCache;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Caracterización de las llaves de caché de CatCodificados: el import encolado,
 * el endpoint de progreso y getAllFast las comparten, así que no pueden cambiar.
 */
class CatCodificadosCacheTest extends TestCase
{
    public function test_llaves_de_progreso_y_cancelacion(): void
    {
        $this->assertSame('excel_import_progress:abc-1', CatCodificadosCache::progressCacheKey('abc-1'));
        $this->assertSame('excel_import_cancelled:abc-1', CatCodificadosCache::cancellationCacheKey('abc-1'));
    }

    public function test_clear_cache_olvida_listados_y_el_id_indicado(): void
    {
        $llaves = [
            'catcodificacion_fast_id_7',
            'catcodificacion_fast_all',
            'catcodificacion_fast_recientes',
            'catcodificacion_estimated_count',
            'catcodificacion_total',
        ];
        foreach ($llaves as $llave) {
            Cache::put($llave, 'x', 60);
        }
        Cache::put('catcodificacion_fast_id_8', 'x', 60);

        CatCodificadosCache::clearCache(7);

        foreach ($llaves as $llave) {
            $this->assertFalse(Cache::has($llave), $llave);
        }
        $this->assertTrue(Cache::has('catcodificacion_fast_id_8'));
    }

    public function test_clear_cache_sin_id_no_toca_llaves_por_id(): void
    {
        Cache::put('catcodificacion_fast_id_7', 'x', 60);
        Cache::put('catcodificacion_fast_all', 'x', 60);

        CatCodificadosCache::clearCache();

        $this->assertTrue(Cache::has('catcodificacion_fast_id_7'));
        $this->assertFalse(Cache::has('catcodificacion_fast_all'));
    }
}
