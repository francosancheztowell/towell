<?php

namespace App\Jobs;

use App\Services\OeeAtadores\OeeAtadoresFileService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ActualizarOeeAtadoresJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900; // 15 minutos

    public int $tries = 3;

    public int $maxExceptions = 3;

    public function __construct(
        private readonly string $filePath,
        private readonly string $weekStart,
        private readonly string $weekEnd,
        private readonly string $cacheKey
    ) {}

    public function handle(): void
    {
        Log::info('Job ActualizarOeeAtadoresJob iniciado', [
            'file_path' => $this->filePath,
            'week_start' => $this->weekStart,
            'week_end' => $this->weekEnd,
            'attempt' => $this->attempts(),
        ]);

        Cache::put($this->cacheKey, ['estado' => 'procesando', 'attempt' => $this->attempts()], 900);

        try {
            $service = new OeeAtadoresFileService($this->filePath);
            $service->actualizarArchivo(
                CarbonImmutable::parse($this->weekStart),
                CarbonImmutable::parse($this->weekEnd)
            );

            Cache::put($this->cacheKey, ['estado' => 'completado'], 300);

            Log::info('Job ActualizarOeeAtadoresJob completado exitosamente', [
                'file_path' => $this->filePath,
                'week_start' => $this->weekStart,
                'week_end' => $this->weekEnd,
            ]);
        } catch (\Throwable $e) {
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
        Log::error('Job ActualizarOeeAtadoresJob falló definitivamente', [
            'file_path' => $this->filePath,
            'week_start' => $this->weekStart,
            'week_end' => $this->weekEnd,
            'error' => $e->getMessage(),
        ]);

        Cache::put($this->cacheKey, [
            'estado' => 'error',
            'mensaje' => $e->getMessage(),
        ], 300);
    }
}
