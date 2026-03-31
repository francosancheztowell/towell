<?php

namespace App\Services\OeeAtadores;

use App\Exports\Reporte00EAtadoresExport;
use App\Models\Atadores\AtaMontadoTelasModel;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\ReferenceHelper;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class OeeAtadoresFileService
{
    private const AVG_TIME_COLS = ['G', 'S', 'AE', 'AQ', 'BC', 'BO', 'CA'];

    private const AVG_CALIF_COLS = ['L', 'X', 'AJ', 'AV', 'BH', 'BT', 'CF'];

    private const AVG_MERMA_COLS = ['N', 'Z', 'AL', 'AX', 'BJ', 'BV', 'CH'];

    private const CONCENTRADO_SLOT_ROWS = [5, 10, 15, 20, 25];

    private const DETAIL_MAX_COLUMN_INDEX = 99;

    private const DETAIL_SECTION_MARKER_LABEL = 'SEMANA';

    private const DETAIL_SECTION_MARKER_COLUMN = 'A';

    private const DETAIL_FOOTER_LABEL_COLUMN = 'B';

    private const DETAIL_FOOTER_WEEK_COLUMN = 'C';

    private const DETAIL_FOOTER_ATADOS_COLUMN = 'CJ';

    private const DETAIL_FOOTER_ATADOS_LABEL = 'ATADOS';

    private const DETAIL_SUMMARY_KEY_COLUMN = 'CK';

    private const DETAIL_SUMMARY_NAME_COLUMN = 'CL';

    private const DETAIL_BLOCK_KEY_COLUMN = 'C';

    private const DETAIL_BLOCK_NAME_LABEL_COLUMN = 'B';

    private const MIN_VISIBLE_ATADORES = 5;

    private const TOTAL_ATADOS_START_ROW = 4;

    private const TOTAL_ATADOS_ROW_STRIDE = 2;

    private const TOTAL_ATADOS_FOOTER_COLUMNS = [
        'D' => 'I',
        'E' => 'U',
        'F' => 'AG',
        'G' => 'AS',
        'H' => 'BE',
        'I' => 'BQ',
        'J' => 'CC',
    ];

    private const GRAFICA_START_ROW = 2;

    private const ANNUAL_OEE_START_COLUMN = 5;

    private const ANNUAL_COLUMN_STRIDE = 2;

    private const ANNUAL_PRIMARY_LOOKUP_ROWS = [
        37 => 303,
        38 => 3180,
        39 => 470,
        40 => 3652,
        41 => 3619,
        42 => 332,
    ];

    private const ANNUAL_NAME_ROWS = [
        17 => 303,
        18 => 3180,
        19 => 470,
        20 => 3652,
        21 => 3619,
        22 => 332,
        23 => 402,
        24 => 99,
        25 => 4691,
    ];

    private const MESES = [
        1 => 'ENERO',
        2 => 'FEBRERO',
        3 => 'MARZO',
        4 => 'ABRIL',
        5 => 'MAYO',
        6 => 'JUNIO',
        7 => 'JULIO',
        8 => 'AGOSTO',
        9 => 'SEPTIEMBRE',
        10 => 'OCTUBRE',
        11 => 'NOVIEMBRE',
        12 => 'DICIEMBRE',
    ];

    private const SEMANA_TEMPLATE_WEEK = 11;

    private const SEMANA_BASE_CAPACITY = 5;

    private const SEMANA_TITLE_START_ROW = 2;

    private const SEMANA_TITLE_END_ROW = 3;

    private const SEMANA_TITLE_START_COLUMN = 2;

    private const SEMANA_TITLE_END_COLUMN = 13;

    private const SEMANA_LIST_START_ROW = 4;

    private const SEMANA_LIST_START_COLUMN = 2;

    private const SEMANA_LIST_END_COLUMN = 13;

    private const SEMANA_MERMA_PROMEDIO_TEMPLATE_ROW = 11;

    private const SEMANA_PROMEDIO_TEMPLATE_ROW = 13;

    private const SEMANA_METRIC_TEMPLATE_START_ROW = 16;

    private const SEMANA_METRIC_TEMPLATE_END_ROW = 22;

    private const SEMANA_METRIC_TEMPLATE_START_COLUMN = 3;

    private const SEMANA_METRIC_TEMPLATE_END_COLUMN = 8;

    private const SEMANA_MANUAL_TEMPLATE_START_ROW = 26;

    private const SEMANA_MANUAL_TEMPLATE_END_ROW = 38;

    private const SEMANA_MANUAL_START_COLUMN = 4;

    private const SEMANA_MANUAL_END_COLUMN = 10;

    private const SEMANA_ACTIONS_TEMPLATE_START_ROW = 3;

    private const SEMANA_ACTIONS_TEMPLATE_END_ROW = 14;

    private const SEMANA_ACTIONS_TEMPLATE_START_COLUMN = 16;

    private const SEMANA_ACTIONS_TEMPLATE_END_COLUMN = 18;

    private const SEMANA_USTER_TEMPLATE_START_ROW = 44;

    private const SEMANA_USTER_TEMPLATE_END_ROW = 52;

    private const SEMANA_USTER_TEMPLATE_START_COLUMN = 12;

    private const SEMANA_USTER_TEMPLATE_END_COLUMN = 19;

    public function __construct(private readonly string $filePath) {}

    public function verificarSemanasConDatos(CarbonImmutable $weekStart, CarbonImmutable $weekEnd): array
    {
        $this->assertWorkbookExists();

        $weeks = $this->getWeeksInRange($weekStart, $weekEnd);
        $year = $this->assertSingleIsoYear($weeks);
        $workbook = $this->loadWorkbook(true, ['DETALLE']);
        $detalle = $workbook->getSheetByName('DETALLE');

        if (! $detalle) {
            throw new RuntimeException('No se encontró la hoja DETALLE en el archivo OEE.');
        }

        $parsed = $this->parseDetalleSections($detalle);
        $diagnostico = $this->buildDiagnostics($weeks, $parsed['map'], $detalle);

        return [
            'anio_iso' => $year,
            'semanas_rango' => array_map(fn (CarbonImmutable $week) => $week->isoWeek(), $weeks),
            'semanas_con_datos' => array_values(array_map(
                fn (array $item) => $item['semana'],
                array_filter($diagnostico, fn (array $item) => $item['tiene_datos'])
            )),
            'diagnostico' => $diagnostico,
        ];
    }

    public function actualizarArchivo(CarbonImmutable $weekStart, CarbonImmutable $weekEnd): string
    {
        ini_set('max_execution_time', '0');
        set_time_limit(0);

        $this->assertWorkbookExists();

        $weeks = $this->getWeeksInRange($weekStart, $weekEnd);
        $requestedWeekNumbers = array_map(fn (CarbonImmutable $week) => $week->isoWeek(), $weeks);
        $year = $this->assertSingleIsoYear($weeks);

        $spreadsheet = IOFactory::load($this->filePath);
        $detalle = $spreadsheet->getSheetByName('DETALLE');

        if (! $detalle) {
            throw new RuntimeException('No se encontró la hoja DETALLE en el archivo OEE.');
        }

        $dataWorkbook = $this->loadWorkbook(true, ['DETALLE']);
        $detalleData = $dataWorkbook->getSheetByName('DETALLE');

        if (! $detalleData) {
            throw new RuntimeException('No se encontró la hoja DETALLE en el archivo OEE.');
        }

        $parsed = $this->parseDetalleSections($detalle, $detalleData);
        $this->normalizeDetalleFooterWeeks($detalle, $parsed['sections']);
        $parsed = $this->parseDetalleSections($detalle);

        $workbookYear = $this->resolveWorkbookYear($spreadsheet);
        if ($workbookYear !== null && $workbookYear !== $year) {
            throw new RuntimeException("El archivo OEE corresponde al año {$workbookYear}; no se pueden mezclar semanas del año ISO {$year}.");
        }

        $diagnostico = $this->buildDiagnostics($weeks, $parsed['map'], $detalle);

        foreach ($weeks as $week) {
            $weekNum = $week->isoWeek();
            $parsed = $this->parseDetalleSections($detalle);
            $existing = $parsed['map'][$weekNum] ?? null;
            $prototypeSection = $this->resolveDetallePrototypeSection(
                $parsed['sections'],
                $weekNum,
                $requestedWeekNumbers
            );

            if ($existing !== null && $prototypeSection !== null) {
                $this->rebuildDetalleSectionFromPrototype($detalle, $existing, $prototypeSection, $week);
            } elseif ($existing !== null) {
                $generated = $this->generateWeeklySection($week, $detalle, $prototypeSection);
                $this->replaceDetalleSection($detalle, $existing, $generated);
            } else {
                $generated = $this->generateWeeklySection($week, $detalle, $prototypeSection);
                $insertTop = $this->resolveInsertTop($parsed['sections'], $weekNum);
                $this->insertDetalleSection($detalle, $insertTop, $generated);
            }
        }

        $parsed = $this->parseDetalleSections($detalle);
        $this->normalizeDetalleFooterWeeks($detalle, $parsed['sections']);
        $this->normalizeDetalleVisualWeeks($detalle, $parsed['sections']);
        $parsed = $this->parseDetalleSections($detalle);

        $availableWeekSheets = $this->syncSemanaSheets($spreadsheet, $parsed['map'], $requestedWeekNumbers);
        $requestedMonths = array_values(array_unique(array_map(
            fn (CarbonImmutable $week) => $week->addDays(3)->month,
            $weeks
        )));

        $this->rebuildConcentradoSheets($spreadsheet, $year, $availableWeekSheets, $requestedMonths);
        $this->rebuildTotalAtadosSheet($spreadsheet, $parsed['map'], $year);
        $this->rebuildGraficaSheet($spreadsheet, count($parsed['map']));
        $this->rebuildAnnualAtadoresSheet($spreadsheet, $availableWeekSheets);

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->setPreCalculateFormulas(false);

        $destDir = dirname($this->filePath);
        $tmpFile = $destDir.DIRECTORY_SEPARATOR.'.oee_tmp_'.uniqid('', true).'.xlsx';

        try {
            $writer->save($tmpFile);
        } catch (\Throwable $e) {
            @unlink($tmpFile);
            throw new RuntimeException('No se pudo generar el archivo temporal: '.$e->getMessage(), 0, $e);
        }

        unset($writer, $spreadsheet, $detalle, $dataWorkbook, $detalleData);
        gc_collect_cycles();

        if (! @rename($tmpFile, $this->filePath)) {
            $copied = @copy($tmpFile, $this->filePath);
            @unlink($tmpFile);

            if (! $copied) {
                $err = error_get_last()['message'] ?? 'Error desconocido al reemplazar el archivo';
                throw new RuntimeException("No se pudo guardar el archivo OEE ({$this->filePath}): {$err}");
            }
        }

        return $this->filePath;
    }

    private function assertWorkbookExists(): void
    {
        if (! is_file($this->filePath)) {
            throw new RuntimeException("El archivo OEE no existe: {$this->filePath}");
        }
    }

    private function loadWorkbook(bool $readDataOnly = false, array $sheetNames = []): Spreadsheet
    {
        $reader = IOFactory::createReaderForFile($this->filePath);
        $reader->setReadDataOnly($readDataOnly);

        if ($sheetNames !== [] && method_exists($reader, 'setLoadSheetsOnly')) {
            $reader->setLoadSheetsOnly($sheetNames);
        }

        return $reader->load($this->filePath);
    }

    private function assertSingleIsoYear(array $weeks): int
    {
        $years = array_values(array_unique(array_map(
            fn (CarbonImmutable $week) => $week->isoWeekYear,
            $weeks
        )));

        if (count($years) !== 1) {
            throw new RuntimeException('El rango debe pertenecer al mismo año ISO para actualizar el archivo anual OEE.');
        }

        return $years[0];
    }

    private function buildDiagnostics(array $weeks, array $sectionMap, Worksheet $detalleSheet): array
    {
        $diagnostico = [];

        foreach ($weeks as $week) {
            $weekNum = $week->isoWeek();
            $export = new Reporte00EAtadoresExport($week);
            $layout = $export->getLayout(1, false);
            $nameMap = $this->loadAtadorNamesForWeek($week);
            $atadorList = $this->extractAtadorList($layout, $nameMap);
            $section = $sectionMap[$weekNum] ?? null;
            $monthNum = $week->addDays(3)->month;

            $diagnostico[] = [
                'semana' => $weekNum,
                'anio_iso' => $week->isoWeekYear,
                'mes' => self::MESES[$monthNum] ?? '',
                'mes_numero' => $monthNum,
                'seccion_encontrada' => $section !== null,
                'fila_inicio' => $section['top'] ?? null,
                'fila_footer' => $section['footer'] ?? null,
                'filas_actuales' => $section['rows'] ?? null,
                'filas_requeridas' => $layout['footer_row'],
                'atadores_visibles' => count($atadorList),
                'excede_limite_atadores' => false,
                'tiene_datos' => $section !== null ? $this->sectionHasData($detalleSheet, $section) : false,
            ];
        }

        return $diagnostico;
    }

    private function sectionHasData(Worksheet $detalleSheet, array $section): bool
    {
        $startRow = $section['top'] + 3;
        $endRow = max($startRow, $section['footer'] - 1);

        for ($row = $startRow; $row <= $endRow; $row++) {
            $label = $this->normalizeLabel($detalleSheet->getCell(self::DETAIL_BLOCK_NAME_LABEL_COLUMN.$row)->getValue());
            if ($label === 'CAPACITACION') {
                continue;
            }

            $value = trim((string) ($detalleSheet->getCell(self::DETAIL_BLOCK_KEY_COLUMN.$row)->getValue() ?? ''));
            if ($value !== '' && $value !== '0') {
                return true;
            }
        }

        return false;
    }

    private function parseDetalleSections(Worksheet $detalle, ?Worksheet $detalleData = null): array
    {
        $maxRow = $detalle->getHighestRow();
        $starts = [];
        $footers = [];

        for ($row = 1; $row <= $maxRow; $row++) {
            $a = $this->normalizeLabel($detalle->getCell(self::DETAIL_SECTION_MARKER_COLUMN.$row)->getValue());
            $b = $this->normalizeLabel($detalle->getCell(self::DETAIL_FOOTER_LABEL_COLUMN.$row)->getValue());
            $cj = $this->normalizeLabel($detalle->getCell(self::DETAIL_FOOTER_ATADOS_COLUMN.$row)->getValue());

            if ($a === self::DETAIL_SECTION_MARKER_LABEL) {
                $starts[] = $row;
            }

            if ($b === self::DETAIL_SECTION_MARKER_LABEL && $cj === self::DETAIL_FOOTER_ATADOS_LABEL) {
                $footers[] = $row;
            }
        }

        $sections = [];
        $map = [];
        $slots = [];
        $footerIndex = 0;

        foreach ($starts as $startRow) {
            while (isset($footers[$footerIndex]) && $footers[$footerIndex] <= $startRow) {
                $footerIndex++;
            }

            if (! isset($footers[$footerIndex])) {
                break;
            }

            $footerRow = $footers[$footerIndex];
            $topRow = max(1, $startRow - 1);
            $weekNum = $this->resolveSectionWeekNumber($detalle, $detalleData, $footerRow);
            $section = [
                'top' => $topRow,
                'start' => $startRow,
                'footer' => $footerRow,
                'rows' => $footerRow - $topRow + 1,
                'week' => $weekNum,
            ];

            $sections[] = $section;

            if ($weekNum !== null && ! isset($map[$weekNum])) {
                $map[$weekNum] = $section;
            } elseif ($weekNum === null) {
                $slots[] = $section;
            }

            $footerIndex++;
        }

        usort($sections, fn (array $left, array $right) => $left['top'] <=> $right['top']);
        ksort($map);

        return ['sections' => $sections, 'map' => $map, 'slots' => $slots];
    }

    private function normalizeDetalleFooterWeeks(Worksheet $detalle, array $sections): void
    {
        foreach ($sections as $section) {
            if (($section['week'] ?? null) === null) {
                continue;
            }

            $detalle->setCellValue(
                self::DETAIL_FOOTER_WEEK_COLUMN.$section['footer'],
                (int) $section['week']
            );
        }
    }

    private function normalizeDetalleVisualWeeks(Worksheet $detalle, array $sections): void
    {
        foreach ($sections as $section) {
            $week = $section['week'] ?? null;
            if ($week === null) {
                continue;
            }

            $anchorRow = $this->resolveDetalleVisualWeekAnchor($detalle, $section);
            if ($anchorRow === null) {
                continue;
            }

            $detalle->setCellValue("A{$anchorRow}", (int) $week);
        }
    }

    private function resolveDetalleVisualWeekAnchor(Worksheet $detalle, array $section): ?int
    {
        $top = (int) ($section['top'] ?? 0);
        $footer = (int) ($section['footer'] ?? 0);

        if ($top < 1 || $footer < $top) {
            return null;
        }

        $candidates = [];
        foreach (array_keys($detalle->getMergeCells()) as $range) {
            if (preg_match('/^A(\d+):A(\d+)$/', $range, $matches) !== 1) {
                continue;
            }

            $rowStart = (int) $matches[1];
            $rowEnd = (int) $matches[2];
            if ($rowStart < $top || $rowEnd > $footer) {
                continue;
            }

            $value = $this->normalizeLabel($detalle->getCell("A{$rowStart}")->getValue());
            if ($value === '' || $value === self::DETAIL_SECTION_MARKER_LABEL) {
                continue;
            }

            $candidates[] = $rowStart;
        }

        if ($candidates !== []) {
            return max($candidates);
        }

        for ($row = $footer; $row >= $top; $row--) {
            $value = $this->normalizeLabel($detalle->getCell("A{$row}")->getValue());
            if ($value === '' || $value === self::DETAIL_SECTION_MARKER_LABEL) {
                continue;
            }

            return $row;
        }

        return null;
    }

    private function resolveSectionWeekNumber(Worksheet $detalle, ?Worksheet $detalleData, int $footerRow): ?int
    {
        $candidates = [
            $detalleData?->getCell(self::DETAIL_FOOTER_WEEK_COLUMN.$footerRow)->getValue(),
            $detalle->getCell(self::DETAIL_FOOTER_WEEK_COLUMN.$footerRow)->getValue(),
        ];

        foreach ($candidates as $value) {
            if (! is_numeric($value)) {
                continue;
            }

            $week = (int) $value;
            if ($week >= 1 && $week <= 53) {
                return $week;
            }

            continue;
        }

        foreach ([$detalleData, $detalle] as $sheet) {
            if (! $sheet) {
                continue;
            }

            $value = $sheet->getCell(self::DETAIL_FOOTER_WEEK_COLUMN.$footerRow)->getValue();
            $week = $this->resolveWeekFormulaValue($sheet, $value);
            if ($week !== null) {
                return $week;
            }
        }

        return null;
    }

    private function resolveWeekFormulaValue(Worksheet $sheet, mixed $value, int $depth = 0): ?int
    {
        if ($depth > 8 || ! is_string($value)) {
            return null;
        }

        $formula = trim($value);
        if (! str_starts_with($formula, '=')) {
            return null;
        }

        if (preg_match('/^=([A-Z]+)(\d+)([+-]\d+)$/', strtoupper($formula), $matches) !== 1) {
            return null;
        }

        $referencedValue = $sheet->getCell($matches[1].$matches[2])->getValue();
        $baseWeek = is_numeric($referencedValue)
            ? (int) $referencedValue
            : $this->resolveWeekFormulaValue($sheet, $referencedValue, $depth + 1);

        if ($baseWeek === null) {
            return null;
        }

        $week = $baseWeek + (int) $matches[3];

        return $week >= 1 && $week <= 53 ? $week : null;
    }

    private function normalizeLabel(mixed $value): string
    {
        $label = strtoupper(trim((string) ($value ?? '')));

        return preg_replace('/\s+/', ' ', $label) ?? $label;
    }

    private function generateWeeklySection(
        CarbonImmutable $week,
        ?Worksheet $detallePrototype = null,
        ?array $prototypeSection = null
    ): array {
        $book = $this->buildDetailPrototypeBook($detallePrototype, $prototypeSection)
            ?? $this->loadSectionTemplateBook();
        $sheet = $book->getSheet(0);
        $export = new Reporte00EAtadoresExport($week);
        $footerRow = $export->renderIntoSheet($sheet, 1, true);
        $layout = $export->getLayout(1, false);
        $nameMap = $this->loadAtadorNamesForWeek($week);

        $this->writeCkCuFormulas($sheet, $layout, 1, $nameMap);
        $sheet->setCellValue(self::DETAIL_FOOTER_WEEK_COLUMN.$footerRow, $week->isoWeek());

        return [
            'spreadsheet' => $book,
            'sheet' => $sheet,
            'layout' => $layout,
            'row_count' => $footerRow,
            'atadores' => $this->extractAtadorList($layout, $nameMap),
        ];
    }

    private function resolveDetallePrototypeSection(array $sections, int $targetWeek, array $requestedWeekNumbers): ?array
    {
        $requestedLookup = array_flip($requestedWeekNumbers);
        $candidates = array_values(array_filter(
            $sections,
            fn (array $section) => ($section['week'] ?? null) !== null && (int) $section['week'] !== $targetWeek
        ));

        if ($candidates === []) {
            return null;
        }

        $preferred = array_values(array_filter(
            $candidates,
            fn (array $section) => ! isset($requestedLookup[(int) $section['week']])
        ));

        $pool = $preferred !== [] ? $preferred : $candidates;

        usort($pool, function (array $left, array $right) use ($targetWeek) {
            $leftRowDistance = abs(((int) $left['rows']) - 46);
            $rightRowDistance = abs(((int) $right['rows']) - 46);

            if ($leftRowDistance !== $rightRowDistance) {
                return $leftRowDistance <=> $rightRowDistance;
            }

            $leftDistance = abs(((int) $left['week']) - $targetWeek);
            $rightDistance = abs(((int) $right['week']) - $targetWeek);

            if ($leftDistance !== $rightDistance) {
                return $leftDistance <=> $rightDistance;
            }

            return ((int) $left['top']) <=> ((int) $right['top']);
        });

        return $pool[0] ?? null;
    }

    private function loadSectionTemplateBook(): Spreadsheet
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
            'No se encontró la plantilla Reporte_00E_Atadores.xlsx en resources/templates/ o storage/app/templates/.'
        );
    }

    private function buildDetailPrototypeBook(?Worksheet $detalle, ?array $prototypeSection): ?Spreadsheet
    {
        if (! $detalle || ! is_array($prototypeSection)) {
            return null;
        }

        $rowCount = (int) ($prototypeSection['rows'] ?? 0);
        $sourceTop = (int) ($prototypeSection['top'] ?? 0);

        if ($rowCount < 1 || $sourceTop < 1) {
            return null;
        }

        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('DETALLE');

        $this->copySectionRange($sheet, $detalle, $sourceTop, $rowCount, 1);

        return $book;
    }

    private function rebuildDetalleSectionFromPrototype(
        Worksheet $detalle,
        array $section,
        array $prototypeSection,
        CarbonImmutable $week
    ): void {
        $prototypeRows = (int) ($prototypeSection['rows'] ?? 0);
        $prototypeTop = (int) ($prototypeSection['top'] ?? 0);
        $targetTop = (int) ($section['top'] ?? 0);

        if ($prototypeRows < 1 || $prototypeTop < 1 || $targetTop < 1) {
            $generated = $this->generateWeeklySection($week);
            $this->replaceDetalleSection($detalle, $section, $generated);

            return;
        }

        $this->resizeDetalleSection($detalle, $section, $prototypeRows);
        $this->copySectionRange($detalle, $detalle, $prototypeTop, $prototypeRows, $targetTop);

        $export = new Reporte00EAtadoresExport($week);
        $footerRow = $export->renderIntoSheet($detalle, $targetTop, true);
        $layout = $export->getLayout($targetTop, false);
        $nameMap = $this->loadAtadorNamesForWeek($week);

        $this->writeCkCuFormulas($detalle, $layout, $targetTop, $nameMap);
        $detalle->setCellValue(self::DETAIL_FOOTER_WEEK_COLUMN.$footerRow, $week->isoWeek());
    }

    private function replaceDetalleSection(Worksheet $detalle, array $section, array $generated): void
    {
        $desiredRows = (int) $generated['row_count'];
        $this->resizeDetalleSection($detalle, $section, $desiredRows);
        $this->copyGeneratedSection($detalle, $generated['sheet'], $section['top'], $desiredRows);
    }

    private function insertDetalleSection(Worksheet $detalle, int $insertTop, array $generated): void
    {
        $rowCount = (int) $generated['row_count'];
        $insertTop = max(1, min($insertTop, $detalle->getHighestRow() + 1));
        $detalle->insertNewRowBefore($insertTop, $rowCount);
        $this->copyGeneratedSection($detalle, $generated['sheet'], $insertTop, $rowCount);
    }

    private function resizeDetalleSection(Worksheet $detalle, array $section, int $desiredRows): void
    {
        $currentRows = (int) $section['rows'];
        if ($currentRows === $desiredRows) {
            return;
        }

        $this->unmergeRowsInRange($detalle, $section['top'], $section['footer']);

        if ($desiredRows > $currentRows) {
            $detalle->insertNewRowBefore($section['footer'], $desiredRows - $currentRows);

            return;
        }

        $rowsToDelete = $currentRows - $desiredRows;
        $deleteStart = $section['footer'] - $rowsToDelete;
        $this->unmergeRowsInRange($detalle, $deleteStart, $section['footer']);
        $detalle->removeRow($deleteStart, $rowsToDelete);
    }

    private function resolveInsertTop(array $sections, int $weekNum): int
    {
        foreach ($sections as $section) {
            if (($section['week'] ?? null) !== null && $section['week'] > $weekNum) {
                return $section['top'];
            }
        }

        $lastSection = end($sections);

        return is_array($lastSection) ? $lastSection['footer'] + 1 : 1;
    }

    private function copyGeneratedSection(Worksheet $target, Worksheet $source, int $targetTop, int $rowCount): void
    {
        $this->copySectionRange($target, $source, 1, $rowCount, $targetTop);
    }

    private function copySectionRange(
        Worksheet $target,
        Worksheet $source,
        int $sourceTop,
        int $rowCount,
        int $targetTop
    ): void {
        $sourceBottom = $sourceTop + $rowCount - 1;
        $targetBottom = $targetTop + $rowCount - 1;
        $this->unmergeRowsInRange($target, $targetTop, $targetBottom);
        $this->clearRange($target, $targetTop, $targetBottom, 1, self::DETAIL_MAX_COLUMN_INDEX);

        $rowOffset = $targetTop - $sourceTop;
        $referenceHelper = ReferenceHelper::getInstance();

        for ($sourceRow = $sourceTop; $sourceRow <= $sourceBottom; $sourceRow++) {
            $targetRow = $sourceRow + $rowOffset;
            $sourceDimension = $source->getRowDimension($sourceRow);
            $targetDimension = $target->getRowDimension($targetRow);

            $targetDimension->setRowHeight($sourceDimension->getRowHeight());
            $targetDimension->setVisible($sourceDimension->getVisible());
            $targetDimension->setOutlineLevel($sourceDimension->getOutlineLevel());
            $targetDimension->setCollapsed($sourceDimension->getCollapsed());

            for ($columnIndex = 1; $columnIndex <= self::DETAIL_MAX_COLUMN_INDEX; $columnIndex++) {
                $column = Coordinate::stringFromColumnIndex($columnIndex);
                $sourceCoordinate = "{$column}{$sourceRow}";
                $targetCoordinate = "{$column}{$targetRow}";
                $sourceCell = $source->getCell($sourceCoordinate);

                $target->duplicateStyle($source->getStyle($sourceCoordinate), $targetCoordinate);

                $value = $sourceCell->getValue();
                $dataType = $sourceCell->getDataType();

                if ($dataType === DataType::TYPE_FORMULA && is_string($value)) {
                    $shifted = $referenceHelper->updateFormulaReferences(
                        $value,
                        'A1',
                        0,
                        $rowOffset,
                        $target->getTitle(),
                        true
                    );
                    $target->setCellValueExplicit($targetCoordinate, $shifted, DataType::TYPE_FORMULA);

                    continue;
                }

                if ($value === null || $value === '') {
                    $target->setCellValueExplicit($targetCoordinate, null, DataType::TYPE_NULL);

                    continue;
                }

                $target->setCellValueExplicit($targetCoordinate, $value, $dataType);
            }
        }

        foreach (array_keys($source->getMergeCells()) as $range) {
            if (preg_match('/^([A-Z]+)(\d+):([A-Z]+)(\d+)$/', $range, $matches) !== 1) {
                continue;
            }

            $rowStart = (int) $matches[2];
            $rowEnd = (int) $matches[4];
            if ($rowStart < $sourceTop || $rowEnd > $sourceBottom) {
                continue;
            }

            $target->mergeCells(sprintf(
                '%s%d:%s%d',
                $matches[1],
                $rowStart + $rowOffset,
                $matches[3],
                $rowEnd + $rowOffset
            ));
        }
    }

    private function unmergeRowsInRange(Worksheet $sheet, int $startRow, int $endRow): void
    {
        foreach (array_keys($sheet->getMergeCells()) as $range) {
            if (preg_match('/^([A-Z]+)(\d+):([A-Z]+)(\d+)$/', $range, $matches) !== 1) {
                continue;
            }

            $rowStart = (int) $matches[2];
            $rowEnd = (int) $matches[4];
            if ($rowStart <= $endRow && $rowEnd >= $startRow) {
                $sheet->unmergeCells($range);
            }
        }
    }

    private function syncSemanaSheets(Spreadsheet $spreadsheet, array $sectionMap, array $requestedWeekNumbers): array
    {
        $detalle = $spreadsheet->getSheetByName('DETALLE');
        if (! $detalle) {
            return [];
        }

        $existingSheets = $this->getExistingSemanaSheets($spreadsheet);
        $templateSheet = $this->resolveSemanaTemplateSheet($spreadsheet, $existingSheets);

        if (! $templateSheet) {
            throw new RuntimeException('No se encontró una hoja SEMANA canónica para regenerar el formato OEE.');
        }

        $available = [];
        foreach ($existingSheets as $weekNum => $sheet) {
            if (! isset($sectionMap[$weekNum])) {
                continue;
            }

            $available[$weekNum] = $sheet->getTitle();
        }

        $targetWeeks = array_values(array_unique(array_filter(
            $requestedWeekNumbers,
            fn (int $weekNum) => isset($sectionMap[$weekNum])
        )));
        sort($targetWeeks);

        if ($targetWeeks === []) {
            ksort($available);

            return $available;
        }

        $templateSource = clone $templateSheet;
        $templateSource->setTitle('__SEM_TMP_'.substr(uniqid('', true), -8));
        $spreadsheet->addSheet($templateSource);

        try {
            foreach ($targetWeeks as $weekNum) {
                $section = $sectionMap[$weekNum] ?? null;
                if ($section === null) {
                    continue;
                }

                $existingSheet = $existingSheets[$weekNum] ?? null;
                $preservedState = $this->captureSemanaPreservedState($existingSheet);
                $sheet = $this->replaceSemanaSheetFromTemplate(
                    $spreadsheet,
                    $templateSource,
                    sprintf('SEMANA %02d', $weekNum),
                    $existingSheet
                );

                $atadorList = $this->extractAtadorListFromDetail(
                    $detalle,
                    $section['top'] + 3,
                    $section['top'],
                    $section['footer']
                );

                $this->writeSemanaContent($sheet, $templateSource, $weekNum, $section['top'], $atadorList, $preservedState);
                $available[$weekNum] = $sheet->getTitle();
            }
        } finally {
            $templateIndex = $spreadsheet->getIndex($templateSource);
            $spreadsheet->removeSheetByIndex($templateIndex);
        }

        ksort($available);

        return $available;
    }

    private function getExistingSemanaSheets(Spreadsheet $spreadsheet): array
    {
        $sheets = [];

        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            if (preg_match('/^SEMANA\s+(\d{2})$/', $sheet->getTitle(), $matches) !== 1) {
                continue;
            }

            $sheets[(int) $matches[1]] = $sheet;
        }

        ksort($sheets);

        return $sheets;
    }

    private function extractAtadorListFromDetail(
        Worksheet $detalle,
        int $firstSummaryRow,
        int $sectionTopRow,
        int $footerRow
    ): array {
        $detailStartRow = $sectionTopRow + 3;
        $blockRanges = [];

        foreach (array_keys($detalle->getMergeCells()) as $range) {
            if (preg_match('/^C(\d+):C(\d+)$/', $range, $matches) !== 1) {
                continue;
            }

            $rowStart = (int) $matches[1];
            $rowEnd = (int) $matches[2];

            if ($rowStart < $detailStartRow || $rowEnd >= $footerRow) {
                continue;
            }

            $blockRanges[] = [$rowStart, $rowEnd];
        }

        usort($blockRanges, fn (array $left, array $right) => $left[0] <=> $right[0]);

        $entries = [];
        $summaryIndex = 0;

        foreach ($blockRanges as [$rowStart]) {
            $label = $this->normalizeLabel($detalle->getCell(self::DETAIL_BLOCK_NAME_LABEL_COLUMN.$rowStart)->getValue());
            if ($label === 'CAPACITACION') {
                continue;
            }

            $summaryRow = $firstSummaryRow + $summaryIndex;
            $summaryIndex++;

            $key = trim((string) ($detalle->getCell(self::DETAIL_BLOCK_KEY_COLUMN.$rowStart)->getValue() ?? ''));
            if ($key === '' || $key === '0') {
                continue;
            }

            $entries[] = [
                'key' => $key,
                'summary_row' => $summaryRow,
                'block_start' => $rowStart,
            ];
        }

        if ($entries !== []) {
            return $entries;
        }

        $summaryIndex = 0;
        for ($row = $detailStartRow; $row < $footerRow; $row++) {
            $label = $this->normalizeLabel($detalle->getCell(self::DETAIL_BLOCK_NAME_LABEL_COLUMN.$row)->getValue());
            if (in_array($label, ['1 ER TURNO', '2 DO TURNO', '3 ER TURNO', 'CAPACITACION'], true)) {
                continue;
            }

            $key = trim((string) ($detalle->getCell(self::DETAIL_BLOCK_KEY_COLUMN.$row)->getValue() ?? ''));
            if ($key === '' || $key === '0') {
                continue;
            }

            $entries[] = [
                'key' => $key,
                'summary_row' => $firstSummaryRow + $summaryIndex,
                'block_start' => $row,
            ];
            $summaryIndex++;
        }

        return $entries;
    }

    private function rebuildConcentradoSheets(
        Spreadsheet $spreadsheet,
        int $year,
        array $availableWeekSheets,
        array $requestedMonths
    ): void {
        $existingSheets = $this->getExistingConcentradoSheets($spreadsheet);
        $months = array_values(array_unique(array_merge(array_keys($existingSheets), $requestedMonths)));
        sort($months);

        foreach ($months as $month) {
            $sheet = $existingSheets[$month] ?? $this->cloneConcentradoSheet($spreadsheet, $month);

            if (! $sheet) {
                continue;
            }

            $this->writeConcentradoContent($sheet, $month, $year, $availableWeekSheets);
        }
    }

    private function getExistingConcentradoSheets(Spreadsheet $spreadsheet): array
    {
        $monthsByName = [];
        foreach (self::MESES as $number => $name) {
            $monthsByName[$this->normalizeLabel($name)] = $number;
        }

        $sheets = [];
        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            if (preg_match('/^CONCENTRADO\s+(.+)$/u', $sheet->getTitle(), $matches) !== 1) {
                continue;
            }

            $month = $monthsByName[$this->normalizeLabel($matches[1])] ?? null;
            if ($month === null) {
                continue;
            }

            $sheets[$month] = $sheet;
        }

        ksort($sheets);

        return $sheets;
    }

    private function cloneConcentradoSheet(Spreadsheet $spreadsheet, int $month): ?Worksheet
    {
        $existing = $this->getExistingConcentradoSheets($spreadsheet);
        $template = end($existing);

        if (! $template instanceof Worksheet) {
            return null;
        }

        $newSheet = clone $template;
        $newSheet->setTitle('CONCENTRADO '.(self::MESES[$month] ?? $month));
        $spreadsheet->addSheet($newSheet);

        return $newSheet;
    }

    private function writeConcentradoContent(
        Worksheet $sheet,
        int $month,
        int $year,
        array $availableWeekSheets
    ): void {
        $spreadsheet = $sheet->getParent();
        $styleTemplate = $this->captureConcentradoStyleTemplate($sheet);
        $sheet->setCellValue('D2', self::MESES[$month] ?? '');
        $sheet->setCellValue('B4', self::MESES[$month] ?? '');

        $weeks = [];
        for ($week = 1; $week <= 53; $week++) {
            if (! isset($availableWeekSheets[$week])) {
                continue;
            }

            if ($this->resolveMonthForWeek($year, $week) === $month) {
                $weeks[] = $week;
            }
        }

        $weeks = array_slice($weeks, 0, count(self::CONCENTRADO_SLOT_ROWS));

        $weekLayouts = [];
        $roster = [];

        foreach ($weeks as $weekNum) {
            $weekSheet = $spreadsheet?->getSheetByName($availableWeekSheets[$weekNum]);
            if (! $weekSheet) {
                continue;
            }

            $layout = $this->describeSemanaSheet($weekSheet);
            $weekLayouts[$weekNum] = [
                'title' => $availableWeekSheets[$weekNum],
                'layout' => $layout,
            ];

            foreach ($layout['entries'] as $entry) {
                $code = (string) $entry['code'];
                if (isset($roster[$code])) {
                    continue;
                }

                $roster[$code] = [
                    'code' => $code,
                    'name' => (string) ($entry['name'] !== '' ? $entry['name'] : $code),
                ];
            }
        }

        $roster = array_values($roster);
        $displayCount = max(self::MIN_VISIBLE_ATADORES, count($roster));
        $clearUntilColumnIndex = max(
            Coordinate::columnIndexFromString($sheet->getHighestColumn()),
            2 + $displayCount
        );
        $lastColumn = Coordinate::stringFromColumnIndex(2 + $displayCount);
        $this->applyConcentradoDynamicStyles($sheet, $displayCount, $styleTemplate);
        $this->clearRange($sheet, 4, 39, 1, $clearUntilColumnIndex);

        foreach (range(0, count($roster) - 1) as $index) {
            $column = Coordinate::stringFromColumnIndex(3 + $index);
            $sheet->setCellValue("{$column}4", $roster[$index]['name']);
            $sheet->setCellValue("{$column}32", "={$column}4");
        }

        $rowLabels = [
            0 => ['label' => 'EFICIENCIA', 'layout_key' => 'eficiencia_row'],
            1 => ['label' => 'CALIDAD/5S SEGURIDAD', 'layout_key' => 'calidad_row'],
            2 => ['label' => 'MERMA KG', 'layout_key' => 'merma_row'],
            3 => ['label' => 'MERMA %', 'layout_key' => 'merma_porcentaje_row'],
            4 => ['label' => 'OEE', 'layout_key' => 'oee_row'],
        ];

        foreach (self::CONCENTRADO_SLOT_ROWS as $index => $slotRow) {
            $weekNum = $weeks[$index] ?? null;
            $weekData = $weekNum !== null ? ($weekLayouts[$weekNum] ?? null) : null;
            if ($weekData === null) {
                continue;
            }

            $sheet->setCellValue("A{$slotRow}", $weekNum);

            foreach ($rowLabels as $offset => $info) {
                $row = $slotRow + $offset;
                $sheet->setCellValue("B{$row}", $info['label']);

                foreach ($roster as $rosterIndex => $entry) {
                    $targetCol = Coordinate::stringFromColumnIndex(3 + $rosterIndex);
                    $sheet->setCellValue(
                        "{$targetCol}{$row}",
                        $this->buildSemanaLookupFormula(
                            $weekData['title'],
                            $weekData['layout'],
                            $info['layout_key'],
                            $entry['code']
                        )
                    );
                }
            }
        }

        $summaryRows = [
            33 => ['label' => 'EFICIENCIA', 'week_rows' => [5, 10, 15, 20, 25]],
            34 => ['label' => 'CALIDAD/5S SEGURIDAD', 'week_rows' => [6, 11, 16, 21, 26]],
            35 => ['label' => 'MERMA %', 'week_rows' => [8, 13, 18, 23, 28]],
            36 => ['label' => 'MERMA KG', 'week_rows' => [7, 12, 17, 22, 27]],
            37 => ['label' => 'OEE', 'week_rows' => [9, 14, 19, 24, 29]],
        ];

        foreach ($summaryRows as $row => $config) {
            $sheet->setCellValue("B{$row}", $config['label']);

            foreach ($roster as $rosterIndex => $entry) {
                $column = Coordinate::stringFromColumnIndex(3 + $rosterIndex);
                $references = array_map(fn (int $weekRow) => "{$column}{$weekRow}", $config['week_rows']);
                $sheet->setCellValue("{$column}{$row}", '=IFERROR(AVERAGE('.implode(',', $references).'),"")');
            }
        }

        $sheet->setCellValue('B39', 'PROMEDIO OEE '.(self::MESES[$month] ?? ''));
        $sheet->setCellValue(
            'C39',
            count($roster) > 0 ? "=IFERROR(AVERAGE(C37:{$lastColumn}37),\"\")" : null
        );
    }

    private function buildSemanaLookupFormula(
        string $sheetTitle,
        array $layout,
        string $layoutKey,
        string $code
    ): string {
        $metricRow = (int) ($layout[$layoutKey] ?? 0);
        $listEndRow = (int) ($layout['list_end_row'] ?? 0);
        $lastMetricColumn = (string) ($layout['last_metric_column'] ?? 'D');

        if ($metricRow < 1 || $listEndRow < 4) {
            return '';
        }

        $lookup = is_numeric($code)
            ? (string) (0 + $code)
            : '"'.str_replace('"', '""', $code).'"';

        $quotedTitle = $this->quoteSheetTitle($sheetTitle);

        return sprintf(
            '=IFERROR(INDEX(%1$s!$D$%2$d:$%3$s$%2$d,1,MATCH(%4$s,%1$s!$B$4:$B$%5$d,0)),"")',
            $quotedTitle,
            $metricRow,
            $lastMetricColumn,
            $lookup,
            $listEndRow
        );
    }

    private function rebuildTotalAtadosSheet(Spreadsheet $spreadsheet, array $sectionMap, int $year): void
    {
        $sheet = $spreadsheet->getSheetByName('TOTAL ATADOS');
        if (! $sheet) {
            return;
        }

        $weeks = array_keys($sectionMap);
        sort($weeks);

        $lastDataRow = self::TOTAL_ATADOS_START_ROW + max(count($weeks) - 1, 0) * self::TOTAL_ATADOS_ROW_STRIDE + 1;
        $this->clearRange($sheet, self::TOTAL_ATADOS_START_ROW, max($sheet->getHighestRow(), $lastDataRow), 1, 13);

        foreach ($weeks as $index => $weekNum) {
            $row = self::TOTAL_ATADOS_START_ROW + ($index * self::TOTAL_ATADOS_ROW_STRIDE);
            $dateRow = $row + 1;
            $footerRow = $sectionMap[$weekNum]['footer'];
            $monday = CarbonImmutable::now(config('app.timezone'))->setISODate($year, $weekNum)->startOfDay();

            $sheet->setCellValue("B{$row}", 'SEMANA');
            $sheet->setCellValue("C{$row}", $weekNum);

            foreach (self::TOTAL_ATADOS_FOOTER_COLUMNS as $targetCol => $detailCol) {
                $sheet->setCellValue("{$targetCol}{$row}", "=DETALLE!{$detailCol}{$footerRow}");
            }

            $sheet->setCellValue("K{$row}", "=SUM(D{$row}:J{$row})");
            $sheet->setCellValue(
                "L{$row}",
                $index === 0 ? null : sprintf('=K%d-K%d', $row, $row - self::TOTAL_ATADOS_ROW_STRIDE)
            );
            $sheet->setCellValue("M{$row}", "=IFERROR(K{$row}/COUNT(D{$row}:J{$row}),\"\")");

            $sheet->setCellValue("D{$dateRow}", ExcelDate::dateTimeToExcel($monday));
            for ($column = 'E'; $column <= 'J'; $column++) {
                $previousColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($column) - 1);
                $sheet->setCellValue("{$column}{$dateRow}", "={$previousColumn}{$dateRow}+1");
            }
        }
    }

    private function rebuildGraficaSheet(Spreadsheet $spreadsheet, int $weekCount): void
    {
        $sheet = $spreadsheet->getSheetByName('grafica');
        if (! $sheet) {
            return;
        }

        $this->clearRange($sheet, self::GRAFICA_START_ROW, max($sheet->getHighestRow(), self::GRAFICA_START_ROW + $weekCount), 1, 3);

        for ($index = 0; $index < $weekCount; $index++) {
            $row = self::GRAFICA_START_ROW + $index;
            $sourceRow = self::TOTAL_ATADOS_START_ROW + ($index * self::TOTAL_ATADOS_ROW_STRIDE);

            $sheet->setCellValue("A{$row}", "=CONCATENATE('TOTAL ATADOS'!B{$sourceRow},'TOTAL ATADOS'!C{$sourceRow})");
            $sheet->setCellValue("B{$row}", "='TOTAL ATADOS'!K{$sourceRow}");
            $sheet->setCellValue("C{$row}", "='TOTAL ATADOS'!M{$sourceRow}");
        }
    }

    private function rebuildAnnualAtadoresSheet(Spreadsheet $spreadsheet, array $availableWeekSheets): void
    {
        $annualSheet = $this->resolveAnnualSheet($spreadsheet);
        if (! $annualSheet) {
            return;
        }

        $year = $this->resolveWorkbookYear($spreadsheet) ?? (int) CarbonImmutable::now(config('app.timezone'))->year;
        $codeNameMap = $this->buildCodeNameMap($annualSheet, $availableWeekSheets);

        foreach (self::ANNUAL_NAME_ROWS as $row => $code) {
            $name = $codeNameMap[(string) $code] ?? $codeNameMap[$code] ?? null;
            if ($name !== null && $name !== '') {
                $annualSheet->setCellValue("B{$row}", $name);
            }
        }

        $highestColumnIndex = Coordinate::columnIndexFromString($annualSheet->getHighestColumn());
        $slotCount = max(
            0,
            intdiv(max(0, $highestColumnIndex - self::ANNUAL_OEE_START_COLUMN), self::ANNUAL_COLUMN_STRIDE) + 1
        );

        for ($weekNum = 1; $weekNum <= $slotCount; $weekNum++) {
            $oeeColumnIndex = self::ANNUAL_OEE_START_COLUMN + (($weekNum - 1) * self::ANNUAL_COLUMN_STRIDE);
            $bonusColumnIndex = $oeeColumnIndex + 1;
            $oeeColumn = Coordinate::stringFromColumnIndex($oeeColumnIndex);
            $bonusColumn = Coordinate::stringFromColumnIndex($bonusColumnIndex);

            $annualSheet->setCellValue("{$oeeColumn}1", sprintf('SEMANA %02d %d', $weekNum, $year));
            $annualSheet->setCellValue("{$oeeColumn}2", 'OEE');
            $annualSheet->setCellValue("{$bonusColumn}2", 'PAGAR');

            foreach (self::ANNUAL_PRIMARY_LOOKUP_ROWS as $row => $code) {
                $weekSheetTitle = $availableWeekSheets[$weekNum] ?? null;

                if (! $weekSheetTitle) {
                    $annualSheet->setCellValue("{$oeeColumn}{$row}", null);

                    continue;
                }

                $weekSheet = $spreadsheet->getSheetByName($weekSheetTitle);
                if (! $weekSheet) {
                    $annualSheet->setCellValue("{$oeeColumn}{$row}", null);

                    continue;
                }

                $layout = $this->describeSemanaSheet($weekSheet);
                $annualSheet->setCellValue(
                    "{$oeeColumn}{$row}",
                    $this->buildSemanaLookupFormula($weekSheetTitle, $layout, 'oee_row', (string) $code)
                );
            }
        }
    }

    private function buildCodeNameMap(Worksheet $annualSheet, array $availableWeekSheets): array
    {
        $map = [];

        for ($row = 1; $row <= 30; $row++) {
            $code = trim((string) ($annualSheet->getCell("A{$row}")->getValue() ?? ''));
            $name = trim((string) ($annualSheet->getCell("B{$row}")->getValue() ?? ''));

            if ($code === '' || ! is_numeric($code)) {
                continue;
            }

            if ($name === '' || str_starts_with($name, '=') || str_contains($name, '#REF!')) {
                continue;
            }

            $map[$code] = $name;
        }

        $codes = array_values(array_unique(array_map(
            'strval',
            array_merge(array_values(self::ANNUAL_NAME_ROWS), array_values(self::ANNUAL_PRIMARY_LOOKUP_ROWS))
        )));

        $records = AtaMontadoTelasModel::query()
            ->whereIn('CveTejedor', $codes)
            ->whereNotNull('NomTejedor')
            ->orderBy('Id')
            ->get(['CveTejedor', 'NomTejedor']);

        foreach ($records as $record) {
            $code = trim((string) ($record->CveTejedor ?? ''));
            $name = trim((string) ($record->NomTejedor ?? ''));

            if ($code === '' || $name === '' || isset($map[$code])) {
                continue;
            }

            $map[$code] = $name;
        }

        return $map;
    }

    private function resolveAnnualSheet(Spreadsheet $spreadsheet): ?Worksheet
    {
        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            if (preg_match('/^ATADORES\s+\d{4}$/', $sheet->getTitle()) === 1) {
                return $sheet;
            }
        }

        return null;
    }

    private function resolveWorkbookYear(Spreadsheet $spreadsheet): ?int
    {
        $annualSheet = $this->resolveAnnualSheet($spreadsheet);

        if ($annualSheet && preg_match('/(\d{4})/', $annualSheet->getTitle(), $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    private function resolveMonthForWeek(int $year, int $weekNum): int
    {
        $week = CarbonImmutable::now(config('app.timezone'))->setISODate($year, $weekNum)->startOfDay();

        if ($week->isoWeekYear !== $year || $week->isoWeek() !== $weekNum) {
            return 0;
        }

        return $week->addDays(3)->month;
    }

    private function writeCkCuFormulas(
        Worksheet $sheet,
        array $layout,
        int $sectionTopRow,
        array $nameMap = []
    ): void {
        $summaryRow = $sectionTopRow + 3;
        $actualSummaryRows = [];

        foreach ($layout['turns'] as $turn) {
            foreach ($turn['blocks'] as $block) {
                $row = $summaryRow++;
                $blockStart = $block['row_start'];
                $key = trim((string) ($block['atador_key'] ?? ''));

                if ($key === '') {
                    $this->clearRange(
                        $sheet,
                        $row,
                        $row,
                        Coordinate::columnIndexFromString(self::DETAIL_SUMMARY_KEY_COLUMN),
                        Coordinate::columnIndexFromString('CU')
                    );

                    continue;
                }

                $actualSummaryRows[] = $row;
                $sheet->setCellValue(self::DETAIL_SUMMARY_KEY_COLUMN.$row, "=C{$blockStart}");
                $sheet->setCellValue(self::DETAIL_SUMMARY_NAME_COLUMN.$row, $nameMap[$key] ?? $key);
                $sheet->setCellValue(
                    "CM{$row}",
                    '=IFERROR(AVERAGE('.implode(',', array_map(fn (string $column) => "{$column}{$blockStart}", self::AVG_CALIF_COLS)).'),"")'
                );
                $sheet->setCellValue(
                    "CN{$row}",
                    '=IFERROR(AVERAGE('.implode(',', array_map(fn (string $column) => "{$column}{$blockStart}", self::AVG_TIME_COLS)).'),"")'
                );
                $sheet->setCellValue("CS{$row}", "=IFERROR(CM{$row}*100/10,\"\")");
                $sheet->setCellValue(
                    "CT{$row}",
                    '=IFERROR(AVERAGE('.implode(',', array_map(fn (string $column) => "{$column}{$blockStart}", self::AVG_MERMA_COLS)).'),"")'
                );
                $sheet->setCellValue("CU{$row}", "=CL{$row}");
            }
        }

        if ($actualSummaryRows === []) {
            return;
        }

        $cnRefs = implode(',', array_map(fn (int $row) => "CN{$row}", $actualSummaryRows));

        foreach ($actualSummaryRows as $row) {
            $sheet->setCellValue("CO{$row}", "=IFERROR(MIN({$cnRefs}),\"\")");
            $sheet->setCellValue("CP{$row}", "=IFERROR(CN{$row}-CO{$row},\"\")");
            $sheet->setCellValue("CQ{$row}", "=IFERROR(CO{$row}-CP{$row},\"\")");
            $sheet->setCellValue("CR{$row}", "=IFERROR(CQ{$row}*100/CO{$row},\"\")");
        }
    }

    private function writeSemanaContent(
        Worksheet $sheet,
        Worksheet $templateSheet,
        int $weekNum,
        int $sectionTopRow,
        array $atadorList,
        array $preservedState = []
    ): void {
        $sheet->setCellValue('B2', "SEMANA {$weekNum}");

        $desiredCapacity = max(self::MIN_VISIBLE_ATADORES, count($atadorList));
        $securityValues = $preservedState['security_values'] ?? [];
        $this->prepareSemanaSheetLayout($sheet, $templateSheet, $desiredCapacity);
        $layout = $this->buildSemanaLayout($desiredCapacity);
        $lastMetricColumnIndex = Coordinate::columnIndexFromString($layout['last_metric_column']);
        $actualCount = count($atadorList);
        $actualLastRow = $actualCount > 0 ? 3 + $actualCount : 8;
        $actualLastMetricColumn = Coordinate::stringFromColumnIndex(3 + max($actualCount, 1));

        $this->clearRange($sheet, 4, $layout['oee_row'], 2, max(13, $lastMetricColumnIndex));

        for ($index = 0; $index < $desiredCapacity; $index++) {
            $sheetRow = 4 + $index;
            $entry = $atadorList[$index] ?? null;

            if (! is_array($entry)) {
                continue;
            }

            $summaryRow = (int) $entry['summary_row'];
            $key = (string) $entry['key'];

            $sheet->setCellValue("B{$sheetRow}", "=DETALLE!CK{$summaryRow}");
            $sheet->setCellValue("C{$sheetRow}", "=DETALLE!CL{$summaryRow}");

            $mapping = [
                'D' => 'CM',
                'E' => 'CN',
                'F' => 'CO',
                'G' => 'CP',
                'H' => 'CQ',
                'I' => 'CR',
                'J' => 'CS',
                'K' => 'CT',
            ];

            foreach ($mapping as $targetCol => $detailCol) {
                $sheet->setCellValue("{$targetCol}{$sheetRow}", "=DETALLE!{$detailCol}{$summaryRow}");
            }

            $sheet->setCellValue("L{$sheetRow}", "=C{$sheetRow}");
            $sheet->setCellValue("M{$sheetRow}", $securityValues[$key] ?? 100);
        }

        $sheet->setCellValue(
            "K{$layout['merma_promedio_row']}",
            $actualCount > 0 ? "=IFERROR(AVERAGE(K4:K{$actualLastRow}),\"\")" : null
        );
        $sheet->setCellValue("C{$layout['promedio_general_row']}", 'PROMEDIO GRAL.');
        $sheet->setCellValue(
            "D{$layout['promedio_general_row']}",
            $actualCount > 0
                ? "=IFERROR(AVERAGE(D{$layout['oee_row']}:{$actualLastMetricColumn}{$layout['oee_row']}),\"\")"
                : null
        );

        $sheet->setCellValue("C{$layout['eficiencia_row']}", 'EFIC. ATADOR');
        $sheet->setCellValue("C{$layout['auxiliar_row']}", 'EFIC. X AUXILIAR');
        $sheet->setCellValue("C{$layout['calidad_row']}", 'CALIDAD/5S SEGURIDAD');
        $sheet->setCellValue("C{$layout['merma_row']}", 'MERMA (PROMEDIO)');
        $sheet->setCellValue("C{$layout['merma_porcentaje_row']}", '% X MERMA');
        $sheet->setCellValue("C{$layout['oee_row']}", 'OEE');

        $sheet->setCellValue(
            "B{$layout['merma_row']}",
            $actualCount > 0
                ? "=IFERROR(MIN(D{$layout['merma_row']}:{$actualLastMetricColumn}{$layout['merma_row']}),\"\")"
                : null
        );

        for ($index = 0; $index < $desiredCapacity; $index++) {
            $entry = $atadorList[$index] ?? null;
            if (! is_array($entry)) {
                continue;
            }

            $column = Coordinate::stringFromColumnIndex(4 + $index);
            $sourceRow = 4 + $index;

            $sheet->setCellValue("{$column}{$layout['header_row']}", "=C{$sourceRow}");
            $sheet->setCellValue("{$column}{$layout['eficiencia_row']}", "=I{$sourceRow}");
            $sheet->setCellValue("{$column}{$layout['calidad_row']}", "=IFERROR(AVERAGE(J{$sourceRow},M{$sourceRow}),\"\")");
            $sheet->setCellValue("{$column}{$layout['merma_row']}", "=K{$sourceRow}");
            $sheet->setCellValue(
                "{$column}{$layout['merma_porcentaje_row']}",
                "=IFERROR(\$B\${$layout['merma_row']}*100/{$column}{$layout['merma_row']},\"\")"
            );
            $sheet->setCellValue(
                "{$column}{$layout['oee_row']}",
                "=IFERROR({$column}{$layout['merma_porcentaje_row']}*{$column}{$layout['calidad_row']}*{$column}{$layout['eficiencia_row']}/1000000,\"\")"
            );
        }

        $this->restoreSemanaManualValues($sheet, $layout, $preservedState['manual_values'] ?? []);
        $this->writeSemanaUsterContent($sheet, $layout, $weekNum, $atadorList, $preservedState['uster_messages'] ?? []);
    }

    private function resolveSemanaTemplateSheet(Spreadsheet $spreadsheet, array $existingSheets): ?Worksheet
    {
        $preferred = $existingSheets[self::SEMANA_TEMPLATE_WEEK] ?? null;
        if ($preferred instanceof Worksheet && $this->isCanonicalSemanaTemplate($preferred)) {
            return $preferred;
        }

        foreach ($existingSheets as $sheet) {
            if ($sheet instanceof Worksheet && $this->isCanonicalSemanaTemplate($sheet)) {
                return $sheet;
            }
        }

        return null;
    }

    private function isCanonicalSemanaTemplate(Worksheet $sheet): bool
    {
        $layout = $this->describeSemanaSheet($sheet);

        return $layout['capacity'] === self::SEMANA_BASE_CAPACITY
            && $this->normalizeLabel($sheet->getCell('B3')->getValue()) === 'CLAVE ATADOR'
            && $this->normalizeLabel($sheet->getCell('C17')->getValue()) === 'EFIC. ATADOR'
            && $this->findSemanaManualStartRow($sheet) === self::SEMANA_MANUAL_TEMPLATE_START_ROW
            && $this->findSemanaUsterAnchor($sheet) !== null;
    }

    private function replaceSemanaSheetFromTemplate(
        Spreadsheet $spreadsheet,
        Worksheet $templateSource,
        string $newTitle,
        ?Worksheet $existingSheet
    ): Worksheet {
        $sheet = clone $templateSource;
        $sheet->setTitle($newTitle);

        if ($existingSheet instanceof Worksheet) {
            $targetIndex = $spreadsheet->getIndex($existingSheet);
            $spreadsheet->removeSheetByIndex($targetIndex);
            $spreadsheet->addSheet($sheet, $targetIndex);

            return $sheet;
        }

        $spreadsheet->addSheet($sheet);

        return $sheet;
    }

    private function captureSemanaPreservedState(?Worksheet $sheet): array
    {
        if (! $sheet instanceof Worksheet) {
            return [
                'security_values' => [],
                'manual_values' => [],
                'uster_messages' => [],
            ];
        }

        return [
            'security_values' => $this->collectSemanaSecurityValues($sheet),
            'manual_values' => $this->captureSemanaManualValues($sheet),
            'uster_messages' => $this->captureSemanaUsterMessages($sheet),
        ];
    }

    private function prepareSemanaSheetLayout(Worksheet $sheet, Worksheet $templateSheet, int $desiredCapacity): void
    {
        $layout = $this->buildSemanaLayout($desiredCapacity);
        $styleTemplate = $this->captureSemanaStyleTemplate($templateSheet);
        $canvasEndRow = max($sheet->getHighestRow(), $layout['uster_end_row'] + 2);
        $canvasEndColumn = max(
            Coordinate::columnIndexFromString($sheet->getHighestColumn()),
            $layout['actions_end_column'],
            $layout['uster_end_column'],
            Coordinate::columnIndexFromString($layout['last_metric_column']),
            20
        );

        $this->unmergeRowsInRange($sheet, self::SEMANA_TITLE_START_ROW, $canvasEndRow);
        $this->resetRangeToDefaultStyle(
            $sheet,
            self::SEMANA_TITLE_START_ROW,
            $canvasEndRow,
            self::SEMANA_TITLE_START_COLUMN,
            $canvasEndColumn
        );

        $this->copyRectRange(
            $sheet,
            $templateSheet,
            self::SEMANA_TITLE_START_ROW,
            self::SEMANA_TITLE_END_ROW,
            self::SEMANA_TITLE_START_COLUMN,
            self::SEMANA_TITLE_END_COLUMN,
            self::SEMANA_TITLE_START_ROW,
            self::SEMANA_TITLE_START_COLUMN
        );
        $this->copyRectRange(
            $sheet,
            $templateSheet,
            self::SEMANA_MERMA_PROMEDIO_TEMPLATE_ROW,
            self::SEMANA_MERMA_PROMEDIO_TEMPLATE_ROW,
            2,
            13,
            $layout['merma_promedio_row'],
            2
        );
        $this->copyRectRange(
            $sheet,
            $templateSheet,
            self::SEMANA_PROMEDIO_TEMPLATE_ROW,
            self::SEMANA_PROMEDIO_TEMPLATE_ROW,
            3,
            13,
            $layout['promedio_general_row'],
            3
        );
        $this->copyRectRange(
            $sheet,
            $templateSheet,
            self::SEMANA_METRIC_TEMPLATE_START_ROW,
            self::SEMANA_METRIC_TEMPLATE_END_ROW,
            self::SEMANA_METRIC_TEMPLATE_START_COLUMN,
            self::SEMANA_METRIC_TEMPLATE_END_COLUMN,
            $layout['header_row'],
            self::SEMANA_METRIC_TEMPLATE_START_COLUMN
        );
        $this->copyRectRange(
            $sheet,
            $templateSheet,
            self::SEMANA_MANUAL_TEMPLATE_START_ROW,
            self::SEMANA_MANUAL_TEMPLATE_END_ROW,
            self::SEMANA_MANUAL_START_COLUMN,
            self::SEMANA_MANUAL_END_COLUMN,
            $layout['manual_start_row'],
            self::SEMANA_MANUAL_START_COLUMN
        );
        $this->copyRectRange(
            $sheet,
            $templateSheet,
            self::SEMANA_ACTIONS_TEMPLATE_START_ROW,
            self::SEMANA_ACTIONS_TEMPLATE_END_ROW,
            self::SEMANA_ACTIONS_TEMPLATE_START_COLUMN,
            self::SEMANA_ACTIONS_TEMPLATE_END_COLUMN,
            self::SEMANA_ACTIONS_TEMPLATE_START_ROW,
            $layout['actions_start_column']
        );

        $this->copySemanaUsterTemplate($sheet, $templateSheet, $layout);
        $this->applySemanaDynamicStyles($sheet, $layout, $desiredCapacity, $styleTemplate);
        $this->clearRange(
            $sheet,
            self::SEMANA_LIST_START_ROW,
            $layout['oee_row'],
            self::SEMANA_LIST_START_COLUMN,
            max(self::SEMANA_LIST_END_COLUMN, Coordinate::columnIndexFromString($layout['last_metric_column']))
        );
        $this->clearRange(
            $sheet,
            $layout['manual_start_row'] + 1,
            $layout['manual_end_row'],
            self::SEMANA_MANUAL_START_COLUMN,
            self::SEMANA_MANUAL_END_COLUMN
        );
        $this->clearSemanaUsterDynamicArea($sheet, $layout);
    }

    private function buildSemanaLayout(int $capacity): array
    {
        $capacity = max(self::MIN_VISIBLE_ATADORES, $capacity);
        $extraColumns = $capacity - self::SEMANA_BASE_CAPACITY;
        $listEndRow = 3 + $capacity;
        $headerRow = $capacity + 11;
        $manualStartRow = $capacity + 21;
        $usterStartRow = $capacity + 39;
        $usterEndRow = $usterStartRow + $capacity + 3;

        return [
            'capacity' => $capacity,
            'insert_row' => $capacity + 4,
            'list_end_row' => $listEndRow,
            'merma_promedio_row' => $capacity + 6,
            'promedio_general_row' => $capacity + 8,
            'header_row' => $headerRow,
            'eficiencia_row' => $headerRow + 1,
            'auxiliar_row' => $headerRow + 2,
            'calidad_row' => $headerRow + 3,
            'merma_row' => $headerRow + 4,
            'merma_porcentaje_row' => $headerRow + 5,
            'oee_row' => $headerRow + 6,
            'last_metric_column' => Coordinate::stringFromColumnIndex(3 + $capacity),
            'manual_start_row' => $manualStartRow,
            'manual_end_row' => $manualStartRow + 12,
            'actions_start_column' => self::SEMANA_ACTIONS_TEMPLATE_START_COLUMN + $extraColumns,
            'actions_end_column' => self::SEMANA_ACTIONS_TEMPLATE_END_COLUMN + $extraColumns,
            'uster_start_row' => $usterStartRow,
            'uster_end_row' => $usterEndRow,
            'uster_start_column' => self::SEMANA_USTER_TEMPLATE_START_COLUMN + $extraColumns,
            'uster_end_column' => self::SEMANA_USTER_TEMPLATE_END_COLUMN + $extraColumns,
        ];
    }

    private function copySemanaUsterTemplate(Worksheet $sheet, Worksheet $templateSheet, array $layout): void
    {
        $usterStartColumn = (int) $layout['uster_start_column'];
        $usterStartRow = (int) $layout['uster_start_row'];
        $capacity = (int) $layout['capacity'];

        $this->copyRectRange(
            $sheet,
            $templateSheet,
            self::SEMANA_USTER_TEMPLATE_START_ROW,
            self::SEMANA_USTER_TEMPLATE_START_ROW + self::SEMANA_BASE_CAPACITY,
            self::SEMANA_USTER_TEMPLATE_START_COLUMN,
            self::SEMANA_USTER_TEMPLATE_END_COLUMN,
            $usterStartRow,
            $usterStartColumn
        );

        if ($capacity > self::SEMANA_BASE_CAPACITY) {
            for ($index = self::SEMANA_BASE_CAPACITY; $index < $capacity; $index++) {
                $this->copyRectRange(
                    $sheet,
                    $templateSheet,
                    self::SEMANA_USTER_TEMPLATE_START_ROW + self::SEMANA_BASE_CAPACITY - 1,
                    self::SEMANA_USTER_TEMPLATE_START_ROW + self::SEMANA_BASE_CAPACITY - 1,
                    self::SEMANA_USTER_TEMPLATE_START_COLUMN,
                    self::SEMANA_USTER_TEMPLATE_END_COLUMN,
                    $usterStartRow + 1 + $index,
                    $usterStartColumn
                );
            }
        }

        $separatorRow = $usterStartRow + 1 + $capacity;
        $noteRow = $separatorRow + 1;

        $this->copyRectRange(
            $sheet,
            $templateSheet,
            self::SEMANA_USTER_TEMPLATE_START_ROW + self::SEMANA_BASE_CAPACITY + 1,
            self::SEMANA_USTER_TEMPLATE_START_ROW + self::SEMANA_BASE_CAPACITY + 1,
            self::SEMANA_USTER_TEMPLATE_START_COLUMN,
            self::SEMANA_USTER_TEMPLATE_END_COLUMN,
            $separatorRow,
            $usterStartColumn
        );
        $this->copyRectRange(
            $sheet,
            $templateSheet,
            self::SEMANA_USTER_TEMPLATE_START_ROW + self::SEMANA_BASE_CAPACITY + 2,
            self::SEMANA_USTER_TEMPLATE_END_ROW,
            self::SEMANA_USTER_TEMPLATE_START_COLUMN,
            self::SEMANA_USTER_TEMPLATE_END_COLUMN,
            $noteRow,
            $usterStartColumn
        );
    }

    private function resetRangeToDefaultStyle(
        Worksheet $sheet,
        int $startRow,
        int $endRow,
        int $startColumnIndex,
        int $endColumnIndex
    ): void {
        $defaultStyle = $sheet->getStyle('A1')->exportArray();

        for ($row = $startRow; $row <= $endRow; $row++) {
            for ($columnIndex = $startColumnIndex; $columnIndex <= $endColumnIndex; $columnIndex++) {
                $coordinate = Coordinate::stringFromColumnIndex($columnIndex).$row;
                $sheet->setCellValueExplicit($coordinate, null, DataType::TYPE_NULL);
                $sheet->getStyle($coordinate)->applyFromArray($defaultStyle);
            }
        }
    }

    private function copyRectRange(
        Worksheet $target,
        Worksheet $source,
        int $sourceStartRow,
        int $sourceEndRow,
        int $sourceStartColumnIndex,
        int $sourceEndColumnIndex,
        int $targetStartRow,
        int $targetStartColumnIndex
    ): void {
        $rowOffset = $targetStartRow - $sourceStartRow;
        $columnOffset = $targetStartColumnIndex - $sourceStartColumnIndex;
        $referenceHelper = ReferenceHelper::getInstance();

        for ($columnIndex = $sourceStartColumnIndex; $columnIndex <= $sourceEndColumnIndex; $columnIndex++) {
            $sourceColumn = Coordinate::stringFromColumnIndex($columnIndex);
            $targetColumn = Coordinate::stringFromColumnIndex($columnIndex + $columnOffset);
            $sourceDimension = $source->getColumnDimension($sourceColumn);
            $targetDimension = $target->getColumnDimension($targetColumn);

            $targetDimension->setWidth($sourceDimension->getWidth());
            $targetDimension->setAutoSize($sourceDimension->getAutoSize());
            $targetDimension->setVisible($sourceDimension->getVisible());
            $targetDimension->setOutlineLevel($sourceDimension->getOutlineLevel());
            $targetDimension->setCollapsed($sourceDimension->getCollapsed());
        }

        for ($sourceRow = $sourceStartRow; $sourceRow <= $sourceEndRow; $sourceRow++) {
            $targetRow = $sourceRow + $rowOffset;
            $sourceDimension = $source->getRowDimension($sourceRow);
            $targetDimension = $target->getRowDimension($targetRow);

            $targetDimension->setRowHeight($sourceDimension->getRowHeight());
            $targetDimension->setVisible($sourceDimension->getVisible());
            $targetDimension->setOutlineLevel($sourceDimension->getOutlineLevel());
            $targetDimension->setCollapsed($sourceDimension->getCollapsed());

            for ($columnIndex = $sourceStartColumnIndex; $columnIndex <= $sourceEndColumnIndex; $columnIndex++) {
                $sourceCoordinate = Coordinate::stringFromColumnIndex($columnIndex).$sourceRow;
                $targetCoordinate = Coordinate::stringFromColumnIndex($columnIndex + $columnOffset).$targetRow;
                $sourceCell = $source->getCell($sourceCoordinate);

                $target->duplicateStyle($source->getStyle($sourceCoordinate), $targetCoordinate);

                $value = $sourceCell->getValue();
                $dataType = $sourceCell->getDataType();

                if ($dataType === DataType::TYPE_FORMULA && is_string($value)) {
                    $target->setCellValueExplicit(
                        $targetCoordinate,
                        $referenceHelper->updateFormulaReferences(
                            $value,
                            'A1',
                            $columnOffset,
                            $rowOffset,
                            $target->getTitle(),
                            true
                        ),
                        DataType::TYPE_FORMULA
                    );

                    continue;
                }

                if ($value === null || $value === '') {
                    $target->setCellValueExplicit($targetCoordinate, null, DataType::TYPE_NULL);

                    continue;
                }

                $target->setCellValueExplicit($targetCoordinate, $value, $dataType);
            }
        }

        foreach (array_keys($source->getMergeCells()) as $range) {
            if (preg_match('/^([A-Z]+)(\d+):([A-Z]+)(\d+)$/', $range, $matches) !== 1) {
                continue;
            }

            $startColumn = Coordinate::columnIndexFromString($matches[1]);
            $endColumn = Coordinate::columnIndexFromString($matches[3]);
            $rowStart = (int) $matches[2];
            $rowEnd = (int) $matches[4];

            if ($rowStart < $sourceStartRow
                || $rowEnd > $sourceEndRow
                || $startColumn < $sourceStartColumnIndex
                || $endColumn > $sourceEndColumnIndex) {
                continue;
            }

            $target->mergeCells(sprintf(
                '%s%d:%s%d',
                Coordinate::stringFromColumnIndex($startColumn + $columnOffset),
                $rowStart + $rowOffset,
                Coordinate::stringFromColumnIndex($endColumn + $columnOffset),
                $rowEnd + $rowOffset
            ));
        }
    }

    private function captureSemanaManualValues(Worksheet $sheet): array
    {
        $startRow = $this->findSemanaManualStartRow($sheet) + 1;
        $values = [];

        for ($row = 0; $row < 12; $row++) {
            for ($columnIndex = self::SEMANA_MANUAL_START_COLUMN; $columnIndex <= self::SEMANA_MANUAL_END_COLUMN; $columnIndex++) {
                $values[$row][$columnIndex] = $sheet->getCell(
                    Coordinate::stringFromColumnIndex($columnIndex).($startRow + $row)
                )->getValue();
            }
        }

        return $values;
    }

    private function restoreSemanaManualValues(Worksheet $sheet, array $layout, array $values): void
    {
        $startRow = (int) ($layout['manual_start_row'] ?? self::SEMANA_MANUAL_TEMPLATE_START_ROW) + 1;

        foreach ($values as $rowOffset => $columns) {
            foreach ($columns as $columnIndex => $value) {
                if ($value === null || $value === '') {
                    continue;
                }

                $sheet->setCellValue(
                    Coordinate::stringFromColumnIndex((int) $columnIndex).($startRow + (int) $rowOffset),
                    $value
                );
            }
        }
    }

    private function captureSemanaUsterMessages(Worksheet $sheet): array
    {
        $anchor = $this->findSemanaUsterAnchor($sheet);
        if ($anchor === null) {
            return [];
        }

        $messages = [];
        $nameColumn = Coordinate::stringFromColumnIndex($anchor['column'] + 3);
        $messageColumn = Coordinate::stringFromColumnIndex($anchor['column'] + 5);

        for ($row = $anchor['row'] + 1; $row <= $sheet->getHighestRow(); $row++) {
            $name = trim((string) ($sheet->getCell("{$nameColumn}{$row}")->getValue() ?? ''));
            $message = $sheet->getCell("{$messageColumn}{$row}")->getValue();
            $normalizedMessage = $this->normalizeLabel($message);

            if (str_starts_with($normalizedMessage, 'NOTA:')) {
                break;
            }

            if ($name === '' || $message === null || $message === '') {
                continue;
            }

            $messages[$this->normalizeLabel($name)] = $message;
        }

        return $messages;
    }

    private function writeSemanaUsterContent(
        Worksheet $sheet,
        array $layout,
        int $weekNum,
        array $atadorList,
        array $preservedMessages = []
    ): void {
        $startColumn = (int) ($layout['uster_start_column'] ?? self::SEMANA_USTER_TEMPLATE_START_COLUMN);
        $startRow = (int) ($layout['uster_start_row'] ?? self::SEMANA_USTER_TEMPLATE_START_ROW);
        $displayCount = max(self::MIN_VISIBLE_ATADORES, count($atadorList));
        $titleColumn = Coordinate::stringFromColumnIndex($startColumn + 5);
        $nameColumn = Coordinate::stringFromColumnIndex($startColumn + 3);
        $messageColumn = Coordinate::stringFromColumnIndex($startColumn + 5);

        $sheet->setCellValue("{$titleColumn}{$startRow}", "USO DE ATADORA USTER EN LA SEMANA {$weekNum}");

        for ($index = 0; $index < $displayCount; $index++) {
            $row = $startRow + 1 + $index;
            $entry = $atadorList[$index] ?? null;
            $name = '';

            if (is_array($entry)) {
                $name = trim((string) ($entry['name'] ?? $entry['key'] ?? ''));
            }

            $sheet->setCellValue("{$nameColumn}{$row}", $name !== '' ? $name : null);

            $message = $name !== ''
                ? ($preservedMessages[$this->normalizeLabel($name)] ?? "UTILIZO ATADORA USTER EN LA SEMANA {$weekNum}")
                : null;

            $sheet->setCellValue("{$messageColumn}{$row}", $message);
        }
    }

    private function clearSemanaUsterDynamicArea(Worksheet $sheet, array $layout): void
    {
        $startColumn = (int) ($layout['uster_start_column'] ?? self::SEMANA_USTER_TEMPLATE_START_COLUMN);
        $startRow = (int) ($layout['uster_start_row'] ?? self::SEMANA_USTER_TEMPLATE_START_ROW);
        $displayCount = max(self::MIN_VISIBLE_ATADORES, (int) ($layout['capacity'] ?? self::MIN_VISIBLE_ATADORES));

        $sheet->setCellValueExplicit(
            Coordinate::stringFromColumnIndex($startColumn + 5).$startRow,
            null,
            DataType::TYPE_NULL
        );

        for ($index = 0; $index < $displayCount; $index++) {
            $row = $startRow + 1 + $index;
            $sheet->setCellValueExplicit(
                Coordinate::stringFromColumnIndex($startColumn + 3).$row,
                null,
                DataType::TYPE_NULL
            );
            $sheet->setCellValueExplicit(
                Coordinate::stringFromColumnIndex($startColumn + 5).$row,
                null,
                DataType::TYPE_NULL
            );
        }
    }

    private function findSemanaUsterAnchor(Worksheet $sheet): ?array
    {
        for ($row = 1; $row <= $sheet->getHighestRow(); $row++) {
            for ($columnIndex = 1; $columnIndex <= Coordinate::columnIndexFromString($sheet->getHighestColumn()); $columnIndex++) {
                $value = $this->normalizeLabel($sheet->getCell(Coordinate::stringFromColumnIndex($columnIndex).$row)->getValue());
                if (str_starts_with($value, 'USO DE ATADORA USTER = PUNTOS')) {
                    return ['row' => $row, 'column' => $columnIndex];
                }
            }
        }

        return null;
    }

    private function collectSemanaSecurityValues(Worksheet $sheet): array
    {
        $layout = $this->describeSemanaSheet($sheet);
        $scores = [];

        for ($row = 4; $row <= $layout['list_end_row']; $row++) {
            $key = trim((string) ($sheet->getCell("B{$row}")->getValue() ?? ''));
            $value = $sheet->getCell("M{$row}")->getValue();

            if ($key === '' || $value === null || $value === '') {
                continue;
            }

            $scores[$key] = $value;
        }

        return $scores;
    }

    private function captureSemanaStyleTemplate(Worksheet $sheet): array
    {
        $layout = $this->describeSemanaSheet($sheet);

        return [
            'list_first' => $this->captureRowStyleSnapshot($sheet, 4, 2, 13),
            'list_middle' => $this->captureRowStyleSnapshot($sheet, min(5, $layout['list_end_row']), 2, 13),
            'list_last' => $this->captureRowStyleSnapshot($sheet, $layout['list_end_row'], 2, 13),
            'metric_column' => $this->captureColumnStyleSnapshot(
                $sheet,
                Coordinate::columnIndexFromString($layout['last_metric_column']),
                range($layout['header_row'], $layout['oee_row'])
            ),
        ];
    }

    private function applySemanaDynamicStyles(
        Worksheet $sheet,
        array $layout,
        int $desiredCapacity,
        array $styleTemplate
    ): void {
        if ($desiredCapacity < 1) {
            return;
        }

        $listEndRow = $layout['list_end_row'];

        for ($row = 4; $row <= $listEndRow; $row++) {
            $snapshot = $row === 4
                ? ($styleTemplate['list_first'] ?? null)
                : ($row === $listEndRow
                    ? ($styleTemplate['list_last'] ?? null)
                    : ($styleTemplate['list_middle'] ?? null));

            if ($snapshot !== null) {
                $this->applyRowStyleSnapshot($sheet, $snapshot, $row, 2, 13);
            }
        }

        $metricTemplate = $styleTemplate['metric_column'] ?? null;
        if (! is_array($metricTemplate)) {
            return;
        }

        $prototypeColumnIndex = (int) ($metricTemplate['column_index'] ?? 0);
        $targetLastColumnIndex = Coordinate::columnIndexFromString($layout['last_metric_column']);

        for ($columnIndex = $prototypeColumnIndex + 1; $columnIndex <= $targetLastColumnIndex; $columnIndex++) {
            $this->applyColumnStyleSnapshot($sheet, $metricTemplate, $columnIndex);
        }
    }

    private function resizeSemanaSheet(Worksheet $sheet, int $desiredCapacity): void
    {
        $layout = $this->describeSemanaSheet($sheet);
        $currentCapacity = $layout['capacity'];

        if ($currentCapacity === $desiredCapacity) {
            return;
        }

        $insertRow = $layout['insert_row'];
        $delta = $desiredCapacity - $currentCapacity;

        if ($delta > 0) {
            $sheet->insertNewRowBefore($insertRow, $delta);

            return;
        }

        $sheet->removeRow($insertRow + $delta, abs($delta));
    }

    private function captureConcentradoStyleTemplate(Worksheet $sheet): array
    {
        $rows = array_merge(range(4, 29), range(32, 37));

        return $this->captureColumnStyleSnapshot($sheet, 7, $rows);
    }

    private function applyConcentradoDynamicStyles(
        Worksheet $sheet,
        int $displayCount,
        array $styleTemplate
    ): void {
        $prototypeColumnIndex = (int) ($styleTemplate['column_index'] ?? 0);
        $targetLastColumnIndex = 2 + $displayCount;

        if ($prototypeColumnIndex < 1 || $targetLastColumnIndex <= $prototypeColumnIndex) {
            return;
        }

        for ($columnIndex = $prototypeColumnIndex + 1; $columnIndex <= $targetLastColumnIndex; $columnIndex++) {
            $this->applyColumnStyleSnapshot($sheet, $styleTemplate, $columnIndex);
        }
    }

    private function describeSemanaSheet(Worksheet $sheet): array
    {
        $eficienciaRow = 17;

        for ($row = 1; $row <= $sheet->getHighestRow(); $row++) {
            if ($this->normalizeLabel($sheet->getCell("C{$row}")->getValue()) === 'EFIC. ATADOR') {
                $eficienciaRow = $row;
                break;
            }
        }

        $capacity = max(self::MIN_VISIBLE_ATADORES, $eficienciaRow - 12);
        $layout = $this->buildSemanaLayout($capacity);
        $listEndRow = $layout['list_end_row'];

        $entries = [];
        for ($row = 4; $row <= $listEndRow; $row++) {
            $code = trim((string) ($sheet->getCell("B{$row}")->getValue() ?? ''));
            $name = trim((string) ($sheet->getCell("C{$row}")->getValue() ?? ''));

            if ($code === '') {
                continue;
            }

            $entries[] = [
                'code' => $code,
                'name' => $name,
                'row' => $row,
                'metric_column' => Coordinate::stringFromColumnIndex(4 + ($row - 4)),
            ];
        }

        $layout['header_row'] = $eficienciaRow - 1;
        $layout['eficiencia_row'] = $eficienciaRow;
        $layout['auxiliar_row'] = $eficienciaRow + 1;
        $layout['calidad_row'] = $eficienciaRow + 2;
        $layout['merma_row'] = $eficienciaRow + 3;
        $layout['merma_porcentaje_row'] = $eficienciaRow + 4;
        $layout['oee_row'] = $eficienciaRow + 5;
        $layout['entries'] = $entries;

        return $layout;
    }

    private function findSemanaManualStartRow(Worksheet $sheet): int
    {
        for ($row = 1; $row <= $sheet->getHighestRow(); $row++) {
            $value = $this->normalizeLabel($sheet->getCell("D{$row}")->getValue());
            if (str_starts_with($value, 'NOMBRE REPORTES')) {
                return $row;
            }
        }

        return 26;
    }

    private function captureRowStyleSnapshot(
        Worksheet $sheet,
        int $row,
        int $startColumnIndex,
        int $endColumnIndex
    ): array {
        $styles = [];

        for ($columnIndex = $startColumnIndex; $columnIndex <= $endColumnIndex; $columnIndex++) {
            $coordinate = Coordinate::stringFromColumnIndex($columnIndex).$row;
            $styles[$columnIndex] = $sheet->getStyle($coordinate)->exportArray();
        }

        return [
            'row_height' => $sheet->getRowDimension($row)->getRowHeight(),
            'styles' => $styles,
        ];
    }

    private function applyRowStyleSnapshot(
        Worksheet $sheet,
        array $snapshot,
        int $targetRow,
        int $startColumnIndex,
        int $endColumnIndex
    ): void {
        $sheet->getRowDimension($targetRow)->setRowHeight((float) ($snapshot['row_height'] ?? -1));

        for ($columnIndex = $startColumnIndex; $columnIndex <= $endColumnIndex; $columnIndex++) {
            $style = $snapshot['styles'][$columnIndex] ?? null;
            if (! is_array($style)) {
                continue;
            }

            $sheet->getStyle(Coordinate::stringFromColumnIndex($columnIndex).$targetRow)->applyFromArray($style);
        }
    }

    private function captureColumnStyleSnapshot(Worksheet $sheet, int $columnIndex, array $rows): array
    {
        $column = Coordinate::stringFromColumnIndex($columnIndex);
        $styles = [];

        foreach ($rows as $row) {
            $styles[$row] = $sheet->getStyle("{$column}{$row}")->exportArray();
        }

        $dimension = $sheet->getColumnDimension($column);

        return [
            'column_index' => $columnIndex,
            'width' => $dimension->getWidth(),
            'auto_size' => $dimension->getAutoSize(),
            'visible' => $dimension->getVisible(),
            'outline_level' => $dimension->getOutlineLevel(),
            'collapsed' => $dimension->getCollapsed(),
            'styles' => $styles,
        ];
    }

    private function applyColumnStyleSnapshot(Worksheet $sheet, array $snapshot, int $targetColumnIndex): void
    {
        $column = Coordinate::stringFromColumnIndex($targetColumnIndex);
        $dimension = $sheet->getColumnDimension($column);

        $dimension->setWidth((float) ($snapshot['width'] ?? -1));
        $dimension->setAutoSize((bool) ($snapshot['auto_size'] ?? false));
        $dimension->setVisible((bool) ($snapshot['visible'] ?? true));
        $dimension->setOutlineLevel((int) ($snapshot['outline_level'] ?? 0));
        $dimension->setCollapsed((bool) ($snapshot['collapsed'] ?? false));

        foreach (($snapshot['styles'] ?? []) as $row => $style) {
            if (! is_array($style)) {
                continue;
            }

            $sheet->getStyle("{$column}{$row}")->applyFromArray($style);
        }
    }

    private function cloneLastSemanaSheet(Spreadsheet $spreadsheet, string $newTitle): ?Worksheet
    {
        $existing = $this->getExistingSemanaSheets($spreadsheet);
        $lastSemana = end($existing);

        if (! $lastSemana instanceof Worksheet) {
            return null;
        }

        $newSheet = clone $lastSemana;
        $newSheet->setTitle($newTitle);
        $spreadsheet->addSheet($newSheet);

        return $newSheet;
    }

    private function extractAtadorList(array $layout, array $nameMap = []): array
    {
        $entries = [];
        $summaryRow = $layout['detail_start_row'];

        foreach ($layout['turns'] as $turn) {
            foreach ($turn['blocks'] as $block) {
                $key = trim((string) ($block['atador_key'] ?? ''));
                if ($key !== '') {
                    $entries[] = [
                        'key' => $key,
                        'name' => $nameMap[$key] ?? $key,
                        'summary_row' => $summaryRow,
                        'block_start' => $block['row_start'],
                    ];
                }

                $summaryRow++;
            }
        }

        return $entries;
    }

    private function getWeeksInRange(CarbonImmutable $weekStart, CarbonImmutable $weekEnd): array
    {
        $weeks = [];
        $current = $weekStart->startOfWeek(Carbon::MONDAY);
        $end = $weekEnd->startOfWeek(Carbon::MONDAY);

        while (! $current->greaterThan($end)) {
            $weeks[] = $current;
            $current = $current->addWeek();
        }

        return $weeks;
    }

    private function loadAtadorNamesForWeek(CarbonImmutable $week): array
    {
        $weekEnd = $week->addDays(6);

        $records = AtaMontadoTelasModel::query()
            ->where('Estatus', 'Autorizado')
            ->whereNotNull('FechaArranque')
            ->whereDate('FechaArranque', '>=', $week->toDateString())
            ->whereDate('FechaArranque', '<=', $weekEnd->toDateString())
            ->orderBy('Turno')
            ->orderBy('CveTejedor')
            ->orderBy('NomTejedor')
            ->orderBy('Id')
            ->get(['CveTejedor', 'NomTejedor']);

        $map = [];

        foreach ($records as $record) {
            $key = trim((string) ($record->CveTejedor ?? ''));
            $name = trim((string) ($record->NomTejedor ?? ''));

            if ($key === '') {
                $key = $name;
            }

            if ($key === '' || isset($map[$key])) {
                continue;
            }

            $map[$key] = $name !== '' ? $name : $key;
        }

        return $map;
    }

    private function clearRange(
        Worksheet $sheet,
        int $startRow,
        int $endRow,
        int $startColumnIndex = 1,
        int $endColumnIndex = self::DETAIL_MAX_COLUMN_INDEX
    ): void {
        for ($row = $startRow; $row <= $endRow; $row++) {
            for ($columnIndex = $startColumnIndex; $columnIndex <= $endColumnIndex; $columnIndex++) {
                $sheet->setCellValueExplicit(
                    Coordinate::stringFromColumnIndex($columnIndex).$row,
                    null,
                    DataType::TYPE_NULL
                );
            }
        }
    }

    private function setFormulaIfBrokenOrBlank(Worksheet $sheet, string $coordinate, string $formulaOrValue): void
    {
        $value = $sheet->getCell($coordinate)->getValue();

        if ($value !== null && $value !== '' && ! $this->hasBrokenReference($value)) {
            return;
        }

        $sheet->setCellValue($coordinate, $formulaOrValue);
    }

    private function hasBrokenReference(mixed $value): bool
    {
        return is_string($value) && str_contains($value, '#REF!');
    }

    private function quoteSheetTitle(string $title): string
    {
        return "'".str_replace("'", "''", $title)."'";
    }
}
