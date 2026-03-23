<?php

namespace App\Exports;

use App\Models\Atadores\AtaMontadoTelasModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class Reporte00EAtadoresRangoExport implements FromArray, WithEvents
{
    private const TEMPLATE_SECTION_LAST_ROW = 46;
    private const MAX_COLUMN_INDEX = 88;

    private static ?array $templateSectionCoordinates = null;
    private static ?array $templateSectionMergeRanges = null;
    private static ?array $columnLabels = null;

    private Collection $records;

    public function __construct(
        private readonly CarbonImmutable $weekStart,
        private readonly CarbonImmutable $weekEnd,
        ?Collection $records = null
    ) {
        $this->records = $records ?? $this->loadRecords();
    }

    public function array(): array
    {
        return [['']];
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
                $templateSourceBook = $this->loadTemplateBook();
                $templateSource = $templateSourceBook->getSheet(0);
                $templateSheet->setTitle('00E Atadores');

                $book->removeSheetByIndex($sheetIndex);
                $book->addExternalSheet($templateSheet, $sheetIndex);

                $sheet = $book->getSheet($sheetIndex);
                $this->renderRangeIntoSheet($sheet, $templateSource);
            },
        ];
    }

    private function renderRangeIntoSheet(Worksheet $sheet, Worksheet $templateSource): void
    {
        $recordsByWeek = $this->groupRecordsByWeek();
        $sectionTopRow = 1;
        $isFirstWeek = true;

        for ($cursor = $this->weekStart; $cursor->lessThanOrEqualTo($this->weekEnd); $cursor = $cursor->addWeek()) {
            if (!$isFirstWeek) {
                $this->copyTemplateSection($sheet, $templateSource, $sectionTopRow);
            }

            $weeklyExport = new Reporte00EAtadoresExport(
                $cursor,
                $recordsByWeek->get($cursor->toDateString(), collect())
            );

            $sectionBottomRow = $weeklyExport->renderIntoSheet($sheet, $sectionTopRow);
            $sectionTopRow = $sectionBottomRow + 1;
            $isFirstWeek = false;
        }
    }

    private function copyTemplateSection(Worksheet $sheet, Worksheet $templateSource, int $sectionTopRow): void
    {
        $rowOffset = $sectionTopRow - 1;
        $columns = $this->getColumnLabels();

        for ($sourceRow = 1; $sourceRow <= self::TEMPLATE_SECTION_LAST_ROW; $sourceRow++) {
            $targetRow = $sourceRow + $rowOffset;
            $sheet->getRowDimension($targetRow)
                ->setRowHeight($templateSource->getRowDimension($sourceRow)->getRowHeight());

            for ($columnIndex = 1; $columnIndex <= self::MAX_COLUMN_INDEX; $columnIndex++) {
                $column = $columns[$columnIndex];
                $sourceCoordinate = "{$column}{$sourceRow}";
                $targetCoordinate = "{$column}{$targetRow}";
                $sheet->duplicateStyle($templateSource->getStyle($sourceCoordinate), $targetCoordinate);
            }
        }

        foreach ($this->getTemplateSectionCoordinates($templateSource) as $coordinate) {
            $sheet->setCellValue(
                $this->offsetCoordinateRow($coordinate, $rowOffset),
                $templateSource->getCell($coordinate)->getValue()
            );
        }

        foreach ($this->getTemplateSectionMergeRanges($templateSource) as $range) {
            $sheet->mergeCells($this->offsetRangeRows($range, $rowOffset));
        }
    }

    private function loadTemplateBook(): Spreadsheet
    {
        $candidates = [
            resource_path('templates/Reporte_00E_Atadores.xlsx'),
            storage_path('app/templates/Reporte_00E_Atadores.xlsx'),
        ];

        foreach ($candidates as $path) {
            if (is_string($path) && $path !== '' && is_file($path)) {
                return IOFactory::load($path);
            }
        }

        throw new RuntimeException(
            'No se encontro la plantilla Reporte_00E_Atadores.xlsx en resources/templates/ o storage/app/templates/.'
        );
    }

    private function loadRecords(): Collection
    {
        $rangeEnd = $this->weekEnd->addDays(6);

        return AtaMontadoTelasModel::query()
            ->where('Estatus', 'Autorizado')
            ->whereNotNull('FechaArranque')
            ->whereDate('FechaArranque', '>=', $this->weekStart->toDateString())
            ->whereDate('FechaArranque', '<=', $rangeEnd->toDateString())
            ->orderBy('FechaArranque')
            ->orderBy('Turno')
            ->orderBy('CveTejedor')
            ->orderBy('NomTejedor')
            ->orderBy('HrInicio')
            ->orderBy('HoraArranque')
            ->orderBy('Id')
            ->get();
    }

    private function groupRecordsByWeek(): Collection
    {
        return $this->records
            ->groupBy(function ($item): string {
                $value = is_array($item) ? ($item['FechaArranque'] ?? null) : ($item->FechaArranque ?? null);

                try {
                    $date = CarbonImmutable::parse((string) $value)->startOfDay();
                } catch (\Throwable $e) {
                    return '__invalid__';
                }

                return $date->startOfWeek()->toDateString();
            })
            ->map(function ($items) {
                return $items instanceof Collection ? $items->values() : collect($items)->values();
            });
    }

    private function getTemplateSectionCoordinates(Worksheet $templateSource): array
    {
        if (self::$templateSectionCoordinates !== null) {
            return self::$templateSectionCoordinates;
        }

        $coordinates = [];
        $columns = $this->getColumnLabels();

        for ($row = 1; $row <= self::TEMPLATE_SECTION_LAST_ROW; $row++) {
            for ($columnIndex = 1; $columnIndex <= self::MAX_COLUMN_INDEX; $columnIndex++) {
                $coordinate = $columns[$columnIndex] . $row;
                $value = $templateSource->getCell($coordinate)->getValue();

                if ($value !== null && $value !== '') {
                    $coordinates[] = $coordinate;
                }
            }
        }

        return self::$templateSectionCoordinates = $coordinates;
    }

    private function getTemplateSectionMergeRanges(Worksheet $templateSource): array
    {
        if (self::$templateSectionMergeRanges !== null) {
            return self::$templateSectionMergeRanges;
        }

        $ranges = [];
        foreach (array_keys($templateSource->getMergeCells()) as $range) {
            if (!preg_match('/([A-Z]+)(\d+):([A-Z]+)(\d+)/', $range, $matches)) {
                continue;
            }

            $rowStart = (int) $matches[2];
            $rowEnd = (int) $matches[4];
            if ($rowStart >= 1 && $rowEnd <= self::TEMPLATE_SECTION_LAST_ROW) {
                $ranges[] = $range;
            }
        }

        return self::$templateSectionMergeRanges = $ranges;
    }

    private function getColumnLabels(): array
    {
        if (self::$columnLabels !== null) {
            return self::$columnLabels;
        }

        $labels = [];
        for ($column = 1; $column <= self::MAX_COLUMN_INDEX; $column++) {
            $labels[$column] = Coordinate::stringFromColumnIndex($column);
        }

        return self::$columnLabels = $labels;
    }

    private function offsetCoordinateRow(string $coordinate, int $rowOffset): string
    {
        if ($rowOffset === 0) {
            return $coordinate;
        }

        if (preg_match('/^([A-Z]+)(\d+)$/', $coordinate, $matches) !== 1) {
            return $coordinate;
        }

        return $matches[1] . ((int) $matches[2] + $rowOffset);
    }

    private function offsetRangeRows(string $range, int $rowOffset): string
    {
        if ($rowOffset === 0) {
            return $range;
        }

        if (preg_match('/^([A-Z]+)(\d+):([A-Z]+)(\d+)$/', $range, $matches) !== 1) {
            return $range;
        }

        return sprintf(
            '%s%d:%s%d',
            $matches[1],
            (int) $matches[2] + $rowOffset,
            $matches[3],
            (int) $matches[4] + $rowOffset
        );
    }
}