<?php

namespace App\Exports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\ReferenceHelper;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class ReportesUrdidoExport implements FromArray, WithEvents, WithTitle
{
    protected array $porFecha;

    private const TEMPLATE_COL_MAX = 30; // AD
    private const TEMPLATE_ROW_MAX = 44;
    private const BLOCK_ROWS = 44;
    private const SPACER_ROWS = 2;
    private const DATA_ROWS = 40;
    private const OP_COLS = ['W', 'X', 'Y', 'Z', 'AA', 'AB'];

    public function __construct(array $porFecha)
    {
        $this->porFecha = $porFecha;
    }

    public function array(): array
    {
        // The sheet is built from template in AfterSheet.
        return [['']];
    }

    public function title(): string
    {
        return 'Reporte Urdido';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $initialSheet = $event->sheet->getDelegate();
                $book = $initialSheet->getParent();
                $sheetIndex = $book->getIndex($initialSheet);
                $sheetTitle = $initialSheet->getTitle();

                $templateSheet = $this->loadTemplateSheet();
                $templateSheet->setTitle($sheetTitle);

                $book->removeSheetByIndex($sheetIndex);
                $book->addExternalSheet($templateSheet, $sheetIndex);

                $sheet = $book->getSheet($sheetIndex);

                $rowCursor = 1;
                $firstBlock = true;

                foreach ($this->porFecha as $fecha => $datos) {
                    if (!$firstBlock) {
                        $this->copyTemplateBlockWithinSheet($sheet, 1, $rowCursor);
                    }
                    $this->fillBlockData($sheet, $rowCursor, (string) $fecha, $datos, $firstBlock);

                    $rowCursor += self::BLOCK_ROWS + self::SPACER_ROWS;
                    $firstBlock = false;
                }
            },
        ];
    }

    private function loadTemplateSheet(): Worksheet
    {
        $candidates = [
            resource_path('templates/formato reportes.xlsx'),  // ruta en el repo (GitHub)
            storage_path('app/templates/formato reportes.xlsx'),
            storage_path('app/formato reportes.xlsx'),
        ];

        foreach ($candidates as $path) {
            if (is_string($path) && $path !== '' && is_file($path)) {
                $book = IOFactory::load($path);

                return $book->getSheet(0);
            }
        }

        throw new RuntimeException(
            'No se encontro la plantilla "formato reportes.xlsx". '
            . 'Colocala en resources/templates/ o en storage/app/templates/.'
        );
    }

    private function copyTemplateBlockWithinSheet(Worksheet $sheet, int $sourceStartRow, int $targetStartRow): void
    {
        $rowOffset = $targetStartRow - $sourceStartRow;

        for ($row = 1; $row <= self::TEMPLATE_ROW_MAX; $row++) {
            $sourceRow = $sourceStartRow + $row - 1;
            $targetRow = $targetStartRow + $row - 1;

            $srcRowDim = $sheet->getRowDimension($sourceRow);
            $dstRowDim = $sheet->getRowDimension($targetRow);
            $dstRowDim->setRowHeight($srcRowDim->getRowHeight());
            $dstRowDim->setVisible($srcRowDim->getVisible());
            $dstRowDim->setCollapsed($srcRowDim->getCollapsed());

            for ($col = 1; $col <= self::TEMPLATE_COL_MAX; $col++) {
                $colLetter = Coordinate::stringFromColumnIndex($col);
                $srcCoord = $colLetter . $sourceRow;
                $dstCoord = $colLetter . $targetRow;

                $value = $sheet->getCell($srcCoord)->getValue();
                if (is_string($value) && str_starts_with($value, '=')) {
                    $value = ReferenceHelper::getInstance()->updateFormulaReferences(
                        $value,
                        'A1',
                        0,
                        $rowOffset,
                        '',
                        true
                    );
                }

                $sheet->setCellValue($dstCoord, $value);
                $sheet->duplicateStyle($sheet->getStyle($srcCoord), $dstCoord);
            }
        }

        foreach ($sheet->getMergeCells() as $range) {
            [$start, $end] = Coordinate::rangeBoundaries($range);
            if ($start[1] < $sourceStartRow || $end[1] > ($sourceStartRow + self::TEMPLATE_ROW_MAX - 1)) {
                continue;
            }

            $startCol = Coordinate::stringFromColumnIndex($start[0]);
            $endCol = Coordinate::stringFromColumnIndex($end[0]);
            $startRow = $start[1] + $rowOffset;
            $endRow = $end[1] + $rowOffset;

            $sheet->mergeCells("{$startCol}{$startRow}:{$endCol}{$endRow}");
        }
    }

    private function fillBlockData(Worksheet $sheet, int $startRow, string $fecha, array $datos, bool $firstBlock): void
    {
        $date = Carbon::parse($fecha);
        $week = (int) $date->weekOfYear;
        $totalKgDia = (float) ($datos['totalKg'] ?? 0);

        $diaMap = [
            0 => 'DO',
            1 => 'LU',
            2 => 'MA',
            3 => 'MI',
            4 => 'JU',
            5 => 'VI',
            6 => 'SA',
        ];

        $porMaquina = $datos['porMaquina'] ?? [];
        $mapMaquina = [];
        foreach ($porMaquina as $maq) {
            $label = (string) ($maq['label'] ?? '');
            if ($label !== '') {
                $mapMaquina[$label] = $maq;
            }
        }

        $machineLabels = ['MC1', 'MC2', 'MC3', 'KM'];
        $machineStarts = ['MC1' => 2, 'MC2' => 7, 'MC3' => 12, 'KM' => 17]; // B,G,L,Q

        // First line in block (week) only for first block.
        if ($firstBlock) {
            $sheet->setCellValue("A{$startRow}", $week);
            $sheet->setCellValue("B{$startRow}", 'Semana');
        } else {
            $sheet->setCellValue("A{$startRow}", '');
            $sheet->setCellValue("B{$startRow}", '');
        }

        // Header lines.
        $sheet->setCellValue("A" . ($startRow + 1), round($totalKgDia, 1));
        $sheet->setCellValue("B" . ($startRow + 1), 'Kg');
        $sheet->setCellValue("V" . ($startRow + 1), $diaMap[(int) $date->dayOfWeek] ?? '');
        $sheet->setCellValue("A" . ($startRow + 2), ucfirst($date->locale('es')->translatedFormat('l, d \\d\\e F \\d\\e Y')));

        foreach ($machineLabels as $label) {
            $maq = $mapMaquina[$label] ?? ['label' => $label, 'filas' => []];
            $rows = $maq['filas'] ?? [];
            $kgM = 0.0;
            $mtsM = 0.0;
            foreach ($rows as $f) {
                $kgM += (float) ($f['p_neto'] ?? 0);
                $mtsM += (float) ($f['metros'] ?? 0);
            }

            $base = $machineStarts[$label];
            $colKg = Coordinate::stringFromColumnIndex($base + 2);
            $colMts = Coordinate::stringFromColumnIndex($base + 3);

            $sheet->setCellValueByColumnAndRow($base + 2, $startRow + 1, $label);
            $sheet->setCellValue("{$colKg}" . ($startRow + 2), round($kgM, 1));
            $sheet->setCellValue("{$colMts}" . ($startRow + 2), (int) round($mtsM));
        }

        // Row numbers and clear data area.
        for ($i = 0; $i < self::DATA_ROWS; $i++) {
            $row = $startRow + 4 + $i;
            $sheet->setCellValue("A{$row}", $i + 1);
            for ($col = 2; $col <= 21; $col++) {
                $sheet->setCellValueByColumnAndRow($col, $row, '');
            }
        }

        // Fill machine production rows.
        foreach ($machineLabels as $label) {
            $base = $machineStarts[$label];
            $rows = ($mapMaquina[$label]['filas'] ?? []);
            $rows = array_slice($rows, 0, self::DATA_ROWS);

            foreach ($rows as $idx => $f) {
                $r = $startRow + 4 + $idx;
                $sheet->setCellValueByColumnAndRow($base, $r, $f['orden'] ?? '');
                $sheet->setCellValueByColumnAndRow($base + 1, $r, $f['julio'] ?? '');
                $sheet->setCellValueByColumnAndRow($base + 2, $r, isset($f['p_neto']) ? round((float) $f['p_neto'], 2) : '');
                $sheet->setCellValueByColumnAndRow($base + 3, $r, $f['metros'] ?? '');
                $sheet->setCellValueByColumnAndRow($base + 4, $r, $f['ope'] ?? '');
            }
        }

        // Operator panel (W..AB).
        $ops = array_values($datos['porOperador'] ?? []);
        $ops = array_slice($ops, 0, count(self::OP_COLS));

        // Reset operator cells.
        foreach (self::OP_COLS as $colLetter) {
            $sheet->setCellValue("{$colLetter}" . ($startRow + 1), '');
            $sheet->setCellValue("{$colLetter}" . ($startRow + 2), '');
            $sheet->setCellValue("{$colLetter}" . ($startRow + 6), '');
            $sheet->setCellValue("{$colLetter}" . ($startRow + 9), 8);
        }

        $metersList = [];
        foreach ($ops as $i => $op) {
            $col = self::OP_COLS[$i];
            $name = trim((string) ($op['nombre'] ?? ''));
            $meters = (float) ($op['metros'] ?? 0);

            $sheet->setCellValue("{$col}" . ($startRow + 1), mb_strtoupper($name));
            $sheet->setCellValue("{$col}" . ($startRow + 2), round($meters));

            $metersList[] = $meters;
        }

        // Percent target row (row + 6, equivalent to template row 7).
        $maxMeters = !empty($metersList) ? max($metersList) : 1;
        foreach ($metersList as $i => $meters) {
            if ($i >= 5) {
                // Template formulas only use W..AA (5 cols).
                continue;
            }
            $pct = (int) max(75, min(90, round(75 + (($meters / max($maxMeters, 1)) * 15))));
            $sheet->setCellValue(self::OP_COLS[$i] . ($startRow + 6), $pct);
        }
    }
}
