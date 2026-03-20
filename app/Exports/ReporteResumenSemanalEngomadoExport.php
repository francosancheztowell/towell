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

class ReporteResumenSemanalEngomadoExport implements FromArray, WithTitle, ShouldAutoSize, WithColumnFormatting, WithCharts
{
    protected array $datosSemanales;
    private int $rowCount;

    public function __construct(array $datosSemanales)
    {
        $this->datosSemanales = $datosSemanales;
        $this->rowCount = count($this->datosSemanales) + 1; // +1 for heading row
    }

    public function array(): array
    {
        $headings = [
            'Semana',
            'No. de ORDENES',
            'No. Julios',
            'KG',
            'Metros',
            'Cuentas',
            'Peso promedio por julio',
            'Metros promedio por julio',
            'Cuenta promedio por julio',
            'EFICIENCIA EN %',
        ];

        $result = [$headings];

        foreach ($this->datosSemanales as $semana) {
            $result[] = [
                $semana['semana_label'],
                $semana['total_ordenes'],
                $semana['total_julios'],
                $semana['total_kg'],
                $semana['total_metros'],
                $semana['total_cuenta'],
                $semana['peso_promedio'],
                $semana['metros_promedio'],
                $semana['cuenta_promedio'],
                null, // Eficiencia
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
            'I' => '#,##0.00',
        ];
    }

    public function charts()
    {
        if ($this->rowCount < 2) {
            return [];
        }

        $sheetName = $this->title();

        $labels = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'$sheetName'!\$A\$2:\$A\${$this->rowCount}", null, $this->rowCount - 1),
        ];

        $series = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'$sheetName'!\$G\$2:\$G\${$this->rowCount}", null, $this->rowCount - 1),
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'$sheetName'!\$H\$2:\$H\${$this->rowCount}", null, $this->rowCount - 1),
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'$sheetName'!\$I\$2:\$I\${$this->rowCount}", null, $this->rowCount - 1),
        ];

        // Correctly define series titles by pointing to header cells
        $plotLabels = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'$sheetName'!\$G\$1", null, 1),
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'$sheetName'!\$H\$1", null, 1),
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'$sheetName'!\$I\$1", null, 1),
        ];

        $dataSeries = new DataSeries(
            DataSeries::TYPE_BARCHART,
            DataSeries::GROUPING_STANDARD,
            range(0, count($series) - 1),
            $plotLabels, // Use the corrected plot labels
            $labels,
            $series
        );

        $plotArea = new PlotArea(new Layout(), [$dataSeries]);
        $legend = new Legend(Legend::POSITION_TOP, null, false);
        $title = new Title('Promedios por Semana');

        $chart = new Chart(
            'chart1',
            $title,
            $legend,
            $plotArea,
            true,
            DataSeries::EMPTY_AS_GAP,
            null,
            null
        );

        $chart->setTopLeftPosition('L2');
        $chart->setBottomRightPosition('X20');

        return $chart;
    }


    public function title(): string
    {
        return 'Resumen Semanal Engomado';
    }
}
