<?php

namespace App\Imports;

use App\Models\Planeacion\ReqCalendarioLine;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Events\BeforeImport;

class ReqCalendarioLineImport implements ToModel, WithBatchInserts, WithChunkReading, WithEvents, WithHeadingRow
{
    private $procesados = 0;

    private $creados = 0;

    private $errores = [];

    private $buffer = [];

    public function model(array $row)
    {
        $filaNum = $this->procesados + 1;

        try {
            // ⚡ Detección rápida de filas vacías
            if (empty(array_filter($row))) {
                Log::debug("Fila {$filaNum}: Fila vacía - Saltando");

                return null;
            }

            // ⚡ Extracción directa y rápida
            $calendarioId = trim((string) ($row['no_calendario'] ?? $row['No Calendario'] ?? ''));
            $fechaInicio = trim((string) ($row['inicio_fecha_hora'] ?? $row['Inicio (Fecha Hora)'] ?? $row['Inicio_Fecha_Hora'] ?? ''));
            $fechaFin = trim((string) ($row['fin_fecha_hora'] ?? $row['Fin (Fecha Hora)'] ?? $row['Fin_Fecha_Hora'] ?? ''));
            $horas = trim((string) ($row['horas'] ?? ''));
            $turno = trim((string) ($row['turno'] ?? ''));

            // ⚠️ DETECCIÓN: Si solo tiene no_calendario y nombre, es formato de calendarios, no líneas
            $tieneSoloCalendario = ! empty($calendarioId) && empty($fechaInicio) && empty($fechaFin) && isset($row['nombre']);
            if ($tieneSoloCalendario) {
                $this->errores[] = "Fila {$filaNum}: El archivo parece ser de CALENDARIOS (tiene 'no_calendario' y 'nombre'), no de LÍNEAS. Las líneas requieren columnas: 'Inicio (Fecha Hora)', 'Fin (Fecha Hora)', 'Horas', 'Turno'";
                Log::error("✗✗✗ Fila {$filaNum}: Formato incorrecto - Este archivo es de calendarios, no de líneas", [
                    'columnas_encontradas' => array_keys($row),
                    'columnas_requeridas' => ['no_calendario', 'inicio_fecha_hora', 'fin_fecha_hora', 'horas', 'turno'],
                ]);
                $this->procesados++;

                return null;
            }

            if (empty($calendarioId) || empty($fechaInicio) || empty($fechaFin)) {
                Log::warning("Fila {$filaNum}: Campos requeridos vacíos - Saltando", [
                    'calendarioId_vacio' => empty($calendarioId),
                    'fechaInicio_vacia' => empty($fechaInicio),
                    'fechaFin_vacia' => empty($fechaFin),
                    'sugerencia' => 'Verifica que el Excel tenga las columnas: No Calendario, Inicio (Fecha Hora), Fin (Fecha Hora), Horas, Turno',
                ]);
                $this->procesados++;

                return null;
            }

            // Truncar
            $calendarioId = substr($calendarioId, 0, 20);

            // Parsear fechas
            $fechaInicioFormato = $this->parseDatetime($fechaInicio);
            $fechaFinFormato = $this->parseDatetime($fechaFin);

            if ($fechaInicioFormato === null || $fechaFinFormato === null) {
                Log::warning("Fila {$filaNum}: No se pudieron parsear las fechas - Saltando", [
                    'fechaInicio_parseada' => $fechaInicioFormato,
                    'fechaFin_parseada' => $fechaFinFormato,
                ]);
                $this->procesados++;

                return null;
            }

            $horasNum = ! empty($horas) ? (float) $horas : 0;
            $turnoNum = ! empty($turno) ? (int) $turno : 0;

            // El calendario es capacidad de máquina: 3 ventanas de 8 h = 24 h/día.
            // El turno 4 (comodín que cubre descansos) es del empleado y aquí sería doble conteo.
            if (! in_array($turnoNum, [1, 2, 3], true)) {
                $this->errores[] = "Fila {$filaNum}: Turno inválido ({$turnoNum}); debe ser 1, 2 o 3";
                $this->procesados++;

                return null;
            }

            ReqCalendarioLine::create([
                'CalendarioId' => $calendarioId,
                'FechaInicio' => $fechaInicioFormato,
                'FechaFin' => $fechaFinFormato,
                'HorasTurno' => $horasNum,
                'Turno' => $turnoNum,
            ]);

            $this->procesados++;
            $this->creados++;

            return null;

        } catch (\Exception $e) {
            $this->procesados++;
            $this->errores[] = "Fila {$filaNum}: {$e->getMessage()}";
            Log::error("✗✗✗ ERROR en fila {$filaNum}: {$e->getMessage()}", [
                'exception' => $e->getTraceAsString(),
                'row_data' => $row,
            ]);

            return null;
        }
    }

    /**
     * Registrar eventos para limpiar datos antes de importar
     */
    public function registerEvents(): array
    {
        return [
            BeforeImport::class => function (BeforeImport $event) {
                try {
                    // Limpiar todas las líneas de calendario para evitar duplicados
                    ReqCalendarioLine::truncate();
                } catch (\Exception $e) {
                }
            },
        ];
    }

    private function parseDatetime($value)
    {
        if (empty($value)) {
            return null;
        }

        $originalValue = $value;
        $value = (string) $value;
        $value = trim($value);

        if (empty($value)) {
            return null;
        }

        // ⚡ Solo formatos más comunes para velocidad
        $formatos = [
            'd/m/Y H:i',        // 01/01/2025 06:30 (más común)
            'Y-m-d H:i:s',      // 2025-01-01 06:30:45
            'd-m-Y H:i',         // 01-01-2025 06:30
        ];

        foreach ($formatos as $formato) {
            try {
                $date = \DateTime::createFromFormat($formato, $value);
                if ($date) {
                    return $date->format('Y-m-d H:i:s');
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        // 🎯 SEGUNDO: Si es un número, es un serial date de Excel
        if (is_numeric($value)) {
            try {
                $excelDate = (float) $value;

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
                        $baseDate->modify('+'.($days - 1).' days');
                    }

                    // Convertir fracción a segundos (la fracción es la hora del día)
                    $seconds = round($fraction * 86400);
                    if ($seconds > 0) {
                        $baseDate->modify('+'.$seconds.' seconds');
                    }

                    return $baseDate->format('Y-m-d H:i:s');
                }
            } catch (\Exception $e) {
                // Silenciar errores para mejor rendimiento
            }
        }

        return null;
    }

    public function batchSize(): int
    {
        return 1000; // Máximo para mejor rendimiento
    }

    public function chunkSize(): int
    {
        return 500; // Máximo para mejor rendimiento
    }

    public function getStats(): array
    {
        return [
            'procesados' => $this->procesados,
            'creados' => $this->creados,
            'errores' => $this->errores,
        ];
    }
}
