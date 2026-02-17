<?php

namespace App\Exports;

use App\Models\Tejedores\TelBpmModel;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TejedoresReporteExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithTitle, WithEvents
{
    protected string $fechaInicio;
    protected string $fechaFin;
    protected Collection $datos;

    public function __construct(string $fechaInicio, string $fechaFin)
    {
        $this->fechaInicio = $fechaInicio;
        $this->fechaFin = $fechaFin;
        $this->datos = $this->obtenerDatos();
    }

    protected function obtenerDatos(): Collection
    {
        return TelBpmModel::query()
            ->whereDate('Fecha', '>=', $this->fechaInicio)
            ->whereDate('Fecha', '<=', $this->fechaFin)
            ->orderBy('Fecha')
            ->orderBy('Folio')
            ->get();
    }

    public function collection(): Collection
    {
        return $this->datos->map(function ($item) {
            return [
                'Folio' => $item->Folio ?? '',
                'Status' => $item->Status ?? '',
                'Fecha' => $item->Fecha ? Carbon::parse($item->Fecha)->format('d/m/Y H:i') : '',
                'NoRecibe' => $item->CveEmplRec ?? '',
                'NombreRecibe' => $item->NombreEmplRec ?? '',
                'TurnoRecibe' => $item->TurnoRecibe ?? '',
                'NoEntrega' => $item->CveEmplEnt ?? '',
                'NombreEntrega' => $item->NombreEmplEnt ?? '',
                'TurnoEntrega' => $item->TurnoEntrega ?? '',
                'NoAutoriza' => $item->CveEmplAutoriza ?? '',
                'NombreAutoriza' => $item->NomEmplAutoriza ?? '',
                'Comentarios' => $item->Comentarios ?? '',
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Folio',
            'Status',
            'Fecha',
            'No Recibe',
            'Nombre Recibe',
            'Turno Recibe',
            'No Entrega',
            'Nombre Entrega',
            'Turno Entrega',
            'No Autoriza',
            'Nombre Autoriza',
            'Comentarios',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '2563EB'],
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
            'A' => 14,
            'B' => 14,
            'C' => 18,
            'D' => 12,
            'E' => 26,
            'F' => 12,
            'G' => 12,
            'H' => 26,
            'I' => 12,
            'J' => 12,
            'K' => 26,
            'L' => 36,
        ];
    }

    public function title(): string
    {
        return 'BPM Tejedores';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();

                $sheet->getStyle("A1:{$highestColumn}{$highestRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'D1D5DB'],
                        ],
                    ],
                ]);

                $columnasCentradas = ['A', 'B', 'C', 'D', 'F', 'G', 'I', 'J'];
                foreach ($columnasCentradas as $col) {
                    $sheet->getStyle("{$col}2:{$col}{$highestRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                $sheet->freezePane('A2');
            },
        ];
    }
}
