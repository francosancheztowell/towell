<?php

namespace App\Jobs;

use App\Services\OeeAtadores\OeeAtadoresFileService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class ActualizarOeeAtadoresJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(
        private readonly string $filePath,
        private readonly string $weekStart,
        private readonly string $weekEnd,
        private readonly string $cacheKey
    ) {}

    public function handle(): void
    {
        Cache::put($this->cacheKey, ['estado' => 'procesando'], 600);

        $service = new OeeAtadoresFileService($this->filePath);
        $service->actualizarArchivo(
            CarbonImmutable::parse($this->weekStart),
            CarbonImmutable::parse($this->weekEnd)
        );

        Cache::put($this->cacheKey, ['estado' => 'completado'], 300);
    }

    public function failed(\Throwable $e): void
    {
        Cache::put($this->cacheKey, [
            'estado' => 'error',
            'mensaje' => $e->getMessage(),
        ], 300);
    }
}
