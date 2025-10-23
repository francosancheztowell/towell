<?php

namespace App\Imports;

use App\Models\ReqEficienciaStd;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReqEficienciaStdImport implements ToModel, WithHeadingRow, WithBatchInserts, WithChunkReading
{
    private int $rowCounter = 0;
    private int $processedRows = 0;
    private int $skippedRows = 0;
    private int $createdRows = 0;
    private int $updatedRows = 0;

    private array $errores = [];

    /**
     * Normaliza una clave eliminando acentos, espacios y convirtiendo a minúsculas
     */
    private function normalizeKey($key)
    {
        $key = trim($key);
        $key = $this->removeAccents($key);
        $key = strtolower($key);
        $key = str_replace([' ', '_', '-'], '', $key);
        return $key;
    }

    /**
     * Normaliza todas las claves de un array
     */
    private function normalizeRowKeys(array $row): array
    {
        $normalized = [];
        foreach ($row as $key => $value) {
            $normalized[$this->normalizeKey($key)] = $value;
        }
        return $normalized;
    }

    /**
     * Elimina acentos de una cadena
     */
    private function removeAccents($string)
    {
        $unwanted_array = [
            'á'=>'a', 'Á'=>'A', 'é'=>'e', 'É'=>'E', 'í'=>'i', 'Í'=>'I', 'ó'=>'o', 'Ó'=>'O', 'ú'=>'u', 'Ú'=>'U', 'ñ'=>'n', 'Ñ'=>'N'
        ];
        return strtr($string, $unwanted_array);
    }

    /**
     * Detecta si una fila luce como un encabezado repetido dentro del cuerpo
     */
    private function looksLikeHeaderRow(array $row): bool
    {
        // Verificar si los VALORES son nombres de columnas (no las claves)
        $headerValues = ['salon', 'notelar', 'no telar', 'fibra', 'eficiencia', 'densidad'];
        $rowValues = array_values($row);

        // Normalizar los valores de la fila
        $normalizedValues = array_map(function($val) {
            return $this->normalizeKey((string)$val);
        }, $rowValues);

        // Contar cuántos valores coinciden con nombres de columnas
        $matches = 0;
        foreach ($normalizedValues as $value) {
            if (in_array($value, $headerValues)) {
                $matches++;
            }
        }

        // Si al menos 3 valores son nombres de columnas, es probablemente un encabezado
        return $matches >= 3;
    }

    /**
     * Busca un valor en el array usando múltiples posibles claves
     */
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

    public function model(array $row)
    {
        try {
            $this->rowCounter++;

            // Normalizar claves de encabezado
            $row = $this->normalizeRowKeys($row);

            // Saltar encabezados repetidos
            if ($this->looksLikeHeaderRow($row)) {
                Log::info('Saltando fila que parece encabezado', ['row' => array_keys($row)]);
                $this->skippedRows++;
                return null;
            }

            // Extraer datos de la fila directamente
            $salon = $this->parseString($this->getValue($row, ['Salon', 'salon', 'SalonTejidoId', 'salontejidoid']), 20);
            $telar = $this->parseString($this->getValue($row, ['NoTelar', 'No Telar', 'notelar', 'Telar']), 10);
            $fibra = $this->parseString($this->getValue($row, ['Fibra', 'FibraId', 'fibraid', 'TipoHilo', 'Tipo Hilo', 'tipo_hilo']), 15);
            $eficiencia = $this->parseFloat($this->getValue($row, ['Eficiencia', 'eficiencia']));
            $densidad = $this->parseString($this->getValue($row, ['Densidad', 'densidad']), 10);

            // Log solo cada 100 filas para reducir overhead
            if ($this->rowCounter % 100 === 0) {
                Log::info("Procesando fila {$this->rowCounter}", [
                    'salon' => $salon,
                    'telar' => $telar,
                    'fibra' => $fibra
                ]);
            }

            // Validar que los campos requeridos no estén vacíos
            if (empty($telar) || empty($fibra) || is_null($eficiencia)) {
                $this->errores[] = "Fila {$this->rowCounter}: Faltan datos requeridos (Telar: '{$telar}', Fibra: '{$fibra}', Eficiencia: '{$eficiencia}')";
                $this->skippedRows++;
                return null;
            }

            // Crear nuevo registro directamente
            $modelo = new ReqEficienciaStd([
                'SalonTejidoId' => $salon,
                'NoTelarId' => $telar,
                'FibraId' => $fibra,
                'Eficiencia' => $eficiencia,
                'Densidad' => $densidad ?? 'Normal'
            ]);

            $this->processedRows++;
            $this->createdRows++;
            return $modelo;

        } catch (\Exception $e) {
            Log::error('Error importing row: ' . $e->getMessage(), [
                'row' => $row,
                'row_counter' => $this->rowCounter,
                'error_line' => $e->getLine(),
                'error_file' => $e->getFile()
            ]);
            $this->errores[] = "Fila {$this->rowCounter}: Error al procesar - " . $e->getMessage();
            $this->skippedRows++;
            return null;
        }
    }

    public function batchSize(): int
    {
        return 50; // Reducir tamaño del batch para mejor rendimiento
    }

    public function chunkSize(): int
    {
        return 25; // Reducir chunk size para procesar menos filas a la vez
    }

    /**
     * Obtiene estadísticas de la importación
     */
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

    /**
     * Parse float values
     */
    private function parseFloat($value)
    {
        if (is_null($value) || $value === '') {
            return null;
        }

        // Si es un porcentaje (ej: "78%"), quitarle el símbolo y convertir
        if (is_string($value) && strpos($value, '%') !== false) {
            $value = str_replace('%', '', $value);
            $value = floatval($value) / 100; // Convertir porcentaje a decimal
        } else {
            $value = floatval($value);
        }

        return $value;
    }

    /**
     * Parse string values with max length trimming
     */
    private function parseString($value, int $maxLength)
    {
        if (is_null($value) || $value === '') {
            return null;
        }

        $s = trim((string)$value);

        // Normalize spaces
        $s = preg_replace('/\s+/', ' ', $s);

        // Trim to max length (multibyte-safe)
        if (function_exists('mb_substr')) {
            $s = mb_substr($s, 0, $maxLength, 'UTF-8');
        } else {
            $s = substr($s, 0, $maxLength);
        }

        return $s === '' ? null : $s;
    }



}

