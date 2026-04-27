<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCharts;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Layout;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ReporteRpmSemanalExport implements FromArray, WithCharts, WithColumnWidths, WithEvents, WithTitle
{
    /** Fila física en Excel de encabezados (sin fila en blanco entre periodo y tabla: Maatwebsite omite filas [] al escribir). */
    private const FILA_ENCABEZADO_DATOS = 3;

    /** Encabezados de datos; deben coincidir con tabla web y leyenda del gráfico. */
    public const COL_GRUPO = 'Grupo / área';

    public const COL_TELAR = 'Telar';

    public const COL_RPM_REAL = 'Suma de RPM real (promedio semana)';

    public const COL_RPM_IDEAL = 'Suma de RPM ideal (fijo por telar)';

    /**
     * @param  array<int, array{grupo: string, no_telar: string, rpm_real: int|null, rpm_ideal: int}>  $filasOrdenTelar
     * @param  array{grupo: string, no_telar: string, rpm_real: int|null, rpm_ideal: int}  $totalGeneral
     */
    public function __construct(
        private array $filasOrdenTelar,
        private array $totalGeneral,
        private string $lunes,
        private string $domingo
    ) {}

    public function array(): array
    {
        $rows = [
            ['Reporte RPM semanal (lunes a domingo)'],
            ['Periodo: del '.$this->lunes.' al '.$this->domingo],
            [self::COL_GRUPO, self::COL_TELAR, self::COL_RPM_REAL, self::COL_RPM_IDEAL],
        ];

        foreach ($this->filasOrdenTelar as $f) {
            $rows[] = [
                $f['grupo'],
                $f['no_telar'],
                $f['rpm_real'] ?? '',
                $f['rpm_ideal'],
            ];
        }

        $tg = $this->totalGeneral;
        $rows[] = [
            $tg['grupo'],
            $tg['no_telar'],
            $tg['rpm_real'] ?? '',
            $tg['rpm_ideal'],
        ];

        return $rows;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 28,
            'B' => 10,
            'C' => 26,
            'D' => 22,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $highestRow = (int) $sheet->getHighestRow();
                $highestCol = 'D';

                $sheet->getStyle('A1:A2')->getFont()->setBold(true);

                $sheet->getStyle('A'.self::FILA_ENCABEZADO_DATOS.":{$highestCol}".self::FILA_ENCABEZADO_DATOS)->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'E2E8F0'],
                    ],
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '64748B']],
                    ],
                ]);

                if ($highestRow > self::FILA_ENCABEZADO_DATOS) {
                    $dataEndRow = $highestRow - 1;
                    $sheet->getStyle('A'.(self::FILA_ENCABEZADO_DATOS + 1).":{$highestCol}{$dataEndRow}")->applyFromArray([
                        'borders' => [
                            'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']],
                        ],
                        'alignment' => [
                            'vertical' => Alignment::VERTICAL_CENTER,
                        ],
                    ]);
                    for ($r = self::FILA_ENCABEZADO_DATOS + 1; $r <= $dataEndRow; $r++) {
                        $sheet->getStyle("C{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        $sheet->getStyle("D{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    }

                    $sheet->getStyle("A{$highestRow}:{$highestCol}{$highestRow}")->applyFromArray([
                        'font' => ['bold' => true],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'E0E7EF'],
                        ],
                        'borders' => [
                            'top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '64748B']],
                            'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '94A3B8']],
                        ],
                    ]);
                    $sheet->getStyle("C{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $sheet->getStyle("D{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }
            },
        ];
    }

    public function title(): string
    {
        return 'RPM semanal';
    }

    /**
     * Gráfico de columnas agrupadas (RPM real vs ideal por telar), visible al abrir el Excel.
     *
     * @return array<int, Chart>
     */
    public function charts(): array
    {
        $n = count($this->filasOrdenTelar);
        if ($n < 1) {
            return [];
        }

        $sheetName = $this->title();
        $firstRow = self::FILA_ENCABEZADO_DATOS + 1;
        $lastRow = self::FILA_ENCABEZADO_DATOS + $n;
        $h = self::FILA_ENCABEZADO_DATOS;

        $catLabels = [
            new DataSeriesValues(
                DataSeriesValues::DATASERIES_TYPE_STRING,
                "'{$sheetName}'!\$B\${$firstRow}:\$B\${$lastRow}",
                null,
                $n
            ),
        ];

        $seriesValues = [
            new DataSeriesValues(
                DataSeriesValues::DATASERIES_TYPE_NUMBER,
                "'{$sheetName}'!\$C\${$firstRow}:\$C\${$lastRow}",
                null,
                $n
            ),
            new DataSeriesValues(
                DataSeriesValues::DATASERIES_TYPE_NUMBER,
                "'{$sheetName}'!\$D\${$firstRow}:\$D\${$lastRow}",
                null,
                $n
            ),
        ];

        $seriesLabels = [
            new DataSeriesValues(
                DataSeriesValues::DATASERIES_TYPE_STRING,
                "'{$sheetName}'!\$C\${$h}",
                null,
                1
            ),
            new DataSeriesValues(
                DataSeriesValues::DATASERIES_TYPE_STRING,
                "'{$sheetName}'!\$D\${$h}",
                null,
                1
            ),
        ];

        $dataSeries = new DataSeries(
            DataSeries::TYPE_BARCHART,
            DataSeries::GROUPING_STANDARD,
            range(0, count($seriesValues) - 1),
            $seriesLabels,
            $catLabels,
            $seriesValues
        );
        $dataSeries->setPlotDirection(DataSeries::DIRECTION_COL);

        $plotArea = new PlotArea(new Layout, [$dataSeries]);
        $legend = new Legend(Legend::POSITION_TOP, null, false);
        $title = new Title('Valores (TELAR, orden numérico)');

        $chart = new Chart(
            'chart_rpm_por_telar',
            $title,
            $legend,
            $plotArea,
            true,
            DataSeries::EMPTY_AS_GAP,
            null,
            null
        );
        $chart->setTopLeftPosition('F2');
        $chart->setBottomRightPosition('AL34');

        return [$chart];
    }
}
