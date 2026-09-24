<?php

namespace App\Services\Monitoreo;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Utilidades comunes del monitoreo: kill switch, conexión de errores y el
 * envoltorio "nunca romper la request" (contrato §1).
 */
final class Monitoreo
{
    /** Conexión clonada de sqlsrv para que un rollback de negocio no se lleve los errores. */
    public const CONEXION_ERRORES = 'sqlsrv_monitoreo';

    public static function activo(): bool
    {
        return (bool) config('monitoreo.enabled', true);
    }

    /**
     * Registra en runtime la conexión de errores copiando la config ACTUAL de sqlsrv.
     *
     * Perezoso a propósito: si se clonara al arrancar, los tests (que cambian sqlsrv
     * a sqlite después del boot) escribirían en el SQL Server real del desarrollador.
     */
    public static function conexionErrores(): string
    {
        $clave = 'database.connections.'.self::CONEXION_ERRORES;

        if (! is_array(config($clave))) {
            config([$clave => config('database.connections.sqlsrv')]);
        }

        return self::CONEXION_ERRORES;
    }

    /**
     * Ejecuta $fn y, si falla, escribe al log y devuelve $default. Nunca lanza.
     *
     * @template T
     *
     * @param  callable(): T  $fn
     * @param  T  $default
     * @return T
     */
    public static function seguro(string $contexto, callable $fn, mixed $default = null): mixed
    {
        try {
            return $fn();
        } catch (Throwable $e) {
            try {
                Log::warning('Monitoreo: '.$contexto.' falló.', [
                    'exception' => $e::class,
                    'message' => mb_substr($e->getMessage(), 0, 500),
                ]);
            } catch (Throwable) {
                // Ni el log puede romper la request.
            }

            return $default;
        }
    }

    /** Recorta a $max caracteres (null si queda vacío). */
    public static function texto(mixed $valor, int $max): ?string
    {
        if ($valor === null || is_array($valor) || is_object($valor)) {
            return null;
        }

        $texto = trim((string) $valor);

        return $texto === '' ? null : mb_substr($texto, 0, $max);
    }
}
