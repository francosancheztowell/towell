<?php

namespace App\Imports;

use App\Models\ReqCalendarioLine;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Illuminate\Support\Facades\Log;

class ReqCalendarioLineImport implements ToModel, WithHeadingRow, WithBatchInserts, WithChunkReading
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
            $fechaInicio = isset($row['inicio_fecha_hora']) || isset($row['Inicio (Fecha Hora)']) ?
                            trim((string)($row['inicio_fecha_hora'] ?? $row['Inicio (Fecha Hora)'])) : null;
            $fechaFin = isset($row['fin_fecha_hora']) || isset($row['Fin (Fecha Hora)']) ?
                        trim((string)($row['fin_fecha_hora'] ?? $row['Fin (Fecha Hora)'])) : null;
            $horas = isset($row['horas']) ? trim((string)$row['horas']) : null;
            $turno = isset($row['turno']) ? trim((string)$row['turno']) : null;

            // 🔍 Logging detallado de lo que se extrae del Excel
            Log::info("Fila {$this->rowCounter} - Datos crudos del Excel:", [
                'calendarioId' => $calendarioId,
                'fechaInicio' => $fechaInicio,
                'fechaFin' => $fechaFin,
                'horas' => $horas,
                'turno' => $turno
            ]);

            if (empty($calendarioId) || empty($fechaInicio) || empty($fechaFin)) {
                Log::warning("Fila {$this->rowCounter}: Datos incompletos");
                return null;
            }

            // Truncar
            $calendarioId = substr($calendarioId, 0, 20);

            // Parsear fechas
            $fechaInicioFormato = $this->parseDatetime($fechaInicio);
            $fechaFinFormato = $this->parseDatetime($fechaFin);

            if ($fechaInicioFormato === null || $fechaFinFormato === null) {
                $this->errores[] = "Fila {$this->rowCounter}: Fechas inválidas";
                Log::warning("Fila {$this->rowCounter}: Fechas no válidas - Inicio: {$fechaInicio}, Fin: {$fechaFin}");
                return null;
            }

            $horasNum = !empty($horas) ? (float)$horas : 0;
            $turnoNum = !empty($turno) ? (int)$turno : 0;

            ReqCalendarioLine::create([
                'CalendarioId' => $calendarioId,
                'FechaInicio' => $fechaInicioFormato,
                'FechaFin' => $fechaFinFormato,
                'HorasTurno' => $horasNum,
                'Turno' => $turnoNum
            ]);

            $this->procesados++;
            $this->creados++;
            Log::info("✓ Línea guardada: {$calendarioId} turno {$turnoNum}");
            return null;

        } catch (\Exception $e) {
            $this->errores[] = "Fila {$this->rowCounter}: {$e->getMessage()}";
            Log::error("✗ Error fila {$this->rowCounter}: {$e->getMessage()}");
            return null;
        }
    }

    private function parseDatetime($value)
    {
        if (empty($value)) {
            return null;
        }

        $originalValue = $value;
        $value = (string)$value;
        $value = trim($value);

        if (empty($value)) {
            return null;
        }

        // 🎯 PRIMERO: Intentar con formatos de texto más comunes (Excel suele devolver así)
        $formatos = [
            'd/m/Y H:i',        // 01/01/2025 06:30 (más probable en Excel ES)
            'd/m/Y H:i:s',      // 01/01/2025 06:30:45
            'Y-m-d H:i:s',      // 2025-01-01 06:30:45
            'Y-m-d H:i',        // 2025-01-01 06:30
            'd-m-Y H:i:s',      // 01-01-2025 06:30:45
            'd-m-Y H:i',        // 01-01-2025 06:30
            'd.m.Y H:i:s',      // 01.01.2025 06:30:45
            'd.m.Y H:i'         // 01.01.2025 06:30
        ];

        foreach ($formatos as $formato) {
            try {
                $date = \DateTime::createFromFormat($formato, $value);
                if ($date) {
                    $resultado = $date->format('Y-m-d H:i:s');
                    Log::info("✓ Fecha parseada: '{$originalValue}' con formato '{$formato}' → '{$resultado}'");
                    return $resultado;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        // 🎯 SEGUNDO: Si es un número, es un serial date de Excel
        if (is_numeric($value)) {
            try {
                $excelDate = (float)$value;

                // Validar que sea un número razonable (entre 1 y 60000 = años 1900-2100 aprox)
                if ($excelDate > 0 && $excelDate < 60000) {
                    // Usar la fórmula correcta de Excel:
                    // Excel trata al 1900-01-00 como día 0 (no existe, es un bug de compatibilidad)
                    // El 1900-01-01 es día 1
                    // Si el número es > 60, sumamos 1 para saltar el bug del 29-02-1900

                    $days = floor($excelDate);
                    $fraction = $excelDate - $days;

                    // Ajuste por bug de Excel (29 de febrero de 1900 no existe)
                    if ($excelDate > 60) {
                        $days = $days - 1;
                    }

                    // Crear fecha base del 1900-01-01
                    $baseDate = new \DateTime('1900-01-01');

                    // Sumar los días
                    if ($days > 1) {
                        $baseDate->modify('+' . ($days - 1) . ' days');
                    }

                    // Convertir fracción a segundos (la fracción es la hora del día)
                    $seconds = round($fraction * 86400);
                    if ($seconds > 0) {
                        $baseDate->modify('+' . $seconds . ' seconds');
                    }

                    $resultado = $baseDate->format('Y-m-d H:i:s');
                    Log::info("✓ Fecha Excel (número) parseada: '{$originalValue}' (days={$days}, fraction={$fraction}) → '{$resultado}'");
                    return $resultado;
                }
            } catch (\Exception $e) {
                Log::warning("Error parseando número Excel: '{$originalValue}' - {$e->getMessage()}");
            }
        }

        Log::warning("✗ No se pudo parsear fecha: '{$originalValue}'");
        return null;
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
