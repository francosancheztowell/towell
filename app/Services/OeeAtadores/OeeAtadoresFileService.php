<?php

namespace App\Services\OeeAtadores;

use App\Exports\Reporte00EAtadoresExport;
use Carbon\CarbonImmutable;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class OeeAtadoresFileService
{
    // Columns for 7 days (mon-sun): avg_time, avg_calif, avg_merma
    private const AVG_TIME_COLS  = ['G', 'S', 'AE', 'AQ', 'BC', 'BO', 'CA'];
    private const AVG_CALIF_COLS = ['L', 'X', 'AJ', 'AV', 'BH', 'BT', 'CF'];
    private const AVG_MERMA_COLS = ['N', 'Z', 'AL', 'AX', 'BJ', 'BV', 'CH'];

    private const MAX_DATA_COL_INDEX = 88; // CJ

    private const MESES = [
        1 => 'ENERO', 2 => 'FEBRERO', 3 => 'MARZO', 4 => 'ABRIL',
        5 => 'MAYO', 6 => 'JUNIO', 7 => 'JULIO', 8 => 'AGOSTO',
        9 => 'SEPTIEMBRE', 10 => 'OCTUBRE', 11 => 'NOVIEMBRE', 12 => 'DICIEMBRE',
    ];

    public function __construct(private readonly string $filePath) {}

    /**
     * Verifica qué semanas del rango ya tienen datos en el archivo OEE.
     * Retorna array con 'semanas_rango' y 'semanas_con_datos'.
     */
    public function verificarSemanasConDatos(CarbonImmutable $weekStart, CarbonImmutable $weekEnd): array
    {
        if (! is_file($this->filePath)) {
            throw new RuntimeException("El archivo OEE no existe: {$this->filePath}");
        }

        // Carga rápida: solo datos (sin estilos), solo hoja DETALLE
        $reader = IOFactory::createReaderForFile($this->filePath);
        $reader->setReadDataOnly(true);
        if (method_exists($reader, 'setLoadSheetsOnly')) {
            $reader->setLoadSheetsOnly(['DETALLE']);
        }
        $spreadsheet = $reader->load($this->filePath);
        $detalle = $spreadsheet->getSheetByName('DETALLE');

        if (! $detalle) {
            throw new RuntimeException('No se encontró la hoja DETALLE en el archivo OEE.');
        }

        $sectionMap = $this->buildSectionMap($detalle);
        $weeks = $this->getWeeksInRange($weekStart, $weekEnd);

        $semanasRango = array_map(fn($w) => $w->isoWeek(), $weeks);
        $semanasConDatos = [];

        foreach ($weeks as $week) {
            $weekNum = $week->isoWeek();
            if (! isset($sectionMap[$weekNum])) {
                continue;
            }
            // "Tiene datos" si la primera celda de clave de atador (C de fila 4 relativa)
            // es no vacía y no es una fórmula del sistema de cadena (=C...+1)
            $firstAtadorRow = $sectionMap[$weekNum]['top'] + 3;
            $cVal = $detalle->getCell("C{$firstAtadorRow}")->getValue();
            if ($cVal !== null && $cVal !== '' && ! (is_string($cVal) && str_starts_with($cVal, '='))) {
                $semanasConDatos[] = $weekNum;
            }
        }

        return [
            'semanas_rango' => $semanasRango,
            'semanas_con_datos' => $semanasConDatos,
        ];
    }

    /**
     * Actualiza el archivo OEE con los datos de las semanas en el rango.
     * Retorna la ruta del archivo guardado.
     */
    public function actualizarArchivo(CarbonImmutable $weekStart, CarbonImmutable $weekEnd): string
    {
        // El procesamiento del xlsx puede tardar varios minutos; sin límite de tiempo
        ini_set('max_execution_time', '0');
        set_time_limit(0);

        if (! is_file($this->filePath)) {
            throw new RuntimeException("El archivo OEE no existe: {$this->filePath}");
        }

        $spreadsheet = IOFactory::load($this->filePath);
        $detalle = $spreadsheet->getSheetByName('DETALLE');

        if (! $detalle) {
            throw new RuntimeException('No se encontró la hoja DETALLE en el archivo OEE.');
        }

        $sectionMap = $this->buildSectionMap($detalle);
        $weeks = $this->getWeeksInRange($weekStart, $weekEnd);
        $monthsUpdated = [];

        foreach ($weeks as $week) {
            $weekNum = $week->isoWeek();

            if (! isset($sectionMap[$weekNum])) {
                continue;
            }

            $sectionInfo = $sectionMap[$weekNum];
            $sectionTopRow = $sectionInfo['top'];
            $footerRow = $sectionInfo['footer'];

            // 1. Pre-limpiar A-CU en el rango de la seccion
            $this->clearSectionData($detalle, $sectionTopRow, $footerRow);

            // 2. Crear export e inyectar datos en DETALLE
            // allowExpand=false: el archivo OEE tiene secciones fijas de 46 filas;
            // insertar filas desplazaria todas las secciones siguientes y romperia el archivo.
            $export = new Reporte00EAtadoresExport($week);
            $actualFooterRow = $export->renderIntoSheet($detalle, $sectionTopRow, false);

            // 3. Obtener layout y escribir formulas CK-CU
            $layout = $export->getLayout($sectionTopRow, true);
            $this->writeCkCuFormulas($detalle, $layout, $sectionTopRow);

            // 4. Crear o actualizar hoja SEMANA XX
            $atadorList = $this->extractAtadorList($layout);
            $this->updateSemanaSheet($spreadsheet, $weekNum, $sectionTopRow, $atadorList);

            // Registrar mes afectado (jueves de la semana ISO)
            $thursday = $week->addDays(3);
            $monthKey = $thursday->month;
            $monthsUpdated[$monthKey] = true;
        }

        // 5. Actualizar hojas CONCENTRADO
        foreach (array_keys($monthsUpdated) as $month) {
            $weeksForMonth = array_filter($weeks, function (CarbonImmutable $w) use ($month) {
                return $w->addDays(3)->month === $month;
            });
            foreach ($weeksForMonth as $week) {
                $this->updateConcentradoSheet($spreadsheet, $week->isoWeek(), $month, $week);
            }
        }

        // 6. Guardar archivo — NO pre-calcular: evita errores en fórmulas
        //    rotas preexistentes (ej. #REF! en TOTAL ATADOS). Excel recalcula al abrir.
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->setPreCalculateFormulas(false);
        $writer->save($this->filePath);

        return $this->filePath;
    }

    /**
     * Construye el mapa weekNumber => [top, footer] para las secciones de DETALLE.
     *
     * El archivo OEE_ATADORES.xlsx tiene 52 secciones fijas de 46 filas cada una.
     * La semana N ocupa filas (N-1)*46+1 a N*46, independientemente del valor de
     * la columna C del footer (que puede ser fórmula, entero, o vacío).
     */
    private function buildSectionMap(Worksheet $detalle): array
    {
        $maxRow = $detalle->getHighestRow();
        $sectionMap = [];

        for ($weekNum = 1; $weekNum <= 52; $weekNum++) {
            $footerRow = $weekNum * 46;
            if ($footerRow > $maxRow) {
                break;
            }
            $sectionMap[$weekNum] = [
                'top'    => ($weekNum - 1) * 46 + 1,
                'footer' => $footerRow,
            ];
        }

        return $sectionMap;
    }

    /**
     * Limpia celdas A-CU (cols 1-99) en el rango de filas de la seccion.
     * Solo toca celdas que ya existen para evitar crear objetos innecesarios.
     */
    private function clearSectionData(Worksheet $sheet, int $startRow, int $endRow): void
    {
        $colEndIndex = 99; // CU

        foreach ($sheet->getCellCollection()->getSortedCoordinates() as $coord) {
            // Extraer fila y columna de la coordenada
            if (! preg_match('/^([A-Z]+)(\d+)$/', $coord, $m)) {
                continue;
            }
            $row = (int) $m[2];
            if ($row < $startRow || $row > $endRow) {
                continue;
            }
            $colIndex = Coordinate::columnIndexFromString($m[1]);
            if ($colIndex > $colEndIndex) {
                continue;
            }
            $sheet->getCell($coord)->setValue(null);
        }

        // Deshacer merges dentro del rango
        foreach (array_keys($sheet->getMergeCells()) as $range) {
            if (! preg_match('/([A-Z]+)(\d+):([A-Z]+)(\d+)/', $range, $m)) {
                continue;
            }
            $r1 = (int) $m[2];
            $r2 = (int) $m[4];
            if ($r1 >= $startRow && $r2 <= $endRow) {
                $sheet->unmergeCells($range);
            }
        }
    }

    /**
     * Escribe las formulas CK-CU en DETALLE para cada bloque del layout.
     *
     * CK-CU summary rows se escriben en rows sectionTopRow+3, +4, +5... (una por bloque).
     * Filas adicionales de estadisticas siguen al ultimo bloque.
     */
    private function writeCkCuFormulas(Worksheet $sheet, array $layout, int $sectionTopRow): void
    {
        // Recopilar todos los bloques en orden: T1 blocks, T2, T3, Capacitacion
        $blocks = [];
        foreach ($layout['turns'] as $turn) {
            foreach ($turn['blocks'] as $block) {
                $blocks[] = $block;
            }
        }
        $blocks[] = $layout['capacitacion'];

        $numBlocks       = count($blocks);
        $firstSummaryRow = $sectionTopRow + 3;
        $lastSummaryRow  = $firstSummaryRow + $numBlocks - 1;

        // Rangos usados en formulas multi-bloque
        $cnRange = "\$CN\${$firstSummaryRow}:\$CN\${$lastSummaryRow}";
        $cmRange = "\$CM\${$firstSummaryRow}:\$CM\${$lastSummaryRow}";
        $crRange = "\$CR\${$firstSummaryRow}:\$CR\${$lastSummaryRow}";

        // CO = estandar = MIN tiempo de todos los bloques (mismo valor para todos)
        // Se calcula despues de escribir todas las CN, pero la formula es auto-referenciada
        // => la escribimos usando MIN del rango CN completo
        $coFormula = "=IFERROR(MIN({$cnRange}),\"\")";

        foreach ($blocks as $i => $block) {
            $summaryRow = $firstSummaryRow + $i;
            $blockStart = $block['row_start'];

            // CK = clave atador
            $sheet->setCellValue("CK{$summaryRow}", "=C{$blockStart}");

            // CL = mismo que clave (nombre en el bloque es la misma key)
            $sheet->setCellValue("CL{$summaryRow}", "=CK{$summaryRow}");

            // CM = avg calidad 7 dias
            $califParts = array_map(fn($c) => "{$c}{$blockStart}", self::AVG_CALIF_COLS);
            $sheet->setCellValue("CM{$summaryRow}", '=IFERROR(AVERAGE(' . implode(',', $califParts) . '),"")');

            // CN = avg tiempo 7 dias
            $timeParts = array_map(fn($c) => "{$c}{$blockStart}", self::AVG_TIME_COLS);
            $sheet->setCellValue("CN{$summaryRow}", '=IFERROR(AVERAGE(' . implode(',', $timeParts) . '),"")');

            // CO = estandar = MIN tiempo de todos los bloques de la seccion
            $sheet->setCellValue("CO{$summaryRow}", $coFormula);

            // CP = diferencia CN - CO
            $sheet->setCellValue("CP{$summaryRow}", "=IFERROR(CN{$summaryRow}-CO{$summaryRow},\"\")");

            // CQ = tiempo ajustado CO - CP
            $sheet->setCellValue("CQ{$summaryRow}", "=IFERROR(CO{$summaryRow}-CP{$summaryRow},\"\")");

            // CR = eficiencia % = CQ*100/CO
            $sheet->setCellValue("CR{$summaryRow}", "=IFERROR(CQ{$summaryRow}*100/CO{$summaryRow},\"\")");

            // CS = calidad % = CM*100/10
            $sheet->setCellValue("CS{$summaryRow}", "=IFERROR(CM{$summaryRow}*100/10,\"\")");

            // CT = avg merma 7 dias
            $mermaParts = array_map(fn($c) => "{$c}{$blockStart}", self::AVG_MERMA_COLS);
            $sheet->setCellValue("CT{$summaryRow}", '=IFERROR(AVERAGE(' . implode(',', $mermaParts) . '),"")');

            // CU = nombre (=CL)
            $sheet->setCellValue("CU{$summaryRow}", "=CL{$summaryRow}");
        }

        // Fila +7: promedio general tiempo (CN)
        $row7 = $lastSummaryRow + 7;
        $sheet->setCellValue("CN{$row7}", "=IFERROR(AVERAGE({$cnRange}),\"\")");

        // Fila +9: MAX tiempo, PROMEDIO GRAL eficiencia
        $row9 = $lastSummaryRow + 9;
        $sheet->setCellValue("CL{$row9}", 'MAX');
        $sheet->setCellValue("CM{$row9}", "=IFERROR(MAX({$cnRange}),\"\")");
        $sheet->setCellValue("CQ{$row9}", 'PROMEDIO GRAL.');
        $sheet->setCellValue("CR{$row9}", "=IFERROR(AVERAGE({$crRange}),\"\")");

        // Fila +10: MIN tiempo
        $row10 = $lastSummaryRow + 10;
        $sheet->setCellValue("CL{$row10}", 'MIN');
        $sheet->setCellValue("CM{$row10}", "=IFERROR(MIN({$cnRange}),\"\")");

        // Fila +13: MAX calidad
        $row13 = $lastSummaryRow + 13;
        $sheet->setCellValue("CL{$row13}", 'MAX');
        $sheet->setCellValue("CM{$row13}", "=IFERROR(MAX({$cmRange}),\"\")");

        // Fila +14: MIN calidad
        $row14 = $lastSummaryRow + 14;
        $sheet->setCellValue("CL{$row14}", 'MIN');
        $sheet->setCellValue("CM{$row14}", "=IFERROR(MIN({$cmRange}),\"\")");

        // Fila +15: EST = promedio tiempo
        $row15 = $lastSummaryRow + 15;
        $sheet->setCellValue("CL{$row15}", 'EST');
        $sheet->setCellValue("CM{$row15}", "=IFERROR(AVERAGE({$cnRange}),\"\")");
    }

    /**
     * Crea o actualiza la hoja SEMANA XX en el workbook.
     */
    private function updateSemanaSheet(
        Spreadsheet $spreadsheet,
        int $weekNum,
        int $sectionTopRow,
        array $atadorList
    ): void {
        $sheetName = sprintf('SEMANA %02d', $weekNum);
        $existing = $spreadsheet->getSheetByName($sheetName);

        if ($existing) {
            // Actualizar solo filas 2-22, preservar 26-51
            $this->writeSemanaContent($existing, $weekNum, $sectionTopRow, $atadorList, false);
        } else {
            // Clonar la ultima hoja SEMANA existente
            $newSheet = $this->cloneLastSemanaSheet($spreadsheet, $sheetName);
            if ($newSheet) {
                $this->writeSemanaContent($newSheet, $weekNum, $sectionTopRow, $atadorList, true);
            }
        }
    }

    /**
     * Escribe el contenido de la hoja SEMANA XX (filas 2-22, y opcionalmente limpia 26-51).
     *
     * @param bool $isNew Si true, también limpia la sección manual (filas 26-51)
     */
    private function writeSemanaContent(
        Worksheet $sheet,
        int $weekNum,
        int $sectionTopRow,
        array $atadorList,
        bool $isNew
    ): void {
        $weekNumPadded = sprintf('%02d', $weekNum);

        // Fila 2: título
        $sheet->setCellValue('B2', "SEMANA {$weekNum}");

        // Filas 4-8: un atador por fila (máximo 5)
        $firstSummaryRow = $sectionTopRow + 3;
        $atadorRows = [4, 5, 6, 7, 8];

        foreach ($atadorRows as $j => $semanaRow) {
            if (! isset($atadorList[$j])) {
                // Limpiar fila si no hay atador
                foreach (['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M'] as $col) {
                    $sheet->setCellValue("{$col}{$semanaRow}", null);
                }
                continue;
            }

            $detailRow = $firstSummaryRow + $j;

            // B = clave, C = nombre (ambos desde CK/CL de DETALLE)
            $sheet->setCellValue("B{$semanaRow}", "=DETALLE!CK{$detailRow}");
            $sheet->setCellValue("C{$semanaRow}", "=DETALLE!CL{$detailRow}");

            // D:K = DETALLE CM:CT (8 cols: calidad, tiempo, estandar, diferencia, ajustado, efic, calidad%, merma)
            $colMap = ['D' => 'CM', 'E' => 'CN', 'F' => 'CO', 'G' => 'CP', 'H' => 'CQ', 'I' => 'CR', 'J' => 'CS', 'K' => 'CT'];
            foreach ($colMap as $semCol => $detCol) {
                $sheet->setCellValue("{$semCol}{$semanaRow}", "=DETALLE!{$detCol}{$detailRow}");
            }

            // L = nombre (=C{row})
            $sheet->setCellValue("L{$semanaRow}", "=C{$semanaRow}");

            // M = 5S score (default 100 solo si es nueva hoja)
            if ($isNew) {
                $sheet->setCellValue("M{$semanaRow}", 100);
            }
        }

        // Fila 11: K11 = promedio merma
        $sheet->setCellValue('K11', '=IFERROR(AVERAGE(K4:K8),"")');

        // Fila 13: promedio general eficiencia
        $sheet->setCellValue('C13', 'PROMEDIO GRAL.');
        $sheet->setCellValue('D13', '=IFERROR(AVERAGE(I4:I8),"")');

        // Filas 16-22: tabla OEE
        // Fila 16: nombres de atadores
        foreach (['D' => 4, 'E' => 5, 'F' => 6, 'G' => 7, 'H' => 8] as $col => $srcRow) {
            $sheet->setCellValue("{$col}16", "=C{$srcRow}");
        }

        // Fila 17: EFIC. ATADOR
        $sheet->setCellValue('C17', 'EFIC. ATADOR');
        foreach (['D' => 4, 'E' => 5, 'F' => 6, 'G' => 7, 'H' => 8] as $col => $srcRow) {
            $sheet->setCellValue("{$col}17", "=I{$srcRow}");
        }

        // Fila 18: EFIC. X AUXILIAR
        $sheet->setCellValue('C18', 'EFIC. X AUXILIAR');
        // (formulas específicas de negocio — se preservan si la hoja ya existía)

        // Fila 19: CALIDAD/5S SEGURIDAD
        $sheet->setCellValue('C19', 'CALIDAD/5S SEGURIDAD');
        foreach (['D' => 4, 'E' => 5, 'F' => 6, 'G' => 7, 'H' => 8] as $col => $srcRow) {
            $sheet->setCellValue("{$col}19", "=IFERROR(AVERAGE(J{$srcRow},M{$srcRow}),\"\")");
        }

        // Fila 20: MERMA (promedio)
        $sheet->setCellValue('C20', 'MERMA (PROMEDIO)');
        foreach (['D' => 4, 'E' => 5, 'F' => 6, 'G' => 7, 'H' => 8] as $col => $srcRow) {
            $sheet->setCellValue("{$col}20", "=K{$srcRow}");
        }

        // B20 = MIN merma (para formula % merma)
        $sheet->setCellValue('B20', '=IFERROR(MIN(D20:H20),"")');

        // Fila 21: % X MERMA
        $sheet->setCellValue('C21', '% X MERMA');
        foreach (['D', 'E', 'F', 'G', 'H'] as $col) {
            $sheet->setCellValue("{$col}21", "=IFERROR(\$B\$20*100/{$col}20,\"\")");
        }

        // Fila 22: OEE
        $sheet->setCellValue('C22', 'OEE');
        foreach (['D', 'E', 'F', 'G', 'H'] as $col) {
            $sheet->setCellValue("{$col}22", "=IFERROR({$col}21*{$col}19*{$col}17/1000000,\"\")");
        }

        // Si es hoja nueva, limpiar sección manual (filas 26-51)
        if ($isNew) {
            for ($row = 26; $row <= 51; $row++) {
                for ($col = 1; $col <= 20; $col++) {
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($col) . $row, null);
                }
            }
        }
    }

    /**
     * Clona la última hoja SEMANA existente y la agrega con el nuevo título.
     */
    private function cloneLastSemanaSheet(Spreadsheet $spreadsheet, string $newTitle): ?Worksheet
    {
        $lastSemana = null;

        foreach ($spreadsheet->getSheetNames() as $name) {
            if (preg_match('/^SEMANA \d{2}$/', $name)) {
                $lastSemana = $spreadsheet->getSheetByName($name);
            }
        }

        if (! $lastSemana) {
            return null;
        }

        $newSheet = clone $lastSemana;
        $newSheet->setTitle($newTitle);
        $spreadsheet->addSheet($newSheet);

        return $newSheet;
    }

    /**
     * Actualiza la hoja CONCENTRADO del mes correspondiente con la semana dada.
     */
    private function updateConcentradoSheet(
        Spreadsheet $spreadsheet,
        int $weekNum,
        int $month,
        CarbonImmutable $weekStart
    ): void {
        $mesNombre = self::MESES[$month] ?? null;
        if (! $mesNombre) {
            return;
        }

        $sheetName = "CONCENTRADO {$mesNombre}";
        $concentrado = $spreadsheet->getSheetByName($sheetName);

        if (! $concentrado) {
            return;
        }

        $weekNumPadded = sprintf('%02d', $weekNum);
        $semanaSheetName = "SEMANA {$weekNumPadded}";

        // Buscar slot con A = weekNum, o primer slot con A vacío
        $slotRows = [5, 10, 15, 20, 25];
        $targetSlotRow = null;

        foreach ($slotRows as $slotRow) {
            $aVal = $concentrado->getCell("A{$slotRow}")->getValue();
            if ((int) $aVal === $weekNum) {
                $targetSlotRow = $slotRow;
                break;
            }
        }

        if ($targetSlotRow === null) {
            foreach ($slotRows as $slotRow) {
                $aVal = $concentrado->getCell("A{$slotRow}")->getValue();
                if ($aVal === null || $aVal === '') {
                    $targetSlotRow = $slotRow;
                    break;
                }
            }
        }

        if ($targetSlotRow === null) {
            return; // No hay slot disponible
        }

        // Escribir las 5 filas del slot
        $concentrado->setCellValue("A{$targetSlotRow}", $weekNum);

        $rowLabels = [
            0 => ['label' => 'EFICIENCIA', 'semanaRow' => 17, 'col' => 'D'],
            1 => ['label' => 'CALIDAD/5S', 'semanaRow' => 19, 'col' => 'D'],
            2 => ['label' => 'MERMA KG',   'semanaRow' => 20, 'col' => 'D'],
            3 => ['label' => 'MERMA %',    'semanaRow' => 21, 'col' => 'D'],
            4 => ['label' => 'OEE',        'semanaRow' => 22, 'col' => 'D'],
        ];

        foreach ($rowLabels as $offset => $info) {
            $row = $targetSlotRow + $offset;
            $concentrado->setCellValue("B{$row}", $info['label']);

            // Columnas C-H referencian SEMANA XX filas 17-22, columnas D-H
            $semCols = ['C' => 'D', 'D' => 'E', 'E' => 'F', 'F' => 'G', 'G' => 'H'];
            foreach ($semCols as $concCol => $semCol) {
                $semRow = $info['semanaRow'];
                $concentrado->setCellValue("{$concCol}{$row}", "='{$semanaSheetName}'!{$semCol}{$semRow}");
            }
        }
    }

    /**
     * Extrae la lista de atadores únicos (no vacíos) del layout, en orden.
     */
    private function extractAtadorList(array $layout): array
    {
        $atadores = [];

        foreach ($layout['turns'] as $turn) {
            foreach ($turn['blocks'] as $block) {
                if (($block['atador_key'] ?? '') !== '') {
                    $atadores[] = $block['atador_key'];
                }
            }
        }

        return array_values(array_unique($atadores));
    }

    /**
     * Retorna todos los lunes ISO de las semanas cubiertas entre weekStart y weekEnd.
     */
    private function getWeeksInRange(CarbonImmutable $weekStart, CarbonImmutable $weekEnd): array
    {
        $weeks = [];
        $current = $weekStart->startOfWeek(\Carbon\Carbon::MONDAY);
        $end = $weekEnd->startOfWeek(\Carbon\Carbon::MONDAY);

        while (! $current->greaterThan($end)) {
            $weeks[] = $current;
            $current = $current->addWeek();
        }

        return $weeks;
    }
}
