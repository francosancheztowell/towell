<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReporteInvTelasExport implements FromArray, WithEvents, WithTitle
{
    private const FILA_DIA_SEMANA = 3;
    private const FILA_FECHA = 4;
    private const COLUMNA_INICIO_DIAS = 5;
    private const COLUMNA_FIN_DIAS_PLANTILLA = 9;

    protected array $secciones;
    protected array $dias;

    /**
     * @param array $secciones Estructura del reporte (nombre, filas)
     * @param array $dias Columnas de días (fecha, label)
     */
    public function __construct(array $secciones, array $dias)
    {
        $this->secciones = $secciones;
        $this->dias = $dias;
    }

    public function array(): array
    {
        return [['']];
    }

    public function title(): string
    {
        return 'Reporte Inv Telas';
    }

    public function registerEvents(): array
    {
        $secciones = $this->secciones;
        $dias = $this->dias;

        return [
            AfterSheet::class => function (AfterSheet $event) use ($secciones, $dias) {
                $initialSheet = $event->sheet->getDelegate();
                $book = $initialSheet->getParent();
                $sheetIndex = $book->getIndex($initialSheet);

                $templatePath = $this->findTemplate();
                if ($templatePath) {
                    $templateBook = IOFactory::load($templatePath);
                    $templateSheet = $templateBook->getSheet(0);
                    $templateSheet->setTitle('Reporte Inv Telas');
                    $book->removeSheetByIndex($sheetIndex);
                    $book->addExternalSheet($templateSheet, $sheetIndex);
                }

                $sheet = $book->getSheet($sheetIndex);
                $this->fillSheet($sheet, $secciones, $dias);
            },
        ];
    }

    protected function findTemplate(): ?string
    {
        $candidates = [
            resource_path('templates/Reportes Tejido.xlsx'),
            storage_path('app/templates/Reportes Tejido.xlsx'),
        ];
        foreach ($candidates as $path) {
            if (is_string($path) && $path !== '' && is_file($path)) {
                return $path;
            }
        }
        return null;
    }

    protected function fillSheet(Worksheet $sheet, array $secciones, array $dias): void
    {
        $filaFinPlantilla = max($sheet->getHighestRow(), self::FILA_FECHA);
        $columnasDiasRequeridas = max(1, count($dias));
        $ultimaColumnaDias = max(
            self::COLUMNA_FIN_DIAS_PLANTILLA,
            self::COLUMNA_INICIO_DIAS + $columnasDiasRequeridas - 1
        );

        $this->extenderColumnasDiasSiAplica($sheet, $ultimaColumnaDias, $filaFinPlantilla);
        $this->limpiarZonaDias($sheet, $ultimaColumnaDias, $filaFinPlantilla);
        $this->escribirEncabezadosDias($sheet, $dias);

        $slots = $this->obtenerSlotsPlantilla($sheet, $filaFinPlantilla);
        $filasIndexadas = $this->indexarFilasPorSeccion($secciones);

        foreach ($slots as $slot) {
            $filaData = $this->resolverFilaParaSlot($filasIndexadas, $slot);

            $sheet->setCellValue("B{$slot['row']}", trim((string) ($filaData['fibra'] ?? '')));
            $sheet->setCellValue("C{$slot['row']}", trim((string) ($filaData['calibre'] ?? '')));
            $sheet->setCellValue("D{$slot['row']}", $this->formatearCuentaCompuesta($filaData));
            $sheet->getStyle("D{$slot['row']}")->getAlignment()->setWrapText(true);

            foreach ($dias as $col => $dia) {
                $columna = Coordinate::stringFromColumnIndex(self::COLUMNA_INICIO_DIAS + $col);
                $valor = trim((string) ($filaData['por_dia'][$dia['fecha']] ?? ''));
                $sheet->setCellValue("{$columna}{$slot['row']}", $valor);
            }
            for ($col = self::COLUMNA_INICIO_DIAS + count($dias); $col <= $ultimaColumnaDias; $col++) {
                $columna = Coordinate::stringFromColumnIndex($col);
                $sheet->setCellValue("{$columna}{$slot['row']}", '');
            }
        }

        $sheet->setSelectedCell('A1');
    }

    protected function extenderColumnasDiasSiAplica(Worksheet $sheet, int $ultimaColumnaDias, int $filaFinPlantilla): void
    {
        for ($col = self::COLUMNA_FIN_DIAS_PLANTILLA + 1; $col <= $ultimaColumnaDias; $col++) {
            $columnaAnterior = Coordinate::stringFromColumnIndex($col - 1);
            $columnaNueva = Coordinate::stringFromColumnIndex($col);
            $sheet->duplicateStyle(
                $sheet->getStyle("{$columnaAnterior}" . self::FILA_DIA_SEMANA . ":{$columnaAnterior}{$filaFinPlantilla}"),
                "{$columnaNueva}" . self::FILA_DIA_SEMANA . ":{$columnaNueva}{$filaFinPlantilla}"
            );

            $anchoAnterior = $sheet->getColumnDimension($columnaAnterior)->getWidth();
            if ($anchoAnterior > 0) {
                $sheet->getColumnDimension($columnaNueva)->setWidth($anchoAnterior);
            }
        }
    }

    protected function limpiarZonaDias(Worksheet $sheet, int $ultimaColumnaDias, int $filaFinPlantilla): void
    {
        for ($col = self::COLUMNA_INICIO_DIAS; $col <= $ultimaColumnaDias; $col++) {
            $columna = Coordinate::stringFromColumnIndex($col);
            for ($row = self::FILA_DIA_SEMANA; $row <= $filaFinPlantilla; $row++) {
                $sheet->setCellValue("{$columna}{$row}", '');
            }
        }
    }

    protected function escribirEncabezadosDias(Worksheet $sheet, array $dias): void
    {
        foreach ($dias as $index => $dia) {
            $columna = Coordinate::stringFromColumnIndex(self::COLUMNA_INICIO_DIAS + $index);
            $diaNombre = (string) ($dia['dia_nombre'] ?? $dia['label'] ?? '');
            $fecha = (string) ($dia['fecha_excel'] ?? $dia['label'] ?? '');

            $sheet->setCellValue("{$columna}" . self::FILA_DIA_SEMANA, $diaNombre);
            $sheet->setCellValue("{$columna}" . self::FILA_FECHA, $fecha);
            $sheet->getStyle("{$columna}" . self::FILA_DIA_SEMANA . ":{$columna}" . self::FILA_FECHA)
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
    }

    protected function formatearFibraCalibre(array $fila): string
    {
        $fibra = trim((string) ($fila['fibra'] ?? ''));
        $calibre = trim((string) ($fila['calibre'] ?? ''));
        if ($fibra === '') {
            return $calibre;
        }
        if ($calibre === '') {
            return $fibra;
        }

        return $fibra . ' - ' . $calibre;
    }

    protected function formatearCuentaCompuesta(array $fila): string
    {
        $cuentaRizo = trim((string) ($fila['cuenta_rizo'] ?? ''));
        $cuentaPie = trim((string) ($fila['cuenta_pie'] ?? ''));

        if ($cuentaRizo !== '' && $cuentaPie !== '') {
            return "R: {$cuentaRizo}\nP: {$cuentaPie}";
        }
        if ($cuentaRizo !== '') {
            return 'R: ' . $cuentaRizo;
        }
        if ($cuentaPie !== '') {
            return 'P: ' . $cuentaPie;
        }

        return '';
    }

    protected function obtenerSlotsPlantilla(Worksheet $sheet, int $filaFinPlantilla): array
    {
        $slots = [];
        $seccionActual = '';
        $posicionEnSeccion = 0;

        for ($row = 1; $row <= $filaFinPlantilla; $row++) {
            $valorA = trim((string) $sheet->getCell("A{$row}")->getFormattedValue());
            if ($valorA === '') {
                continue;
            }

            if ($this->esValorTelar($valorA)) {
                $slots[] = [
                    'row' => $row,
                    'seccion' => $seccionActual,
                    'no_telar' => $valorA,
                    'posicion' => $posicionEnSeccion,
                ];
                $posicionEnSeccion++;
                continue;
            }

            $seccionActual = $this->normalizarTexto($valorA);
            $posicionEnSeccion = 0;
        }

        return $slots;
    }

    protected function indexarFilasPorSeccion(array $secciones): array
    {
        $index = [];

        foreach ($secciones as $seccion) {
            $key = $this->normalizarTexto((string) ($seccion['nombre'] ?? ''));
            $index[$key] = [
                'ordered' => array_values($seccion['filas'] ?? []),
                'by_telar' => [],
            ];

            foreach ($index[$key]['ordered'] as $fila) {
                $noTelar = trim((string) ($fila['no_telar'] ?? ''));
                if ($noTelar !== '') {
                    $index[$key]['by_telar'][$noTelar] = $fila;
                }
            }
        }

        return $index;
    }

    protected function resolverFilaParaSlot(array $filasIndexadas, array $slot): array
    {
        $seccion = $slot['seccion'] ?? '';
        $noTelar = (string) ($slot['no_telar'] ?? '');
        $posicion = (int) ($slot['posicion'] ?? 0);

        if (isset($filasIndexadas[$seccion]['by_telar'][$noTelar])) {
            return $filasIndexadas[$seccion]['by_telar'][$noTelar];
        }

        if (isset($filasIndexadas[$seccion]['ordered'][$posicion])) {
            return $filasIndexadas[$seccion]['ordered'][$posicion];
        }

        return [
            'fibra' => '',
            'calibre' => '',
            'cuenta_rizo' => '',
            'cuenta_pie' => '',
            'por_dia' => [],
        ];
    }

    protected function esValorTelar(string $valor): bool
    {
        return preg_match('/^[0-9]+$/', trim($valor)) === 1;
    }

    protected function normalizarTexto(string $valor): string
    {
        return $this->aMayusculas(trim(preg_replace('/\s+/', ' ', $valor) ?? ''));
    }

    protected function aMayusculas(string $texto): string
    {
        return function_exists('mb_strtoupper')
            ? mb_strtoupper($texto, 'UTF-8')
            : strtoupper($texto);
    }
}
