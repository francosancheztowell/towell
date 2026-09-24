<?php

namespace App\Services\Monitoreo;

/**
 * Conexión SQLite dedicada de Laravel Pulse (fase 14).
 *
 * Pulse solo soporta mysql/mariadb/pgsql/sqlite; con sqlsrv lanza "Unsupported
 * database driver". Se registra en runtime para no tocar config/database.php
 * (gitignored + skip-worktree).
 */
final class PulseConexion
{
    public const NOMBRE = 'pulse';

    public static function registrar(): void
    {
        $clave = 'database.connections.'.self::NOMBRE;
        if (is_array(config($clave))) {
            return;
        }

        $archivo = (string) (config('pulse.storage.database.sqlite')
            ?: (app()->runningUnitTests() ? ':memory:' : storage_path('pulse/pulse.sqlite')));

        config([$clave => [
            'driver' => 'sqlite',
            'database' => $archivo,
            'prefix' => '',
            'foreign_key_constraints' => false,
            'busy_timeout' => 5000,
            'journal_mode' => 'wal',
            'synchronous' => 'normal',
        ]]);

        if (config('pulse.enabled')) {
            self::asegurarArchivo($archivo);
        }
    }

    /** El conector de SQLite truena si el archivo no existe: se crea (vacío) la primera vez. */
    public static function asegurarArchivo(string $archivo): void
    {
        if ($archivo === ':memory:' || is_file($archivo)) {
            return;
        }

        Monitoreo::seguro('crear archivo de Pulse', function () use ($archivo): void {
            if (! is_dir(dirname($archivo))) {
                mkdir(dirname($archivo), 0775, true);
            }
            touch($archivo);
        });
    }
}
