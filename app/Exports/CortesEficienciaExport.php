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
    protected array $foliosPorTurno;
    protected string $fecha;

    public function __construct(array $info, string $fecha)
    {
        $this->datos = $info['datos'];
        $this->foliosPorTurno = $info['foliosPorTurno'];
        $this->fecha = $fecha;
    }

    public function collection()
    {
        $rows = collect();

        foreach ($this->datos as $row) {
            $rows->push($this->mapLinea($row['telar'], $row['t1'], 'Turno 1'));
            $rows->push($this->mapLinea($row['telar'], $row['t2'], 'Turno 2'));
            $rows->push($this->mapLinea($row['telar'], $row['t3'], 'Turno 3'));
        }

        return $rows;
    }

    protected function mapLinea($telar, $linea, $turno)
    {
        if (!$linea) {
            return [
                'Fecha' => $this->fecha,
                'Telar' => $telar,
                'Turno' => $turno,
            ];
        }

        return [
            'Fecha' => optional($linea->Date ? Carbon::parse($linea->Date) : null)->format('Y-m-d') ?? $this->fecha,
            'Telar' => $telar,
            'Turno' => $turno,
            'RPM Std' => $linea->RpmStd,
            '% EF Std' => $linea->EficienciaSTD,
            'RPM R1' => $linea->RpmR1,
            '% EF R1' => $linea->EficienciaR1,
            'RPM R2' => $linea->RpmR2,
            '% EF R2' => $linea->EficienciaR2,
            'RPM R3' => $linea->RpmR3,
            '% EF R3' => $linea->EficienciaR3,
        ];
    }

    public function headings(): array
    {
        return [
            'Fecha',
            'Telar',
            'Turno',
            'RPM Std',
            '% EF Std',
            'RPM R1',
            '% EF R1',
            'RPM R2',
            '% EF R2',
            'RPM R3',
            '% EF R3',
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
            'B' => 10,
            'C' => 10,
            'D' => 10,
            'E' => 10,
            'F' => 10,
            'G' => 10,
            'H' => 10,
            'I' => 10,
            'J' => 10,
            'K' => 10,
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
