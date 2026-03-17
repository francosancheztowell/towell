<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class ControlMermaExport implements FromArray, WithEvents, WithTitle
{
    private const DATA_START_ROW = 6;
    private const TEMPLATE_FIRST_DATA_ROW = 6;
    private const TEMPLATE_MIDDLE_DATA_ROW = 7;
    private const TEMPLATE_LAST_DATA_ROW = 19;
    private const TEMPLATE_TOTAL_ROW = 20;
    private const TEMPLATE_MAX_COLUMN = 28; // AB
    private const TEMPLATE_VISIBLE_DATA_ROWS = 14;

    public function __construct(
        private readonly Collection $rows
    ) {
    }

    public function array(): array
    {
        return [['']];
    }

    public function title(): string
    {
        return 'Control Merma';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $initialSheet = $event->sheet->getDelegate();
                $book = $initialSheet->getParent();
                $sheetIndex = $book->getIndex($initialSheet);

                $templateBook = $this->loadTemplateBook();
                $templateSheet = $templateBook->getSheet(0);
                $templateSheet->setTitle($this->title());

                $book->removeSheetByIndex($sheetIndex);
                $book->addExternalSheet($templateSheet, $sheetIndex);

                $sheet = $book->getSheet($sheetIndex);
                $this->fillSheet($sheet);
            },
        ];
    }

    private function loadTemplateBook(): Spreadsheet
    {
        $candidates = [
            resource_path('templates/ControlMerma.xlsx'),
            storage_path('app/templates/ControlMerma.xlsx'),
        ];

        foreach ($candidates as $path) {
            if (is_string($path) && $path !== '' && is_file($path)) {
                return IOFactory::load($path);
            }
        }

        throw new RuntimeException('No se encontro la plantilla ControlMerma.xlsx en resources/templates/.');
    }

    private function fillSheet(Worksheet $sheet): void
    {
        [$lastDataRow, $totalRow] = $this->prepareSheet($sheet);

        foreach ($this->rows->values() as $index => $row) {
            $sheetRow = self::DATA_START_ROW + $index;
            $this->fillDataRow($sheet, $sheetRow, (array) $row);
        }

        $this->writeRowFormulas($sheet, $lastDataRow);
        $this->writeTotalFormulas($sheet, $lastDataRow, $totalRow);
    }

    private function prepareSheet(Worksheet $sheet): array
    {
        $requiredDataRows = max($this->rows->count(), self::TEMPLATE_VISIBLE_DATA_ROWS);
        $extraRows = max(0, $requiredDataRows - self::TEMPLATE_VISIBLE_DATA_ROWS);

        if ($extraRows > 0) {
            $sheet->insertNewRowBefore(self::TEMPLATE_TOTAL_ROW, $extraRows);
        }

        $lastDataRow = self::DATA_START_ROW + $requiredDataRows - 1;
        $totalRow = $lastDataRow + 1;

        $this->applyDataRowStyles($sheet, $lastDataRow);
        $this->clearDataRows($sheet, $lastDataRow);

        return [$lastDataRow, $totalRow];
    }

    private function applyDataRowStyles(Worksheet $sheet, int $lastDataRow): void
    {
        $this->copyRowStyle($sheet, self::TEMPLATE_FIRST_DATA_ROW, self::DATA_START_ROW);

        for ($row = self::DATA_START_ROW + 1; $row < $lastDataRow; $row++) {
            $this->copyRowStyle($sheet, self::TEMPLATE_MIDDLE_DATA_ROW, $row);
        }

        if ($lastDataRow > self::DATA_START_ROW) {
            $this->copyRowStyle($sheet, self::TEMPLATE_LAST_DATA_ROW, $lastDataRow);
        }
    }

    private function clearDataRows(Worksheet $sheet, int $lastDataRow): void
    {
        for ($row = self::DATA_START_ROW; $row <= $lastDataRow; $row++) {
            for ($column = 1; $column <= self::TEMPLATE_MAX_COLUMN; $column++) {
                $sheet->setCellValueByColumnAndRow($column, $row, null);
            }
        }
    }

    private function copyRowStyle(Worksheet $sheet, int $sourceRow, int $targetRow): void
    {
        $sheet->getRowDimension($targetRow)
            ->setRowHeight($sheet->getRowDimension($sourceRow)->getRowHeight());

        for ($column = 1; $column <= self::TEMPLATE_MAX_COLUMN; $column++) {
            $sourceCoordinate = $sheet->getCellByColumnAndRow($column, $sourceRow)->getCoordinate();
            $targetCoordinate = $sheet->getCellByColumnAndRow($column, $targetRow)->getCoordinate();
            $sheet->duplicateStyle($sheet->getStyle($sourceCoordinate), $targetCoordinate);
        }
    }

    private function fillDataRow(Worksheet $sheet, int $rowNumber, array $row): void
    {
        $fecha = $row['fecha'] ?? null;
        if ($fecha instanceof Carbon) {
            $sheet->setCellValue("A{$rowNumber}", ExcelDate::dateTimeToExcel($fecha));
        }

        $sheet->setCellValueExplicit("B{$rowNumber}", (string) ($row['maquina_display'] ?? ''), DataType::TYPE_STRING);
        $sheet->setCellValueExplicit("F{$rowNumber}", (string) ($row['folio'] ?? ''), DataType::TYPE_STRING);
        $sheet->setCellValue("D{$rowNumber}", $row['merma_sin_goma'] ?? null);
        $sheet->setCellValue("E{$rowNumber}", $row['merma_con_goma'] ?? null);
        $sheet->setCellValue("G{$rowNumber}", $row['cuenta'] ?? null);
        $sheet->setCellValue("H{$rowNumber}", $row['hilo'] ?? null);

        $urdSlots = $row['urd_slots'] ?? [];
        $engSlots = $row['eng_slots'] ?? [];

        $this->fillSlot($sheet, $rowNumber, 'I', 'J', $urdSlots[0] ?? []);
        $this->fillSlot($sheet, $rowNumber, 'L', 'M', $urdSlots[1] ?? []);
        $this->fillSlot($sheet, $rowNumber, 'O', 'P', $urdSlots[2] ?? []);

        $this->fillSlot($sheet, $rowNumber, 'S', 'T', $engSlots[0] ?? []);
        $this->fillSlot($sheet, $rowNumber, 'V', 'W', $engSlots[1] ?? []);
        $this->fillSlot($sheet, $rowNumber, 'Y', 'Z', $engSlots[2] ?? []);
    }

    private function fillSlot(Worksheet $sheet, int $rowNumber, string $labelColumn, string $countColumn, array $slot): void
    {
        $sheet->setCellValue("{$labelColumn}{$rowNumber}", $slot['label'] ?? null);
        $sheet->setCellValue("{$countColumn}{$rowNumber}", $slot['count'] ?? null);
    }

    private function writeRowFormulas(Worksheet $sheet, int $lastDataRow): void
    {
        for ($row = self::DATA_START_ROW; $row <= $lastDataRow; $row++) {
            $sheet->setCellValue("C{$row}", sprintf(
                '=IF(COUNTA(D%d:E%d)=0,"",SUM(D%d:E%d))',
                $row,
                $row,
                $row,
                $row
            ));

            $sheet->setCellValue("R{$row}", sprintf(
                '=IF(COUNTA(J%d,M%d,P%d)=0,"",SUM(J%d,M%d,P%d))',
                $row,
                $row,
                $row,
                $row,
                $row,
                $row
            ));

            $sheet->setCellValue("AB{$row}", sprintf(
                '=IF(COUNTA(T%d,W%d,Z%d)=0,"",SUM(T%d,W%d,Z%d))',
                $row,
                $row,
                $row,
                $row,
                $row,
                $row
            ));

            $sheet->setCellValue("K{$row}", $this->buildMtsFormula('D', 'R', 'J', $row));
            $sheet->setCellValue("N{$row}", $this->buildMtsFormula('D', 'R', 'M', $row));
            $sheet->setCellValue("Q{$row}", $this->buildMtsFormula('D', 'R', 'P', $row));

            $sheet->setCellValue("U{$row}", $this->buildMtsFormula('E', 'AB', 'T', $row));
            $sheet->setCellValue("X{$row}", $this->buildMtsFormula('E', 'AB', 'W', $row));
            $sheet->setCellValue("AA{$row}", $this->buildMtsFormula('E', 'AB', 'Z', $row));
        }
    }

    private function writeTotalFormulas(Worksheet $sheet, int $lastDataRow, int $totalRow): void
    {
        $sheet->setCellValue("C{$totalRow}", "=SUM(C" . self::DATA_START_ROW . ":C{$lastDataRow})");
        $sheet->setCellValue("D{$totalRow}", "=SUM(D" . self::DATA_START_ROW . ":D{$lastDataRow})");
        $sheet->setCellValue("E{$totalRow}", "=SUM(E" . self::DATA_START_ROW . ":E{$lastDataRow})");
    }

    private function buildMtsFormula(string $mermaColumn, string $totalColumn, string $countColumn, int $row): string
    {
        return sprintf(
            '=IF(OR(%1$s%4$d="",%1$s%4$d=0,%2$s%4$d="",%2$s%4$d=0,%3$s%4$d="",%3$s%4$d=0,H%4$d="",H%4$d=0,G%4$d="",G%4$d=0),"",IFERROR(((%1$s%4$d/%2$s%4$d)*%3$s%4$d*H%4$d*1000)/(0.59*G%4$d),""))',
            $mermaColumn,
            $totalColumn,
            $countColumn,
            $row
        );
    }
}
