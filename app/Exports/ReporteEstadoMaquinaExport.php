<?php

declare(strict_types=1);

namespace App\Exports;

use App\Services\Mecanicos\ReporteEstadoMaquinaService;
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

final class ReporteEstadoMaquinaExport implements FromArray, WithDrawings, WithEvents, WithTitle
{
    private const FILA_TITULO = 4;

    private const FILA_PERIODO = 5;

    private const FILA_SALON = 7;

    private const FILA_TELAR = 8;

    private const FILA_NUMERO = 9;

    private const FILA_CALIF = 10;

    private const FILA_DATOS = 11;

    /**
     * @param  array{
     *     mes: string,
     *     lunes: string,
     *     domingo: string,
     *     desde: string,
     *     hasta: string,
     *     salones: list<array{nombre: string, color: string, telares: list<array{id: string, nombre: string}>}>,
     *     actividades: list<array{id: int, nombre: string, prioridad: string, valores: array<string, int>}>
     * }  $reporte
     */
    public function __construct(
        private readonly array $reporte,
    ) {}

    public function title(): string
    {
        return 'Estado Máquina';
    }

    public function array(): array
    {
        $filas = [
            [''],
            [''],
            [''],
            ['HOJA DE VERIFICACIÓN ESTADO MÁQUINA'],
            ['Periodo: '.$this->etiquetaPeriodo()],
            [''],
        ];

        $salonRow = ['Control', 'Prioridad'];
        $telarRow = ['', ''];
        $numeroRow = ['', ''];
        $califRow = ['', ''];

        foreach ($this->reporte['salones'] as $salon) {
            $primero = true;
            foreach ($salon['telares'] as $telar) {
                $salonRow[] = $primero ? $salon['nombre'] : '';
                $telarRow[] = 'Telar';
                $numeroRow[] = $telar['id'];
                $califRow[] = 'calificación';
                $primero = false;
            }
        }

        $filas[] = $salonRow;
        $filas[] = $telarRow;
        $filas[] = $numeroRow;
        $filas[] = $califRow;

        foreach ($this->reporte['actividades'] as $actividad) {
            $fila = [$actividad['nombre'], $actividad['prioridad']];
            foreach ($this->telaresPlanos() as $telar) {
                $fila[] = $actividad['valores'][$telar['id']] ?? 0;
            }
            $filas[] = $fila;
        }

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
                $ultimaColLetra = Coordinate::stringFromColumnIndex($ultimaCol);
                $ultimaFila = self::FILA_DATOS + count($this->reporte['actividades']) - 1;
                $rangoTabla = 'A'.self::FILA_SALON.':'.$ultimaColLetra.$ultimaFila;
                $rangoTelares = 'C'.self::FILA_TELAR.':'.$ultimaColLetra.self::FILA_CALIF;

                $sheet->mergeCells('A'.self::FILA_TITULO.':F'.self::FILA_TITULO);
                $sheet->mergeCells('A'.self::FILA_PERIODO.':F'.self::FILA_PERIODO);
                $sheet->mergeCells('A'.self::FILA_SALON.':A'.self::FILA_CALIF);
                $sheet->mergeCells('B'.self::FILA_SALON.':B'.self::FILA_CALIF);

                $sheet->getRowDimension(1)->setRowHeight(22);
                $sheet->getRowDimension(2)->setRowHeight(22);
                $sheet->getRowDimension(3)->setRowHeight(10);
                $sheet->getRowDimension(self::FILA_TITULO)->setRowHeight(22);
                $sheet->getRowDimension(self::FILA_PERIODO)->setRowHeight(18);
                $sheet->getRowDimension(6)->setRowHeight(10);
                $sheet->getRowDimension(self::FILA_SALON)->setRowHeight(22);
                $sheet->getRowDimension(self::FILA_TELAR)->setRowHeight(18);
                $sheet->getRowDimension(self::FILA_NUMERO)->setRowHeight(20);
                $sheet->getRowDimension(self::FILA_CALIF)->setRowHeight(72);

                $sheet->getStyle('A'.self::FILA_TITULO)->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A'.self::FILA_TITULO.':A'.self::FILA_PERIODO)
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                $col = 3;
                foreach ($this->reporte['salones'] as $salon) {
                    $count = count($salon['telares']);
                    if ($count < 1) {
                        continue;
                    }
                    $inicio = Coordinate::stringFromColumnIndex($col);
                    $fin = Coordinate::stringFromColumnIndex($col + $count - 1);
                    if ($count > 1) {
                        $sheet->mergeCells($inicio.self::FILA_SALON.':'.$fin.self::FILA_SALON);
                    }
                    $sheet->getStyle($inicio.self::FILA_SALON.':'.$fin.self::FILA_CALIF)->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => $salon['color']],
                        ],
                        'font' => ['bold' => true, 'size' => 10],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical' => Alignment::VERTICAL_CENTER,
                            'wrapText' => true,
                        ],
                    ]);
                    $col += $count;
                }

                $sheet->getStyle($rangoTelares)->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);

                $sheet->getStyle('C'.self::FILA_NUMERO.':'.$ultimaColLetra.self::FILA_NUMERO)
                    ->getFont()
                    ->setBold(true)
                    ->setSize(11);

                $sheet->getStyle('C'.self::FILA_CALIF.':'.$ultimaColLetra.self::FILA_CALIF)
                    ->getAlignment()
                    ->setTextRotation(90)
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(false);
                $sheet->getStyle('C'.self::FILA_CALIF.':'.$ultimaColLetra.self::FILA_CALIF)
                    ->getFont()
                    ->setBold(false)
                    ->setSize(9);

                $sheet->getStyle('A'.self::FILA_SALON.':B'.self::FILA_CALIF)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'F3F4F6'],
                    ],
                ]);

                $sheet->getStyle($rangoTabla)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '7F7F7F'],
                        ],
                    ],
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $sheet->getStyle('C'.self::FILA_DATOS.':'.$ultimaColLetra.$ultimaFila)
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setWrapText(false);
                $sheet->getStyle('B'.self::FILA_DATOS.':B'.$ultimaFila)
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $filaExcel = self::FILA_DATOS;
                foreach ($this->reporte['actividades'] as $actividad) {
                    $sheet->getRowDimension($filaExcel)->setRowHeight(18);
                    $colExcel = 3;
                    foreach ($this->telaresPlanos() as $telar) {
                        $valor = (int) ($actividad['valores'][$telar['id']] ?? 0);
                        $color = ReporteEstadoMaquinaService::COLOR_CELDA[$valor] ?? null;
                        $celda = Coordinate::stringFromColumnIndex($colExcel).$filaExcel;
                        if ($color !== null) {
                            $sheet->getStyle($celda)->getFill()
                                ->setFillType(Fill::FILL_SOLID)
                                ->getStartColor()
                                ->setRGB($color);
                        } else {
                            $sheet->getStyle($celda)->getFont()->getColor()->setRGB('808080');
                        }
                        $colExcel++;
                    }
                    $filaExcel++;
                }

                $sheet->getColumnDimension('A')->setWidth(32);
                $sheet->getColumnDimension('B')->setWidth(12);
                for ($i = 3; $i <= $ultimaCol; $i++) {
                    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setWidth(9);
                }

                $sheet->freezePane('C'.self::FILA_DATOS);
                $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
                $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A3);
                $sheet->getPageSetup()->setFitToPage(true);
                $sheet->getPageSetup()->setFitToWidth(1);
                $sheet->getPageSetup()->setFitToHeight(1);
            },
        ];
    }

    /**
     * @return list<array{id: string, nombre: string}>
     */
    private function telaresPlanos(): array
    {
        $telares = [];
        foreach ($this->reporte['salones'] as $salon) {
            foreach ($salon['telares'] as $telar) {
                $telares[] = $telar;
            }
        }

        return $telares;
    }

    private function ultimaColumna(): int
    {
        return 2 + count($this->telaresPlanos());
    }

    private function etiquetaPeriodo(): string
    {
        $desde = Carbon::parse($this->reporte['desde'], ReporteEstadoMaquinaService::TZ)->format('d/m/Y');
        $hasta = Carbon::parse($this->reporte['hasta'], ReporteEstadoMaquinaService::TZ)->format('d/m/Y');

        return $desde.' al '.$hasta;
    }
}
