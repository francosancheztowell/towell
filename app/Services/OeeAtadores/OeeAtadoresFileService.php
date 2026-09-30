<?php

namespace App\Services\OeeAtadores;

use App\Exports\Reporte00EAtadoresExport;
use App\Models\Atadores\AtaMontadoTelasModel;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\ReferenceHelper;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class OeeAtadoresFileService
{
    private const AVG_TIME_COLS = ['G', 'S', 'AE', 'AQ', 'BC', 'BO', 'CA'];

    private const AVG_CALIF_COLS = ['L', 'X', 'AJ', 'AV', 'BH', 'BT', 'CF'];

    private const AVG_MERMA_COLS = ['N', 'Z', 'AL', 'AX', 'BJ', 'BV', 'CH'];

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

    private static ?array $columnLabels = null;

    public function __construct(private string $filePath) {}

    public function verificarSemanasConDatos(CarbonImmutable $weekStart, CarbonImmutable $weekEnd): array
    {
        $this->assertWorkbookExists();

        $weeks = $this->getWeeksInRange($weekStart, $weekEnd);
        $year = $this->assertSingleIsoYear($weeks);
        $workbook = $this->loadWorkbook(true, ['DETALLE']);
        $detalle = $workbook->getSheetByName('DETALLE');

        if (! $detalle) {
            throw new OeeAtadoresReglaException('No se encontró la hoja DETALLE en el archivo OEE.');
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
        ini_set('memory_limit', '2048M');
        set_time_limit(0);

        $exportStartedAt = microtime(true);
        $this->assertWorkbookExists();

        // Guardar la ruta original para restaurar al final
        $originalFilePath = $this->filePath;
        $usandoTempLocal = false;

        // Optimización: copiar archivo a temp local para evitar lentitud de red
        $localTempFile = $this->copiarArchivoATemporalLocal($this->filePath);
        if ($localTempFile !== $originalFilePath) {
            $usandoTempLocal = true;
        }

        $weeks = $this->getWeeksInRange($weekStart, $weekEnd);
        $requestedWeekNumbers = array_map(fn (CarbonImmutable $week) => $week->isoWeek(), $weeks);
        $year = $this->assertSingleIsoYear($weeks);
        $logContext = [
            'file_path' => $this->filePath,
            'from' => $weekStart->toDateString(),
            'to' => $weekEnd->toDateString(),
            'weeks' => $requestedWeekNumbers,
            'week_count' => count($requestedWeekNumbers),
            'iso_year' => $year,
        ];

        Log::info('OEE Atadores export started', $logContext);

        $stageStartedAt = microtime(true);
        // CRÍTICO: PhpSpreadsheet NO carga charts por defecto. Sin setIncludeCharts(true)
        // todas las gráficas (SEMANA, CONCENTRADO, ATADORES) se PIERDEN al guardar.
        $reader = IOFactory::createReaderForFile($this->filePath);
        // NOTA: setIncludeCharts(true) corrompe el archivo al guardar (bug
        // conocido de PhpSpreadsheet cuando modificamos hojas que referencian
        // charts). Lo dejamos desactivado — las gráficas se perderán, pero el
        // archivo abre correctamente.
        $spreadsheet = $reader->load($this->filePath);
        $detalle = $spreadsheet->getSheetByName('DETALLE');

        if (! $detalle) {
            throw new OeeAtadoresReglaException('No se encontró la hoja DETALLE en el archivo OEE.');
        }

        $dataWorkbook = null;
        $detalleData = null;

        $parsed = $this->parseDetalleSections($detalle);
        $this->logExportStage('load_main_workbook', $stageStartedAt, $logContext);

        if ($this->sectionsRequireDetalleDataWorkbook($detalle, $parsed['sections'])) {
            $stageStartedAt = microtime(true);
            $dataWorkbook = $this->loadWorkbook(true, ['DETALLE']);
            $detalleData = $dataWorkbook->getSheetByName('DETALLE');

            if (! $detalleData) {
                throw new OeeAtadoresReglaException('No se encontro la hoja DETALLE en el archivo OEE.');
            }

            $parsed = $this->parseDetalleSections($detalle, $detalleData);
            $this->logExportStage('load_detalle_data_only', $stageStartedAt, $logContext);
        }

        $stageStartedAt = microtime(true);
        $this->normalizeDetalleFooterWeeks($detalle, $parsed['sections']);
        $parsed = $this->parseDetalleSections($detalle);

        $workbookYear = $this->resolveWorkbookYear($spreadsheet);
        if ($workbookYear !== null && $workbookYear !== $year) {
            throw new OeeAtadoresReglaException("El archivo OEE corresponde al año {$workbookYear}; no se pueden mezclar semanas del año ISO {$year}.");
        }

        $recordsByWeek = $this->preloadRecordsByWeek($weeks);
        $this->logExportStage('prepare_detalle', $stageStartedAt, $logContext + [
            'detected_sections' => count($parsed['sections'] ?? []),
        ]);

        $detalleStageStartedAt = microtime(true);
        foreach ($weeks as $week) {
            $weekStageStartedAt = microtime(true);
            $weekNum = $week->isoWeek();
            $parsed = $this->parseDetalleSections($detalle);
            $existing = $parsed['map'][$weekNum] ?? null;
            // Si la sección ya existe en el destino, usarla como su propio prototipo
            // para preservar colores/estilos actuales (especialmente cuando se expande
            // por detectar más atadores en un turno). Solo buscamos otro prototipo si
            // la semana es nueva.
            $prototypeSection = $existing ?? $this->resolveDetallePrototypeSection(
                $parsed['sections'],
                $weekNum,
                $requestedWeekNumbers
            );
            $weekRecords = $recordsByWeek->get($week->toDateString(), collect());

            if ($existing !== null && $prototypeSection !== null) {
                $action = 'rebuild_from_prototype';
                $this->rebuildDetalleSectionFromPrototype($detalle, $existing, $prototypeSection, $week, $weekRecords);
            } elseif ($existing !== null) {
                $action = 'replace_existing';
                $generated = $this->generateWeeklySection($week, $detalle, $prototypeSection, $weekRecords);
                try {
                    $this->replaceDetalleSection($detalle, $existing, $generated);
                } finally {
                    $this->disposeGeneratedTempSheet($generated);
                }
                unset($generated);
            } else {
                $action = 'insert_new';
                $generated = $this->generateWeeklySection($week, $detalle, $prototypeSection, $weekRecords);
                try {
                    $insertTop = $this->resolveInsertTop($parsed['sections'], $weekNum);
                    $this->insertDetalleSection($detalle, $insertTop, $generated);
                } finally {
                    $this->disposeGeneratedTempSheet($generated);
                }
                unset($generated);
            }

            $this->logExportStage('detalle_week', $weekStageStartedAt, $logContext + [
                'week' => $weekNum,
                'date' => $week->toDateString(),
                'action' => $action,
                'records' => $weekRecords->count(),
                'had_existing_section' => $existing !== null,
                'used_prototype' => $prototypeSection !== null,
            ]);
        }

        $this->logExportStage('detalle_all_weeks', $detalleStageStartedAt, $logContext);

        $stageStartedAt = microtime(true);
        $parsed = $this->parseDetalleSections($detalle);
        $this->normalizeDetalleFooterWeeks($detalle, $parsed['sections']);
        $this->normalizeDetalleVisualWeeks($detalle, $parsed['sections']);
        $parsed = $this->parseDetalleSections($detalle);
        $this->logExportStage('normalize_detalle', $stageStartedAt, $logContext + [
            'final_sections' => count($parsed['sections'] ?? []),
        ]);

        // SOLO actualizamos DETALLE. Las hojas SEMANA, CONCENTRADO, TOTAL ATADOS,
        // grafica y ATADORES YYYY NO se tocan para preservar sus colores/formato
        // intactos. El usuario las actualizará manualmente o desde Excel.
        Log::info('OEE Atadores: skipping SEMANA/CONCENTRADO/TOTAL/grafica/ANNUAL rebuilds (detalle-only mode)', $logContext);

        $stageStartedAt = microtime(true);
        Log::info('OEE Atadores stage START', $logContext + ['stage' => 'save_temp_file', 'mem_mb' => (int) round(memory_get_usage(true) / 1048576)]);
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

        $this->logExportStage('save_temp_file', $stageStartedAt, $logContext + [
            'tmp_file' => $tmpFile,
        ]);

        unset($writer, $spreadsheet, $detalle, $dataWorkbook, $detalleData);
        gc_collect_cycles();

        $stageStartedAt = microtime(true);
        if (! @rename($tmpFile, $this->filePath)) {
            $copied = @copy($tmpFile, $this->filePath);
            @unlink($tmpFile);

            if (! $copied) {
                $err = error_get_last()['message'] ?? 'Error desconocido al reemplazar el archivo';
                throw new RuntimeException("No se pudo guardar el archivo OEE ({$this->filePath}): {$err}");
            }
        }

        $this->logExportStage('replace_destination_file', $stageStartedAt, $logContext);

        // Si usamos archivo temp local, restaurar al origen original
        if ($usandoTempLocal) {
            $this->restaurarArchivoOriginal($this->filePath, $originalFilePath);
            $this->filePath = $originalFilePath;
        }

        $this->logExportStage('completed', $exportStartedAt, $logContext);

        return $this->filePath;
    }

    private function logExportStage(string $stage, float $startedAt, array $context = []): void
    {
        Log::info('OEE Atadores export stage', $context + [
            'stage' => $stage,
            'elapsed_ms' => $this->elapsedMilliseconds($startedAt),
        ]);
    }

    private function elapsedMilliseconds(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
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

        if ($readDataOnly && method_exists($reader, 'setReadEmptyCells')) {
            $reader->setReadEmptyCells(false);
        }

        if (method_exists($reader, 'setIncludeCharts')) {
            $reader->setIncludeCharts(false);
        }

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
            throw new OeeAtadoresReglaException('El rango debe pertenecer al mismo año ISO para actualizar el archivo anual OEE.');
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

    private function sectionsRequireDetalleDataWorkbook(Worksheet $detalle, array $sections): bool
    {
        foreach ($sections as $section) {
            if (($section['week'] ?? null) !== null) {
                continue;
            }

            $footerRow = (int) ($section['footer'] ?? 0);
            if ($footerRow < 1) {
                continue;
            }

            $footerValue = $detalle->getCell(self::DETAIL_FOOTER_WEEK_COLUMN.$footerRow)->getValue();
            if (is_numeric($footerValue)) {
                continue;
            }

            $normalized = trim((string) ($footerValue ?? ''));
            if ($normalized !== '' && str_starts_with($normalized, '=')) {
                return true;
            }
        }

        return false;
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
        ?array $prototypeSection = null,
        ?Collection $preloadedRecords = null
    ): array {
        // NOTA: anteriormente intentamos crear la hoja prototipo dentro del mismo
        // workbook de $detalle para preservar theme colors al duplicar estilos,
        // pero eso causaba que insertNewRowBefore() propagara actualizaciones de
        // fórmulas a TODAS las hojas del libro (32+ hojas), colapsando CPU y
        // memoria (OOM a 512MB, 10x más lento). Volvemos al enfoque de workbook
        // aislado — el tema se perderá en algunos casos, pero el proceso funciona.
        $book = $this->buildDetailPrototypeBook($detallePrototype, $prototypeSection)
            ?? $this->loadSectionTemplateBook();
        $sheet = $book->getSheet(0);
        $export = new Reporte00EAtadoresExport($week, $preloadedRecords);
        $footerRow = $export->renderIntoSheet($sheet, 1, true);
        $layout = $export->getLayout(1, false);
        $nameMap = $preloadedRecords !== null
            ? $this->buildAtadorNameMapFromRecords($preloadedRecords)
            : $this->loadAtadorNamesForWeek($week);

        $this->writeCkCuFormulas($sheet, $layout, 1, $nameMap);
        $sheet->setCellValue(self::DETAIL_FOOTER_WEEK_COLUMN.$footerRow, $week->isoWeek());

        return [
            'spreadsheet' => $book,
            'sheet' => $sheet,
            'layout' => $layout,
            'row_count' => $footerRow,
            'atadores' => $this->extractAtadorList($layout, $nameMap),
            'temp_sheet_in_parent' => null,
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
        CarbonImmutable $week,
        ?Collection $preloadedRecords = null
    ): void {
        // Snapshot de la sección destino clonándola a un libro aislado ANTES
        // de regenerar. Después del rebuild reaplicamos estilos con
        // duplicateStyle() (entre worksheets, seguro) desde el snapshot.
        $snapshotBook = new Spreadsheet;
        $snapshotSheet = $snapshotBook->getActiveSheet();
        $snapshotSheet->setTitle('SNAPSHOT');
        $this->copySectionRange($snapshotSheet, $detalle, (int) $section['top'], (int) $section['rows'], 1);
        $originalRows = (int) $section['rows'];

        $generated = $this->generateWeeklySection($week, $detalle, $prototypeSection, $preloadedRecords);
        try {
            $this->replaceDetalleSection($detalle, $section, $generated);
        } finally {
            $this->disposeGeneratedTempSheet($generated);
        }

        // Reaplicar estilos desde snapshot a la sección recién escrita
        $desiredRows = (int) $generated['row_count'];
        $targetTop = (int) $section['top'];
        $columnLabels = $this->getColumnLabels();
        $detailTemplateSrcRow = max(1, $originalRows - 1); // penúltima fila del snapshot
        $footerSrcRow = $originalRows;

        for ($r = 0; $r < $desiredRows; $r++) {
            if ($r < $originalRows - 1) {
                $srcRow = $r + 1;
            } elseif ($r === $desiredRows - 1) {
                $srcRow = $footerSrcRow;
            } else {
                $srcRow = $detailTemplateSrcRow;
            }

            for ($c = 1; $c <= self::DETAIL_MAX_COLUMN_INDEX; $c++) {
                $col = $columnLabels[$c];
                $srcCoord = $col.$srcRow;
                if (! $snapshotSheet->cellExists($srcCoord)) {
                    continue;
                }
                $fillType = $snapshotSheet->getStyle($srcCoord)->getFill()->getFillType();
                if ($fillType === null || $fillType === \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_NONE) {
                    continue;
                }
                $tgtCoord = $col.($targetTop + $r);
                $detalle->duplicateStyle($snapshotSheet->getStyle($srcCoord), $tgtCoord);
            }
        }

        $snapshotBook->disconnectWorksheets();
        unset($snapshotBook, $snapshotSheet);
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

    /**
     * Elimina la hoja prototipo temporal del workbook padre si fue creada
     * por generateWeeklySection(). Debe llamarse después de consumir el
     * resultado para no dejar hojas huérfanas en el Excel final.
     */
    private function disposeGeneratedTempSheet(array $generated): void
    {
        $tempSheet = $generated['temp_sheet_in_parent'] ?? null;
        if (! $tempSheet instanceof Worksheet) {
            return;
        }

        $parent = $tempSheet->getParent();
        if ($parent === null) {
            return;
        }

        $index = $parent->getIndex($tempSheet);
        if ($index !== false && $index >= 0) {
            $parent->removeSheetByIndex($index);
        }
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
        $columnLabels = $this->getColumnLabels();

        for ($sourceRow = $sourceTop; $sourceRow <= $sourceBottom; $sourceRow++) {
            $targetRow = $sourceRow + $rowOffset;
            $sourceDimension = $source->getRowDimension($sourceRow);
            $targetDimension = $target->getRowDimension($targetRow);

            $targetDimension->setRowHeight($sourceDimension->getRowHeight());
            $targetDimension->setVisible($sourceDimension->getVisible());
            $targetDimension->setOutlineLevel($sourceDimension->getOutlineLevel());
            $targetDimension->setCollapsed($sourceDimension->getCollapsed());

            for ($columnIndex = 1; $columnIndex <= self::DETAIL_MAX_COLUMN_INDEX; $columnIndex++) {
                $column = $columnLabels[$columnIndex];
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

    private function getColumnLabels(): array
    {
        if (self::$columnLabels !== null) {
            return self::$columnLabels;
        }

        $labels = [];
        for ($column = 1; $column <= self::DETAIL_MAX_COLUMN_INDEX; $column++) {
            $labels[$column] = Coordinate::stringFromColumnIndex($column);
        }

        return self::$columnLabels = $labels;
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

    private function preloadRecordsByWeek(array $weeks): Collection
    {
        if ($weeks === []) {
            return collect();
        }

        $sortedWeeks = $weeks;
        usort($sortedWeeks, fn (CarbonImmutable $a, CarbonImmutable $b) => $a->timestamp <=> $b->timestamp);
        $rangeStart = reset($sortedWeeks)->toDateString();
        $rangeEnd = end($sortedWeeks)->addDays(6)->toDateString();

        $records = AtaMontadoTelasModel::query()
            ->where('Estatus', 'Autorizado')
            ->whereNotNull('FechaArranque')
            ->whereBetween('FechaArranque', [$rangeStart, $rangeEnd])
            ->orderBy('FechaArranque')
            ->orderBy('Turno')
            ->orderBy('CveTejedor')
            ->orderBy('NomTejedor')
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

        return $records->groupBy(function ($record): string {
            $value = is_array($record) ? ($record['FechaArranque'] ?? null) : ($record->FechaArranque ?? null);
            try {
                return CarbonImmutable::parse((string) $value)
                    ->startOfDay()
                    ->startOfWeek(CarbonInterface::MONDAY)
                    ->toDateString();
            } catch (\Throwable $e) {
                return '__invalid__';
            }
        });
    }

    private function buildAtadorNameMapFromRecords(Collection $records): array
    {
        $map = [];

        foreach ($records as $record) {
            $key = trim((string) (is_array($record) ? ($record['CveTejedor'] ?? '') : ($record->CveTejedor ?? '')));
            $name = trim((string) (is_array($record) ? ($record['NomTejedor'] ?? '') : ($record->NomTejedor ?? '')));

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

    private function copiarArchivoATemporalLocal(string $originalPath): string
    {
        $stageStartedAt = microtime(true);
        $tempDir = sys_get_temp_dir();
        $tempFile = $tempDir.DIRECTORY_SEPARATOR.'oee_atadores_'.uniqid('', true).'.xlsx';

        if (! copy($originalPath, $tempFile)) {
            // Si falla la copia, usar el original (no optimizar)
            Log::warning('No se pudo copiar archivo OEE a temp local, usando original', [
                'original' => $originalPath,
            ]);

            return $originalPath;
        }

        $this->logExportStage('copy_to_local_temp', $stageStartedAt, [
            'original' => $originalPath,
            'temp' => $tempFile,
            'original_size_bytes' => @filesize($originalPath),
        ]);

        // Actualizar la ruta para usar el archivo local
        $this->filePath = $tempFile;

        return $tempFile;
    }

    private function restaurarArchivoOriginal(string $tempFile, string $originalPath): void
    {
        $stageStartedAt = microtime(true);

        // Copiar el archivo temp de vuelta al origen original
        if (! copy($tempFile, $originalPath)) {
            Log::error('No se pudo copiar archivo OEE de vuelta a la ruta original', [
                'temp' => $tempFile,
                'original' => $originalPath,
            ]);
            // El archivo temporal se queda para recuperación manual
            // No eliminar el temp file en caso de falla
            $this->logExportStage('restore_failed_temp_retained', $stageStartedAt, [
                'temp' => $tempFile,
                'original' => $originalPath,
            ]);

            return;
        }

        // Eliminar el archivo temporal solo si la copia fue exitosa
        @unlink($tempFile);

        $this->logExportStage('restore_from_local_temp', $stageStartedAt, [
            'temp' => $tempFile,
            'original' => $originalPath,
        ]);
    }
}
