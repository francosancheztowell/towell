<?php

namespace App\Services\OeeAtadores;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class OeeAtadoresPythonExportService
{
    /**
     * Ejecuta scripts/oee_export.py y actualiza el archivo OEE en disco.
     *
     * @param  string  $statusFilePath  Ruta absoluta al JSON de estado (atomizado por Python)
     */
    public function run(
        string $filePath,
        CarbonImmutable $weekStart,
        CarbonImmutable $weekEnd,
        string $token,
        string $statusFilePath
    ): void {
        $script = config('oee.script_path');
        if (! is_string($script) || $script === '' || ! is_file($script)) {
            throw new RuntimeException('No se encontró el script oee_export.py (config oee.script_path).');
        }

        $connectionName = (string) config('oee.database_connection', 'sqlsrv');
        $db = config("database.connections.{$connectionName}");
        if (! is_array($db)) {
            throw new RuntimeException("Conexión de base de datos [{$connectionName}] no definida.");
        }

        $host = (string) ($db['host'] ?? 'localhost');
        $database = (string) ($db['database'] ?? '');
        $username = (string) ($db['username'] ?? '');
        $password = (string) ($db['password'] ?? '');
        $port = (int) ($db['port'] ?? 1433);

        $binaryParts = $this->pythonBinaryParts();
        $command = array_merge($binaryParts, [
            $script,
            '--week-start', $weekStart->toDateString(),
            '--week-end', $weekEnd->toDateString(),
            '--token', $token,
            '--file-path', $filePath,
            '--status-file', $statusFilePath,
            '--db-host', $host,
            '--db-database', $database,
            '--db-username', $username,
            '--db-password', $password,
            '--db-port', (string) $port,
        ]);

        $timeout = (int) config('oee.python_timeout', 900);
        $result = Process::timeout($timeout)
            ->path(base_path())
            ->run($command);

        if (! $result->successful()) {
            $hint = trim($result->errorOutput().' '.$result->output());
            throw new RuntimeException(
                $hint !== '' ? $hint : 'El proceso Python de exportación OEE terminó con error.'
            );
        }
    }

    /**
     * @return list<string>
     */
    private function pythonBinaryParts(): array
    {
        $binary = trim((string) config('oee.python_binary', 'python'));
        if ($binary === '') {
            return ['python'];
        }

        $parts = preg_split('/\s+/', $binary);

        return is_array($parts) && $parts !== [] ? array_values($parts) : ['python'];
    }
}
