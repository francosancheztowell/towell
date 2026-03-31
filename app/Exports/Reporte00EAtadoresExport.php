<?php

namespace App\Exports;

use App\Models\Atadores\AtaMontadoTelasModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class Reporte00EAtadoresExport implements FromArray, WithEvents, WithTitle
{
    private const DETAIL_START_ROW = 4;

    private const BASE_DETAIL_ROWS = 42;

    private const FOOTER_BASE_ROW = 46;

    private const DEFAULT_BLOCK_HEIGHT = 6;

    private const MIN_BLOCKS_PER_TURN = 2;

    private const MAX_COLUMN_INDEX = 88;

    private const CAPACITACION_HEIGHT = 6;

    private static ?array $templateClearCoordinates = null;

    private static ?array $columnLabels = null;

    private const TURN_DEFINITIONS = [
        1 => [
            'label' => '1 ER TURNO',
            'first_prototype' => [4, 9],
            'next_prototype' => [10, 15],
        ],
        2 => [
            'label' => '2 DO TURNO',
            'first_prototype' => [16, 21],
            'next_prototype' => [22, 27],
        ],
        3 => [
            'label' => '3 ER TURNO',
            'first_prototype' => [28, 33],
            'next_prototype' => [34, 39],
        ],
    ];

    private const DAY_DEFINITIONS = [
        0 => [
            'header' => 'H2',
            'start' => 'D',
            'end' => 'E',
            'duration' => 'F',
            'avg_time' => 'G',
            'atado' => 'H',
            'telar' => 'I',
            'calif' => 'J',
            'five_s' => 'K',
            'avg_calif' => 'L',
            'merma' => 'M',
            'avg_merma' => 'N',
            'footer_count' => 'I',
        ],
        1 => [
            'header' => 'T2',
            'start' => 'P',
            'end' => 'Q',
            'duration' => 'R',
            'avg_time' => 'S',
            'atado' => 'T',
            'telar' => 'U',
            'calif' => 'V',
            'five_s' => 'W',
            'avg_calif' => 'X',
            'merma' => 'Y',
            'avg_merma' => 'Z',
            'footer_count' => 'U',
        ],
        2 => [
            'header' => 'AF2',
            'start' => 'AB',
            'end' => 'AC',
            'duration' => 'AD',
            'avg_time' => 'AE',
            'atado' => 'AF',
            'telar' => 'AG',
            'calif' => 'AH',
            'five_s' => 'AI',
            'avg_calif' => 'AJ',
            'merma' => 'AK',
            'avg_merma' => 'AL',
            'footer_count' => 'AG',
        ],
        3 => [
            'header' => 'AR2',
            'start' => 'AN',
            'end' => 'AO',
            'duration' => 'AP',
            'avg_time' => 'AQ',
            'atado' => 'AR',
            'telar' => 'AS',
            'calif' => 'AT',
            'five_s' => 'AU',
            'avg_calif' => 'AV',
            'merma' => 'AW',
            'avg_merma' => 'AX',
            'footer_count' => 'AS',
        ],
        4 => [
            'header' => 'BD2',
            'start' => 'AZ',
            'end' => 'BA',
            'duration' => 'BB',
            'avg_time' => 'BC',
            'atado' => 'BD',
            'telar' => 'BE',
            'calif' => 'BF',
            'five_s' => 'BG',
            'avg_calif' => 'BH',
            'merma' => 'BI',
            'avg_merma' => 'BJ',
            'footer_count' => 'BE',
        ],
        5 => [
            'header' => 'BP2',
            'start' => 'BL',
            'end' => 'BM',
            'duration' => 'BN',
            'avg_time' => 'BO',
            'atado' => 'BP',
            'telar' => 'BQ',
            'calif' => 'BR',
            'five_s' => 'BS',
            'avg_calif' => 'BT',
            'merma' => 'BU',
            'avg_merma' => 'BV',
            'footer_count' => 'BQ',
        ],
        6 => [
            'header' => 'CB2',
            'start' => 'BX',
            'end' => 'BY',
            'duration' => 'BZ',
            'avg_time' => 'CA',
            'atado' => 'CB',
            'telar' => 'CC',
            'calif' => 'CD',
            'five_s' => 'CE',
            'avg_calif' => 'CF',
            'merma' => 'CG',
            'avg_merma' => 'CH',
            'footer_count' => 'CC',
        ],
    ];

    private Collection $records;

    public function __construct(
        private readonly CarbonImmutable $weekStart,
        ?Collection $records = null,
        private readonly ?string $sheetTitle = null
    ) {
        $this->records = $this->prepareRecords($records ?? $this->loadRecords());
    }

    public function array(): array
    {
        return [['']];
    }

    public function title(): string
    {
        return $this->sheetTitle ?: '00E Atadores';
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
                $this->renderIntoSheet($sheet);
            },
        ];
    }

    public function getLayout(int $sectionTopRow = 1, bool $limitToBase = false): array
    {
        return $this->buildLayout($sectionTopRow, $limitToBase);
    }

    public function renderIntoSheet(Worksheet $sheet, int $sectionTopRow = 1, bool $allowExpand = true): int
    {
        $layout = $this->buildLayout($sectionTopRow, ! $allowExpand);
        $extraRows = max(0, $layout['detail_rows'] - self::BASE_DETAIL_ROWS);
        $footerBaseRow = $this->offsetRow(self::FOOTER_BASE_ROW, $sectionTopRow);

        if ($extraRows > 0 && $allowExpand) {
            $sheet->insertNewRowBefore($footerBaseRow, $extraRows);
        }

        $this->unmergeDynamicRanges($sheet, $sectionTopRow, $layout['footer_row']);
        $this->clearTemplateContent($sheet, $sectionTopRow);
        $this->applyHeaderDates($sheet, $sectionTopRow);
        $this->applyDetailStyles($sheet, $layout);
        $this->applyColumnAStyles($sheet, $layout, $sectionTopRow);
        $this->writeDetailSections($sheet, $layout);
        $this->writeFooter($sheet, $layout);

        return $layout['footer_row'];
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
        $weekEnd = $this->weekStart->addDays(6);

        return AtaMontadoTelasModel::query()
            ->where('Estatus', 'Autorizado')
            ->whereNotNull('FechaArranque')
            ->whereBetween('FechaArranque', [$this->weekStart->toDateString(), $weekEnd->toDateString()])
            ->orderBy('Turno')
            ->orderBy('CveTejedor')
            ->orderBy('NomTejedor')
            ->orderBy('FechaArranque')
            ->orderBy('HrInicio')
            ->orderBy('HoraArranque')
            ->orderBy('Id')
            ->get([
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
            ]);
    }

    private function prepareRecords(Collection $records): Collection
    {
        return $records
            ->map(function ($item) {
                $fechaArranque = $this->parseDate($this->readValue($item, 'FechaArranque'));
                $turno = $this->normalizeTurn($this->readValue($item, 'Turno'));

                if ($fechaArranque === null || $turno === null) {
                    return null;
                }

                return [
                    'id' => (int) ($this->readValue($item, 'Id') ?? 0),
                    'fecha' => $fechaArranque,
                    'day_index' => $this->weekStart->diffInDays($fechaArranque),
                    'turno' => $turno,
                    'atador_key' => $this->resolveAtadorKey($item),
                    'atado' => $this->resolveAtadoLabel($this->readValue($item, 'Tipo')),
                    'telar' => trim((string) ($this->readValue($item, 'NoTelarId') ?? '')),
                    'hora_inicio' => $this->normalizeTime($this->readValue($item, 'HrInicio')),
                    'hora_fin' => $this->normalizeTime($this->readValue($item, 'HoraArranque')),
                    'calif' => $this->normalizeNumber($this->readValue($item, 'Calidad')),
                    'five_s' => $this->normalizeNumber($this->readValue($item, 'Limpieza')),
                    'merma' => $this->normalizeNumber($this->readValue($item, 'MergaKg')),
                ];
            })
            ->filter(function (?array $item): bool {
                return is_array($item)
                    && $item['day_index'] >= 0
                    && $item['day_index'] <= 6;
            })
            ->values();
    }

    private function buildLayout(int $sectionTopRow = 1, bool $limitToBase = false): array
    {
        $recordsByTurnAndAtador = [];

        foreach ($this->records as $record) {
            $turno = $record['turno'];
            $atadorKey = $record['atador_key'];
            $recordsByTurnAndAtador[$turno][$atadorKey][] = $record;
        }

        $turns = [];
        $currentRow = $this->offsetRow(self::DETAIL_START_ROW, $sectionTopRow);

        foreach (self::TURN_DEFINITIONS as $turno => $definition) {
            $blocks = collect($recordsByTurnAndAtador[$turno] ?? [])
                ->sortKeysUsing('strnatcasecmp')
                ->map(function (array $items, string $atadorKey): array {
                    $itemsByDay = array_fill(0, 7, []);
                    foreach ($items as $item) {
                        $itemsByDay[$item['day_index']][] = $item;
                    }

                    $maxDaily = max(array_map('count', $itemsByDay));

                    return [
                        'atador_key' => $atadorKey,
                        'items_by_day' => $itemsByDay,
                        'height' => max(self::DEFAULT_BLOCK_HEIGHT, $maxDaily),
                    ];
                })
                ->values()
                ->all();

            while (count($blocks) < self::MIN_BLOCKS_PER_TURN) {
                $blocks[] = [
                    'atador_key' => '',
                    'items_by_day' => array_fill(0, 7, []),
                    'height' => self::DEFAULT_BLOCK_HEIGHT,
                ];
            }

            if ($limitToBase) {
                $blocks = array_slice($blocks, 0, self::MIN_BLOCKS_PER_TURN);
                foreach ($blocks as &$block) {
                    $block['height'] = self::DEFAULT_BLOCK_HEIGHT;
                }
                unset($block);
            }

            $turnStartRow = $currentRow;
            foreach ($blocks as $index => &$block) {
                $block['row_start'] = $currentRow;
                $block['row_end'] = $currentRow + $block['height'] - 1;
                $block['prototype'] = $this->offsetPrototype(
                    $index === 0 ? $definition['first_prototype'] : $definition['next_prototype'],
                    $sectionTopRow
                );
                $currentRow = $block['row_end'] + 1;
            }
            unset($block);

            $turns[$turno] = [
                'label' => $definition['label'],
                'row_start' => $turnStartRow,
                'row_end' => $currentRow - 1,
                'blocks' => $blocks,
            ];
        }

        $capRowStart = $currentRow;
        $capRowEnd = $capRowStart + self::CAPACITACION_HEIGHT - 1;
        $footerRow = $capRowEnd + 1;

        return [
            'turns' => $turns,
            'capacitacion' => [
                'label' => 'CAPACITACION',
                'row_start' => $capRowStart,
                'row_end' => $capRowEnd,
                'prototype' => $this->offsetPrototype([40, 45], $sectionTopRow),
            ],
            'detail_start_row' => $this->offsetRow(self::DETAIL_START_ROW, $sectionTopRow),
            'detail_rows' => $capRowEnd - $this->offsetRow(self::DETAIL_START_ROW, $sectionTopRow) + 1,
            'last_detail_row' => $capRowEnd,
            'footer_row' => $footerRow,
            'week_number' => $this->weekStart->isoWeek(),
        ];
    }

    private function unmergeDynamicRanges(Worksheet $sheet, int $sectionTopRow, int $footerRow): void
    {
        $detailStartRow = $this->offsetRow(self::DETAIL_START_ROW, $sectionTopRow);
        $ranges = $sheet->getMergeCells();

        foreach (array_keys($ranges) as $range) {
            if (! preg_match('/([A-Z]+)(\d+):([A-Z]+)(\d+)/', $range, $matches)) {
                continue;
            }

            $rowStart = (int) $matches[2];
            $rowEnd = (int) $matches[4];
            if ($rowStart <= $footerRow && $rowEnd >= $detailStartRow) {
                $sheet->unmergeCells($range);
            }
        }
    }

    private function clearTemplateContent(Worksheet $sheet, int $sectionTopRow): void
    {
        $rowOffset = $sectionTopRow - 1;

        foreach ($this->getTemplateClearCoordinates() as $coordinate) {
            $sheet->setCellValue($this->offsetCoordinateRow($coordinate, $rowOffset), null);
        }
    }

    private function applyHeaderDates(Worksheet $sheet, int $sectionTopRow): void
    {
        $rowOffset = $sectionTopRow - 1;

        foreach (self::DAY_DEFINITIONS as $offset => $day) {
            $sheet->setCellValue(
                $this->offsetCoordinateRow($day['header'], $rowOffset),
                ExcelDate::dateTimeToExcel($this->weekStart->addDays($offset))
            );
        }
    }

    private function applyDetailStyles(Worksheet $sheet, array $layout): void
    {
        foreach ($layout['turns'] as $turn) {
            foreach ($turn['blocks'] as $block) {
                $this->applyBlockStyles($sheet, $block['prototype'], $block['row_start'], $block['height']);
            }
        }

        $cap = $layout['capacitacion'];
        $this->applyBlockStyles($sheet, $cap['prototype'], $cap['row_start'], self::CAPACITACION_HEIGHT);
    }

    private function applyBlockStyles(Worksheet $sheet, array $prototype, int $targetStartRow, int $height): void
    {
        for ($offset = 0; $offset < $height; $offset++) {
            $sourceRow = $this->resolvePrototypeRow($prototype, $offset, $height);
            $targetRow = $targetStartRow + $offset;

            $sheet->getRowDimension($targetRow)
                ->setRowHeight($sheet->getRowDimension($sourceRow)->getRowHeight());
            $this->copyRowStyles($sheet, $sourceRow, $targetRow, 2, self::MAX_COLUMN_INDEX);
        }
    }

    private function applyColumnAStyles(Worksheet $sheet, array $layout, int $sectionTopRow): void
    {
        $turnThreeBlocks = $layout['turns'][3]['blocks'];
        $lastTurnThreeBlock = end($turnThreeBlocks);
        $headerLabelRow = $this->offsetRow(2, $sectionTopRow);

        foreach ($layout['turns'] as $turno => $turn) {
            foreach ($turn['blocks'] as $block) {
                $isWeekNumberBlock = $turno === 3 && $block['row_start'] === $lastTurnThreeBlock['row_start'];
                $prototype = $this->offsetPrototype($isWeekNumberBlock ? [34, 39] : [4, 9], $sectionTopRow);

                for ($offset = 0; $offset < $block['height']; $offset++) {
                    $sourceRow = $this->resolvePrototypeRow($prototype, $offset, $block['height']);
                    $targetRow = $block['row_start'] + $offset;
                    $this->copyRowStyles($sheet, $sourceRow, $targetRow, 1, 1);
                }
            }
        }

        $cap = $layout['capacitacion'];
        for ($offset = 0; $offset < self::CAPACITACION_HEIGHT; $offset++) {
            $sourceRow = $this->resolvePrototypeRow($cap['prototype'], $offset, self::CAPACITACION_HEIGHT);
            $targetRow = $cap['row_start'] + $offset;
            $this->copyRowStyles($sheet, $sourceRow, $targetRow, 1, 1);
        }

        $labelEndRow = $lastTurnThreeBlock['row_start'] - 1;
        if ($labelEndRow >= $headerLabelRow) {
            $sheet->mergeCells("A{$headerLabelRow}:A{$labelEndRow}");
            $sheet->setCellValue("A{$headerLabelRow}", 'SEMANA');
        }

        $sheet->mergeCells("A{$lastTurnThreeBlock['row_start']}:A{$lastTurnThreeBlock['row_end']}");
        $sheet->setCellValue("A{$lastTurnThreeBlock['row_start']}", $layout['week_number']);
    }

    private function writeDetailSections(Worksheet $sheet, array $layout): void
    {
        foreach ($layout['turns'] as $turn) {
            $sheet->mergeCells("B{$turn['row_start']}:B{$turn['row_end']}");
            $sheet->setCellValue("B{$turn['row_start']}", $turn['label']);

            foreach ($turn['blocks'] as $block) {
                $this->prepareAtadorBlock($sheet, $block);
            }
        }

        foreach ($layout['turns'] as $turn) {
            foreach ($turn['blocks'] as $block) {
                $this->finalizeAtadorBlock($sheet, $block);
            }
        }

        $cap = $layout['capacitacion'];
        $sheet->mergeCells("B{$cap['row_start']}:B{$cap['row_end']}");
        $sheet->mergeCells("C{$cap['row_start']}:C{$cap['row_end']}");
        $sheet->setCellValue("B{$cap['row_start']}", $cap['label']);
    }

    private function prepareAtadorBlock(Worksheet $sheet, array $block): void
    {
        $rows = array_fill(0, $block['row_end'] - $block['row_start'] + 1, []);

        foreach ($block['items_by_day'] as $dayIndex => $items) {
            foreach ($items as $rowOffset => $item) {
                $rows[$rowOffset][$dayIndex] = $item;
            }
        }

        foreach ($rows as $rowOffset => $itemsByDay) {
            $row = $block['row_start'] + $rowOffset;

            foreach ($itemsByDay as $dayIndex => $item) {
                $map = self::DAY_DEFINITIONS[$dayIndex];
                $this->writeTimeValue($sheet, "{$map['start']}{$row}", $item['hora_inicio']);
                $this->writeTimeValue($sheet, "{$map['end']}{$row}", $item['hora_fin']);
                $sheet->setCellValue("{$map['atado']}{$row}", $item['atado']);
                $sheet->setCellValueExplicit("{$map['telar']}{$row}", $item['telar'], DataType::TYPE_STRING);
                $sheet->setCellValue("{$map['calif']}{$row}", $item['calif']);
                $sheet->setCellValue("{$map['five_s']}{$row}", $item['five_s']);
                $sheet->setCellValue("{$map['merma']}{$row}", $item['merma']);
            }
        }
    }

    private function finalizeAtadorBlock(Worksheet $sheet, array $block): void
    {
        $sheet->setCellValue("C{$block['row_start']}", $block['atador_key']);
        $this->writeBlockFormulas($sheet, $block['row_start'], $block['row_end']);
        $sheet->mergeCells("C{$block['row_start']}:C{$block['row_end']}");
        $this->mergeSummaryColumns($sheet, $block['row_start'], $block['row_end']);
    }

    private function mergeSummaryColumns(Worksheet $sheet, int $rowStart, int $rowEnd): void
    {
        foreach (self::DAY_DEFINITIONS as $day) {
            foreach ([$day['avg_time'], $day['avg_calif'], $day['avg_merma']] as $column) {
                $sheet->mergeCells("{$column}{$rowStart}:{$column}{$rowEnd}");
            }
        }

        $sheet->mergeCells("CJ{$rowStart}:CJ{$rowEnd}");
    }

    private function writeBlockFormulas(Worksheet $sheet, int $rowStart, int $rowEnd): void
    {
        foreach (self::DAY_DEFINITIONS as $day) {
            for ($row = $rowStart; $row <= $rowEnd; $row++) {
                $sheet->setCellValue(
                    "{$day['duration']}{$row}",
                    '=IF(OR('
                    ."{$day['end']}{$row}"
                    .'="",'
                    ."{$day['start']}{$row}"
                    .'=""),"",'
                    ."{$day['end']}{$row}"
                    .'-'
                    ."{$day['start']}{$row}"
                    .')'
                );
            }

            $sheet->setCellValue(
                "{$day['avg_time']}{$rowStart}",
                '=IF(COUNT('
                ."{$day['telar']}{$rowStart}:{$day['telar']}{$rowEnd}"
                .')=0,"",SUM('
                ."{$day['duration']}{$rowStart}:{$day['duration']}{$rowEnd}"
                .')/COUNT('
                ."{$day['telar']}{$rowStart}:{$day['telar']}{$rowEnd}"
                .'))'
            );

            $sheet->setCellValue(
                "{$day['avg_calif']}{$rowStart}",
                '=IF(COUNTA('
                ."{$day['calif']}{$rowStart}:{$day['five_s']}{$rowEnd}"
                .')=0,"",AVERAGE('
                ."{$day['calif']}{$rowStart}:{$day['five_s']}{$rowEnd}"
                .'))'
            );

            $sheet->setCellValue(
                "{$day['avg_merma']}{$rowStart}",
                '=IF(COUNT('
                ."{$day['merma']}{$rowStart}:{$day['merma']}{$rowEnd}"
                .')=0,"",AVERAGE('
                ."{$day['merma']}{$rowStart}:{$day['merma']}{$rowEnd}"
                .'))'
            );
        }

        $sheet->setCellValue(
            "CJ{$rowStart}",
            sprintf(
                '=COUNT(I%d:I%d,U%d:U%d,AG%d:AG%d,AS%d:AS%d,BE%d:BE%d,BQ%d:BQ%d,CC%d:CC%d)',
                $rowStart,
                $rowEnd,
                $rowStart,
                $rowEnd,
                $rowStart,
                $rowEnd,
                $rowStart,
                $rowEnd,
                $rowStart,
                $rowEnd,
                $rowStart,
                $rowEnd,
                $rowStart,
                $rowEnd
            )
        );

        if ($rowStart + 1 <= $rowEnd) {
            $sheet->setCellValue(
                'CI'.($rowStart + 1),
                sprintf(
                    '=MIN(CA%d,BO%d,BC%d,AQ%d,AE%d,S%d,G%d)',
                    $rowStart,
                    $rowStart,
                    $rowStart,
                    $rowStart,
                    $rowStart,
                    $rowStart,
                    $rowStart
                )
            );
        }
    }

    private function writeFooter(Worksheet $sheet, array $layout): void
    {
        $footerRow = $layout['footer_row'];
        $detailStartRow = $layout['detail_start_row'];
        $lastDetailRow = $layout['last_detail_row'];

        $sheet->setCellValue("B{$footerRow}", 'SEMANA');
        $sheet->setCellValue("C{$footerRow}", $layout['week_number']);
        $sheet->setCellValue("CJ{$footerRow}", 'ATADOS');

        $footerColumns = [];
        foreach (self::DAY_DEFINITIONS as $day) {
            $footerColumns[] = $day['footer_count'].$footerRow;
            $sheet->setCellValue(
                "{$day['footer_count']}{$footerRow}",
                '=COUNT('
                ."{$day['footer_count']}{$detailStartRow}"
                .':'
                ."{$day['footer_count']}{$lastDetailRow}"
                .')'
            );
        }

        $sheet->setCellValue("CI{$footerRow}", '='.implode('+', $footerColumns));
    }

    private function copyRowStyles(
        Worksheet $sheet,
        int $sourceRow,
        int $targetRow,
        int $startColumnIndex,
        int $endColumnIndex
    ): void {
        $columns = $this->getColumnLabels();

        for ($columnIndex = $startColumnIndex; $columnIndex <= $endColumnIndex; $columnIndex++) {
            $column = $columns[$columnIndex];
            $sourceCoordinate = "{$column}{$sourceRow}";
            $targetCoordinate = "{$column}{$targetRow}";

            $sourceStyle = $sheet->getStyle($sourceCoordinate);
            $targetStyle = $sheet->getStyle($targetCoordinate);

            $targetStyle->getFont()->setName($sourceStyle->getFont()->getName());
            $targetStyle->getFont()->setSize($sourceStyle->getFont()->getSize());
            $targetStyle->getFont()->setBold($sourceStyle->getFont()->getBold());
            $targetStyle->getFont()->setItalic($sourceStyle->getFont()->getItalic());
            $targetStyle->getFont()->setColor($sourceStyle->getFont()->getColor());

            $targetStyle->getFill()->setFillType($sourceStyle->getFill()->getFillType());
            if ($sourceStyle->getFill()->getFillType() !== \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_NONE) {
                $targetStyle->getFill()->getStartColor()->setRGB(
                    $sourceStyle->getFill()->getStartColor()->getRGB()
                );
                $targetStyle->getFill()->getEndColor()->setRGB(
                    $sourceStyle->getFill()->getEndColor()->getRGB()
                );
            }

            $targetStyle->getAlignment()->setHorizontal($sourceStyle->getAlignment()->getHorizontal());
            $targetStyle->getAlignment()->setVertical($sourceStyle->getAlignment()->getVertical());
            $targetStyle->getAlignment()->setWrapText($sourceStyle->getAlignment()->getWrapText());

            $targetStyle->getBorders()->getTop()->setBorderStyle($sourceStyle->getBorders()->getTop()->getBorderStyle());
            $targetStyle->getBorders()->getBottom()->setBorderStyle($sourceStyle->getBorders()->getBottom()->getBorderStyle());
            $targetStyle->getBorders()->getLeft()->setBorderStyle($sourceStyle->getBorders()->getLeft()->getBorderStyle());
            $targetStyle->getBorders()->getRight()->setBorderStyle($sourceStyle->getBorders()->getRight()->getBorderStyle());

            $targetStyle->getNumberFormat()->setFormatCode(
                $sourceStyle->getNumberFormat()->getFormatCode()
            );
        }
    }

    private function writeTimeValue(Worksheet $sheet, string $coordinate, ?string $time): void
    {
        if ($time === null) {
            $sheet->setCellValue($coordinate, null);

            return;
        }

        $sheet->setCellValue($coordinate, $this->toExcelTime($time));
    }

    private function toExcelTime(string $time): float
    {
        [$hours, $minutes, $seconds] = array_map('intval', explode(':', $time));

        return (($hours * 3600) + ($minutes * 60) + $seconds) / 86400;
    }

    private function resolvePrototypeRow(array $prototype, int $offset, int $height): int
    {
        [$firstRow, $lastRow] = $prototype;

        if ($offset === 0) {
            return $firstRow;
        }

        if ($offset === $height - 1) {
            return $lastRow;
        }

        return $firstRow + 1;
    }

    private function normalizeTurn(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $turno = (int) trim((string) $value);

        return in_array($turno, [1, 2, 3], true) ? $turno : null;
    }

    private function resolveAtadorKey(mixed $item): string
    {
        $cve = trim((string) ($this->readValue($item, 'CveTejedor') ?? ''));
        if ($cve !== '') {
            return $cve;
        }

        return trim((string) ($this->readValue($item, 'NomTejedor') ?? ''));
    }

    private function resolveAtadoLabel(mixed $tipo): string
    {
        $tipo = mb_strtolower(trim((string) ($tipo ?? '')), 'UTF-8');

        if ($tipo === 'rizo') {
            return 'R';
        }

        if ($tipo === 'pie') {
            return 'P';
        }

        return $tipo !== '' ? mb_strtoupper(mb_substr($tipo, 0, 1, 'UTF-8'), 'UTF-8') : '';
    }

    private function parseDate(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse((string) $value)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function normalizeTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^(\d{1,2}:\d{2}:\d{2})/', $raw, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('/^(\d{1,2}:\d{2})$/', $raw, $matches) === 1) {
            return $matches[1].':00';
        }

        try {
            return CarbonImmutable::parse($raw)->format('H:i:s');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function normalizeNumber(mixed $value): float|int|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? $value + 0 : null;
    }

    private function readValue(mixed $item, string $key): mixed
    {
        if (is_array($item)) {
            return $item[$key] ?? null;
        }

        return $item->{$key} ?? null;
    }

    private function offsetRow(int $row, int $sectionTopRow): int
    {
        return $sectionTopRow + $row - 1;
    }

    private function offsetPrototype(array $prototype, int $sectionTopRow): array
    {
        return [
            $this->offsetRow($prototype[0], $sectionTopRow),
            $this->offsetRow($prototype[1], $sectionTopRow),
        ];
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

    private function getTemplateClearCoordinates(): array
    {
        if (self::$templateClearCoordinates !== null) {
            return self::$templateClearCoordinates;
        }

        $templateSheet = $this->loadTemplateBook()->getSheet(0);
        $coordinates = [];

        for ($row = self::DETAIL_START_ROW; $row <= self::FOOTER_BASE_ROW; $row++) {
            for ($column = 1; $column <= self::MAX_COLUMN_INDEX; $column++) {
                $coordinate = $this->getColumnLabels()[$column].$row;
                $value = $templateSheet->getCell($coordinate)->getValue();

                if ($value !== null && $value !== '') {
                    $coordinates[] = $coordinate;
                }
            }
        }

        return self::$templateClearCoordinates = $coordinates;
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
}
