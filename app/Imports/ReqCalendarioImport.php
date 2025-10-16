<?php

namespace App\Imports;

use App\Models\ReqCalendarioTab;
use App\Models\ReqCalendarioLine;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Illuminate\Support\Facades\Log;

class ReqCalendarioImport implements ToModel, WithHeadingRow, WithBatchInserts, WithChunkReading
{
    private $calendariosProcesados = 0;
    private $lineasProcesadas = 0;
    private $calendariosCreados = 0;
    private $lineasCreadas = 0;
    private $errores = [];
    private $rowCounter = 0;
    private $seccionActual = null; // 'calendarios' o 'lineas'

    public function model(array $row)
    {
        try {
            $this->rowCounter++;

            // ⚡ OPTIMIZACIÓN: Detectar rápidamente filas completamente vacías
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

            Log::info("========== FILA {$this->rowCounter} INICIADA ==========");
            Log::info("Datos crudos de la fila: " . json_encode($row));

            // Normalizar claves
            $row = $this->normalizeRowKeys($row);

            Log::info("Claves normalizadas: " . json_encode(array_keys($row)));
            Log::info("Valores normalizados: " . json_encode($row));

            // Detectar sección actual por los valores de encabezado
            $this->detectarSeccion($row);

            Log::info("Sección actual: " . ($this->seccionActual ?? 'NINGUNA'));

            // Si no hemos detectado sección aún, saltamos
            if ($this->seccionActual === null) {
                Log::debug("Saltando fila {$this->rowCounter} - sección no identificada");
                return null;
            }

            // Procesar según la sección
            if ($this->seccionActual === 'calendarios') {
                Log::info("Procesando como CALENDARIO");
                $resultado = $this->procesarCalendario($row);
                Log::info("Resultado procesamiento calendario: " . ($resultado ? 'CREADO' : 'SALTADO'));
                return $resultado;
            } elseif ($this->seccionActual === 'lineas') {
                Log::info("Procesando como LÍNEA");
                $resultado = $this->procesarLinea($row);
                Log::info("Resultado procesamiento línea: " . ($resultado ? 'CREADO' : 'SALTADO'));
                return $resultado;
            }

            Log::warning("Fila {$this->rowCounter}: No se procesó por sección desconocida");
            return null;

        } catch (\Exception $e) {
            Log::error("EXCEPCIÓN en fila {$this->rowCounter}: {$e->getMessage()}", [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->errores[] = "Fila {$this->rowCounter}: {$e->getMessage()}";
            return null;
        }
    }

    private function normalizeKey($key)
    {
        return strtolower(str_replace([' ', '_', '-', '(', ')', 'á', 'é', 'í', 'ó', 'ú'], ['', '', '', '', '', 'a', 'e', 'i', 'o', 'u'], $key));
    }

    private function normalizeRowKeys(array $row): array
    {
        $normalized = [];
        foreach ($row as $key => $value) {
            if ($key !== null && $value !== null) {
                $normalized[$this->normalizeKey((string)$key)] = $value;
            }
        }
        return $normalized;
    }

    private function getValue(array $row, array $possibleKeys)
    {
        foreach ($possibleKeys as $key) {
            $normalizedKey = $this->normalizeKey($key);
            if (isset($row[$normalizedKey]) && !empty(trim((string)$row[$normalizedKey]))) {
                return trim((string)$row[$normalizedKey]);
            }
        }
        return null;
    }

    private function detectarSeccion($row)
    {
        Log::info("--- DETECTANDO SECCIÓN ---");

        // Buscar indicadores de sección
        $hasNoCalendario = $this->getValue($row, ['No Calendario', 'nocalendario', 'calendario']) !== null;
        $hasNombre = $this->getValue($row, ['Nombre']) !== null;
        $hasInicio = $this->getValue($row, ['Inicio', 'inicio', 'fechainicio','Inicio (fecha Hora)']) !== null;
        $hasFin = $this->getValue($row, ['Fin', 'fin', 'fechafin','Fin (Fecha Hora)']) !== null;

        Log::info("Indicadores encontrados:", [
            'hasNoCalendario' => $hasNoCalendario,
            'hasNombre' => $hasNombre,
            'hasInicio' => $hasInicio,
            'hasFin' => $hasFin
        ]);

        if ($hasNoCalendario && $hasNombre && !$hasInicio && !$hasFin) {
            Log::info("✓✓✓ SECCIÓN CALENDARIOS DETECTADA en fila {$this->rowCounter}");
            $this->seccionActual = 'calendarios';
        } elseif ($hasNoCalendario && $hasInicio && $hasFin) {
            Log::info("✓✓✓ SECCIÓN LÍNEAS DETECTADA en fila {$this->rowCounter}");
            $this->seccionActual = 'lineas';
        } else {
            Log::debug("No coincide con ninguna sección en fila {$this->rowCounter}");
        }
    }

    private function procesarCalendario($row)
    {
        Log::info(">>> PROCESANDO CALENDARIO <<<");

        // Si es encabezado repetido, saltar
        $calendarioId = $this->getValue($row, ['No Calendario']);
        $nombre = $this->getValue($row, ['Nombre']);

        Log::info("Valores extraídos:", [
            'calendarioId' => $calendarioId,
            'nombre' => $nombre
        ]);

        if (empty($calendarioId) || empty($nombre)) {
            Log::warning("Fila {$this->rowCounter}: Datos incompletos - Saltando");
            return null;
        }

        // Truncar a máximo 20 caracteres
        $calendarioId = substr($calendarioId, 0, 20);
        $nombre = substr($nombre, 0, 255);

        Log::info("Valores después de truncar:", [
            'calendarioId' => $calendarioId,
            'nombre' => $nombre
        ]);

        try {
            Log::info("Intentando guardar calendario: {$calendarioId}");

            ReqCalendarioTab::updateOrCreate(
                ['CalendarioId' => $calendarioId],
                ['Nombre' => $nombre]
            );

            $this->calendariosProcesados++;
            $this->calendariosCreados++;
            Log::info("✓✓✓ Calendario GUARDADO EXITOSAMENTE: {$calendarioId}");

            return null;
        } catch (\Exception $e) {
            Log::error("✗✗✗ ERROR al guardar calendario fila {$this->rowCounter}: {$e->getMessage()}", [
                'exception' => $e->getTraceAsString()
            ]);
            $this->errores[] = "Fila {$this->rowCounter}: Error al guardar calendario: {$e->getMessage()}";
            return null;
        }
    }

    private function procesarLinea($row)
    {
        Log::info(">>> PROCESANDO LÍNEA <<<");

        $calendarioId = $this->getValue($row, ['No Calendario', 'nocalendario']);
        $fechaInicio = $this->getValue($row, ['Inicio', 'inicio', 'fechainicio', 'inicio (fecha hora)']);
        $fechaFin = $this->getValue($row, ['Fin', 'fin', 'fechafin', 'fin (fecha hora)']);
        $horas = $this->getValue($row, ['Horas', 'horas', 'horasturno']);
        $turno = $this->getValue($row, ['Turno', 'turno']);

        Log::info("Valores extraídos de línea:", [
            'calendarioId' => $calendarioId,
            'fechaInicio' => $fechaInicio,
            'fechaFin' => $fechaFin,
            'horas' => $horas,
            'turno' => $turno
        ]);

        if (empty($calendarioId) || empty($fechaInicio) || empty($fechaFin)) {
            Log::warning("Fila {$this->rowCounter}: Datos incompletos en línea - Saltando");
            return null;
        }

        try {
            // Truncar CalendarioId
            $calendarioId = substr($calendarioId, 0, 20);

            Log::info("Parseando fechas...");
            // Parsear fechas
            $fechaInicioFormato = $this->parseDatetime($fechaInicio);
            $fechaFinFormato = $this->parseDatetime($fechaFin);

            Log::info("Fechas parseadas:", [
                'fechaInicioFormato' => $fechaInicioFormato,
                'fechaFinFormato' => $fechaFinFormato
            ]);

            if ($fechaInicioFormato === null || $fechaFinFormato === null) {
                $this->errores[] = "Fila {$this->rowCounter}: No se pudieron parsear las fechas";
                Log::error("✗✗✗ Fila {$this->rowCounter}: Fechas no válidas");
                return null;
            }

            // Convertir valores
            $horas = !empty($horas) ? (float)$horas : 0;
            $turno = !empty($turno) ? (int)$turno : 0;

            Log::info("Intentando guardar línea: Cal={$calendarioId}, Turno={$turno}");

            ReqCalendarioLine::create([
                'CalendarioId' => $calendarioId,
                'FechaInicio' => $fechaInicioFormato,
                'FechaFin' => $fechaFinFormato,
                'HorasTurno' => $horas,
                'Turno' => $turno
            ]);

            $this->lineasProcesadas++;
            $this->lineasCreadas++;
            Log::info("✓✓✓ Línea GUARDADA EXITOSAMENTE: {$calendarioId} turno {$turno}");

            return null;
        } catch (\Exception $e) {
            Log::error("✗✗✗ ERROR al guardar línea fila {$this->rowCounter}: {$e->getMessage()}", [
                'exception' => $e->getTraceAsString()
            ]);
            $this->errores[] = "Fila {$this->rowCounter}: Error al guardar línea: {$e->getMessage()}";
            return null;
        }
    }

    private function parseDatetime($value)
    {
        if (empty($value)) {
            return null;
        }

        // Convertir a string
        $value = (string)$value;
        $value = trim($value);

        if (empty($value)) {
            return null;
        }

        // Si es un número Excel (días desde 1900)
        if (is_numeric($value)) {
            try {
                $excelDate = (int)$value;
                $unixDate = ($excelDate - 25569) * 86400;
                return date('Y-m-d H:i:s', $unixDate);
            } catch (\Exception $e) {
                return null;
            }
        }

        // Intentar diferentes formatos de fecha
        $formatos = [
            'd/m/Y H:i',
            'd/m/Y H:i:s',
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'd/m/Y',
            'Y-m-d'
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

        Log::warning("No se pudo parsear la fecha: {$value}");
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
            'calendarios_procesados' => $this->calendariosProcesados,
            'lineas_procesadas' => $this->lineasProcesadas,
            'calendarios_creados' => $this->calendariosCreados,
            'lineas_creadas' => $this->lineasCreadas,
            'errores' => $this->errores
        ];
    }
}
