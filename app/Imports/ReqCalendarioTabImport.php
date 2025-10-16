<?php

namespace App\Imports;

use App\Models\ReqCalendarioTab;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Illuminate\Support\Facades\Log;

class ReqCalendarioTabImport implements ToModel, WithHeadingRow, WithBatchInserts, WithChunkReading
{
    private $procesados = 0;
    private $creados = 0;
    private $errores = [];
    private $rowCounter = 0;

    public function model(array $row)
    {
        try {
            $this->rowCounter++;

            // ⚡ Detectar rápidamente filas vacías
            $allEmpty = true;
            foreach ($row as $cell) {
                if (!empty(trim((string)$cell))) {
                    $allEmpty = false;
                    break;
                }
            }
            if ($allEmpty) {
                return null;
            }

            $calendarioId = isset($row['no_calendario']) ? trim((string)$row['no_calendario']) : null;
            $nombre = isset($row['nombre']) ? trim((string)$row['nombre']) : null;

            if (empty($calendarioId) || empty($nombre)) {
                Log::warning("Fila {$this->rowCounter}: Datos incompletos");
                return null;
            }

            // Truncar
            $calendarioId = substr($calendarioId, 0, 20);
            $nombre = substr($nombre, 0, 255);

            ReqCalendarioTab::updateOrCreate(
                ['CalendarioId' => $calendarioId],
                ['Nombre' => $nombre]
            );

            $this->procesados++;
            $this->creados++;
            Log::info("✓ Calendario guardado: {$calendarioId}");
            return null;

        } catch (\Exception $e) {
            $this->errores[] = "Fila {$this->rowCounter}: {$e->getMessage()}";
            Log::error("✗ Error fila {$this->rowCounter}: {$e->getMessage()}");
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

    public function getStats(): array
    {
        return [
            'procesados' => $this->procesados,
            'creados' => $this->creados,
            'errores' => $this->errores
        ];
    }
}
