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

    private const WEEKLY_TEMPLATE_CLEAR_START_ROW = 4;

    private const MAX_COLUMN_INDEX = 88;

    private static ?array $templateState = null;

    private static ?array $columnLabels = null;

    private ?Collection $records = null;

    public function __construct(
        private readonly CarbonImmutable $weekStart,
        private readonly CarbonImmutable $weekEnd,
        ?Collection $records = null
    ) {
        $this->records = $records;
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
                $templateState = $this->resolveTemplateState($templateSheet);
                $templateSheet->setTitle('OEE de Atadores');

                $templateSourceBook = $this->loadTemplateBook();
                $templateSourceSheet = $templateSourceBook->getSheet(0);
                $templateSourceSheet->setTitle('_oee_template_source');

                $book->removeSheetByIndex($sheetIndex);
                $book->addExternalSheet($templateSheet, $sheetIndex);
                $book->addExternalSheet($templateSourceSheet);
                $templateSourceSheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);

                $sheet = $book->getSheet($sheetIndex);
                $templateSourceIndex = $book->getIndex($templateSourceSheet);

                $this->renderRangeIntoSheet($sheet, $templateState, $templateSourceSheet);
                $book->removeSheetByIndex($templateSourceIndex);
            },
        ];
    }

    private function renderRangeIntoSheet(Worksheet $sheet, array $templateState, Worksheet $templateSource): void
    {
        $recordsByWeek = $this->groupRecordsByWeek();
        $sectionTopRow = 1;
        $isFirstWeek = true;

        for ($cursor = $this->weekStart; $cursor->lessThanOrEqualTo($this->weekEnd); $cursor = $cursor->addWeek()) {
            if (! $isFirstWeek) {
                $this->copyTemplateSection($sheet, $templateSource, $templateState['section_snapshot'], $sectionTopRow);
            }

            $weeklyExport = new Reporte00EAtadoresExport(
                $cursor,
                $recordsByWeek->get($cursor->toDateString(), collect()),
                null,
                $templateState['weekly_context']
            );

            $sectionBottomRow = $weeklyExport->renderIntoSheet($sheet, $sectionTopRow);
            $sectionTopRow = $sectionBottomRow + 1;
            $isFirstWeek = false;
        }
    }

    private function copyTemplateSection(
        Worksheet $sheet,
        Worksheet $templateSource,
        array $sectionSnapshot,
        int $sectionTopRow
    ): void
    {
        $rowOffset = $sectionTopRow - 1;
        $columns = $this->getColumnLabels();

        foreach (($sectionSnapshot['row_heights'] ?? []) as $sourceRow => $height) {
            $targetRow = (int) $sourceRow + $rowOffset;
            $sheet->getRowDimension($targetRow)->setRowHeight((float) $height);

            foreach (($sectionSnapshot['style_runs'][$sourceRow] ?? []) as $styleRun) {
                $startColumn = $columns[(int) $styleRun['start']];
                $endColumn = $columns[(int) $styleRun['end']];
                $sheet->duplicateStyle(
                    $templateSource->getStyle("{$startColumn}{$sourceRow}"),
                    "{$startColumn}{$targetRow}:{$endColumn}{$targetRow}"
                );
            }
        }

        foreach (($sectionSnapshot['values'] ?? []) as $coordinate => $value) {
            $sheet->setCellValue(
                $this->offsetCoordinateRow($coordinate, $rowOffset),
                $value
            );
        }

        foreach (($sectionSnapshot['merge_ranges'] ?? []) as $range) {
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
        $rangeStart = $this->weekStart->startOfDay()->toDateTimeString();
        $rangeEndExclusive = $this->weekEnd->addDays(7)->startOfDay()->toDateTimeString();

        return AtaMontadoTelasModel::query()
            ->select([
                'Id',
                'FechaArranque',
                'Turno',
                'CveTejedor',
                'NomTejedor',
                'Tipo',
                'NoTelarId',
                'HrInicio',
                'HoraArranque',
                'Calidad',
                'Limpieza',
                'MergaKg',
            ])
            ->where('Estatus', 'Autorizado')
            ->whereNotNull('FechaArranque')
            ->where('FechaArranque', '>=', $rangeStart)
            ->where('FechaArranque', '<', $rangeEndExclusive)
            ->orderBy('FechaArranque')
            ->orderBy('Turno')
            ->orderBy('CveTejedor')
            ->orderBy('NomTejedor')
            ->orderBy('HrInicio')
            ->orderBy('HoraArranque')
            ->orderBy('Id')
            ->toBase()
            ->get();
    }

    private function groupRecordsByWeek(): Collection
    {
        $records = $this->records ??= $this->loadRecords();

        return $records
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

    private function resolveTemplateState(Worksheet $templateSheet): array
    {
        if (self::$templateState !== null) {
            return self::$templateState;
        }

        $columns = $this->getColumnLabels();
        $clearCoordinates = [];
        $sectionValues = [];
        $rowHeights = [];
        $styleRuns = [];

        for ($row = 1; $row <= self::TEMPLATE_SECTION_LAST_ROW; $row++) {
            $rowHeights[$row] = $templateSheet->getRowDimension($row)->getRowHeight();
            $styleRuns[$row] = $this->captureRowStyleRuns($templateSheet, $row);

            for ($columnIndex = 1; $columnIndex <= self::MAX_COLUMN_INDEX; $columnIndex++) {
                $coordinate = $columns[$columnIndex].$row;
                $value = $templateSheet->getCell($coordinate)->getValue();

                if ($value !== null && $value !== '') {
                    $sectionValues[$coordinate] = $value;
                    if ($row >= self::WEEKLY_TEMPLATE_CLEAR_START_ROW) {
                        $clearCoordinates[] = $coordinate;
                    }
                }
            }
        }

        return self::$templateState = [
            'weekly_context' => [
                'clear_coordinates' => $clearCoordinates,
            ],
            'section_snapshot' => [
                'row_heights' => $rowHeights,
                'style_runs' => $styleRuns,
                'values' => $sectionValues,
                'merge_ranges' => $this->captureTemplateSectionMergeRanges($templateSheet),
            ],
        ];
    }

    private function captureTemplateSectionMergeRanges(Worksheet $templateSheet): array
    {
        $ranges = [];
        foreach (array_keys($templateSheet->getMergeCells()) as $range) {
            if (! preg_match('/([A-Z]+)(\d+):([A-Z]+)(\d+)/', $range, $matches)) {
                continue;
            }

            $rowStart = (int) $matches[2];
            $rowEnd = (int) $matches[4];
            if ($rowStart >= 1 && $rowEnd <= self::TEMPLATE_SECTION_LAST_ROW) {
                $ranges[] = $range;
            }
        }

        return $ranges;
    }

    private function captureRowStyleRuns(Worksheet $templateSheet, int $row): array
    {
        $columns = $this->getColumnLabels();
        $runs = [];
        $runStart = 1;
        $currentHash = $templateSheet->getStyle($columns[$runStart].$row)->getHashCode();

        for ($columnIndex = 2; $columnIndex <= self::MAX_COLUMN_INDEX; $columnIndex++) {
            $hash = $templateSheet->getStyle($columns[$columnIndex].$row)->getHashCode();
            if ($hash === $currentHash) {
                continue;
            }

            $runs[] = $this->buildStyleRun($runStart, $columnIndex - 1);
            $runStart = $columnIndex;
            $currentHash = $hash;
        }

        $runs[] = $this->buildStyleRun($runStart, self::MAX_COLUMN_INDEX);

        return $runs;
    }

    private function buildStyleRun(int $startColumnIndex, int $endColumnIndex): array
    {
        return [
            'start' => $startColumnIndex,
            'end' => $endColumnIndex,
        ];
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

        return $matches[1].((int) $matches[2] + $rowOffset);
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
