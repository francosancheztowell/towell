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
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class ReportesUrdidoExport implements FromArray, WithEvents, WithTitle
{
    protected array $porFecha;
    protected array $defectosData;

    private const TEMPLATE_COL_MAX = 30; // AD
    private const TEMPLATE_ROW_MAX = 44;
    private const BLOCK_ROWS = 44;
    private const SPACER_ROWS = 2;
    private const DATA_ROWS = 40;
    private const OP_COLS = ['W', 'X', 'Y', 'Z', 'AA', 'AB'];

    private const DEFECT_QUALITY_START_ROW = 14;
    private const DEFECT_QUALITY_TEMPLATE_ROWS = 25;
    private const DEFECT_SECURITY_START_ROW = 39;
    private const DEFECT_SECURITY_TEMPLATE_ROWS = 20;
    private const DEFECT_FOOTER_START_ROW = 60;
    private const DEFECT_DATA_FIRST_COL = 2; // B
    private const DEFECT_DATA_LAST_COL = 8; // H
    private const DEFECT_FOOTER_COL_START = 2; // B
    private const DEFECT_FOOTER_VISIBLE_COLS = 6; // B:G

    public function __construct(array $porFecha, array $defectosData = [])
    {
        $this->porFecha = $porFecha;
        $this->defectosData = [
            'calidad_rows' => array_values($defectosData['calidad_rows'] ?? []),
            'seguridad_rows' => array_values($defectosData['seguridad_rows'] ?? []),
            'footer_operators' => array_values($defectosData['footer_operators'] ?? []),
        ];
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

                $templateBook = $this->loadTemplateBook();
                $sheet0 = $templateBook->getSheet(0);
                $sheet0->setTitle('URDIDO');
                $sheet1 = $templateBook->getSheetCount() > 1 ? $templateBook->getSheet(1) : null;
                $sheet2 = $templateBook->getSheetCount() > 2 ? $templateBook->getSheet(2) : null;

                $book->removeSheetByIndex($sheetIndex);
                $book->addExternalSheet($sheet0, $sheetIndex);

                $sheet = $book->getSheet($sheetIndex);
                $rowCursor = 1;
                $firstBlock = true;

                foreach ($this->porFecha as $fecha => $datos) {
                    if (! $firstBlock) {
                        $this->copyTemplateBlockWithinSheet($sheet, 1, $rowCursor);
                    }
                    $this->fillBlockData($sheet, $rowCursor, (string) $fecha, $datos, $firstBlock);
                    $rowCursor += self::BLOCK_ROWS + self::SPACER_ROWS;
                    $firstBlock = false;
                }

                if ($sheet1 !== null) {
                    $sheet1->setTitle('Engomado');
                    $book->addExternalSheet($sheet1);

                    $engSheet = $book->getSheet($book->getSheetCount() - 1);
                    $rowCursor2 = 1;
                    $firstBlock2 = true;

                    foreach ($this->porFecha as $fecha => $datos) {
                        if (! $firstBlock2) {
                            $this->copyTemplateBlockWithinSheet($engSheet, 1, $rowCursor2);
                        }
                        $this->fillBlockDataEngomado($engSheet, $rowCursor2, (string) $fecha, $datos, $firstBlock2);
                        $rowCursor2 += self::BLOCK_ROWS + self::SPACER_ROWS;
                        $firstBlock2 = false;
                    }
                }

                if ($sheet2 !== null) {
                    $sheet2->setTitle('Defectos');
                    $book->addExternalSheet($sheet2);

                    $defectSheet = $book->getSheet($book->getSheetCount() - 1);
                    $this->fillDefectsSheet($defectSheet);
                }
            },
        ];
    }

    private function loadTemplateBook(): Spreadsheet
    {
        $candidates = [
            resource_path('templates/formato reportes.xlsx'),
            storage_path('app/templates/formato reportes.xlsx'),
            storage_path('app/formato reportes.xlsx'),
        ];

        foreach ($candidates as $path) {
            if (is_string($path) && $path !== '' && is_file($path)) {
                return IOFactory::load($path);
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
                $srcCoord = $colLetter.$sourceRow;
                $dstCoord = $colLetter.$targetRow;

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
        $machineStarts = ['MC1' => 2, 'MC2' => 7, 'MC3' => 12, 'KM' => 17];

        if ($firstBlock) {
            $sheet->setCellValue("A{$startRow}", $week);
            $sheet->setCellValue("B{$startRow}", 'Semana');
        } else {
            $sheet->setCellValue("A{$startRow}", '');
            $sheet->setCellValue("B{$startRow}", '');
        }

        $sheet->setCellValue('A'.($startRow + 1), round($totalKgDia, 1));
        $sheet->setCellValue('B'.($startRow + 1), 'Kg');
        $sheet->setCellValue('V'.($startRow + 1), $diaMap[(int) $date->dayOfWeek] ?? '');
        $sheet->setCellValue('A'.($startRow + 2), ucfirst($date->locale('es')->translatedFormat('l, d \\d\\e F \\d\\e Y')));

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
            $sheet->setCellValue("{$colKg}".($startRow + 2), round($kgM, 1));
            $sheet->setCellValue("{$colMts}".($startRow + 2), (int) round($mtsM));
        }

        for ($i = 0; $i < self::DATA_ROWS; $i++) {
            $row = $startRow + 4 + $i;
            $sheet->setCellValue("A{$row}", $i + 1);
            for ($col = 2; $col <= 21; $col++) {
                $sheet->setCellValueByColumnAndRow($col, $row, '');
            }
        }

        foreach ($machineLabels as $label) {
            $base = $machineStarts[$label];
            $rows = array_slice($mapMaquina[$label]['filas'] ?? [], 0, self::DATA_ROWS);

            foreach ($rows as $idx => $f) {
                $r = $startRow + 4 + $idx;
                $sheet->setCellValueByColumnAndRow($base, $r, $f['orden'] ?? '');
                $sheet->setCellValueByColumnAndRow($base + 1, $r, $f['julio'] ?? '');
                $sheet->setCellValueByColumnAndRow($base + 2, $r, isset($f['p_neto']) ? round((float) $f['p_neto'], 2) : '');
                $sheet->setCellValueByColumnAndRow($base + 3, $r, (isset($f['metros']) && $f['metros'] != 0) ? $f['metros'] : '');
                $sheet->setCellValueByColumnAndRow($base + 4, $r, $f['ope'] ?? '');
            }
        }

        foreach (self::OP_COLS as $colLetter) {
            for ($r = 1; $r <= self::TEMPLATE_ROW_MAX; $r++) {
                $sheet->setCellValue("{$colLetter}".($startRow + $r - 1), '');
            }
        }
    }

    private function fillBlockDataEngomado(Worksheet $sheet, int $startRow, string $fecha, array $datos, bool $firstBlock): void
    {
        $date = Carbon::parse($fecha);
        $week = (int) $date->weekOfYear;

        $engomado = $datos['engomado'] ?? ['WP2' => ['filas' => []], 'WP3' => ['filas' => []]];
        $wp2Filas = $engomado['WP2']['filas'] ?? [];
        $wp3Filas = $engomado['WP3']['filas'] ?? [];

        $wpLabels = ['WP2', 'WP3'];
        $wpStarts = ['WP2' => 2, 'WP3' => 7];

        if ($firstBlock) {
            $sheet->setCellValue("A{$startRow}", $week);
            $sheet->setCellValue("B{$startRow}", 'Semana');
        } else {
            $sheet->setCellValue("A{$startRow}", '');
            $sheet->setCellValue("B{$startRow}", '');
        }

        $totalKgEng = 0.0;
        foreach (['WP2' => $wp2Filas, 'WP3' => $wp3Filas] as $filas) {
            foreach ($filas as $f) {
                $totalKgEng += (float) ($f['p_neto'] ?? 0);
            }
        }

        $sheet->setCellValue('A'.($startRow + 1), round($totalKgEng, 1));
        $sheet->setCellValue('B'.($startRow + 1), 'Kg');
        $sheet->setCellValue('A'.($startRow + 2), ucfirst($date->locale('es')->translatedFormat('l, d \\d\\e F \\d\\e Y')));

        foreach ($wpLabels as $wp) {
            $filas = $wp === 'WP2' ? $wp2Filas : $wp3Filas;
            $kgM = 0.0;
            $mtsM = 0.0;
            foreach ($filas as $f) {
                $kgM += (float) ($f['p_neto'] ?? 0);
                $mtsM += (float) ($f['metros'] ?? 0);
            }

            $base = $wpStarts[$wp];
            $colKg = Coordinate::stringFromColumnIndex($base + 2);
            $colMts = Coordinate::stringFromColumnIndex($base + 3);
            $sheet->setCellValueByColumnAndRow($base + 2, $startRow + 1, $wp);
            $sheet->setCellValue("{$colKg}".($startRow + 2), round($kgM, 1));
            $sheet->setCellValue("{$colMts}".($startRow + 2), (int) round($mtsM));
        }

        for ($i = 0; $i < self::DATA_ROWS; $i++) {
            $row = $startRow + 4 + $i;
            $sheet->setCellValue("A{$row}", $i + 1);
            for ($col = 2; $col <= 11; $col++) {
                $sheet->setCellValueByColumnAndRow($col, $row, '');
            }
        }

        foreach (['WP2' => $wp2Filas, 'WP3' => $wp3Filas] as $wp => $filas) {
            $base = $wpStarts[$wp];
            $filas = array_slice($filas, 0, self::DATA_ROWS);
            foreach ($filas as $idx => $f) {
                $r = $startRow + 4 + $idx;
                $sheet->setCellValueByColumnAndRow($base, $r, $f['orden'] ?? '');
                $sheet->setCellValueByColumnAndRow($base + 1, $r, $f['julio'] ?? '');
                $sheet->setCellValueByColumnAndRow($base + 2, $r, isset($f['p_neto']) ? round((float) $f['p_neto'], 2) : '');
                $sheet->setCellValueByColumnAndRow($base + 3, $r, (isset($f['metros']) && $f['metros'] != 0) ? $f['metros'] : '');
                $sheet->setCellValueByColumnAndRow($base + 4, $r, $f['ope'] ?? '');
            }
        }
    }

    private function fillDefectsSheet(Worksheet $sheet): void
    {
        $calidadRows = $this->defectosData['calidad_rows'];
        $seguridadRows = $this->defectosData['seguridad_rows'];
        $footerOperators = $this->defectosData['footer_operators'];

        $layout = $this->prepareDefectsLayout($sheet, count($calidadRows), count($seguridadRows));

        $this->clearDefectSection($sheet, $layout['quality_start'], $layout['quality_display_rows']);
        $this->fillDefectRows($sheet, $layout['quality_start'], $calidadRows);

        $this->clearDefectSection($sheet, $layout['security_start'], $layout['security_display_rows']);
        $this->fillDefectRows($sheet, $layout['security_start'], $seguridadRows);

        $this->fillDefectFooter($sheet, $layout, $footerOperators);
    }

    private function prepareDefectsLayout(Worksheet $sheet, int $qualityCount, int $securityCount): array
    {
        $qualityDisplayRows = max(self::DEFECT_QUALITY_TEMPLATE_ROWS, $qualityCount);
        $qualityExtraRows = $qualityDisplayRows - self::DEFECT_QUALITY_TEMPLATE_ROWS;

        if ($qualityExtraRows > 0) {
            $this->insertStyledRowsBefore(
                $sheet,
                self::DEFECT_SECURITY_START_ROW,
                $qualityExtraRows,
                self::DEFECT_SECURITY_START_ROW - 1
            );
        }

        $securityStart = self::DEFECT_SECURITY_START_ROW + $qualityExtraRows;
        $securityDisplayRows = max(self::DEFECT_SECURITY_TEMPLATE_ROWS, $securityCount);
        $securityExtraRows = $securityDisplayRows - self::DEFECT_SECURITY_TEMPLATE_ROWS;
        $footerStart = self::DEFECT_FOOTER_START_ROW + $qualityExtraRows;

        if ($securityExtraRows > 0) {
            $this->insertStyledRowsBefore(
                $sheet,
                $footerStart,
                $securityExtraRows,
                $securityStart + self::DEFECT_SECURITY_TEMPLATE_ROWS - 1
            );
            $footerStart += $securityExtraRows;
        }

        return [
            'quality_start' => self::DEFECT_QUALITY_START_ROW,
            'quality_display_rows' => $qualityDisplayRows,
            'quality_end' => self::DEFECT_QUALITY_START_ROW + $qualityDisplayRows - 1,
            'security_start' => $securityStart,
            'security_display_rows' => $securityDisplayRows,
            'security_end' => $securityStart + $securityDisplayRows - 1,
            'footer_start' => $footerStart,
        ];
    }

    private function insertStyledRowsBefore(Worksheet $sheet, int $insertBeforeRow, int $rowsToInsert, int $styleRow): void
    {
        if ($rowsToInsert <= 0) {
            return;
        }

        $sheet->insertNewRowBefore($insertBeforeRow, $rowsToInsert);

        $highestCol = Coordinate::columnIndexFromString($sheet->getHighestColumn());
        $srcRowDim = $sheet->getRowDimension($styleRow);

        for ($offset = 0; $offset < $rowsToInsert; $offset++) {
            $targetRow = $insertBeforeRow + $offset;
            $dstRowDim = $sheet->getRowDimension($targetRow);
            $dstRowDim->setRowHeight($srcRowDim->getRowHeight());
            $dstRowDim->setVisible($srcRowDim->getVisible());
            $dstRowDim->setCollapsed($srcRowDim->getCollapsed());

            for ($col = 1; $col <= $highestCol; $col++) {
                $colLetter = Coordinate::stringFromColumnIndex($col);
                $sheet->duplicateStyle($sheet->getStyle("{$colLetter}{$styleRow}"), "{$colLetter}{$targetRow}");
                $sheet->setCellValue("{$colLetter}{$targetRow}", '');
            }
        }
    }

    private function clearDefectSection(Worksheet $sheet, int $startRow, int $displayRows): void
    {
        for ($offset = 0; $offset < $displayRows; $offset++) {
            $row = $startRow + $offset;
            $sheet->setCellValue("A{$row}", $offset + 1);
            for ($col = self::DEFECT_DATA_FIRST_COL; $col <= self::DEFECT_DATA_LAST_COL; $col++) {
                $sheet->setCellValueByColumnAndRow($col, $row, '');
            }
        }
    }

    private function fillDefectRows(Worksheet $sheet, int $startRow, array $rows): void
    {
        foreach ($rows as $index => $data) {
            $row = $startRow + $index;

            $this->setDefectDateCell($sheet, "B{$row}", $data['fecha'] ?? null);
            $sheet->setCellValue("C{$row}", $data['area'] ?? '');
            $sheet->setCellValue("D{$row}", $data['orden'] ?? '');
            $sheet->setCellValue("E{$row}", $data['julio'] ?? '');
            $sheet->setCellValue("F{$row}", $data['defecto'] ?? '');
            $sheet->setCellValue("G{$row}", $data['ope'] ?? '');

            $penalizacion = $data['penalizar'] ?? '';
            $sheet->setCellValue("H{$row}", $penalizacion === '' ? '' : (float) $penalizacion);
        }
    }

    private function setDefectDateCell(Worksheet $sheet, string $cell, mixed $value): void
    {
        if ($value === null || trim((string) $value) === '') {
            $sheet->setCellValue($cell, '');

            return;
        }

        $date = $value instanceof \DateTimeInterface
            ? Carbon::instance($value)->startOfDay()
            : Carbon::parse((string) $value)->startOfDay();

        $sheet->setCellValue($cell, ExcelDate::dateTimeToExcel($date));
    }

    private function fillDefectFooter(Worksheet $sheet, array $layout, array $footerOperators): void
    {
        $footerStart = $layout['footer_start'];
        $columnsPerGroup = max(self::DEFECT_FOOTER_VISIBLE_COLS, (int) ceil(max(count($footerOperators), 1) / 2));
        $endColIndex = self::DEFECT_FOOTER_COL_START + $columnsPerGroup - 1;

        $qualityHeaderRow1 = $footerStart;
        $qualityPenaltyRow1 = $footerStart + 1;
        $qualityScoreRow1 = $footerStart + 2;
        $qualityHeaderRow2 = $footerStart + 4;
        $qualityPenaltyRow2 = $footerStart + 5;
        $qualityScoreRow2 = $footerStart + 6;
        $securityHeaderRow1 = $footerStart + 8;
        $securityPenaltyRow1 = $footerStart + 9;
        $securityScoreRow1 = $footerStart + 10;
        $securityHeaderRow2 = $footerStart + 12;
        $securityPenaltyRow2 = $footerStart + 13;
        $securityScoreRow2 = $footerStart + 14;

        $this->ensureFooterStyles($sheet, $columnsPerGroup, $footerStart);

        foreach ($this->getFooterRows($footerStart) as $row) {
            for ($col = self::DEFECT_FOOTER_COL_START; $col <= $endColIndex; $col++) {
                $sheet->setCellValueByColumnAndRow($col, $row, '');
            }
        }

        $sheet->setCellValue("A{$qualityHeaderRow1}", '');
        $sheet->setCellValue("A{$qualityHeaderRow2}", '');
        $sheet->setCellValue("A{$securityHeaderRow1}", '');
        $sheet->setCellValue("A{$securityHeaderRow2}", '');
        $sheet->setCellValue("A{$qualityPenaltyRow1}", 'Penalizar');
        $sheet->setCellValue("A{$qualityScoreRow1}", 'Calidad');
        $sheet->setCellValue("A{$qualityPenaltyRow2}", 'Penalizar');
        $sheet->setCellValue("A{$qualityScoreRow2}", 'Calidad');
        $sheet->setCellValue("A{$securityPenaltyRow1}", 'Penalizar');
        $sheet->setCellValue("A{$securityScoreRow1}", "Seguridad 5`s");
        $sheet->setCellValue("A{$securityPenaltyRow2}", 'Penalizar');
        $sheet->setCellValue("A{$securityScoreRow2}", "Seguridad 5`s");

        $qualityCriteriaRange = '$G$'.$layout['quality_start'].':$G$'.$layout['quality_end'];
        $qualityValueRange = '$H$'.$layout['quality_start'].':$H$'.$layout['quality_end'];
        $securityCriteriaRange = '$G$'.$layout['security_start'].':$G$'.$layout['security_end'];
        $securityValueRange = '$H$'.$layout['security_start'].':$H$'.$layout['security_end'];

        $group1Operators = array_slice($footerOperators, 0, $columnsPerGroup);
        $group2Operators = array_slice($footerOperators, $columnsPerGroup, $columnsPerGroup);

        $this->populateFooterGroup(
            $sheet,
            $group1Operators,
            $columnsPerGroup,
            $qualityHeaderRow1,
            $qualityPenaltyRow1,
            $qualityScoreRow1,
            $qualityCriteriaRange,
            $qualityValueRange
        );

        $this->populateFooterGroup(
            $sheet,
            $group2Operators,
            $columnsPerGroup,
            $qualityHeaderRow2,
            $qualityPenaltyRow2,
            $qualityScoreRow2,
            $qualityCriteriaRange,
            $qualityValueRange
        );

        $this->populateFooterGroup(
            $sheet,
            $group1Operators,
            $columnsPerGroup,
            $securityHeaderRow1,
            $securityPenaltyRow1,
            $securityScoreRow1,
            $securityCriteriaRange,
            $securityValueRange,
            $qualityHeaderRow1
        );

        $this->populateFooterGroup(
            $sheet,
            $group2Operators,
            $columnsPerGroup,
            $securityHeaderRow2,
            $securityPenaltyRow2,
            $securityScoreRow2,
            $securityCriteriaRange,
            $securityValueRange,
            $qualityHeaderRow2
        );
    }

    private function ensureFooterStyles(Worksheet $sheet, int $columnsPerGroup, int $footerStart): void
    {
        if ($columnsPerGroup <= self::DEFECT_FOOTER_VISIBLE_COLS) {
            return;
        }

        $sourceCol = Coordinate::stringFromColumnIndex(
            self::DEFECT_FOOTER_COL_START + self::DEFECT_FOOTER_VISIBLE_COLS - 1
        );
        $sourceWidth = $sheet->getColumnDimension($sourceCol)->getWidth();

        foreach ($this->getFooterRows($footerStart) as $row) {
            for (
                $col = self::DEFECT_FOOTER_COL_START + self::DEFECT_FOOTER_VISIBLE_COLS;
                $col <= self::DEFECT_FOOTER_COL_START + $columnsPerGroup - 1;
                $col++
            ) {
                $colLetter = Coordinate::stringFromColumnIndex($col);
                $sheet->getColumnDimension($colLetter)->setWidth($sourceWidth);
                $sheet->duplicateStyle($sheet->getStyle("{$sourceCol}{$row}"), "{$colLetter}{$row}");
                $sheet->setCellValue("{$colLetter}{$row}", '');
            }
        }
    }

    private function getFooterRows(int $footerStart): array
    {
        return [
            $footerStart,
            $footerStart + 1,
            $footerStart + 2,
            $footerStart + 4,
            $footerStart + 5,
            $footerStart + 6,
            $footerStart + 8,
            $footerStart + 9,
            $footerStart + 10,
            $footerStart + 12,
            $footerStart + 13,
            $footerStart + 14,
        ];
    }

    private function populateFooterGroup(
        Worksheet $sheet,
        array $operators,
        int $columnsPerGroup,
        int $headerRow,
        int $penaltyRow,
        int $scoreRow,
        string $criteriaRange,
        string $valueRange,
        ?int $sourceHeaderRow = null
    ): void {
        for ($offset = 0; $offset < $columnsPerGroup; $offset++) {
            $colIndex = self::DEFECT_FOOTER_COL_START + $offset;
            $colLetter = Coordinate::stringFromColumnIndex($colIndex);
            $headerCell = "{$colLetter}{$headerRow}";
            $penaltyCell = "{$colLetter}{$penaltyRow}";
            $scoreCell = "{$colLetter}{$scoreRow}";
            $operator = $operators[$offset] ?? '';

            if ($operator === '') {
                $sheet->setCellValue($headerCell, '');
                $sheet->setCellValue($penaltyCell, '');
                $sheet->setCellValue($scoreCell, '');

                continue;
            }

            if ($sourceHeaderRow === null) {
                $sheet->setCellValue($headerCell, $operator);
            } else {
                $sheet->setCellValue($headerCell, "={$colLetter}{$sourceHeaderRow}");
            }

            $sheet->setCellValue(
                $penaltyCell,
                "=IF({$headerCell}=\"\",\"\",SUMIF({$criteriaRange},{$headerCell},{$valueRange}))"
            );
            $sheet->setCellValue($scoreCell, "=IF({$headerCell}=\"\",\"\",100-{$penaltyCell})");
        }
    }
}
