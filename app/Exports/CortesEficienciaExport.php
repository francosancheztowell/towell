<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Carbon\Carbon;

class CortesEficienciaExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithTitle, WithEvents, WithColumnFormatting
{
    protected Collection $datos;
    protected string $fecha;

    public function __construct(array $info, string $fecha)
    {
        $this->datos = $info['datos'];
        $this->fecha = $fecha;
    }

    public function collection()
    {
        $rows = collect();

        foreach ($this->datos as $row) {
            $rows->push($this->mapFilaVisualizacion($row));
        }

        return $rows;
    }

    protected function mapFilaVisualizacion($row)
    {
        $t1 = $row['t1'] ?? null;
        $t2 = $row['t2'] ?? null;
        $t3 = $row['t3'] ?? null;
        $lineaBase = $t1 ?: ($t2 ?: $t3);

        return [
            'Fecha' => optional($lineaBase && $lineaBase->Date ? Carbon::parse($lineaBase->Date) : null)->format('Y-m-d') ?? $this->fecha,
            'Telar' => $row['telar'],
            'RPM Std' => $lineaBase->RpmStd ?? null,
            '% EF Std' => $lineaBase->EficienciaSTD ?? null,

            'T1 RPM' => $this->obtenerUltimaRpm($t1),
            'T1 % EF H1' => $t1->EficienciaR1 ?? null,
            'T1 Obs H1' => $t1->ObsR1 ?? null,
            'T1 % EF H2' => $t1->EficienciaR2 ?? null,
            'T1 Obs H2' => $t1->ObsR2 ?? null,
            'T1 % EF H3' => $t1->EficienciaR3 ?? null,
            'T1 Obs H3' => $t1->ObsR3 ?? null,

            'T2 RPM' => $this->obtenerUltimaRpm($t2),
            'T2 % EF H1' => $t2->EficienciaR1 ?? null,
            'T2 Obs H1' => $t2->ObsR1 ?? null,
            'T2 % EF H2' => $t2->EficienciaR2 ?? null,
            'T2 Obs H2' => $t2->ObsR2 ?? null,
            'T2 % EF H3' => $t2->EficienciaR3 ?? null,
            'T2 Obs H3' => $t2->ObsR3 ?? null,

            'T3 RPM' => $this->obtenerUltimaRpm($t3),
            'T3 % EF H1' => $t3->EficienciaR1 ?? null,
            'T3 Obs H1' => $t3->ObsR1 ?? null,
            'T3 % EF H2' => $t3->EficienciaR2 ?? null,
            'T3 Obs H2' => $t3->ObsR2 ?? null,
            'T3 % EF H3' => $t3->EficienciaR3 ?? null,
            'T3 Obs H3' => $t3->ObsR3 ?? null,
        ];
    }

    protected function obtenerUltimaRpm($linea)
    {
        foreach (['RpmR3', 'RpmR2', 'RpmR1'] as $campo) {
            $valor = $linea->{$campo} ?? null;
            if ($valor !== null && $valor !== '' && is_numeric($valor)) {
                return $valor;
            }
        }
        return null;
    }

    public function headings(): array
    {
        return [
            'Fecha',
            'Telar',
            'RPM Std',
            '% EF Std',
            'T1 RPM',
            'T1 % EF H1',
            'T1 Obs H1',
            'T1 % EF H2',
            'T1 Obs H2',
            'T1 % EF H3',
            'T1 Obs H3',
            'T2 RPM',
            'T2 % EF H1',
            'T2 Obs H1',
            'T2 % EF H2',
            'T2 Obs H2',
            'T2 % EF H3',
            'T2 Obs H3',
            'T3 RPM',
            'T3 % EF H1',
            'T3 Obs H1',
            'T3 % EF H2',
            'T3 Obs H2',
            'T3 % EF H3',
            'T3 Obs H3',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1D4ED8'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12,
            'B' => 8,
            'C' => 10,
            'D' => 10,
            'E' => 8,
            'F' => 10,
            'G' => 24,
            'H' => 10,
            'I' => 24,
            'J' => 10,
            'K' => 24,
            'L' => 8,
            'M' => 10,
            'N' => 24,
            'O' => 10,
            'P' => 24,
            'Q' => 10,
            'R' => 24,
            'S' => 8,
            'T' => 10,
            'U' => 24,
            'V' => 10,
            'W' => 24,
            'X' => 10,
            'Y' => 24,
        ];
    }

    public function title(): string
    {
        try {
            $fecha = Carbon::parse($this->fecha)->format('d-m-Y');
        } catch (\Throwable $th) {
            $fecha = $this->fecha;
        }

        return 'Cortes de eficiencia ' . $fecha;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();
                $sheet->getStyle('A1:' . $highestColumn . $highestRow)
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);
            },
        ];
    }

    public function columnFormats(): array
    {
        return [];
    }
}
