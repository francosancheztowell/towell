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
    private int $duplicadosRows = 0; // Duplicados dentro del mismo Excel
    private array $errores = [];
    private array $processedInThisImport = []; // Para rastrear registros ya procesados en esta importación

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

            // Log para debugging - Antes de normalizar
            Log::info("=== FILA {$this->rowCounter} - ANTES DE NORMALIZAR ===", [
                'row_keys_original' => array_keys($row),
                'row_values_original' => array_values($row)
            ]);

            // Normalizar claves de encabezado
            $row = $this->normalizeRowKeys($row);

            // Log después de normalizar
            Log::info("=== FILA {$this->rowCounter} - DESPUÉS DE NORMALIZAR ===", [
                'row_keys_normalized' => array_keys($row),
                'row_values_normalized' => array_values($row)
            ]);

            // Saltar encabezados repetidos
            if ($this->looksLikeHeaderRow($row)) {
                Log::info('Saltando fila que parece encabezado', ['row' => array_keys($row)]);
                $this->skippedRows++;
                return null;
            }

            // Extraer datos de la fila
            $salonExcel = $this->parseString($this->getValue($row, ['Salon', 'salon', 'SalonTejidoId', 'salontejidoid']), 20);
            $telar = $this->parseString($this->getValue($row, ['NoTelar', 'No Telar', 'notelar', 'Telar']), 10);
            $fibra = $this->parseString($this->getValue($row, ['Fibra', 'FibraId', 'fibraid', 'TipoHilo', 'Tipo Hilo', 'tipo_hilo']), 15);
            $eficiencia = $this->parseFloat($this->getValue($row, ['Eficiencia', 'eficiencia']));
            $densidad = $this->parseString($this->getValue($row, ['Densidad', 'densidad']), 10);

            // Si viene el salón en el Excel, usarlo; si no, extraerlo del nombre del telar
            $salon = !empty($salonExcel) ? $salonExcel : $this->extraerSalon($telar);

            // Si el telar es solo un número (ej: "201"), generar el nombre completo (ej: "JAC 201")
            if (!empty($salon) && is_numeric($telar)) {
                $telar = $this->generarNombreTelar($salon, $telar);
            }

            Log::info("Datos extraídos fila {$this->rowCounter}", [
                'salon_excel' => $salonExcel,
                'salon' => $salon,
                'telar' => $telar,
                'fibra' => $fibra,
                'eficiencia' => $eficiencia,
                'densidad' => $densidad
            ]);

            // Validar que los campos requeridos no estén vacíos
            if (empty($telar) || empty($fibra) || is_null($eficiencia)) {
                $this->errores[] = "Fila {$this->rowCounter}: Faltan datos requeridos (Telar: '{$telar}', Fibra: '{$fibra}', Eficiencia: '{$eficiencia}')";
                $this->skippedRows++;
                return null;
            }

            // Crear clave única para este registro (telar + fibra + densidad)
            $uniqueKey = $telar . '|' . $fibra . '|' . ($densidad ?? 'Normal');

            // Verificar si ya existe una eficiencia con el mismo telar y fibra en la BD
            $eficienciaExistente = ReqEficienciaStd::where('NoTelarId', $telar)
                                                  ->where('FibraId', $fibra)
                                                  ->where('Densidad', $densidad ?? 'Normal')
                                                  ->first();

            if ($eficienciaExistente) {
                // Actualizar registro existente
                $eficienciaExistente->update([
                    'SalonTejidoId' => $salon,
                    'Eficiencia' => $eficiencia,
                    'Densidad' => $densidad ?? 'Normal'
                ]);
                $this->processedRows++;
                $this->updatedRows++;
                Log::info("Eficiencia existente actualizada: {$telar} - {$fibra}");
                return null;
            } else {
                // Crear nuevo registro
                $modelo = new ReqEficienciaStd([
                    'SalonTejidoId' => $salon,
                    'NoTelarId' => $telar,
                    'FibraId' => $fibra,
                    'Eficiencia' => $eficiencia,
                    'Densidad' => $densidad ?? 'Normal'
                ]);

                $this->processedRows++;
                $this->createdRows++;
                Log::info("Nueva eficiencia creada: {$telar} - {$fibra}");
                return $modelo;
            }

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
        return 100;
    }

    public function chunkSize(): int
    {
        return 100;
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

    /**
     * Extraer el salón del nombre del telar
     */
    private function extraerSalon($nombreTelar)
    {
        if (empty($nombreTelar)) {
            return 'Desconocido';
        }

        $nombreTelar = trim($nombreTelar);

        // Patrones conocidos
        if (stripos($nombreTelar, 'JAC') !== false) {
            return 'Jacquard';
        } elseif (stripos($nombreTelar, 'Smith') !== false) {
            return 'Smith';
        } elseif (stripos($nombreTelar, 'Itema') !== false) {
            return 'Itema';
        }

        // Si no coincide con ningún patrón, extraer la primera palabra
        $partes = explode(' ', $nombreTelar);
        return $partes[0] ?? 'Desconocido';
    }

    /**
     * Generar el nombre completo del telar basado en el salón y número
     */
    private function generarNombreTelar($salon, $numeroTelar)
    {
        if (empty($salon) || empty($numeroTelar)) {
            return $numeroTelar;
        }

        // Convertir salón a mayúsculas para comparación
        $salonUpper = strtoupper(trim($salon));

        // Determinar el prefijo basado en el salón
        if (strpos($salonUpper, 'JACQUARD') !== false || strpos($salonUpper, 'JAC') !== false) {
            $prefijo = 'JAC';
        } elseif (strpos($salonUpper, 'SMITH') !== false) {
            $prefijo = 'Smith';
        } elseif (strpos($salonUpper, 'ITEMA') !== false) {
            $prefijo = 'Itema';
        } else {
            // Si no coincide con los patrones conocidos, usar las primeras 3 letras del salón
            $prefijo = strtoupper(substr(trim($salon), 0, 3));
        }

        return $prefijo . ' ' . $numeroTelar;
    }
}

