<?php

namespace App\Imports;

use App\Models\ReqEficienciaStd;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ReqEficienciaStdImport implements ToCollection, WithHeadingRow, WithChunkReading
{
    private int $rowCounter = 0;
    private int $processedRows = 0;
    private int $skippedRows = 0;
    private int $createdRows = 0;
    private int $updatedRows = 0;

    private array $errores = [];

    /**
     * Normaliza una clave para buscar en el array (case-insensitive)
     * Maneja espacios, guiones, guiones bajos, puntos, etc.
     */
    private function normalizeKey($key)
    {
        if (is_null($key)) {
            return null;
        }
        $key = trim((string)$key);
        $key = strtolower($key);
        // Normalizar espacios múltiples a uno
        $key = preg_replace('/\s+/', ' ', $key);
        // Normalizar guiones y guiones bajos a espacios para comparación flexible
        // Pero primero intentamos preservar el formato original
        return trim($key);
    }

    /**
     * Obtiene el valor del array usando la clave normalizada
     */
    private function getValueByKey(Collection $row, string $exactKey, array $alternatives = [])
    {
        // Normalizar la clave exacta una sola vez
        $normalizedExact = $this->normalizeKey($exactKey);

        // Primero intentar con la clave exacta (case-insensitive, preservando espacios)
        foreach ($row->keys() as $key) {
            $normalizedKey = $this->normalizeKey($key);
            if ($normalizedKey === $normalizedExact) {
                $value = $row->get($key);
                if ($value !== null && $value !== '') {
                    return $value;
                }
            }
        }

        // Si no se encuentra, intentar con alternativas
        foreach ($alternatives as $alt) {
            $normalizedAlt = $this->normalizeKey($alt);
            foreach ($row->keys() as $key) {
                $normalizedKey = $this->normalizeKey($key);
                if ($normalizedKey === $normalizedAlt) {
                    $value = $row->get($key);
                    if ($value !== null && $value !== '') {
                        return $value;
                    }
                }
            }
        }

        // Si aún no se encuentra, intentar búsqueda parcial (contiene)
        foreach ($row->keys() as $key) {
            $normalizedKey = $this->normalizeKey($key);
            // Si la clave normalizada contiene la búsqueda normalizada
            if (strpos($normalizedKey, $normalizedExact) !== false || strpos($normalizedExact, $normalizedKey) !== false) {
                $value = $row->get($key);
                if ($value !== null && $value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            $this->rowCounter++;

            try {
                // Log de las claves disponibles solo en la primera fila para debugging
                if ($this->rowCounter === 1) {
                }

                // Extraer valores usando los encabezados exactos: Salon, No Telar, Fibra, Eficiencia, Densidad
                $salon = $this->parseString($this->getValueByKey($row, 'Salon', ['salon', 'SalonTejidoId', 'salontejidoid']), 20);

                // Buscar "No Telar" con más variaciones posibles y normalizar a dígitos
                $telar = $this->parseTelar($this->getValueByKey($row, 'No Telar', [
                    'NoTelar',
                    'notelar',
                    'no telar',
                    'no_telar',
                    'Telar',
                    'telar',
                    'NoTelarId',
                    'notelarid',
                    'no telar id',
                    'No. Telar',
                    'No. de Telar'
                ]), 10);

                $fibra = $this->parseString($this->getValueByKey($row, 'Fibra', ['FibraId', 'fibra', 'fibraid', 'TipoHilo', 'Tipo de Hilo', 'Hilo', 'hilo']), 120);
                $eficiencia = $this->parseFloat($this->getValueByKey($row, 'Eficiencia', ['eficiencia', 'EficienciaStd']));
                $densidad = $this->parseString($this->getValueByKey($row, 'Densidad', ['densidad']), 10) ?? 'Normal';

                // Validar que los campos requeridos no estén vacíos
                if (empty($telar) || empty($fibra) || is_null($eficiencia)) {
                    $this->errores[] = "Fila {$this->rowCounter}: Faltan datos requeridos (Telar: '{$telar}', Fibra: '{$fibra}', Eficiencia: '{$eficiencia}')";
                    $this->skippedRows++;
                    continue;
                }

                // Usar updateOrCreate para evitar duplicados
                // Buscar por: SalonTejidoId, NoTelarId, FibraId, Densidad
                $existe = ReqEficienciaStd::where('SalonTejidoId', $salon ?? 'JACQUARD')
                    ->where('NoTelarId', $telar)
                    ->where('FibraId', $fibra)
                    ->where('Densidad', $densidad)
                    ->first();

                if ($existe) {
                    // Actualizar registro existente
                    $existe->update([
                        'Eficiencia' => $eficiencia
                    ]);
                    $this->updatedRows++;
                } else {
                    // Crear nuevo registro
                    ReqEficienciaStd::create([
                        'SalonTejidoId' => $salon ?? 'JACQUARD',
                        'NoTelarId' => $telar,
                        'FibraId' => $fibra,
                        'Eficiencia' => $eficiencia,
                        'Densidad' => $densidad
                    ]);
                    $this->createdRows++;
                }

                $this->processedRows++;

            } catch (\Exception $e) {
                Log::error('Error importing row: ' . $e->getMessage(), [
                    'row' => $row->toArray(),
                    'row_counter' => $this->rowCounter,
                    'error_line' => $e->getLine(),
                    'error_file' => $e->getFile()
                ]);
                $this->errores[] = "Fila {$this->rowCounter}: Error al procesar - " . $e->getMessage();
                $this->skippedRows++;
            }
        }
    }

    public function chunkSize(): int
    {
        return 100; // Procesar 100 filas a la vez
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
     * Normaliza NoTelar permitiendo solo dígitos; si no hay dígitos, retorna null.
     */
    private function parseTelar($value, int $maxLength = 10)
    {
        if (is_null($value) || $value === '') {
            return null;
        }

        // Forzar a string y limpiar
        $value = (string) $value;
        $value = trim($value);

        // Si viene una fórmula de Excel, limpiarla
        if (strpos($value, '=') === 0) {
            $value = $this->cleanExcelFormula($value);
        }

        // Conservar solo dígitos
        $digits = preg_replace('/\D+/', '', $value);

        if ($digits === '' || $digits === null) {
            return null;
        }

        if ($maxLength && strlen($digits) > $maxLength) {
            $digits = substr($digits, 0, $maxLength);
        }

        return $digits;
    }

    /**
     * Limpia una fórmula de Excel extrayendo texto entre comillas si existe.
     */
    private function cleanExcelFormula($formula)
    {
        $formula = trim($formula);
        if (strpos($formula, '=') === 0) {
            $formula = substr($formula, 1);
        }

        // Extraer texto entre comillas si existe
        if (preg_match('/"([^"]*)"/', $formula, $matches)) {
            return $matches[1];
        }

        return $formula;
    }

    /**
     * Parse string values with max length trimming
     * Mejorado para preservar el contenido completo
     */
    private function parseString($value, int $maxLength)
    {
        if (is_null($value) || $value === '') {
            return null;
        }

        // Convertir a string preservando el valor original
        $s = (string)$value;

        // Limpiar solo espacios al inicio y final, NO normalizar espacios internos
        // para preservar el formato original del texto
        $s = trim($s);

        // Si está vacío después del trim, retornar null
        if ($s === '') {
            return null;
        }

        // Solo truncar si excede el máximo, usando multibyte-safe
        if (function_exists('mb_strlen') && mb_strlen($s, 'UTF-8') > $maxLength) {
            if (function_exists('mb_substr')) {
                $s = mb_substr($s, 0, $maxLength, 'UTF-8');
            } else {
                $s = substr($s, 0, $maxLength);
            }
        } elseif (!function_exists('mb_strlen') && strlen($s) > $maxLength) {
            $s = substr($s, 0, $maxLength);
        }

        return $s;
    }



}

