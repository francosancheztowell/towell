<?php

namespace App\Helpers;

use App\Models\Sistema\SSYSFoliosSecuencia;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FolioHelper
{
    /**
     * Obtiene el siguiente folio para un módulo dado
     * Incrementa automáticamente el consecutivo después de usarlo
     *
     * @param  string  $modulo  Nombre del módulo (ej: 'Trama', 'Reenconado', 'REENCONADO')
     * @param  int  $longitudConsecutivo  Longitud del consecutivo con ceros a la izquierda (default: 5)
     * @param  int|null  $idRespaldo  Id de la secuencia a usar si la búsqueda por módulo falla
     *                                (URD/ENG usa 14: el nombre del módulo no siempre coincide en BD)
     * @return string Folio generado (ej: 'TR00001')
     */
    public static function obtenerSiguienteFolio(string $modulo, int $longitudConsecutivo = 5, ?int $idRespaldo = null): string
    {
        try {
            $r = SSYSFoliosSecuencia::nextFolio($modulo, $longitudConsecutivo);
        } catch (\Throwable $e) {
            if ($idRespaldo !== null && $e instanceof \Exception) {
                return SSYSFoliosSecuencia::nextFolioById($idRespaldo, $longitudConsecutivo)['folio'] ?? '';
            }
            Log::error('Error al generar folio', [
                'modulo' => $modulo,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        return $r['folio'] ?? '';
    }

    /**
     * Consume exactamente el folio que muestra obtenerFolioSugerido() y luego
     * incrementa el consecutivo. Es la semántica de Trama, donde el consecutivo
     * guardado es "el siguiente a usar" (en obtenerSiguienteFolio() es "el último
     * usado"). Bloquea la fila: llamarlo dentro de la transacción que guarda el
     * registro para que el folio y su dueño se confirmen juntos.
     *
     * @param  string  $prefijoSiNulo  prefijo a usar si la fila tiene prefijo NULL (Trama usaba 'TR')
     * @return string Folio consumido, o '' si el módulo no tiene secuencia (no incrementa nada)
     */
    public static function consumirFolioSugerido(string $modulo, int $longitudConsecutivo = 5, string $prefijoSiNulo = ''): string
    {
        return DB::transaction(function () use ($modulo, $longitudConsecutivo, $prefijoSiNulo) {
            $row = DB::table('dbo.SSYSFoliosSecuencias')->where('modulo', $modulo)->lockForUpdate()->first();
            if (! $row) {
                return '';
            }
            $pref = $row->prefijo ?? ($row->Prefijo ?? $prefijoSiNulo);
            $con = (int) ($row->consecutivo ?? ($row->Consecutivo ?? 0));
            DB::table('dbo.SSYSFoliosSecuencias')->where('modulo', $modulo)->increment('consecutivo');

            return $pref.str_pad((string) $con, $longitudConsecutivo, '0', STR_PAD_LEFT);
        });
    }

    /**
     * Crea la secuencia de un módulo la primera vez que se usa, sembrada con el
     * número del último folio existente con ese prefijo para no reutilizar folios.
     * Si ya existe no la toca.
     *
     * @param  QueryBuilder|EloquentBuilder  $foliosExistentes  consulta sobre la tabla dueña de los folios (columna Folio)
     */
    public static function asegurarSecuencia(string $modulo, string $prefijo, QueryBuilder|EloquentBuilder $foliosExistentes): void
    {
        DB::transaction(function () use ($modulo, $prefijo, $foliosExistentes): void {
            $secuencia = SSYSFoliosSecuencia::query()
                ->where('modulo', $modulo)
                ->lockForUpdate()
                ->first();

            if ($secuencia !== null) {
                return;
            }

            $ultimoFolio = (string) $foliosExistentes
                ->where('Folio', 'like', $prefijo.'%')
                ->orderByDesc('Folio')
                ->value('Folio');

            SSYSFoliosSecuencia::create([
                'modulo' => $modulo,
                'prefijo' => $prefijo,
                'consecutivo' => (int) substr($ultimoFolio, strlen($prefijo)),
            ]);
        });
    }

    /**
     * Obtiene el folio sugerido sin incrementar el consecutivo
     * Útil para mostrar en la UI antes de guardar
     *
     * @param  string  $modulo  Nombre del módulo
     * @param  int  $longitudConsecutivo  Longitud del consecutivo con ceros a la izquierda (default: 5)
     * @return string Folio sugerido
     */
    public static function obtenerFolioSugerido(string $modulo, int $longitudConsecutivo = 5): string
    {
        try {
            $row = DB::table('dbo.SSYSFoliosSecuencias')->where('modulo', $modulo)->first();
            if (! $row) {
                Log::warning('No se encontró secuencia para módulo', ['modulo' => $modulo]);

                return '';
            }
            $pref = $row->prefijo ?? ($row->Prefijo ?? '');
            $con = (int) ($row->consecutivo ?? ($row->Consecutivo ?? 0));

            return $pref.str_pad((string) $con, $longitudConsecutivo, '0', STR_PAD_LEFT);
        } catch (\Throwable $e) {
            Log::error('Error al obtener folio sugerido', [
                'modulo' => $modulo,
                'error' => $e->getMessage(),
            ]);

            return '';
        }
    }
}
