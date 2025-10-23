<?php
// app/Imports/ReqAplicacionesImport.php

namespace App\Imports;

use App\Models\ReqAplicaciones;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class ReqAplicacionesImport implements ToModel, WithHeadingRow, WithBatchInserts, WithChunkReading
{
    private int $rowCounter   = 0;
    private int $processedRows= 0;
    private int $createdRows  = 0;
    private int $updatedRows  = 0;
    private int $skippedRows  = 0;
    private array $errores    = [];

    /**
     * Mapea cada fila del Excel a un modelo o actualización.
     * Acepta encabezados flexibles: clave|aplicacionid, salon|salontejidoid, telar|notelarid
     */
    public function model(array $row)
    {
        try {
            $this->rowCounter++;

            // Saltar filas completamente vacías
            if (empty(array_filter($row, fn ($v) => $v !== null && $v !== ''))) {
                $this->skippedRows++;
                return null;
            }

            // Extraer con fallback de encabezados
            $clave  = trim((string)($row['clave'] ?? $row['aplicacionid'] ?? ''));
            $nombre = trim((string)($row['nombre'] ?? ''));
            $salon  = trim((string)($row['salon'] ?? $row['salontejidoid'] ?? ''));
            $telar  = trim((string)($row['telar'] ?? $row['notelarid'] ?? ''));

            // Validaciones mínimas
            if ($clave === '' || $nombre === '' || $salon === '' || $telar === '') {
                $this->skippedRows++;
                return null;
            }

            // Truncamientos preventivos
            $clave  = mb_substr($clave,  0, 50);
            $nombre = mb_substr($nombre, 0, 100);
            $salon  = mb_substr($salon,  0, 50);
            $telar  = mb_substr($telar,  0, 50);

            // ¿Existe ya por clave?
            $existente = ReqAplicaciones::where('AplicacionId', $clave)->first();

            if ($existente) {
                $existente->update([
                    'Nombre'        => $nombre,
                    'SalonTejidoId' => $salon,
                    'NoTelarId'     => $telar,
                ]);
                $this->processedRows++;
                $this->updatedRows++;
                return null; // No crear un nuevo modelo: ya se actualizó
            }

            // Crear nuevo registro
            $this->processedRows++;
            $this->createdRows++;

            return new ReqAplicaciones([
                'AplicacionId'  => $clave,
                'Nombre'        => $nombre,
                'SalonTejidoId' => $salon,
                'NoTelarId'     => $telar,
            ]);
        } catch (\Throwable $e) {
            $this->errores[] = "Fila {$this->rowCounter}: {$e->getMessage()}";
            $this->skippedRows++;
            Log::warning('ReqAplicacionesImport error', ['row' => $row, 'ex' => $e]);
            return null;
        }
    }

    public function batchSize(): int
    {
        return 100;
    }

    public function chunkSize(): int
    {
        return 100;
    }

    /** Estadísticas para el controlador */
    public function getStats(): array
    {
        return [
            'processed_rows' => $this->processedRows,
            'created_rows'   => $this->createdRows,
            'updated_rows'   => $this->updatedRows,
            'skipped_rows'   => $this->skippedRows,
            'total_rows'     => $this->rowCounter,
            'errores'        => $this->errores,
        ];
    }
}
