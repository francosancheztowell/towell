<?php

namespace App\Imports;

use App\Models\ReqVelocidadStd;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Illuminate\Support\Facades\Log;

class ReqVelocidadStdImport implements ToModel, WithHeadingRow, WithBatchInserts, WithChunkReading
{
    private $rowCounter = 0;
    private $processedRows = 0;
    private $skippedRows = 0;
    private $createdRows = 0;
    private $updatedRows = 0;
    private $errores = [];

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

            // Extraer datos de la fila usando el mismo método que eficiencia
            $salonExcel = $this->parseString($this->getValue($row, ['Salon', 'salon', 'SalonTejidoId', 'salontejidoid']), 20);
            $telar = $this->parseString($this->getValue($row, ['NoTelar', 'No Telar', 'notelar', 'Telar']), 10);
            $fibra = $this->parseString($this->getValue($row, ['Fibra', 'FibraId', 'fibraid']), 15);
            $velocidad = $this->parseFloat($this->getValue($row, ['RPM', 'rpm', 'Velocidad', 'velocidad']));
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
                'velocidad' => $velocidad,
                'densidad' => $densidad
            ]);

            // Validar que los campos requeridos no estén vacíos
            if (empty($telar) || empty($fibra) || is_null($velocidad)) {
                $this->errores[] = "Fila {$this->rowCounter}: Faltan datos requeridos (Telar: '{$telar}', Fibra: '{$fibra}', Velocidad: '{$velocidad}')";
                $this->skippedRows++;
                return null;
            }

            // Verificar si ya existe una velocidad con el mismo telar, fibra y densidad en la BD
            $velocidadExistente = ReqVelocidadStd::where('NoTelarId', $telar)
                                                 ->where('FibraId', $fibra)
                                                 ->where('Densidad', $densidad ?? 'Normal')
                                                 ->first();

            if ($velocidadExistente) {
                // Actualizar registro existente
                $velocidadExistente->update([
                    'SalonTejidoId' => $salon,
                    'Velocidad' => $velocidad,
                    'Densidad' => $densidad ?? 'Normal'
                ]);
                $this->processedRows++;
                $this->updatedRows++;
                Log::info("Velocidad existente actualizada: {$telar} - {$fibra}");
                return null;
            } else {
                // Crear nuevo registro
                $modelo = new ReqVelocidadStd([
                    'SalonTejidoId' => $salon,
                    'NoTelarId' => $telar,
                    'FibraId' => $fibra,
                    'Velocidad' => $velocidad,
                    'Densidad' => $densidad ?? 'Normal'
                ]);

                $this->processedRows++;
                $this->createdRows++;
                Log::info("Nueva velocidad creada: {$telar} - {$fibra} - {$velocidad} RPM");
                return $modelo;
            }

        } catch (\Exception $e) {
            $this->errores[] = "Fila {$this->rowCounter}: {$e->getMessage()}";
            $this->skippedRows++;
            return null;
        }
    }

    private function normalizeKey($key)
    {
        return strtolower(str_replace([' ', '_', '-'], '', $key));
    }

    /**
     * Normaliza las claves de un array de fila
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

    /**
     * Genera el nombre completo del telar
     */
    private function generarNombreTelar($salon, $numeroTelar)
    {
        return $salon . ' ' . $numeroTelar;
    }


    private function looksLikeHeaderRow($row)
    {
        $headerValues = ['notelar', 'no telar', 'fibra', 'rpm', 'velocidad', 'densidad'];
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
            return null;
        }

        $value = (string)$value;
        $value = trim($value);

        // Manejar fórmulas de Excel
        if (strpos($value, '=') === 0) {
            $value = $this->cleanExcelFormula($value);
        }

        // Limitar longitud si se especifica
        if ($maxLength && strlen($value) > $maxLength) {
            $value = substr($value, 0, $maxLength);
        }

        return $value;
    }

    private function cleanExcelFormula($formula)
    {
        $formula = trim($formula);
        if (strpos($formula, '=') === 0) {
            $formula = substr($formula, 1);
        }

        // Extraer texto entre comillas
        if (preg_match('/"([^"]*)"/', $formula, $matches)) {
            return $matches[1];
        }

        return $formula;
    }

    private function parseInteger($value)
    {
        if (is_null($value) || $value === '') {
            return null;
        }

        $value = (string)$value;
        $value = trim(str_replace([' RPM', 'RPM'], '', $value));
        $intValue = intval($value);

        return $intValue > 0 ? $intValue : null;
    }

    private function parseFloat($value)
    {
        if (is_null($value) || $value === '') {
            return null;
        }

        $value = (string)$value;
        $value = trim(str_replace([' RPM', 'RPM'], '', $value));
        $floatValue = floatval($value);

        return $floatValue > 0 ? $floatValue : null;
    }

    private function extraerSalon($telar)
    {
        if (!$telar) return null;

        $parts = explode(' ', $telar);
        return count($parts) > 0 ? $parts[0] : null;
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
