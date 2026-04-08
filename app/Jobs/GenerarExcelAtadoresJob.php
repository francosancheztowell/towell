<?php

namespace App\Jobs;

use App\Exports\Reporte00EAtadoresRangoExport;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class GenerarExcelAtadoresJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 720;

    public function __construct(
        private string $lunesInicio,
        private string $lunesFin,
        private string $cacheKey
    ) {
    }

    public function handle(): void
    {
        Cache::store('file')->put($this->cacheKey, ['estado' => 'procesando'], 600);

        try {
            $lunesInicio = CarbonImmutable::parse($this->lunesInicio);
            $lunesFin = CarbonImmutable::parse($this->lunesFin);

            $nombreArchivo = 'OEE_Atadores_'
                . $lunesInicio->format('d-m-Y')
                . '_al_'
                . $lunesFin->addDays(6)->format('d-m-Y')
                . '.xlsx';

            $export = new Reporte00EAtadoresRangoExport($lunesInicio, $lunesFin);
            $tempPath = 'temp/' . uniqid() . '_' . $nombreArchivo;

            Excel::store($export, $tempPath, 'local');

            Cache::store('file')->put($this->cacheKey, [
                'estado' => 'completado',
                'path' => $tempPath,
                'filename' => $nombreArchivo,
            ], 600);
        } catch (\Throwable $e) {
            Log::error('Error al generar Excel de Atadores en job', [
                'cache_key' => $this->cacheKey,
                'error' => $e->getMessage(),
            ]);
            Cache::store('file')->put($this->cacheKey, [
                'estado' => 'error',
                'mensaje' => 'No se pudo generar el archivo: ' . $e->getMessage(),
            ], 600);
        }
    }
}
