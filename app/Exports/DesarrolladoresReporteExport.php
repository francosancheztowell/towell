<?php

namespace App\Exports;

use App\Models\Planeacion\Catalogos\CatCodificados;
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

class DesarrolladoresReporteExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithTitle, WithEvents
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
        return CatCodificados::whereDate('FechaArranque', '>=', $this->fechaInicio)
            ->whereDate('FechaArranque', '<=', $this->fechaFin)
            ->orderBy('Departamento')
            ->orderBy('OrdenTejido')
            ->get();
    }

    public function collection(): Collection
    {
        return $this->datos->map(function ($item) {
            return [
                'Salon' => $item->Departamento ?? '',
                'Orden' => $item->OrdenTejido ?? '',
                'FechaArranque' => $item->FechaArranque ? Carbon::parse($item->FechaArranque)->format('d/m/Y') : '',
                'Clave' => $item->Clave ?? '',
                'ClaveModelo' => $item->ClaveModelo ?? '',
                'JulioRizo' => $item->JulioRizo ?? '',
                'JulioPie' => $item->JulioPie ?? '',
                'Total' => $item->Total ?? '',
                'EfiInicial' => $item->EfiInicial ?? '',
                'HoraCreacion' => $item->HoraCreacion ?? '',
                'HrInicio' => $item->HrInicio ?? '',
                'HrTermino' => $item->HrTermino ?? '',
                'EfiFinal' => $item->EfiFinal ?? '',
                'Supervisor' => $item->Supervisor ?? '',
                'CodificacionModelo' => $item->CodigoDibujo ?? '',
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Salón',
            'Orden',
            'Fecha Arranque',
            'Clave',
            'Clave Modelo',
            'Julio Rizo',
            'Julio Pie',
            'Total',
            'Efi. Inicial',
            'Hora Creación',
            'Hr Inicio',
            'Hr Término',
            'Efi. Final',
            'Supervisor',
            'Codificación Modelo',
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
            'A' => 12,  // Salón
            'B' => 15,  // Orden
            'C' => 15,  // Fecha Arranque
            'D' => 15,  // Clave
            'E' => 18,  // Clave Modelo
            'F' => 12,  // Julio Rizo
            'G' => 12,  // Julio Pie
            'H' => 10,  // Total
            'I' => 12,  // Efi. Inicial
            'J' => 14,  // Hora Creación
            'K' => 12,  // Hr Inicio
            'L' => 12,  // Hr Término
            'M' => 12,  // Efi. Final
            'N' => 20,  // Supervisor
            'O' => 25,  // Codificación Modelo
        ];
    }

    public function title(): string
    {
        return 'Desarrolladores';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();

                // Aplicar bordes a todas las celdas con datos
                $sheet->getStyle("A1:{$highestColumn}{$highestRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'D1D5DB'],
                        ],
                    ],
                ]);

                // Centrar contenido de las columnas numéricas
                $columnasCentradas = ['A', 'B', 'C', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M'];
                foreach ($columnasCentradas as $col) {
                    $sheet->getStyle("{$col}2:{$col}{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                // Congelar primera fila
                $sheet->freezePane('A2');
            },
        ];
    }
}
