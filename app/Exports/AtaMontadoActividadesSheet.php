<?php

namespace App\Exports;

use App\Models\Atadores\AtaMontadoTelasModel;
use App\Models\Atadores\AtaMontadoActividadesModel;
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

class AtaMontadoActividadesSheet implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithTitle, WithEvents
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
        // Obtener los folios (NoJulio + NoProduccion) de la fecha
        $folios = AtaMontadoTelasModel::whereDate('Fecha', $this->fecha)
            ->select('NoJulio', 'NoProduccion')
            ->get();

        if ($folios->isEmpty()) {
            return collect();
        }

        // Construir query con los folios encontrados
        $query = AtaMontadoActividadesModel::query();

        $folios->each(function ($folio, $index) use ($query) {
            if ($index === 0) {
                $query->where(function ($q) use ($folio) {
                    $q->where('NoJulio', $folio->NoJulio)
                      ->where('NoProduccion', $folio->NoProduccion);
                });
            } else {
                $query->orWhere(function ($q) use ($folio) {
                    $q->where('NoJulio', $folio->NoJulio)
                      ->where('NoProduccion', $folio->NoProduccion);
                });
            }
        });

        return $query->orderBy('NoJulio')->orderBy('NoProduccion')->get();
    }

    public function collection()
    {
        return $this->datos->map(function ($item) {
            return [
                'No. Julio' => $item->NoJulio ?? '-',
                'No. Producción' => $item->NoProduccion ?? '-',
                'Actividad ID' => $item->ActividadId ?? '-',
                'Porcentaje' => $item->Porcentaje !== null ? number_format($item->Porcentaje, 2) . '%' : '-',
                'Estado' => $item->Estado ?? '-',
                'Cve. Empleado' => $item->CveEmpl ?? '-',
                'Nom. Empleado' => $item->NomEmpl ?? '-',
                'Turno' => $item->Turno ?? '-',
            ];
        });
    }

    public function headings(): array
    {
        return [
            'No. Julio',
            'No. Producción',
            'Actividad ID',
            'Porcentaje',
            'Estado',
            'Cve. Empleado',
            'Nom. Empleado',
            'Turno',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 14,  // No. Julio
            'B' => 16,  // No. Producción
            'C' => 14,  // Actividad ID
            'D' => 12,  // Porcentaje
            'E' => 12,  // Estado
            'F' => 14,  // Cve. Empleado
            'G' => 25,  // Nom. Empleado
            'H' => 8,   // Turno
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:H1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F59E0B'], // Ámbar/naranja
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
        return 'Actividades';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $this->datos->count() + 1;

                if ($lastRow > 1) {
                    $sheet->getStyle("A1:H{$lastRow}")->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['rgb' => 'D1D5DB'],
                            ],
                        ],
                    ]);
                }

                // Alineación centrada para columnas específicas
                $sheet->getStyle("D2:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);  // Porcentaje
                $sheet->getStyle("H2:H{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Turno

                $sheet->freezePane('A2');
            },
        ];
    }
}
