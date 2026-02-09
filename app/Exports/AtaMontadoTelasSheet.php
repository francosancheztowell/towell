<?php

namespace App\Exports;

use App\Models\Atadores\AtaMontadoTelasModel;
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

class AtaMontadoTelasSheet implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithTitle, WithEvents
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
        return AtaMontadoTelasModel::whereDate('Fecha', $this->fecha)
            ->orderBy('Turno')
            ->orderBy('NoTelarId')
            ->get();
    }

    public function collection()
    {
        return $this->datos->map(function ($item) {
            return [
                'Estatus' => $item->Estatus ?? '-',
                'Fecha' => $item->Fecha ? Carbon::parse($item->Fecha)->format('d/m/Y') : '-',
                'Turno' => $item->Turno ?? '-',
                'No. Julio' => $item->NoJulio ?? '-',
                'No. Producción' => $item->NoProduccion ?? '-',
                'Tipo' => $item->Tipo ?? '-',
                'Metros' => $item->Metros !== null ? number_format($item->Metros, 2) : '-',
                'No. Telar' => $item->NoTelarId ?? '-',
                'Lote Proveedor' => $item->LoteProveedor ?? '-',
                'No. Proveedor' => $item->NoProveedor ?? '-',
                'Merga Kg' => $item->MergaKg !== null ? number_format($item->MergaKg, 2) : '-',
                'Hora Paro' => $item->HoraParo ?? '-',
                'Hora Arranque' => $item->HoraArranque ?? '-',
                'Hr. Inicio' => $item->HrInicio ?? '-',
                'Calidad' => $item->Calidad ?? '-',
                'Limpieza' => $item->Limpieza ?? '-',
                'Cve. Supervisor' => $item->CveSupervisor ?? '-',
                'Nom. Supervisor' => $item->NomSupervisor ?? '-',
                'Cve. Tejedor' => $item->CveTejedor ?? '-',
                'Nom. Tejedor' => $item->NomTejedor ?? '-',
                'Fecha Supervisor' => $item->FechaSupervisor ? Carbon::parse($item->FechaSupervisor)->format('d/m/Y H:i') : '-',
                'Obs' => $item->Obs ?? '-',
                'Comentarios Sup.' => $item->comments_sup ?? '-',
                'Comentarios Tej.' => $item->comments_tej ?? '-',
                'Comentarios Ata.' => $item->comments_ata ?? '-',
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Estatus',
            'Fecha',
            'Turno',
            'No. Julio',
            'No. Producción',
            'Tipo',
            'Metros',
            'No. Telar',
            'Lote Proveedor',
            'No. Proveedor',
            'Merga Kg',
            'Hora Paro',
            'Hora Arranque',
            'Hr. Inicio',
            'Calidad',
            'Limpieza',
            'Cve. Supervisor',
            'Nom. Supervisor',
            'Cve. Tejedor',
            'Nom. Tejedor',
            'Fecha Supervisor',
            'Obs',
            'Comentarios Sup.',
            'Comentarios Tej.',
            'Comentarios Ata.',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12,  // Estatus
            'B' => 12,  // Fecha
            'C' => 8,   // Turno
            'D' => 14,  // No. Julio
            'E' => 16,  // No. Producción
            'F' => 10,  // Tipo
            'G' => 10,  // Metros
            'H' => 10,  // No. Telar
            'I' => 14,  // Lote Proveedor
            'J' => 12,  // No. Proveedor
            'K' => 10,  // Merga Kg
            'L' => 12,  // Hora Paro
            'M' => 14,  // Hora Arranque
            'N' => 12,  // Hr. Inicio
            'O' => 10,  // Calidad
            'P' => 10,  // Limpieza
            'Q' => 14,  // Cve. Supervisor
            'R' => 20,  // Nom. Supervisor
            'S' => 14,  // Cve. Tejedor
            'T' => 20,  // Nom. Tejedor
            'U' => 18,  // Fecha Supervisor
            'V' => 25,  // Obs
            'W' => 25,  // Comentarios Sup.
            'X' => 25,  // Comentarios Tej.
            'Y' => 25,  // Comentarios Ata.
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:Y1')->applyFromArray([
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
        return 'Telas';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $this->datos->count() + 1;

                if ($lastRow > 1) {
                    $sheet->getStyle("A1:Y{$lastRow}")->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['rgb' => 'D1D5DB'],
                            ],
                        ],
                    ]);
                }

                $sheet->freezePane('A2');
            },
        ];
    }
}
