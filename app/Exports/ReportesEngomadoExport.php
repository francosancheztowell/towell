<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class ReportesEngomadoExport implements FromArray, WithStyles, WithColumnWidths, WithTitle, WithEvents
{
    protected array $porMaquina;

    public function __construct(array $porMaquina)
    {
        $this->porMaquina = array_values($porMaquina);
    }

    public function array(): array
    {
        $maquinas = $this->porMaquina;
        if (empty($maquinas)) {
            return [['Sin datos']];
        }

        $colsPerBlock = 5; // ORDEN, JULIO, P. NETO, METROS, Operador

        $row1 = [];
        foreach ($maquinas as $maq) {
            $row1[] = $maq['label'] ?? '';
            $row1 = array_merge($row1, array_fill(0, $colsPerBlock - 1, ''));
        }

        $row2 = [];
        foreach ($maquinas as $maq) {
            $row2[] = 'ORDEN';
            $row2[] = 'JULIO';
            $row2[] = 'P. NETO';
            $row2[] = 'METROS';
            $row2[] = 'Operador';
        }

        $filas = array_map(fn($m) => $m['filas'] ?? [], $maquinas);
        $maxRows = max(array_map('count', $filas)) ?: 0;

        $resumenFilas = [];
        foreach ($maquinas as $maq) {
            $totalKg = 0;
            $totalMetros = 0;
            foreach ($maq['filas'] ?? [] as $f) {
                $totalKg += (float) ($f['p_neto'] ?? 0);
                $totalMetros += (int) ($f['metros'] ?? 0);
            }
            $resumenFilas[] = [$maq['label'] ?? '', round($totalKg, 2), $totalMetros];
        }

        $numMaquinas = count($maquinas);
        $totalColsMaquinas = $numMaquinas * $colsPerBlock;
        $resumenStartCol = $totalColsMaquinas + 2;

        $data = [];
        $r1 = $row1;
        $r1 = array_pad($r1, $totalColsMaquinas, '');
        $r1 = array_pad($r1, $resumenStartCol - 1, '');
        $r1[] = 'Resumen por máquina';
        $data[] = $r1;

        $r2 = $row2;
        $r2 = array_pad($r2, $totalColsMaquinas, '');
        $r2 = array_pad($r2, $resumenStartCol - 1, '');
        $r2 = array_merge($r2, ['MAQUINA', 'P. NETO (Kg)', 'METROS']);
        $data[] = $r2;

        $totalDataRows = max($maxRows, $numMaquinas);
        for ($i = 0; $i < $totalDataRows; $i++) {
            $row = [];
            foreach ($maquinas as $idx => $maq) {
                $filasMaq = $filas[$idx] ?? [];
                $fila = $filasMaq[$i] ?? null;
                if ($fila) {
                    $pNeto = isset($fila['p_neto']) && $fila['p_neto'] !== '' ? round((float) $fila['p_neto'], 2) : null;
                    $row[] = $fila['orden'] ?? '';
                    $row[] = $fila['julio'] ?? '';
                    $row[] = $pNeto;
                    $row[] = $fila['metros'] ?? '';
                    $row[] = $fila['ope'] ?? '';
                } else {
                    $row = array_merge($row, array_fill(0, $colsPerBlock, ''));
                }
            }
            $row = array_pad($row, $totalColsMaquinas, '');
            $row = array_pad($row, $resumenStartCol - 1, '');
            if ($i < $numMaquinas) {
                $row = array_merge($row, $resumenFilas[$i]);
            } else {
                $row = array_merge($row, array_fill(0, 3, ''));
            }
            $data[] = $row;
        }

        return $data;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ],
            2 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '3B82F6']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ],
        ];
    }

    public function columnWidths(): array
    {
        $cols = [];
        $numMaquinas = count($this->porMaquina);
        $colsPerBlock = 5;
        $widths = [12, 8, 10, 10, 10];
        for ($b = 0; $b < $numMaquinas; $b++) {
            for ($c = 0; $c < $colsPerBlock; $c++) {
                $colLetter = Coordinate::stringFromColumnIndex($b * $colsPerBlock + $c + 1);
                $cols[$colLetter] = $widths[$c];
            }
        }
        $resumenStartCol = $numMaquinas * $colsPerBlock + 2;
        $cols[Coordinate::stringFromColumnIndex($resumenStartCol - 1)] = 4;
        $cols[Coordinate::stringFromColumnIndex($resumenStartCol)] = 10;
        $cols[Coordinate::stringFromColumnIndex($resumenStartCol + 1)] = 12;
        $cols[Coordinate::stringFromColumnIndex($resumenStartCol + 2)] = 10;
        return $cols;
    }

    public function title(): string
    {
        return 'Reporte Engomado';
    }

    public function registerEvents(): array
    {
        $maquinas = $this->porMaquina;
        $colsPerBlock = 5;

        return [
            AfterSheet::class => function (AfterSheet $event) use ($maquinas, $colsPerBlock) {
                $sheet = $event->sheet->getDelegate();

                foreach ($maquinas as $idx => $maq) {
                    $startCol = $idx * $colsPerBlock + 1;
                    $endCol = $startCol + $colsPerBlock - 1;
                    $startLetter = Coordinate::stringFromColumnIndex($startCol);
                    $endLetter = Coordinate::stringFromColumnIndex($endCol);
                    $sheet->mergeCells("{$startLetter}1:{$endLetter}1");
                }

                $highestRow = $sheet->getHighestRow();
                $highestCol = $sheet->getHighestColumn();
                if ($highestRow > 0 && $highestCol) {
                    $range = 'A1:' . $highestCol . $highestRow;
                    $sheet->getStyle($range)->getBorders()->getAllBorders()
                        ->setBorderStyle(Border::BORDER_THIN)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('000000'));
                }

                $totalColsMaquinas = count($maquinas) * $colsPerBlock;
                $resumenStartCol = $totalColsMaquinas + 2;
                $rStart = Coordinate::stringFromColumnIndex($resumenStartCol);
                $rEnd = Coordinate::stringFromColumnIndex($resumenStartCol + 2);
                $sheet->mergeCells("{$rStart}1:{$rEnd}1");
                $sheet->getStyle("{$rStart}1")->getFont()->setBold(true);
                $sheet->getStyle("{$rStart}1")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("{$rStart}2:{$rEnd}2")
                    ->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '059669']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);

                $numMaquinas = count($maquinas);
                for ($b = 0; $b < $numMaquinas; $b++) {
                    $colPneto = $b * $colsPerBlock + 3;
                    $letter = Coordinate::stringFromColumnIndex($colPneto);
                    $sheet->getStyle("{$letter}3:{$letter}{$highestRow}")->getNumberFormat()->setFormatCode('0.00');
                }
                $letterResumen = Coordinate::stringFromColumnIndex($resumenStartCol + 1);
                $sheet->getStyle("{$letterResumen}3:{$letterResumen}{$highestRow}")->getNumberFormat()->setFormatCode('0.00');
            },
        ];
    }
}
