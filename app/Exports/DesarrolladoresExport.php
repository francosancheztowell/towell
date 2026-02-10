<?php

namespace App\Exports;

use App\Models\Planeacion\Catalogos\CatCodificados;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Carbon\Carbon;

class DesarrolladoresExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithTitle, WithEvents
{
    protected string $fecha;
    protected Collection $datos;

    public function __construct(string $fecha)
    {
        $this->fecha = $fecha;
        $this->datos = $this->obtenerDatos();
    }

    protected function obtenerDatos(): Collection
    {
        return CatCodificados::whereDate('FechaModificacion', $this->fecha)
            ->orWhereDate('FechaCreacion', $this->fecha)
            ->orderBy('TelarId')
            ->orderBy('NoOrden')
            ->get();
    }

    public function collection()
    {
        return $this->datos->map(function ($item) {
            return [
                'Salon' => $item->TelarId ?? '-',
                'Orden' => $item->NoOrden ?? '-',
                'Fecha Arranque' => $item->FechaArranque ? Carbon::parse($item->FechaArranque)->format('d/m/Y H:i') : '-',
                'Tamaño Clave' => $item->ClaveModelo ?? '-',
                'Modelo' => $item->Nombre ?? '-',
                'Julio Rizo' => $item->JulioRizo ?? '-',
                'Julio Pie' => $item->JulioPie ?? '-',
                'Total Pasadas' => $item->Total ?? '-',
                'Ef Inicio' => $item->EfiInicial ?? '-',
                'Hora Inicio' => $item->HrInicio ?? '-',
                'Hora Final' => $item->HrTermino ?? '-',
                'Ef Final' => $item->EfiFinal ?? '-',
                'Desarrollador' => $item->RespInicio ?? '-',
                'Codificación Modelo' => $item->CodigoDibujo ?? '-',
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Salon',
            'Orden',
            'Fecha Arranque',
            'Tamaño Clave',
            'Modelo',
            'Julio Rizo',
            'Julio Pie',
            'Total Pasadas',
            'Ef Inicio',
            'Hora Inicio',
            'Hora Final',
            'Ef Final',
            'Desarrollador',
            'Codificación Modelo',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 10,  // Salon
            'B' => 18,  // Orden
            'C' => 18,  // Fecha Arranque
            'D' => 16,  // Tamaño Clave
            'E' => 30,  // Modelo
            'F' => 12,  // Julio Rizo
            'G' => 12,  // Julio Pie
            'H' => 14,  // Total Pasadas
            'I' => 12,  // Ef Inicio
            'J' => 12,  // Hora Inicio
            'K' => 12,  // Hora Final
            'L' => 12,  // Ef Final
            'M' => 20,  // Desarrollador
            'N' => 22,  // Codificación Modelo
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:N1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '3B82F6'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function title(): string
    {
        return 'Desarrolladores ' . Carbon::parse($this->fecha)->format('d-m-Y');
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $this->datos->count() + 1;

                if ($lastRow > 1) {
                    $sheet->getStyle("A1:N{$lastRow}")->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['rgb' => 'D1D5DB'],
                            ],
                        ],
                    ]);
                }

                // Alineación centrada para columnas específicas
                $sheet->getStyle("A2:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Salon
                $sheet->getStyle("F2:H{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Julios y Pasadas
                $sheet->getStyle("I2:L{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Eficiencias y Horas

                $sheet->freezePane('A2');
            },
        ];
    }
}
