<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReporteInvTelasExport implements FromArray, WithEvents, WithTitle
{
    private const FILA_DIA_SEMANA = 3;
    private const FILA_FECHA = 4;
    private const COLUMNA_INICIO_DIAS = 5;
    private const COLUMNAS_POR_DIA = 3;
    private const FILL_COLORS = [
        'blue' => '3B82F6',
        'orange' => 'FB923C',
        'yellow' => 'FDE047',
    ];
    private const FONT_COLORS = [
        'blue' => 'FFFFFF',
        'orange' => 'FFFFFF',
        'yellow' => '713F12',
    ];
    private const TURNO_ALIGNMENT = [
        1 => Alignment::HORIZONTAL_LEFT,
        2 => Alignment::HORIZONTAL_CENTER,
        3 => Alignment::HORIZONTAL_RIGHT,
    ];

    protected array $secciones;
    protected array $dias;

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
        $totalColumnasDias = max(1, count($dias)) * self::COLUMNAS_POR_DIA;
        $ultimaColumnaDias = self::COLUMNA_INICIO_DIAS + $totalColumnasDias - 1;

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

            foreach ($dias as $diaIdx => $dia) {
                $colBase = self::COLUMNA_INICIO_DIAS + ($diaIdx * self::COLUMNAS_POR_DIA);
                $celdaDia = $filaData['por_dia'][$dia['fecha']] ?? ['turnos' => []];
                $turnos = $celdaDia['turnos'] ?? [];

                foreach ([1, 2, 3] as $t) {
                    $colTurno = $colBase + ($t - 1);
                    $letra = Coordinate::stringFromColumnIndex($colTurno);
                    $coord = "{$letra}{$slot['row']}";
                    $txt = trim((string) ($turnos[$t]['texto'] ?? ''));
                    $color = $turnos[$t]['color'] ?? null;

                    $sheet->setCellValue($coord, $txt);
                    $sheet->getStyle($coord)->getAlignment()
                        ->setHorizontal(self::TURNO_ALIGNMENT[$t])
                        ->setVertical(Alignment::VERTICAL_CENTER);
                    $this->aplicarColorCelda($sheet, $coord, $color);
                }
            }
        }

        // Fuente de datos
        $ultimaColumnaLetra = Coordinate::stringFromColumnIndex($ultimaColumnaDias);
        $sheet->getStyle("A5:{$ultimaColumnaLetra}{$filaFinPlantilla}")
            ->getFont()
            ->setSize(9);

        // Anchos de columna para turnos
        for ($diaIdx = 0; $diaIdx < count($dias); $diaIdx++) {
            $colBase = self::COLUMNA_INICIO_DIAS + ($diaIdx * self::COLUMNAS_POR_DIA);
            for ($t = 0; $t < self::COLUMNAS_POR_DIA; $t++) {
                $letra = Coordinate::stringFromColumnIndex($colBase + $t);
                $sheet->getColumnDimension($letra)->setWidth(10);
            }
        }

        $sheet->setSelectedCell('A1');
    }

    protected function limpiarZonaDias(Worksheet $sheet, int $ultimaColumnaDias, int $filaFinPlantilla): void
    {
        for ($col = self::COLUMNA_INICIO_DIAS; $col <= $ultimaColumnaDias; $col++) {
            $columna = Coordinate::stringFromColumnIndex($col);
            for ($row = self::FILA_DIA_SEMANA; $row <= $filaFinPlantilla; $row++) {
                $sheet->setCellValue("{$columna}{$row}", '');
                $sheet->getStyle("{$columna}{$row}")->getFill()->setFillType(Fill::FILL_NONE);
            }
        }
    }

    protected function escribirEncabezadosDias(Worksheet $sheet, array $dias): void
    {
        foreach ($dias as $index => $dia) {
            $colBase = self::COLUMNA_INICIO_DIAS + ($index * self::COLUMNAS_POR_DIA);
            $colFin = $colBase + self::COLUMNAS_POR_DIA - 1;
            $letraInicio = Coordinate::stringFromColumnIndex($colBase);
            $letraFin = Coordinate::stringFromColumnIndex($colFin);

            $diaNombre = (string) ($dia['dia_nombre'] ?? $dia['label'] ?? '');
            $fecha = (string) ($dia['fecha_excel'] ?? $dia['label'] ?? '');

            // Fila dia de semana: merge 3 celdas
            $sheet->setCellValue("{$letraInicio}" . self::FILA_DIA_SEMANA, $diaNombre);
            $sheet->mergeCells("{$letraInicio}" . self::FILA_DIA_SEMANA . ":{$letraFin}" . self::FILA_DIA_SEMANA);

            // Fila fecha: merge 3 celdas
            $sheet->setCellValue("{$letraInicio}" . self::FILA_FECHA, $fecha);
            $sheet->mergeCells("{$letraInicio}" . self::FILA_FECHA . ":{$letraFin}" . self::FILA_FECHA);

            // Estilo encabezados
            $rangoHeader = "{$letraInicio}" . self::FILA_DIA_SEMANA . ":{$letraFin}" . self::FILA_FECHA;
            $sheet->getStyle($rangoHeader)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle($rangoHeader)->getFont()->setBold(true);

            // Borde exterior del grupo de 3 columnas en encabezados
            $sheet->getStyle($rangoHeader)->getBorders()->getOutline()
                ->setBorderStyle(Border::BORDER_THIN);
        }
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

    protected function aplicarColorCelda(Worksheet $sheet, string $coordenada, ?string $color): void
    {
        if ($color === null || !isset(self::FILL_COLORS[$color])) {
            return;
        }

        $sheet->getStyle($coordenada)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB(self::FILL_COLORS[$color]);

        if (isset(self::FONT_COLORS[$color])) {
            $sheet->getStyle($coordenada)->getFont()
                ->getColor()
                ->setARGB(self::FONT_COLORS[$color]);
        }
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
