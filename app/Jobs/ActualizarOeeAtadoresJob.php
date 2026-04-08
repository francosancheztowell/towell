<?php

namespace App\Jobs;

use App\Services\OeeAtadores\OeeAtadoresPythonExportService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ActualizarOeeAtadoresJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public int $tries = 1;

    public int $maxExceptions = 1;

    public function __construct(
        private readonly string $filePath,
        private readonly string $weekStart,
        private readonly string $weekEnd,
        private readonly string $cacheKey,
        private readonly string $token,
    ) {}

    public function handle(OeeAtadoresPythonExportService $exporter): void
    {
        Log::info('Job ActualizarOeeAtadoresJob iniciado', [
            'file_path' => $this->filePath,
            'week_start' => $this->weekStart,
            'week_end' => $this->weekEnd,
            'attempt' => $this->attempts(),
        ]);

        $statusPath = storage_path('app/oee_export_'.$this->token.'.json');

        Cache::store('file')->put($this->cacheKey, [
            'estado' => 'procesando',
            'attempt' => $this->attempts(),
            'status_file' => $statusPath,
        ], 900);

        try {
            $exporter->run(
                $this->filePath,
                CarbonImmutable::parse($this->weekStart),
                CarbonImmutable::parse($this->weekEnd),
                $this->token,
                $statusPath
            );

            $mensaje = $this->readStatusMessage($statusPath, 'Archivo OEE actualizado correctamente.');

            Cache::store('file')->put($this->cacheKey, [
                'estado' => 'completado',
                'mensaje' => $mensaje,
            ], 300);

            Log::info('Job ActualizarOeeAtadoresJob completado exitosamente', [
                'file_path' => $this->filePath,
                'week_start' => $this->weekStart,
                'week_end' => $this->weekEnd,
            ]);
        } catch (\Throwable $e) {
            $mensaje = $this->readStatusMessage($statusPath, $e->getMessage());

            Cache::store('file')->put($this->cacheKey, [
                'estado' => 'error',
                'mensaje' => $mensaje,
                'attempt' => $this->attempts(),
            ], 300);

            Log::error('Job ActualizarOeeAtadoresJob error', [
                'file_path' => $this->filePath,
                'week_start' => $this->weekStart,
                'week_end' => $this->weekEnd,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        $statusPath = storage_path('app/oee_export_'.$this->token.'.json');
        $mensaje = $this->readStatusMessage($statusPath, $e->getMessage());

        Log::error('Job ActualizarOeeAtadoresJob falló definitivamente', [
            'file_path' => $this->filePath,
            'week_start' => $this->weekStart,
            'week_end' => $this->weekEnd,
            'error' => $e->getMessage(),
        ]);

        Cache::store('file')->put($this->cacheKey, [
            'estado' => 'error',
            'mensaje' => $mensaje,
        ], 300);
    }

    private function readStatusMessage(string $statusPath, string $fallback): string
    {
        if (! is_file($statusPath)) {
            return $fallback;
        }

        $decoded = json_decode((string) file_get_contents($statusPath), true);
        if (! is_array($decoded)) {
            return $fallback;
        }

        $m = $decoded['mensaje'] ?? null;

        return is_string($m) && $m !== '' ? $m : $fallback;
    }
}
