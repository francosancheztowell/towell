<?php

namespace App\Exports;

use App\Models\Atadores\AtaMontadoTelasModel;
use App\Models\Atadores\AtaMontadoMaquinasModel;
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

class AtaMontadoMaquinasSheet implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithTitle, WithEvents
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
        $query = AtaMontadoMaquinasModel::query();

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
                'Máquina ID' => $item->MaquinaId ?? '-',
                'Estado' => $item->Estado ?? '-',
            ];
        });
    }

    public function headings(): array
    {
        return [
            'No. Julio',
            'No. Producción',
            'Máquina ID',
            'Estado',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 14,  // No. Julio
            'B' => 16,  // No. Producción
            'C' => 15,  // Máquina ID
            'D' => 12,  // Estado
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:D1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '10B981'], // Verde esmeralda
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
        return 'Máquinas';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $this->datos->count() + 1;

                if ($lastRow > 1) {
                    $sheet->getStyle("A1:D{$lastRow}")->applyFromArray([
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
