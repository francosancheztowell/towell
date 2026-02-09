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
                'Fecha Tejido' => $item->FechaTejido ? Carbon::parse($item->FechaTejido)->format('d/m/Y') : '-',
                'Telar' => $item->TelarId ?? '-',
                'No. Orden' => $item->NoOrden ?? '-',
                'Código Dibujo' => $item->CodigoDibujo ?? '-',
                'Clave Modelo' => $item->ClaveModelo ?? '-',
                'Nombre' => $item->Nombre ?? '-',
                'Julio Rizo' => $item->JulioRizo ?? '-',
                'Julio Pie' => $item->JulioPie ?? '-',
                'Eficiencia Inicial' => $item->EfiInicial ?? '-',
                'Eficiencia Final' => $item->EfiFinal ?? '-',
                'Hora Inicio' => $item->HrInicio ?? '-',
                'Hora Término' => $item->HrTermino ?? '-',
                'Minutos Cambio' => $item->MinutosCambio ?? '-',
                'Responsable Inicio' => $item->RespInicio ?? '-',
                'Trama Ancho Peine' => $item->TramaAnchoPeine !== null ? number_format($item->TramaAnchoPeine, 2) : '-',
                'Desperdicio Trama' => $item->DesperdicioTrama !== null ? number_format($item->DesperdicioTrama, 2) : '-',
                'Long. Lucha Total' => $item->LogLuchaTotal !== null ? number_format($item->LogLuchaTotal, 2) : '-',
                'Total Pasadas' => $item->Total ?? '-',
                'Peine' => $item->Peine ?? '-',
                'Ancho' => $item->Ancho ?? '-',
                'Largo' => $item->Largo ?? '-',
                'Calibre Trama' => $item->Tra ?? '-',
                'Color Trama' => $item->ColorTrama ?? '-',
                'Calibre Rizo' => $item->CalibreRizo ?? '-',
                'Cuenta Rizo' => $item->CuentaRizo ?? '-',
                'Fibra Rizo' => $item->FibraRizo ?? '-',
                'Calibre Pie' => $item->CalibrePie ?? '-',
                'Cuenta Pie' => $item->CuentaPie ?? '-',
                'Fibra Pie' => $item->FibraPie ?? '-',
                'Tipo Rizo' => $item->TipoRizo ?? '-',
                'Altura Rizo' => $item->AlturaRizo ?? '-',
                'Velocidad STD' => $item->VelocidadSTD ?? '-',
                'Eficiencia STD' => $item->EficienciaSTD ?? '-',
                'Obs' => $item->Obs ?? '-',
                'Usuario Crea' => $item->UsuarioCrea ?? '-',
                'Fecha Creación' => $item->FechaCreacion ? Carbon::parse($item->FechaCreacion)->format('d/m/Y') : '-',
                'Usuario Modifica' => $item->UsuarioModifica ?? '-',
                'Fecha Modificación' => $item->FechaModificacion ? Carbon::parse($item->FechaModificacion)->format('d/m/Y') : '-',
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Fecha Tejido',
            'Telar',
            'No. Orden',
            'Código Dibujo',
            'Clave Modelo',
            'Nombre',
            'Julio Rizo',
            'Julio Pie',
            'Eficiencia Inicial',
            'Eficiencia Final',
            'Hora Inicio',
            'Hora Término',
            'Minutos Cambio',
            'Responsable Inicio',
            'Trama Ancho Peine',
            'Desperdicio Trama',
            'Long. Lucha Total',
            'Total Pasadas',
            'Peine',
            'Ancho',
            'Largo',
            'Calibre Trama',
            'Color Trama',
            'Calibre Rizo',
            'Cuenta Rizo',
            'Fibra Rizo',
            'Calibre Pie',
            'Cuenta Pie',
            'Fibra Pie',
            'Tipo Rizo',
            'Altura Rizo',
            'Velocidad STD',
            'Eficiencia STD',
            'Obs',
            'Usuario Crea',
            'Fecha Creación',
            'Usuario Modifica',
            'Fecha Modificación',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12,  // Fecha Tejido
            'B' => 8,   // Telar
            'C' => 16,  // No. Orden
            'D' => 14,  // Código Dibujo
            'E' => 14,  // Clave Modelo
            'F' => 25,  // Nombre
            'G' => 12,  // Julio Rizo
            'H' => 12,  // Julio Pie
            'I' => 14,  // Eficiencia Inicial
            'J' => 14,  // Eficiencia Final
            'K' => 12,  // Hora Inicio
            'L' => 12,  // Hora Término
            'M' => 14,  // Minutos Cambio
            'N' => 18,  // Responsable Inicio
            'O' => 16,  // Trama Ancho Peine
            'P' => 16,  // Desperdicio Trama
            'Q' => 16,  // Long. Lucha Total
            'R' => 12,  // Total Pasadas
            'S' => 10,  // Peine
            'T' => 10,  // Ancho
            'U' => 10,  // Largo
            'V' => 14,  // Calibre Trama
            'W' => 14,  // Color Trama
            'X' => 12,  // Calibre Rizo
            'Y' => 12,  // Cuenta Rizo
            'Z' => 14,  // Fibra Rizo
            'AA' => 12, // Calibre Pie
            'AB' => 12, // Cuenta Pie
            'AC' => 14, // Fibra Pie
            'AD' => 12, // Tipo Rizo
            'AE' => 12, // Altura Rizo
            'AF' => 14, // Velocidad STD
            'AG' => 14, // Eficiencia STD
            'AH' => 25, // Obs
            'AI' => 18, // Usuario Crea
            'AJ' => 14, // Fecha Creación
            'AK' => 18, // Usuario Modifica
            'AL' => 16, // Fecha Modificación
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:AL1')->applyFromArray([
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
                    $sheet->getStyle("A1:AL{$lastRow}")->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['rgb' => 'D1D5DB'],
                            ],
                        ],
                    ]);
                }

                // Alineación centrada para columnas específicas
                $sheet->getStyle("B2:B{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Telar
                $sheet->getStyle("I2:J{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Eficiencias
                $sheet->getStyle("K2:M{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Horas

                $sheet->freezePane('A2');
            },
        ];
    }
}
