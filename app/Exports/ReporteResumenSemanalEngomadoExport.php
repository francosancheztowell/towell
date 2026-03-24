<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCharts;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Layout;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;

use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReporteResumenSemanalEngomadoExport implements FromArray, WithTitle, ShouldAutoSize, WithColumnFormatting, WithCharts, WithStyles
{
    protected array $datosSemanales;
    private int $rowCount;

    public function __construct(array $datosSemanales)
    {
        $this->datosSemanales = $datosSemanales;
        $this->rowCount = count($this->datosSemanales) + 2;
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->mergeCells('A1:I1');
        $sheet->setCellValue('A1', 'ENGOMADO');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 16,
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            ],
        ]);

        return [
            2 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'FF0000'],
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                ],
            ],
            'I2' => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => '000000'],
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'FFFF00'],
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                ],
            ],
        ];
    }

    public function array(): array
    {
        $headings = [
            'Semana',
            'No. de ORDENES',
            'No. de julios',
            'KG',
            'Metros',
            'Peso promedio por julio',
            'Metros promedio por julio',
            'Cuenta promedio por julio',
            'EFICIENCIA EN %',
        ];

        $result = [[''], $headings];

        foreach ($this->datosSemanales as $semana) {
            $result[] = [
                $semana['semana_label'],
                $semana['total_ordenes'],
                $semana['total_julios'],
                $semana['total_kg'],
                $semana['total_metros'],
                $semana['peso_promedio'],
                $semana['metros_promedio'],
                $semana['cuenta_promedio'],
                $semana['eficiencia'] ?? 0,
            ];
        }

        return $result;
    }

    public function columnFormats(): array
    {
        return [
            'B' => '#,##0',
            'C' => '#,##0',
            'D' => '#,##0.00',
            'E' => '#,##0.00',
            'F' => '#,##0.00',
            'G' => '#,##0.00',
            'H' => '#,##0.00',
            'I' => '#,##0.00"%"',
        ];
    }

    public function charts()
    {
        if ($this->rowCount < 2) {
            return [];
        }

        $sheetName = $this->title();

        $labels = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'$sheetName'!\$A\$3:\$A\${$this->rowCount}", null, $this->rowCount - 2),
        ];

        $series1 = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'$sheetName'!\$F\$3:\$F\${$this->rowCount}", null, $this->rowCount - 2),
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'$sheetName'!\$G\$3:\$G\${$this->rowCount}", null, $this->rowCount - 2),
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'$sheetName'!\$H\$3:\$H\${$this->rowCount}", null, $this->rowCount - 2),
        ];

        $plotLabels1 = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'$sheetName'!\$F\$2", null, 1),
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'$sheetName'!\$G\$2", null, 1),
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'$sheetName'!\$H\$2", null, 1),
        ];

        $dataSeries1 = new DataSeries(
            DataSeries::TYPE_BARCHART,
            DataSeries::GROUPING_STANDARD,
            range(0, count($series1) - 1),
            $plotLabels1,
            $labels,
            $series1
        );

        $plotArea1 = new PlotArea(new Layout(), [$dataSeries1]);
        $legend1 = new Legend(Legend::POSITION_TOP, null, false);
        $title1 = new Title('Promedios por Semana');

        $chart1 = new Chart(
            'chart_averages',
            $title1,
            $legend1,
            $plotArea1,
            true,
            DataSeries::EMPTY_AS_GAP,
            null,
            null
        );
        $chart1->setTopLeftPosition('L2');
        $chart1->setBottomRightPosition('X20');

        $series2 = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'$sheetName'!\$I\$3:\$I\${$this->rowCount}", null, $this->rowCount - 2),
        ];

        $plotLabels2 = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'$sheetName'!\$I\$2", null, 1),
        ];

        $dataSeries2 = new DataSeries(
            DataSeries::TYPE_LINECHART,
            DataSeries::GROUPING_STANDARD,
            range(0, count($series2) - 1),
            $plotLabels2,
            $labels,
            $series2
        );

        $plotArea2 = new PlotArea(new Layout(), [$dataSeries2]);
        $legend2 = new Legend(Legend::POSITION_TOP, null, false);
        $title2 = new Title('Eficiencia por Semana');

        $chart2 = new Chart(
            'chart_efficiency',
            $title2,
            $legend2,
            $plotArea2,
            true,
            DataSeries::EMPTY_AS_GAP,
            null,
            null
        );
        $chart2->setTopLeftPosition('L22');
        $chart2->setBottomRightPosition('X40');

        return [$chart1, $chart2];
    }

    public function title(): string
    {
        return 'Resumen Semanal Engomado';
    }
}
