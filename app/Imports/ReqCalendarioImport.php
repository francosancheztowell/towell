<?php

namespace App\Imports;

use App\Models\Planeacion\ReqCalendarioTab;
use App\Models\Planeacion\ReqCalendarioLine;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\BeforeImport;
use Illuminate\Support\Facades\Log;

class ReqCalendarioImport implements ToModel, WithHeadingRow, WithBatchInserts, WithChunkReading, WithEvents
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

            // Normalizar claves
            $row = $this->normalizeRowKeys($row);

            // Detectar sección actual por los valores de encabezado
            $this->detectarSeccion($row);

            // Si no hemos detectado sección aún, saltamos
            if ($this->seccionActual === null) {
                return null;
            }

            // Procesar según la sección
            if ($this->seccionActual === 'calendarios') {
                $resultado = $this->procesarCalendario($row);
                return $resultado;
            } elseif ($this->seccionActual === 'lineas') {
                $resultado = $this->procesarLinea($row);
                return $resultado;
            }

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

    /**
     * Registrar eventos para limpiar datos antes de importar
     */
    public function registerEvents(): array
    {
        return [
            BeforeImport::class => function(BeforeImport $event) {

                // Limpiar todas las líneas de calendario
                $deletedLines = ReqCalendarioLine::truncate();

                // Limpiar todas las tablas de calendario
                $deletedTabs = ReqCalendarioTab::truncate();
            }
        ];
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

        // Buscar indicadores de sección
        $hasNoCalendario = $this->getValue($row, ['No Calendario', 'nocalendario', 'calendario']) !== null;
        $hasNombre = $this->getValue($row, ['Nombre']) !== null;
        $hasInicio = $this->getValue($row, ['Inicio', 'inicio', 'fechainicio','Inicio (fecha Hora)']) !== null;
        $hasFin = $this->getValue($row, ['Fin', 'fin', 'fechafin','Fin (Fecha Hora)']) !== null;

        if ($hasNoCalendario && $hasNombre && !$hasInicio && !$hasFin) {
            $this->seccionActual = 'calendarios';
        } elseif ($hasNoCalendario && $hasInicio && $hasFin) {
            $this->seccionActual = 'lineas';
        } else {
        }
    }

    private function procesarCalendario($row)
    {

        // Si es encabezado repetido, saltar
        $calendarioId = $this->getValue($row, ['No Calendario']);
        $nombre = $this->getValue($row, ['Nombre']);

        if (empty($calendarioId) || empty($nombre)) {
            return null;
        }

        // Truncar a máximo 20 caracteres
        $calendarioId = substr($calendarioId, 0, 20);
        $nombre = substr($nombre, 0, 255);

        try {

            ReqCalendarioTab::updateOrCreate(
                ['CalendarioId' => $calendarioId],
                ['Nombre' => $nombre]
            );

            $this->calendariosProcesados++;
            $this->calendariosCreados++;

            return null;
        } catch (\Exception $e) {
            $this->errores[] = "Fila {$this->rowCounter}: Error al guardar calendario: {$e->getMessage()}";
            return null;
        }
    }

    private function procesarLinea($row)
    {

        $calendarioId = $this->getValue($row, ['No Calendario', 'nocalendario']);
        $fechaInicio = $this->getValue($row, ['Inicio', 'inicio', 'fechainicio', 'inicio (fecha hora)']);
        $fechaFin = $this->getValue($row, ['Fin', 'fin', 'fechafin', 'fin (fecha hora)']);
        $horas = $this->getValue($row, ['Horas', 'horas', 'horasturno']);
        $turno = $this->getValue($row, ['Turno', 'turno']);

        if (empty($calendarioId) || empty($fechaInicio) || empty($fechaFin)) {
            return null;
        }

        try {
            // Truncar CalendarioId
            $calendarioId = substr($calendarioId, 0, 20);

            // Parsear fechas
            $fechaInicioFormato = $this->parseDatetime($fechaInicio);
            $fechaFinFormato = $this->parseDatetime($fechaFin);

            if ($fechaInicioFormato === null || $fechaFinFormato === null) {
                $this->errores[] = "Fila {$this->rowCounter}: No se pudieron parsear las fechas";
                return null;
            }

            // Convertir valores
            $horas = !empty($horas) ? (float)$horas : 0;
            $turno = !empty($turno) ? (int)$turno : 0;

            ReqCalendarioLine::create([
                'CalendarioId' => $calendarioId,
                'FechaInicio' => $fechaInicioFormato,
                'FechaFin' => $fechaFinFormato,
                'HorasTurno' => $horas,
                'Turno' => $turno
            ]);

            $this->lineasProcesadas++;
            $this->lineasCreadas++;

            return null;
        } catch (\Exception $e) {
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
