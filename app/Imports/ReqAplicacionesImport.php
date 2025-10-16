<?php

namespace App\Imports;

use App\Models\ReqAplicaciones;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Illuminate\Support\Facades\Log;

class ReqAplicacionesImport implements ToModel, WithHeadingRow, WithBatchInserts, WithChunkReading
{
    private $rowCounter = 0;
    private $processedRows = 0;
    private $createdRows = 0;
    private $updatedRows = 0;
    private $skippedRows = 0;
    private $errores = [];

    public function model(array $row)
    {
        try {
            $this->rowCounter++;

            Log::info("=== FILA {$this->rowCounter} - RAW ===", ['row' => $row]);

            // Normalizar claves de encabezado
            $row = $this->normalizeRowKeys($row);

            Log::info("=== FILA {$this->rowCounter} - NORMALIZED ===", ['row_keys' => array_keys($row), 'row_values' => array_values($row)]);

            // Saltar encabezados repetidos
            if ($this->looksLikeHeaderRow($row)) {
                Log::info("Saltando fila {$this->rowCounter} que parece encabezado");
                $this->skippedRows++;
                return null;
            }

            // Extraer datos de la fila
            $clave = $this->parseString($this->getValue($row, ['Clave', 'clave', 'AplicacionId', 'aplicacionid']), 50);
            $nombre = $this->parseString($this->getValue($row, ['Nombre', 'nombre']), 100);
            $salon = $this->parseString($this->getValue($row, ['Salon', 'salon', 'SalonTejidoId', 'salontejidoid']), 50);
            $telar = $this->parseString($this->getValue($row, ['Telar', 'telar', 'NoTelarId', 'notelarid']), 50);

            Log::info("Datos extraídos fila {$this->rowCounter}", [
                'clave' => $clave,
                'nombre' => $nombre,
                'salon' => $salon,
                'telar' => $telar
            ]);

            // Validar que los campos requeridos no estén vacíos
            if (empty($clave) || empty($nombre) || empty($salon) || empty($telar)) {
                $this->errores[] = "Fila {$this->rowCounter}: Faltan datos requeridos (Clave: '{$clave}', Nombre: '{$nombre}', Salon: '{$salon}', Telar: '{$telar}')";
                Log::warning("Fila {$this->rowCounter} tiene campos vacíos");
                $this->skippedRows++;
                return null;
            }

            // Verificar si ya existe una aplicación con la misma clave
            $aplicacionExistente = ReqAplicaciones::where('AplicacionId', $clave)->first();

            if ($aplicacionExistente) {
                // Actualizar registro existente
                $aplicacionExistente->update([
                    'Nombre' => $nombre,
                    'SalonTejidoId' => $salon,
                    'NoTelarId' => $telar
                ]);
                $this->processedRows++;
                $this->updatedRows++;
                Log::info("Aplicación actualizada: {$clave}");
                return null;
            } else {
                // Crear nuevo registro
                $modelo = new ReqAplicaciones([
                    'AplicacionId' => $clave,
                    'Nombre' => $nombre,
                    'SalonTejidoId' => $salon,
                    'NoTelarId' => $telar
                ]);

                $this->processedRows++;
                $this->createdRows++;
                Log::info("Nueva aplicación creada: {$clave}");
                return $modelo;
            }

        } catch (\Exception $e) {
            $this->errores[] = "Fila {$this->rowCounter}: {$e->getMessage()}";
            Log::error("Error en fila {$this->rowCounter}: {$e->getMessage()}");
            $this->skippedRows++;
            return null;
        }
    }

    private function normalizeKey($key)
    {
        return strtolower(str_replace([' ', '_', '-'], '', $key));
    }

    private function normalizeRowKeys(array $row): array
    {
        $normalized = [];
        foreach ($row as $key => $value) {
            $normalized[$this->normalizeKey($key)] = $value;
        }
        return $normalized;
    }

    private function getValue(array $row, array $possibleKeys)
    {
        foreach ($possibleKeys as $key) {
            $normalizedKey = $this->normalizeKey($key);
            if (isset($row[$normalizedKey])) {
                return $row[$normalizedKey];
            }
        }
        return null;
    }

    private function looksLikeHeaderRow(array $row): bool
    {
        $headerValues = ['clave', 'aplicacionid', 'nombre', 'salon', 'telar', 'notelarid', 'salontejidoid'];
        $rowValues = array_values($row);

        $normalizedValues = array_map(function($val) {
            return $this->normalizeKey((string)$val);
        }, $rowValues);

        $matches = 0;
        foreach ($normalizedValues as $value) {
            if (in_array($value, $headerValues)) {
                $matches++;
            }
        }

        return $matches >= 3;
    }

    private function parseString($value, $maxLength = null)
    {
        if (is_null($value) || $value === '') {
            Log::debug("parseString: valor nulo o vacío");
            return null;
        }

        $value = (string)$value;
        $value = trim($value);

        if (empty($value)) {
            Log::debug("parseString: valor vacío después de trim: '$value'");
            return null;
        }

        // Truncar si es necesario
        if ($maxLength && strlen($value) > $maxLength) {
            $value = substr($value, 0, $maxLength);
        }

        Log::debug("parseString retorna: '$value'");
        return $value;
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
            'processed_rows' => $this->processedRows,
            'created_rows' => $this->createdRows,
            'updated_rows' => $this->updatedRows,
            'skipped_rows' => $this->skippedRows,
            'total_rows' => $this->rowCounter,
            'errores' => $this->errores
        ];
    }
}
