<?php

// app/Imports/ReqAplicacionesImport.php

namespace App\Imports;

use App\Imports\Contracts\ImportConEstadisticas;
use App\Models\Planeacion\ReqAplicaciones;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ReqAplicacionesImport implements ImportConEstadisticas, ToModel, WithBatchInserts, WithChunkReading, WithHeadingRow
{
    private int $rowCounter = 0;

    private int $processedRows = 0;

    private int $createdRows = 0;

    private int $updatedRows = 0;

    private int $skippedRows = 0;

    private array $errores = [];

    /**
     * Mapea cada fila del Excel a un modelo o actualización.
     * Encabezados: clave|aplicacionid, nombre, factor.
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

            // Estructura actual del catálogo: Clave, Nombre, Factor (19-06b). Antes exigía Salón y
            // Telar, que ya no son columnas del modelo, y no leía Factor: el Excel vigente no cargaba nada.
            $clave = mb_substr(trim((string) ($row['clave'] ?? $row['aplicacionid'] ?? '')), 0, 50);
            $nombre = mb_substr(trim((string) ($row['nombre'] ?? '')), 0, 100);
            $factorBruto = $row['factor'] ?? null;
            $factor = is_numeric($factorBruto) ? (float) $factorBruto : null;

            if ($clave === '' || $nombre === '') {
                $this->skippedRows++;
                $this->errores[] = "Fila {$this->rowCounter}: faltan Clave o Nombre";

                return null;
            }

            $existente = ReqAplicaciones::where('AplicacionId', $clave)->first();
            $this->processedRows++;

            if ($existente) {
                $existente->update(['Nombre' => $nombre, 'Factor' => $factor ?? $existente->Factor]);
                $this->updatedRows++;

                return null;
            }

            $this->createdRows++;

            return new ReqAplicaciones(['AplicacionId' => $clave, 'Nombre' => $nombre, 'Factor' => $factor]);
        } catch (\Throwable $e) {
            report($e);
            $this->errores[] = "Fila {$this->rowCounter}: no se pudo procesar la fila";
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
            'created_rows' => $this->createdRows,
            'updated_rows' => $this->updatedRows,
            'skipped_rows' => $this->skippedRows,
            'total_rows' => $this->rowCounter,
            'errores' => $this->errores,
        ];
    }
}
