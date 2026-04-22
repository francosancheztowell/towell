<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class PromedioParosEficienciaExport implements FromArray, WithEvents, WithTitle
{
    private const TEMPLATE_FIRST_DAY_TURN_STARTS = [1 => 2, 2 => 13, 3 => 24];

    private const TEMPLATE_STANDARD_DAY_START_ROW = 35;

    private const TEMPLATE_STANDARD_DAY_BLOCK_HEIGHT = 34;

    private const TEMPLATE_STANDARD_DAY_SOURCE_START_ROW = 239;

    private const TEMPLATE_STANDARD_DAY_SOURCE_END_ROW = 272;

    private const TEMPLATE_VISIBLE_DAYS = 8;

    private const TEMPLATE_MAX_COLUMN = 43; // AQ

    private const DATE_COLUMNS = ['A', 'L', 'AJ'];

    private const TURN_LABEL_OFFSET = 6;

    private const COMPACT_SECTION_START_COLUMN = 38; // AL

    private const TEMPLATE_PINK_LABEL_FILL = 'FFE59EDE';

    private const FORCED_BLUE_LABEL_FILL = 'FFD9E2F3';

    private const INTEGER_FORMAT = '0';

    private const RPM_FORMAT = '0.##';

    /** Ancho mínimo (unidades Excel) para columnas de datos por telar; evita ajuste de texto que apila dígitos. */
    private const TELAR_COLUMN_MIN_WIDTH = 10.5;

    private const STANDARD_METRIC_ROW_OFFSETS = [
        'paros_trama' => 1,
        'paros_urdimbre' => 3,
        'paros_rizo' => 5,
        'paros_otros' => 7,
        'marcas' => 9,
        'rpm' => 10,
    ];

    private const COMPACT_METRIC_ROW_OFFSETS = [
        'paros_trama' => 1,
        'paros_urdimbre' => 3,
        'paros_rizo' => 4,
        'paros_otros' => 6,
        'marcas' => 8,
        'rpm' => 9,
    ];

    public function __construct(
        private readonly array $report
    ) {}

    public function array(): array
    {
        return [['']];
    }

    public function title(): string
    {
        return 'Promedio Paros y Eficiencia';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $initialSheet = $event->sheet->getDelegate();
                $book = $initialSheet->getParent();
                $sheetIndex = $book->getIndex($initialSheet);

                $templateBook = $this->loadTemplateBook(dataOnly: false);
                $templateSheet = $templateBook->getSheet(0);
                $templateSheet->setTitle($this->title());
                $metadata = $this->loadTemplateMetadata($templateSheet);
                $this->normalizeLabelBandColors($templateSheet, $metadata['pink_coordinates']);
                $this->copyWorkbookTheme($templateBook, $book);

                $book->removeSheetByIndex($sheetIndex);
                $book->addExternalSheet($templateSheet, $sheetIndex);

                $sheet = $book->getSheet($sheetIndex);
                $this->fillSheet($sheet, $metadata['telar_columns']);
            },
        ];
    }

    private function fillSheet(Worksheet $sheet, array $telarColumns): void
    {
        $days = array_values($this->report['days'] ?? []);
        $metrics = $this->report['metrics'] ?? [];
        $blocksToRender = max(self::TEMPLATE_VISIBLE_DAYS, count($days));

        $this->ensureDayCapacity($sheet, count($days));

        $mergedLookup = $this->buildMergedLookup($sheet);

        for ($dayIndex = 0; $dayIndex < $blocksToRender; $dayIndex++) {
            $this->clearDayBlock($sheet, $dayIndex, $telarColumns, $mergedLookup);
            $this->writeEfficiencyFormulas($sheet, $dayIndex, $telarColumns, $mergedLookup);

            if (isset($days[$dayIndex])) {
                $this->fillDayBlock(
                    $sheet,
                    $dayIndex,
                    (array) $days[$dayIndex],
                    $metrics[(string) ($days[$dayIndex]['date_key'] ?? '')] ?? [],
                    $telarColumns,
                    $mergedLookup
                );
            }
        }

        $this->ensureTelarColumnWidth($sheet, $telarColumns);

        $sheet->setSelectedCell('A1');
    }

    private function loadTemplateBook(bool $dataOnly): Spreadsheet
    {
        $templatePath = resource_path('templates/PromedioParosMarcas.xlsx');
        if (! is_file($templatePath)) {
            throw new RuntimeException('No se encontro la plantilla PromedioParosMarcas.xlsx en resources/templates/.');
        }

        if ($dataOnly) {
            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);

            return $reader->load($templatePath);
        }

        return IOFactory::load($templatePath);
    }

    private function loadTemplateMetadata(Worksheet $templateSheet): array
    {
        $templatePath = resource_path('templates/PromedioParosMarcas.xlsx');
        $version = is_file($templatePath) ? (string) filemtime($templatePath) : 'missing';

        return Cache::rememberForever(
            'promedio_paros_template_metadata_'.$version,
            function () use ($templateSheet): array {
                $dataOnlyBook = $this->loadTemplateBook(dataOnly: true);

                return [
                    'telar_columns' => $this->extractTelarColumns($dataOnlyBook->getSheet(0)),
                    'pink_coordinates' => $this->extractPinkCoordinates($templateSheet),
                ];
            }
        );
    }

    private function extractTelarColumns(Worksheet $sheet): array
    {
        $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestColumn());
        $columns = [];

        for ($column = 1; $column <= $highestColumn; $column++) {
            $cell = $sheet->getCellByColumnAndRow($column, 1);
            $value = $cell->getValue();

            if (! is_numeric($value) && is_string($value) && str_starts_with($value, '=')) {
                try {
                    $value = $cell->getCalculatedValue();
                } catch (\Throwable) {
                    $value = null;
                }
            }

            if ($value !== null && $value !== '' && is_numeric($value)) {
                $columns[(string) (int) $value] = $column;
            }
        }

        return $columns;
    }

    private function extractPinkCoordinates(Worksheet $sheet): array
    {
        $coordinates = [];
        $highestRow = $sheet->getHighestRow();

        for ($row = 1; $row <= $highestRow; $row++) {
            for ($columnIndex = 1; $columnIndex <= self::TEMPLATE_MAX_COLUMN; $columnIndex++) {
                $coordinate = Coordinate::stringFromColumnIndex($columnIndex).$row;
                $fill = $sheet->getStyle($coordinate)->getFill();
                $startColor = strtoupper((string) $fill->getStartColor()->getARGB());

                if ($fill->getFillType() === Fill::FILL_SOLID && $startColor === self::TEMPLATE_PINK_LABEL_FILL) {
                    $coordinates[] = $coordinate;
                }
            }
        }

        return $coordinates;
    }

    private function ensureDayCapacity(Worksheet $sheet, int $dayCount): void
    {
        $extraDays = max(0, $dayCount - self::TEMPLATE_VISIBLE_DAYS);
        if ($extraDays === 0) {
            return;
        }

        $insertStartRow = self::TEMPLATE_STANDARD_DAY_SOURCE_END_ROW + 1;
        $sheet->insertNewRowBefore($insertStartRow, $extraDays * self::TEMPLATE_STANDARD_DAY_BLOCK_HEIGHT);

        for ($index = 0; $index < $extraDays; $index++) {
            $targetStartRow = $insertStartRow + ($index * self::TEMPLATE_STANDARD_DAY_BLOCK_HEIGHT);
            $this->copyStandardDayBlock($sheet, $targetStartRow);
        }
    }

    private function copyStandardDayBlock(Worksheet $sheet, int $targetStartRow): void
    {
        $sourceStartRow = self::TEMPLATE_STANDARD_DAY_SOURCE_START_ROW;
        $rowOffset = $targetStartRow - $sourceStartRow;

        for ($offset = 0; $offset < self::TEMPLATE_STANDARD_DAY_BLOCK_HEIGHT; $offset++) {
            $sourceRow = $sourceStartRow + $offset;
            $targetRow = $targetStartRow + $offset;

            $sheet->getRowDimension($targetRow)
                ->setRowHeight($sheet->getRowDimension($sourceRow)->getRowHeight());

            for ($column = 1; $column <= self::TEMPLATE_MAX_COLUMN; $column++) {
                $sourceCoordinate = Coordinate::stringFromColumnIndex($column).$sourceRow;
                $targetCoordinate = Coordinate::stringFromColumnIndex($column).$targetRow;

                $sheet->duplicateStyle($sheet->getStyle($sourceCoordinate), $targetCoordinate);
                $sheet->setCellValue($targetCoordinate, $sheet->getCell($sourceCoordinate)->getValue());
            }
        }

        foreach ($sheet->getMergeCells() as $range) {
            if (! $this->rangeFitsRows($range, $sourceStartRow, self::TEMPLATE_STANDARD_DAY_SOURCE_END_ROW)) {
                continue;
            }

            $sheet->mergeCells($this->shiftRangeRows($range, $rowOffset));
        }
    }

    private function clearDayBlock(
        Worksheet $sheet,
        int $dayIndex,
        array $telarColumns,
        array $mergedLookup
    ): void {
        foreach ($this->getDateRows($dayIndex) as $row) {
            foreach (self::DATE_COLUMNS as $column) {
                $sheet->setCellValue("{$column}{$row}", null);
            }
        }

        foreach ($this->getTurnLabelRows($dayIndex) as $row) {
            foreach (self::DATE_COLUMNS as $column) {
                $sheet->setCellValue("{$column}{$row}", null);
            }
        }

        foreach ($this->getTurnStartRows($dayIndex) as $turnStartRow) {
            foreach ($telarColumns as $columnIndex) {
                foreach ($this->resolveMetricRowOffsets($columnIndex) as $offset) {
                    $row = $turnStartRow + $offset;
                    $coordinate = Coordinate::stringFromColumnIndex($columnIndex).$row;
                    $this->clearDataCoordinate($sheet, $coordinate, $mergedLookup);
                }
            }

            foreach ($telarColumns as $columnIndex) {
                $formulaCoordinate = Coordinate::stringFromColumnIndex($columnIndex).$turnStartRow;
                $this->clearDataCoordinate($sheet, $formulaCoordinate, $mergedLookup);
            }
        }
    }

    private function fillDayBlock(
        Worksheet $sheet,
        int $dayIndex,
        array $day,
        array $metricsByTurn,
        array $telarColumns,
        array $mergedLookup
    ): void {
        $date = $day['date'] instanceof Carbon
            ? $day['date']->copy()
            : Carbon::parse((string) ($day['date_key'] ?? 'now'));

        foreach ($this->getDateRows($dayIndex) as $row) {
            foreach (self::DATE_COLUMNS as $column) {
                $sheet->setCellValue("{$column}{$row}", ExcelDate::dateTimeToExcel($date));
            }
        }

        foreach ($this->getTurnStartRows($dayIndex) as $turn => $turnStartRow) {
            $turnLabel = (string) ($day['turn_labels'][$turn] ?? '');
            $labelRow = $turnStartRow + self::TURN_LABEL_OFFSET;
            foreach (self::DATE_COLUMNS as $column) {
                $sheet->setCellValue("{$column}{$labelRow}", $turnLabel);
            }

            foreach ($telarColumns as $telar => $columnIndex) {
                $values = $metricsByTurn[$turn][$telar] ?? null;
                if (! is_array($values)) {
                    continue;
                }

                foreach ($this->resolveMetricRowOffsets($columnIndex) as $metric => $offset) {
                    $value = $values[$metric] ?? null;
                    if ($value === null) {
                        continue;
                    }

                    $coordinate = Coordinate::stringFromColumnIndex($columnIndex).($turnStartRow + $offset);
                    if ($this->canWriteDataCoordinate($coordinate, $mergedLookup)) {
                        if ($metric === 'rpm') {
                            $writable = $this->normalizeWritableDecimal($value);
                            $format = is_int($writable) ? self::INTEGER_FORMAT : self::RPM_FORMAT;
                        } else {
                            $writable = $this->normalizeWritableInteger($value);
                            $format = self::INTEGER_FORMAT;
                        }

                        $sheet->setCellValue($coordinate, $writable);
                        $this->applyNumericCellPresentation($sheet, $coordinate, $format);
                    }
                }
            }
        }
    }

    private function writeEfficiencyFormulas(
        Worksheet $sheet,
        int $dayIndex,
        array $telarColumns,
        array $mergedLookup
    ): void {
        foreach ($this->getTurnStartRows($dayIndex) as $turnStartRow) {
            foreach ($telarColumns as $columnIndex) {
                $metricRowOffsets = $this->resolveMetricRowOffsets($columnIndex);
                $marcasRow = $turnStartRow + $metricRowOffsets['marcas'];
                $rpmRow = $turnStartRow + $metricRowOffsets['rpm'];
                $column = Coordinate::stringFromColumnIndex($columnIndex);
                $coordinate = "{$column}{$turnStartRow}";

                if (! $this->canWriteDataCoordinate($coordinate, $mergedLookup)) {
                    continue;
                }

                $sheet->setCellValue(
                    $coordinate,
                    sprintf(
                        '=IF(OR(%1$s%2$d="",%1$s%3$d="",%1$s%3$d=0),"",IFERROR(ROUND((%1$s%2$d*100000)/(%1$s%3$d*60*8),0),""))',
                        $column,
                        $marcasRow,
                        $rpmRow
                    )
                );
                $this->applyNumericCellPresentation($sheet, $coordinate, self::INTEGER_FORMAT);
            }
        }
    }

    private function applyNumericCellPresentation(Worksheet $sheet, string $coordinate, string $formatCode): void
    {
        $style = $sheet->getStyle($coordinate);
        $style->getAlignment()->setWrapText(false);
        $style->getNumberFormat()->setFormatCode($formatCode);
    }

    private function ensureTelarColumnWidth(Worksheet $sheet, array $telarColumns): void
    {
        foreach ($telarColumns as $columnIndex) {
            $columnLetter = Coordinate::stringFromColumnIndex($columnIndex);
            $dimension = $sheet->getColumnDimension($columnLetter);
            $width = $dimension->getWidth();

            if ($width <= 0 || $width < self::TELAR_COLUMN_MIN_WIDTH) {
                $dimension->setWidth(self::TELAR_COLUMN_MIN_WIDTH);
            }
        }
    }

    private function copyWorkbookTheme(Spreadsheet $sourceBook, Spreadsheet $targetBook): void
    {
        $sourceTheme = $sourceBook->getTheme();
        $targetTheme = $targetBook->getTheme();

        $targetTheme
            ->setThemeColorName($sourceTheme->getThemeColorName(), $sourceTheme->getThemeColors())
            ->setThemeFontName($sourceTheme->getThemeFontName())
            ->setMajorFontValues(
                $sourceTheme->getMajorFontLatin(),
                $sourceTheme->getMajorFontEastAsian(),
                $sourceTheme->getMajorFontComplexScript(),
                $sourceTheme->getMajorFontSubstitutions()
            )
            ->setMinorFontValues(
                $sourceTheme->getMinorFontLatin(),
                $sourceTheme->getMinorFontEastAsian(),
                $sourceTheme->getMinorFontComplexScript(),
                $sourceTheme->getMinorFontSubstitutions()
            );
    }

    private function normalizeLabelBandColors(Worksheet $sheet, array $coordinates): void
    {
        foreach ($coordinates as $coordinate) {
            $fill = $sheet->getStyle($coordinate)->getFill();
            $fill->getStartColor()->setARGB(self::FORCED_BLUE_LABEL_FILL);
            $fill->getEndColor()->setARGB(self::FORCED_BLUE_LABEL_FILL);
        }
    }

    private function resolveMetricRowOffsets(int $columnIndex): array
    {
        return $columnIndex >= self::COMPACT_SECTION_START_COLUMN
            ? self::COMPACT_METRIC_ROW_OFFSETS
            : self::STANDARD_METRIC_ROW_OFFSETS;
    }

    private function clearDataCoordinate(Worksheet $sheet, string $coordinate, array $mergedLookup): void
    {
        if (! isset($mergedLookup[$coordinate])) {
            $sheet->setCellValue($coordinate, null);

            return;
        }

        if ($mergedLookup[$coordinate]['top_left']) {
            $sheet->setCellValue($coordinate, null);
        }
    }

    private function canWriteDataCoordinate(string $coordinate, array $mergedLookup): bool
    {
        return ! isset($mergedLookup[$coordinate]);
    }

    private function normalizeWritableInteger(mixed $value): mixed
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return $value;
        }

        return (int) round((float) $value, 0);
    }

    private function normalizeWritableDecimal(mixed $value): mixed
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return $value;
        }

        $rounded = round((float) $value, 2);
        $asInteger = (int) round($rounded, 0);

        return abs($rounded - $asInteger) < 0.0000001 ? $asInteger : $rounded;
    }

    private function buildMergedLookup(Worksheet $sheet): array
    {
        $lookup = [];

        foreach ($sheet->getMergeCells() as $range) {
            [$start, $end] = explode(':', $range);
            [$startColumn, $startRow] = Coordinate::coordinateFromString($start);
            [$endColumn, $endRow] = Coordinate::coordinateFromString($end);

            $startColumnIndex = Coordinate::columnIndexFromString($startColumn);
            $endColumnIndex = Coordinate::columnIndexFromString($endColumn);

            for ($columnIndex = $startColumnIndex; $columnIndex <= $endColumnIndex; $columnIndex++) {
                for ($row = $startRow; $row <= $endRow; $row++) {
                    $coordinate = Coordinate::stringFromColumnIndex($columnIndex).$row;
                    $lookup[$coordinate] = [
                        'top_left' => $coordinate === $start,
                    ];
                }
            }
        }

        return $lookup;
    }

    private function getTurnStartRows(int $dayIndex): array
    {
        if ($dayIndex === 0) {
            return self::TEMPLATE_FIRST_DAY_TURN_STARTS;
        }

        $blockStartRow = self::TEMPLATE_STANDARD_DAY_START_ROW
            + (($dayIndex - 1) * self::TEMPLATE_STANDARD_DAY_BLOCK_HEIGHT);

        return [
            1 => $blockStartRow + 1,
            2 => $blockStartRow + 12,
            3 => $blockStartRow + 23,
        ];
    }

    private function getDateRows(int $dayIndex): array
    {
        if ($dayIndex === 0) {
            return [2, 13, 24];
        }

        $blockStartRow = self::TEMPLATE_STANDARD_DAY_START_ROW
            + (($dayIndex - 1) * self::TEMPLATE_STANDARD_DAY_BLOCK_HEIGHT);

        return [
            $blockStartRow,
            $blockStartRow + 12,
            $blockStartRow + 23,
        ];
    }

    private function getTurnLabelRows(int $dayIndex): array
    {
        $rows = [];

        foreach ($this->getTurnStartRows($dayIndex) as $turnStartRow) {
            $rows[] = $turnStartRow + self::TURN_LABEL_OFFSET;
        }

        return $rows;
    }

    private function rangeFitsRows(string $range, int $startRow, int $endRow): bool
    {
        [$start, $end] = explode(':', $range);
        [, $rangeStartRow] = Coordinate::coordinateFromString($start);
        [, $rangeEndRow] = Coordinate::coordinateFromString($end);

        return $rangeStartRow >= $startRow && $rangeEndRow <= $endRow;
    }

    private function shiftRangeRows(string $range, int $rowOffset): string
    {
        [$start, $end] = explode(':', $range);
        [$startColumn, $startRow] = Coordinate::coordinateFromString($start);
        [$endColumn, $endRow] = Coordinate::coordinateFromString($end);

        return sprintf(
            '%s%d:%s%d',
            $startColumn,
            $startRow + $rowOffset,
            $endColumn,
            $endRow + $rowOffset
        );
    }
}
