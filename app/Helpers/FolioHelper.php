<?php

namespace App\Helpers;

use App\Models\SSYSFoliosSecuencias;
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
            DB::beginTransaction();

            // Bloquear el registro para evitar condiciones de carrera
            $secuencia = SSYSFoliosSecuencias::where('Modulo', $modulo)
                ->lockForUpdate()
                ->first();

            if (!$secuencia) {
                throw new \Exception("No se encontró secuencia para el módulo: {$modulo}");
            }

            // Obtener el consecutivo actual
            $consecutivo = $secuencia->Consecutivo;
            $prefijo = $secuencia->Prefijo;

            // Generar el folio con padding de ceros
            $folio = $prefijo . str_pad((string)$consecutivo, $longitudConsecutivo, '0', STR_PAD_LEFT);

            // Incrementar el consecutivo
            $secuencia->Consecutivo = $consecutivo + 1;
            $secuencia->save();

            DB::commit();

            Log::info('Folio generado', [
                'modulo' => $modulo,
                'folio' => $folio,
                'consecutivo_usado' => $consecutivo,
                'nuevo_consecutivo' => $secuencia->Consecutivo,
            ]);

            return $folio;

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error al generar folio', [
                'modulo' => $modulo,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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
            $secuencia = SSYSFoliosSecuencias::where('Modulo', $modulo)->first();

            if (!$secuencia) {
                Log::warning('No se encontró secuencia para módulo', ['modulo' => $modulo]);
                return '';
            }

            $consecutivo = $secuencia->Consecutivo;
            $prefijo = $secuencia->Prefijo;

            $folio = $prefijo . str_pad((string)$consecutivo, $longitudConsecutivo, '0', STR_PAD_LEFT);

            Log::info('Folio sugerido generado', [
                'modulo' => $modulo,
                'folio' => $folio,
                'consecutivo' => $consecutivo,
            ]);

            return $folio;
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
        $secuencia = SSYSFoliosSecuencias::where('Modulo', $modulo)->first();

        if (!$secuencia) {
            return null;
        }

        return [
            'Id' => $secuencia->Id,
            'Modulo' => $secuencia->Modulo,
            'Prefijo' => $secuencia->Prefijo,
            'Consecutivo' => $secuencia->Consecutivo,
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
            $secuencia = SSYSFoliosSecuencias::where('Modulo', $modulo)->first();

            if (!$secuencia) {
                return false;
            }

            $secuencia->Consecutivo = $nuevoConsecutivo;
            $secuencia->save();

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

