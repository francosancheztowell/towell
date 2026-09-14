<?php

declare(strict_types=1);

namespace App\Exports;

use App\Services\Mecanicos\ReporteOtDiariasService;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final class ReporteOtDiariasExport implements FromArray, WithDrawings, WithEvents, WithTitle
{
    private const FILA_TITULO = 4;

    private const FILA_PERIODO = 5;

    private const FILA_GRUPO = 7;

    private const FILA_SUB = 8;

    private const FILA_DATOS = 9;

    private const COL_NOMBRE = 1;

    /**
     * @param  array<string, mixed>  $reporte
     */
    public function __construct(
        private readonly array $reporte,
    ) {}

    public function title(): string
    {
        return 'OT Diarias';
    }

    public function array(): array
    {
        $filas = [
            [''],
            [''],
            [''],
            ['ÓRDENES DE TRABAJO DIARIAS'],
            ['Periodo: '.$this->etiquetaPeriodo()],
            [''],
        ];

        $grupo = [$this->reporte['etiqueta_semana']];
        $sub = ['MECÁNICOS'];

        foreach ($this->reporte['dias'] as $dia) {
            $grupo[] = $dia['etiqueta'];
            $grupo[] = '';
            $grupo[] = '';
            $sub[] = 'REALIZADAS';
            $sub[] = 'FIRMADAS';
            $sub[] = 'OCUPACIÓN MIN.';
        }

        array_push($grupo, 'TOTAL SEMANA EN TURNO', '', 'DE TRAMA', '', 'GENERAL', '', 'MIN. SEMANA', '%', 'OCUPACIÓN %', '% CUMPLIMIENTO', '% OT FINAL');
        array_push($sub, 'REALIZADAS', 'FIRMADAS', 'OT TRAMA', 'CUMPLIDAS TRAMA', 'TOTAL REALIZADAS', 'TOTAL CUMPLIDAS', '', '', '', '', '');

        $filas[] = $grupo;
        $filas[] = $sub;

        foreach ($this->reporte['mecanicos'] as $mecanico) {
            $fila = [$mecanico['nombre']];
            foreach ($this->reporte['dias'] as $dia) {
                $celda = $mecanico['dias'][$dia['fecha']];
                $fila[] = $celda['realizadas'];
                $fila[] = $celda['firmadas'];
                $fila[] = $celda['ocupacion'];
            }
            array_push(
                $fila,
                $mecanico['realizadas_semana'],
                $mecanico['firmadas_semana'],
                $mecanico['ot_trama'],
                $mecanico['cumplidas_trama'],
                $mecanico['total_realizadas'],
                $mecanico['total_cumplidas'],
                $mecanico['min_semana'],
                $mecanico['pct_capacidad'] / 100,
                $mecanico['ocupacion_pct'] / 100,
                $mecanico['pct_cumplimiento'] / 100,
                $mecanico['pct_ot_final'] / 100,
            );
            $filas[] = $fila;
        }

        $pie = array_fill(0, $this->ultimaColumna(), '');
        $pie[0] = '';
        $colTrama = $this->columnaInicioTotales() + 2;
        $pie[$colTrama - 1] = $this->reporte['pie']['ot_trama'];
        $pie[$colTrama] = $this->reporte['pie']['cumplidas_trama'];
        $pie[$this->ultimaColumna() - 1] = $this->reporte['pie']['pct_ot_final'] / 100;
        $filas[] = $pie;

        return $filas;
    }

    public function drawings(): Drawing|array
    {
        $logo = public_path('images/fondosTowell/logo.png');
        if (! is_file($logo) || ! is_readable($logo)) {
            return [];
        }

        $drawing = new Drawing;
        $drawing->setPath($logo);
        $drawing->setHeight(58);
        $drawing->setCoordinates('A1');
        $drawing->setOffsetX(4);
        $drawing->setOffsetY(6);

        return $drawing;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $ultimaCol = $this->ultimaColumna();
                $ultimaLetra = Coordinate::stringFromColumnIndex($ultimaCol);
                $ultimaFila = self::FILA_DATOS + count($this->reporte['mecanicos']);
                $rangoTabla = 'A'.self::FILA_GRUPO.':'.$ultimaLetra.$ultimaFila;

                $sheet->mergeCells('A'.self::FILA_TITULO.':F'.self::FILA_TITULO);
                $sheet->mergeCells('A'.self::FILA_PERIODO.':F'.self::FILA_PERIODO);
                $sheet->getStyle('A'.self::FILA_TITULO)->getFont()->setBold(true)->setSize(14);

                $col = 2;
                foreach ($this->reporte['dias'] as $dia) {
                    $inicio = Coordinate::stringFromColumnIndex($col);
                    $fin = Coordinate::stringFromColumnIndex($col + 2);
                    $sheet->mergeCells($inicio.self::FILA_GRUPO.':'.$fin.self::FILA_GRUPO);
                    $this->pintar($sheet, $inicio.self::FILA_GRUPO.':'.$fin.self::FILA_GRUPO, ReporteOtDiariasService::COLOR_ORO, false);
                    $this->pintar($sheet, $inicio.self::FILA_SUB.':'.$fin.self::FILA_SUB, ReporteOtDiariasService::COLOR_VERDE, true);
                    $col += 3;
                }

                $this->mergePintar($sheet, $col, 2, self::FILA_GRUPO, ReporteOtDiariasService::COLOR_ORO, false);
                $this->pintar($sheet, Coordinate::stringFromColumnIndex($col).self::FILA_SUB.':'.Coordinate::stringFromColumnIndex($col + 1).self::FILA_SUB, ReporteOtDiariasService::COLOR_ORO, false);
                $col += 2;
                $this->mergePintar($sheet, $col, 2, self::FILA_GRUPO, ReporteOtDiariasService::COLOR_TEAL, true);
                $this->pintar($sheet, Coordinate::stringFromColumnIndex($col).self::FILA_SUB.':'.Coordinate::stringFromColumnIndex($col + 1).self::FILA_SUB, ReporteOtDiariasService::COLOR_TEAL, true);
                $col += 2;
                $this->mergePintar($sheet, $col, 2, self::FILA_GRUPO, ReporteOtDiariasService::COLOR_ORO, false);
                $this->pintar($sheet, Coordinate::stringFromColumnIndex($col).self::FILA_SUB.':'.Coordinate::stringFromColumnIndex($col + 1).self::FILA_SUB, ReporteOtDiariasService::COLOR_ORO, false);
                $col += 2;
                $this->pintar($sheet, Coordinate::stringFromColumnIndex($col).self::FILA_GRUPO, ReporteOtDiariasService::COLOR_MAGENTA, true);
                $this->pintar($sheet, Coordinate::stringFromColumnIndex($col + 1).self::FILA_GRUPO, ReporteOtDiariasService::COLOR_MAGENTA, true);
                $this->pintar($sheet, Coordinate::stringFromColumnIndex($col).self::FILA_SUB.':'.Coordinate::stringFromColumnIndex($col + 1).self::FILA_SUB, ReporteOtDiariasService::COLOR_MAGENTA, true);
                $col += 2;
                foreach ([0, 1, 2] as $offset) {
                    $celda = Coordinate::stringFromColumnIndex($col + $offset);
                    $this->pintar($sheet, $celda.self::FILA_GRUPO, ReporteOtDiariasService::COLOR_SALMON, false);
                    $this->pintar($sheet, $celda.self::FILA_SUB, ReporteOtDiariasService::COLOR_SALMON, false);
                }

                $this->pintar($sheet, 'A'.self::FILA_GRUPO.':A'.self::FILA_SUB, ReporteOtDiariasService::COLOR_VERDE, true);

                $sheet->getStyle($rangoTabla)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '7F7F7F'],
                        ],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                    'font' => ['size' => 9],
                ]);
                $sheet->getStyle('A'.self::FILA_DATOS.':A'.$ultimaFila)
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle('A'.self::FILA_DATOS.':A'.($ultimaFila - 1))->getFont()->setBold(true);

                $colsDec1 = [];
                for ($i = 2; $i <= $ultimaCol - 4; $i++) {
                    $colsDec1[] = $i;
                }
                foreach ($colsDec1 as $i) {
                    $letra = Coordinate::stringFromColumnIndex($i);
                    $sheet->getStyle($letra.self::FILA_DATOS.':'.$letra.$ultimaFila)
                        ->getNumberFormat()
                        ->setFormatCode('#,##0.0');
                }
                $colPctCap = $this->ultimaColumna() - 3;
                $sheet->getStyle(Coordinate::stringFromColumnIndex($colPctCap).self::FILA_DATOS.':'.Coordinate::stringFromColumnIndex($colPctCap).$ultimaFila)
                    ->getNumberFormat()
                    ->setFormatCode('0.00%');
                for ($i = $this->ultimaColumna() - 2; $i <= $this->ultimaColumna(); $i++) {
                    $letra = Coordinate::stringFromColumnIndex($i);
                    $sheet->getStyle($letra.self::FILA_DATOS.':'.$letra.$ultimaFila)
                        ->getNumberFormat()
                        ->setFormatCode('0.0%');
                }

                $sheet->getColumnDimension('A')->setWidth(22);
                for ($i = 2; $i <= $ultimaCol; $i++) {
                    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setWidth(11);
                }
                $sheet->freezePane('B'.self::FILA_DATOS);
                $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
                $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A3);
                $sheet->getPageSetup()->setFitToPage(true);
                $sheet->getPageSetup()->setFitToWidth(1);
                $sheet->getPageSetup()->setFitToHeight(1);
                $sheet->getRowDimension(self::FILA_GRUPO)->setRowHeight(22);
                $sheet->getRowDimension(self::FILA_SUB)->setRowHeight(28);
            },
        ];
    }

    private function mergePintar(Worksheet $sheet, int $col, int $span, int $fila, string $color, bool $blanco): void
    {
        $inicio = Coordinate::stringFromColumnIndex($col);
        $fin = Coordinate::stringFromColumnIndex($col + $span - 1);
        if ($span > 1) {
            $sheet->mergeCells($inicio.$fila.':'.$fin.$fila);
        }
        $this->pintar($sheet, $inicio.$fila.':'.$fin.$fila, $color, $blanco);
    }

    private function pintar(Worksheet $sheet, string $rango, string $color, bool $blanco): void
    {
        $sheet->getStyle($rango)->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => $color],
            ],
            'font' => [
                'bold' => true,
                'color' => ['rgb' => $blanco ? 'FFFFFF' : '111827'],
            ],
        ]);
    }

    private function columnaInicioTotales(): int
    {
        return 2 + (count($this->reporte['dias']) * 3);
    }

    private function ultimaColumna(): int
    {
        return $this->columnaInicioTotales() + 10;
    }

    private function etiquetaPeriodo(): string
    {
        $desde = Carbon::parse($this->reporte['desde'], ReporteOtDiariasService::TZ)->format('d/m/Y');
        $hasta = Carbon::parse($this->reporte['hasta'], ReporteOtDiariasService::TZ)->format('d/m/Y');

        return $desde.' al '.$hasta;
    }
}
