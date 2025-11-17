<?php

namespace App\Helpers;

use App\Models\SSYSFoliosSecuencia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FolioHelper
{
    /**
     * Obtiene el siguiente folio para un módulo dado
     * Incrementa automáticamente el consecutivo después de usarlo
     *
     * @param string $modulo Nombre del módulo (ej: 'Trama', 'Reenconado', 'REENCONADO')
     * @param int $longitudConsecutivo Longitud del consecutivo con ceros a la izquierda (default: 5)
     * @return string Folio generado (ej: 'TR00001')
     */
    public static function obtenerSiguienteFolio(string $modulo, int $longitudConsecutivo = 5): string
    {
        try {
            $r = SSYSFoliosSecuencia::nextFolio($modulo, $longitudConsecutivo);
            return $r['folio'] ?? '';
        } catch (\Throwable $e) {
            Log::error('Error al generar folio', [
                'modulo' => $modulo,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Obtiene el folio sugerido sin incrementar el consecutivo
     * Útil para mostrar en la UI antes de guardar
     *
     * @param string $modulo Nombre del módulo
     * @param int $longitudConsecutivo Longitud del consecutivo con ceros a la izquierda (default: 5)
     * @return string Folio sugerido
     */
    public static function obtenerFolioSugerido(string $modulo, int $longitudConsecutivo = 5): string
    {
        try {
            $row = DB::table('dbo.SSYSFoliosSecuencias')->where('modulo', $modulo)->first();
            if (!$row) {
                Log::warning('No se encontró secuencia para módulo', ['modulo' => $modulo]);
                return '';
            }
            $pref = $row->prefijo ?? ($row->Prefijo ?? '');
            $con  = (int)($row->consecutivo ?? ($row->Consecutivo ?? 0));
            return $pref . str_pad((string)$con, $longitudConsecutivo, '0', STR_PAD_LEFT);
        } catch (\Throwable $e) {
            Log::error('Error al obtener folio sugerido', [
                'modulo' => $modulo,
                'error' => $e->getMessage(),
            ]);
            return '';
        }
    }

    /**
     * Obtiene información de la secuencia de un módulo
     *
     * @param string $modulo Nombre del módulo
     * @return array|null ['Id', 'Modulo', 'Prefijo', 'Consecutivo']
     */
    public static function obtenerInfoSecuencia(string $modulo): ?array
    {
        $row = DB::table('dbo.SSYSFoliosSecuencias')->where('modulo', $modulo)->first();
        if (!$row) {
            return null;
        }
        return [
            'Id' => $row->Id ?? null,
            'Modulo' => $row->Modulo ?? ($row->modulo ?? null),
            'Prefijo' => $row->Prefijo ?? ($row->prefijo ?? null),
            'Consecutivo' => $row->Consecutivo ?? ($row->consecutivo ?? null),
        ];
    }

    /**
     * Reinicia el consecutivo de un módulo (útil para pruebas o reset)
     *
     * @param string $modulo Nombre del módulo
     * @param int $nuevoConsecutivo Nuevo valor del consecutivo
     * @return bool
     */
    public static function reiniciarConsecutivo(string $modulo, int $nuevoConsecutivo = 1): bool
    {
        try {
            DB::table('dbo.SSYSFoliosSecuencias')->where('modulo', $modulo)->update(['consecutivo' => $nuevoConsecutivo]);
            Log::info('Consecutivo reiniciado', [
                'modulo' => $modulo,
                'nuevo_consecutivo' => $nuevoConsecutivo,
            ]);
            return true;
        } catch (\Throwable $e) {
            Log::error('Error al reiniciar consecutivo', [
                'modulo' => $modulo,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}

