<?php

namespace App\Imports;

use App\Models\Planeacion\ReqTelares;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ReqTelaresImport implements ToModel, WithHeadingRow, WithBatchInserts, WithChunkReading
{
    private int $rowCounter = 0;
    private int $processedRows = 0;
    private int $skippedRows = 0;
    private int $createdRows = 0;
    private int $updatedRows = 0;
    private array $errores = [];

    /**
     * Obtiene el primer valor no vacío para un conjunto de alias de encabezado, con normalización.
     */
    private function getValue(array $row, array $aliases)
    {
        foreach ($aliases as $alias) {
            $key = $this->normalizeKey($alias);
            if (array_key_exists($key, $row)) {
                $val = $row[$key];
                if ($val !== '' && $val !== null) {
                    return $val;
                }
            }
        }
        return null;
    }

    /** Normaliza una clave de encabezado: minúsculas, sin acentos y sólo [a-z0-9_] */
    private function normalizeKey(string $key): string
    {
        $s = mb_strtolower(trim($key), 'UTF-8');
        $s = $this->removeAccents($s);
        // Reemplaza cualquier cosa que no sea a-z0-9 por guion bajo
        $s = preg_replace('/[^a-z0-9]+/u', '_', $s);
        // Colapsa múltiples guiones bajos
        $s = preg_replace('/_+/', '_', $s);
        return trim($s, '_');
    }

    /** Normaliza todas las claves del arreglo de fila */
    private function normalizeRowKeys(array $row): array
    {
        $out = [];
        foreach ($row as $k => $v) {
            $out[$this->normalizeKey((string)$k)] = $v;
        }
        return $out;
    }

    /**
     * Elimina acentos de un string de forma segura.
     */
    private function removeAccents(string $value): string
    {
        $trans = [
            'á' => 'a','é' => 'e','í' => 'i','ó' => 'o','ú' => 'u','ñ' => 'n',
            'Á' => 'A','É' => 'E','Í' => 'I','Ó' => 'O','Ú' => 'U','Ñ' => 'N',
        ];
        return strtr($value, $trans);
    }

    /** Detecta si una fila luce como un encabezado repetido dentro del cuerpo */
    private function looksLikeHeaderRow(array $row): bool
    {
        // Solo detectar como encabezado si TODAS las claves son exactamente los nombres de columnas
        $exactHeaders = ['Salon', 'TELAR', 'Nombre', 'Grupo'];
        $rowKeys = array_keys($row);

        // Si hay exactamente 4 claves y todas coinciden con los encabezados
        if (count($rowKeys) === 4) {
            $matches = 0;
            foreach ($exactHeaders as $header) {
                if (in_array($header, $rowKeys)) {
                    $matches++;
                }
            }
            // Solo es encabezado si coinciden todas las 4 columnas
            return $matches === 4;
        }

        return false;
    }

    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        try {
            $this->rowCounter++;

            // Log para debugging
            Log::info("Procesando fila {$this->rowCounter}", [
                'row_keys' => array_keys($row),
                'row_values' => array_values($row),
                'raw_row' => $row
            ]);

            // Normalizar claves de encabezado para búsqueda robusta
            $row = $this->normalizeRowKeys($row);

            // Saltar filas que son encabezados repetidos dentro del cuerpo
            if ($this->looksLikeHeaderRow($row)) {
                Log::info('Saltando fila que parece encabezado', ['row' => array_keys($row)]);
                $this->skippedRows++;
                return null;
            }

            // Extraer datos de la fila
            $salon = $this->parseString($this->getValue($row, ['Salon', 'salón', 'salon_tejido_id']), 20);
            $telar = $this->parseString($this->getValue($row, ['TELAR', 'tela', 'no_telar_id']), 10);
            $grupo = $this->parseString($this->getValue($row, ['Grupo', 'grupo', 'categoría', 'categoria']), 30);

            // Generar nombre automáticamente basado en el salón y número de telar
            $nombre = $this->generarNombre($salon, $telar);

            Log::info("Datos extraídos fila {$this->rowCounter}", [
                'salon' => $salon,
                'telar' => $telar,
                'nombre_generado' => $nombre,
                'grupo' => $grupo
            ]);

            // Validar que los campos requeridos no estén vacíos
            if (empty($salon) || empty($telar)) {
                $this->errores[] = "Fila {$this->rowCounter}: Faltan datos requeridos (Salon: '{$salon}', Telar: '{$telar}')";
                $this->skippedRows++;
                return null;
            }

            // Verificar si ya existe un telar con el mismo salón y número
            $telarExistente = ReqTelares::where('SalonTejidoId', $salon)
                                      ->where('NoTelarId', $telar)
                                      ->first();

            if ($telarExistente) {
                // Actualizar registro existente
                $telarExistente->update([
                    'Nombre' => $nombre,
                    'Grupo' => $grupo
                ]);
                $this->processedRows++;
                $this->updatedRows++;
                Log::info("Telar existente actualizado: {$salon} - {$telar}");
                return null; // No crear nuevo modelo, solo actualizar
            } else {
                // Crear nuevo registro
                $modelo = new ReqTelares([
                    'SalonTejidoId' => $salon,
                    'NoTelarId' => $telar,
                    'Nombre' => $nombre,
                    'Grupo' => $grupo
                ]);

                $this->processedRows++;
                $this->createdRows++;
                Log::info("Nuevo telar creado: {$salon} - {$telar}");
                return $modelo;
            }

        } catch (\Exception $e) {
            // Log the error but continue processing
            Log::error('Error importing row: ' . $e->getMessage(), [
                'row' => $row,
                'row_counter' => $this->rowCounter,
                'error_line' => $e->getLine(),
                'error_file' => $e->getFile()
            ]);

            $this->errores[] = "Fila {$this->rowCounter}: Error al procesar - " . $e->getMessage();
            $this->skippedRows++;
            return null; // Skip this row
        }
    }

    /**
     * @return int
     */
    public function batchSize(): int
    {
        return 100;
    }

    /**
     * Tamaño de lectura por chunks para controlar memoria
     */
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
     * Parse string values with max length trimming
     */
    private function parseString($value, int $maxLength)
    {
        if (is_null($value) || $value === '') {
            return null;
        }

        $s = trim((string)$value);

        // Detectar y limpiar fórmulas de Excel
        if (strpos($s, '=') === 0) {
            $s = $this->cleanExcelFormula($s);
        }

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
     * Limpia fórmulas de Excel para extraer valores legibles
     */
    private function cleanExcelFormula($formula)
    {
        // Si es una fórmula de concatenación como =+"JAC "&B2
        if (preg_match('/=\+?"([^"]+)"&/', $formula, $matches)) {
            $baseText = $matches[1];

            // Extraer el número de la celda (B2, B3, etc.)
            if (preg_match('/&([A-Z]+\d+)/', $formula, $cellMatches)) {
                $cellRef = $cellMatches[1];
                // Extraer solo el número de la celda
                if (preg_match('/(\d+)/', $cellRef, $numberMatches)) {
                    $number = $numberMatches[1];
                    return $baseText . $number;
                }
            }
        }

        // Si no se puede procesar la fórmula, devolver un valor por defecto
        return 'N/A';
    }

    /**
     * Genera el nombre del telar basado en el salón y número
     */
    private function generarNombre($salon, $telar)
    {
        if (empty($salon) || empty($telar)) {
            return null;
        }

        // Convertir a mayúsculas para comparación
        $salonUpper = strtoupper(trim($salon));

        // Determinar el prefijo basado en el salón
        if (strpos($salonUpper, 'JACQUARD') !== false) {
            $prefijo = 'JAC';
        } elseif (strpos($salonUpper, 'SMITH') !== false) {
            $prefijo = 'Smith';
        } else {
            // Si no coincide con los patrones conocidos, usar las primeras 3 letras del salón
            $prefijo = strtoupper(substr(trim($salon), 0, 3));
        }

        return $prefijo . ' ' . $telar;
    }
}
