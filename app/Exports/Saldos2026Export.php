<?php

namespace App\Exports;

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
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class Saldos2026Export implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithTitle, WithEvents
{
    public function __construct(private readonly Collection $registros) {}

    public function collection(): Collection
    {
        $row = 1; // fila 1 = encabezado, datos desde fila 2

        return $this->registros->map(function ($r) use (&$row) {
            $row++;

            $esLider          = $r->_esLider ?? true;
            $esGrupoVinculado = $r->_esGrupoVinculado ?? false;

            $cantidadProduzir  = $esLider ? ($r->_sumTotalPedido ?? $r->TotalPedido ?? 0) : 'ABIERTO';
            $rollosProg        = $esLider ? ($r->_sumTotalRollos ?? $r->TotalRollos ?? 0) : 'ABIERTO';
            $toallasTejidas    = $esLider ? ($r->_sumProduccion ?? $r->Produccion ?? 0) : 'ABIERTO';
            $saldo            = $esLider ? ($r->_sumSaldoPedido ?? $r->SaldoPedido ?? 0) : 'ABIERTO';

            // Faltan: solo para líderes de grupo vinculadas, o registros normales
            $faltan = null;
            if ($esLider && $esGrupoVinculado) {
                $faltan = "=IF(K{$row}>0,K{$row}-AK{$row},\"\")";
            } elseif (!$esGrupoVinculado) {
                $faltan = "=IF(K{$row}>0,K{$row}-AK{$row},\"\")";
            } else {
                $faltan = '—';
            }

            // Avance y Rollos x Tejer: mismo criterio
            $avance = null;
            if ($esLider && $esGrupoVinculado) {
                $avance = "=IF(K{$row}>0,AK{$row}/K{$row},\"\")";
            } elseif (!$esGrupoVinculado) {
                $avance = "=IF(K{$row}>0,AK{$row}/K{$row},\"\")";
            } else {
                $avance = '—';
            }

            $rollosXTejer = null;
            if ($esLider && $esGrupoVinculado) {
                $rollosXTejer = "=IF(AND(AG{$row}>0,AH{$row}>0,AL{$row}>0),CEILING(AL{$row}/(AG{$row}*AH{$row}),1),\"\")";
            } elseif (!$esGrupoVinculado) {
                $rollosXTejer = "=IF(AND(AG{$row}>0,AH{$row}>0,AL{$row}>0),CEILING(AL{$row}/(AG{$row}*AH{$row}),1),\"\")";
            } else {
                $rollosXTejer = '—';
            }

            return [
            $r->NoTelarId,
            $r->EnProceso ? 'Sí' : '',
            $r->OrdCompartida,
            $r->_ordenLider ?? $r->OrdenLider,
            $r->NoProduccion,
            $r->Programado ? Carbon::parse($r->Programado)->format('d/m/Y') : '',
            $r->Prioridad,
            $r->NombreProducto,
            $r->TamanoClave,
            $r->ItemId,
            $cantidadProduzir,
            '',                // SOLICITADO — pendiente
            $r->LargoCrudo,
            $r->PesoCrudo,
            $r->Luchaje,
            $r->CuentaRizo,
            $r->CalibreRizo2,
            $r->FibraRizo,
            $r->CuentaPie,
            $r->CalibrePie2,
            $r->FibraPie,
            $r->C1,
            $r->ObsC1,
            $r->C2,
            $r->ObsC2,
            $r->C3,
            $r->ObsC3,
            $r->C4,
            $r->ObsC4,
            $r->MedidaCenefa,
            $r->MedIniRizoCenefa,
            $r->Rasurado,
            $r->NoTiras,
            $r->Repeticiones,
            $rollosProg,
            $toallasTejidas,
            $saldo,
            $faltan,
            $avance,
            $rollosXTejer,
            $r->Observaciones,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'TELAR',
            'En Proceso',
            'Orden Vinculada',
            'Orden Jefe Líder',
            'Número de Orden',
            'Telar Programado',
            'Prioridad',
            'Modelo',
            'CLAVE MODELO',
            'CLAVE AX',
            'Cantidad a Producir',
            'SOLICITADO',
            'Largo',
            'Peso crudo',
            'Luchaje',
            'Cuenta Rizo',
            'Calibre Rizo',
            'Fibra Rizo',
            'Cuenta Pie',
            'Calibre Pie',
            'Fibra Pie',
            'C1',
            'OBS C1',
            'C2',
            'OBS C2',
            'C3',
            'OBS C3',
            'C4',
            'OBS C4',
            'Med. de Cenefa',
            'Med. inicio rizo a cenefa',
            'RAZURADA',
            'TIRAS',
            'Repeticiones por corte',
            'Rollos programados',
            'Toallas Tejidas',
            'SALDO',
            'Faltan',
            'Avance',
            'Rollos por Tejer',
            'Observaciones',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 9],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1D4ED8'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 8,   // TELAR
            'B' => 10,  // En Proceso
            'C' => 14,  // Orden Vinculada
            'D' => 18,  // Orden Jefe Líder
            'E' => 16,  // Número de Orden
            'F' => 14,  // Telar Programado
            'G' => 10,  // Prioridad
            'H' => 28,  // Modelo
            'I' => 14,  // CLAVE MODELO
            'J' => 14,  // CLAVE AX
            'K' => 16,  // Cantidad a Producir
            'L' => 12,  // SOLICITADO
            'M' => 8,   // Largo
            'N' => 10,  // Peso crudo
            'O' => 10,  // Luchaje
            'P' => 10,  // Cuenta Rizo
            'Q' => 12,  // Calibre Rizo
            'R' => 16,  // Fibra Rizo
            'S' => 10,  // Cuenta Pie
            'T' => 12,  // Calibre Pie
            'U' => 16,  // Fibra Pie
            'V' => 8,   // C1
            'W' => 10,  // OBS C1
            'X' => 8,   // C2
            'Y' => 10,  // OBS C2
            'Z' => 8,   // C3
            'AA' => 10, // OBS C3
            'AB' => 8,  // C4
            'AC' => 10, // OBS C4
            'AD' => 14, // Med. de Cenefa
            'AE' => 20, // Med. inicio rizo a cenefa
            'AF' => 12, // RAZURADA
            'AG' => 8,  // TIRAS
            'AH' => 18, // Repeticiones por corte
            'AI' => 16, // Rollos programados
            'AJ' => 14, // Toallas Tejidas
            'AK' => 10, // SALDO
            'AL' => 10, // Faltan
            'AM' => 10, // Avance
            'AN' => 14, // Rollos por Tejer
            'AO' => 24, // Observaciones
        ];
    }

    public function title(): string
    {
        return 'Saldos 2026';
    }

    public function registerEvents(): array
    {
        // Columnas verdes (Largo, RIZO x3, Calibre Pie, Fibra Pie, Rollos prog., Rollos x Tejer)
        $colsVerde      = ['M', 'P', 'Q', 'R', 'T', 'U', 'AI', 'AN'];
        // Columnas amarillas (Cuenta Pie)
        $colsAmarillo   = ['S'];

        return [
            AfterSheet::class => function (AfterSheet $event) use ($colsVerde, $colsAmarillo) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();

                $sheet->getStyle("A1:{$highestColumn}{$highestRow}")
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)
                    ->setColor(new Color('CCCCCC'));

                $sheet->getRowDimension(1)->setRowHeight(26);

                // Formato porcentaje para columna Avance (AM)
                if ($highestRow > 1) {
                    $sheet->getStyle("AM2:AM{$highestRow}")
                        ->getNumberFormat()
                        ->setFormatCode('0.0%');
                }

                // Color de encabezado para columnas verde/amarillo
                foreach ($colsVerde as $col) {
                    $sheet->getStyle("{$col}1")->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('16A34A');
                }
                foreach ($colsAmarillo as $col) {
                    $sheet->getStyle("{$col}1")->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('D97706');
                }

                // Alternar color filas de datos + colorear columnas especiales
                for ($row = 2; $row <= $highestRow; $row++) {
                    $baseRgb = ($row % 2 === 0) ? 'F8FAFF' : 'FFFFFF';

                    $sheet->getStyle("A{$row}:{$highestColumn}{$row}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB($baseRgb);

                    foreach ($colsVerde as $col) {
                        $sheet->getStyle("{$col}{$row}")->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setRGB('DCFCE7');
                    }
                    foreach ($colsAmarillo as $col) {
                        $sheet->getStyle("{$col}{$row}")->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setRGB('FEF3C7');
                    }
                }
            },
        ];
    }
}
