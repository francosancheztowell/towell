<?php

namespace App\Exports;

use App\Support\Planeacion\TelarSalonResolver;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Rellena la hoja "SALDOS 2026" desde resources/templates/SALDOS_2026.xlsx.
 *
 * Por consumo de memoria (plantilla ~6 MB con varias hojas y pivots), solo se carga esa hoja:
 * el archivo descargado contiene únicamente "SALDOS 2026" con encabezados y formato de plantilla.
 */
final class Saldos2026Export
{
    private const SHEET_NAME = 'SALDOS 2026';

    private const DATA_START_ROW = 4;

    /** Última fila de la muestra en plantilla; se amplía según registros. */
    private const TEMPLATE_SAMPLE_LAST_ROW = 108;

    public function __construct(private readonly Collection $registros) {}

    public function downloadResponse(string $downloadName = 'saldos-2026.xlsx'): BinaryFileResponse
    {
        $prevLimit = ini_get('memory_limit');
        $prevMaxExec = ini_get('max_execution_time');
        @ini_set('memory_limit', '1024M');
        // Plantilla grande + getStyle por rangos y writer Xlsx: puede superar 60s (XAMPP default).
        @set_time_limit(600);

        try {
            $spreadsheet = $this->loadTemplateWorkbook();
            $sheet = $spreadsheet->getSheetByName(self::SHEET_NAME)
                ?? throw new RuntimeException('La plantilla no contiene la hoja "'.self::SHEET_NAME.'".');

            $this->fillSaldosSheet($sheet);

            $tmp = tempnam(sys_get_temp_dir(), 'saldos2026_');
            if ($tmp === false) {
                throw new RuntimeException('No se pudo crear archivo temporal para la exportación.');
            }
            $path = $tmp.'.xlsx';
            rename($tmp, $path);

            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->setPreCalculateFormulas(false);
            $writer->save($path);
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet, $writer);
            gc_collect_cycles();

            return response()->download($path, $downloadName)->deleteFileAfterSend(true);
        } finally {
            if ($prevLimit !== false && $prevLimit !== '') {
                @ini_set('memory_limit', (string) $prevLimit);
            }
            if ($prevMaxExec !== false && $prevMaxExec !== '') {
                @ini_set('max_execution_time', (string) $prevMaxExec);
            }
        }
    }

    private function loadTemplateWorkbook(): Spreadsheet
    {
        $candidates = [
            resource_path('templates/SALDOS_2026.xlsx'),
            storage_path('app/templates/SALDOS_2026.xlsx'),
        ];

        foreach ($candidates as $path) {
            if (is_string($path) && $path !== '' && is_file($path)) {
                $reader = IOFactory::createReader('Xlsx');
                $reader->setReadEmptyCells(false);
                $reader->setIncludeCharts(false);
                // Evita cargar el libro completo (pivots, otras hojas): reduce RAM y tiempo.
                $reader->setLoadSheetsOnly([self::SHEET_NAME]);

                return $reader->load($path);
            }
        }

        throw new RuntimeException(
            'No se encontró la plantilla SALDOS_2026.xlsx. Colócala en resources/templates/ o storage/app/templates/.'
        );
    }

    private function fillSaldosSheet(Worksheet $sheet): void
    {
        $lastCol = 'BK';
        $outputRows = $this->countOutputRows();
        $clearEnd = max(self::TEMPLATE_SAMPLE_LAST_ROW, self::DATA_START_ROW + $outputRows + 10);
        $this->clearDataArea($sheet, self::DATA_START_ROW, $clearEnd, $lastCol);

        $excelRow = self::DATA_START_ROW;
        $vals = $this->registros->values();
        $prev = null;
        $liderRows = [];

        foreach ($vals as $i => $r) {
            if ($i > 0 && $prev !== null && $this->needsTelarSeparator($prev, $r)) {
                $this->writeSeparatorRow($sheet, $excelRow);
                $excelRow++;
            }
            $prev = $r;

            $this->writeDataRow($sheet, $excelRow, $r, $liderRows);
            $excelRow++;
        }

        $lastWrittenRow = $excelRow - 1;
        if ($lastWrittenRow >= self::DATA_START_ROW) {
            $this->applyColumnNumberFormats($sheet, self::DATA_START_ROW, $lastWrittenRow);
            foreach ($liderRows as $bgRow) {
                $sheet->getStyle("BG{$bgRow}")->getNumberFormat()->setFormatCode('0.0%');
            }
        }
    }

    private function countOutputRows(): int
    {
        $vals = $this->registros->values();
        $n = 0;
        $prev = null;
        foreach ($vals as $i => $r) {
            if ($i > 0 && $prev !== null && $this->needsTelarSeparator($prev, $r)) {
                $n++;
            }
            $n++;
            $prev = $r;
        }

        return $n;
    }

    private function clearDataArea(Worksheet $sheet, int $startRow, int $endRow, string $lastCol): void
    {
        if ($endRow < $startRow) {
            return;
        }
        $numRows = $endRow - $startRow + 1;
        $numCols = Coordinate::columnIndexFromString($lastCol);
        $blankRow = array_fill(0, $numCols, '');
        $matrix = array_fill(0, $numRows, $blankRow);
        // Usar un sentinel evita que fromArray omita las cadenas vacias y deje
        // visibles los datos de muestra que trae la plantilla.
        $sheet->fromArray($matrix, '__SKIP_NULL__', 'A'.$startRow, false);
    }

    private function writeSeparatorRow(Worksheet $sheet, int $row): void
    {
        $sheet->getRowDimension($row)->setRowHeight(10);
        // No getStyle() por celda: evita explotar Style\Supervisor en plantillas pesadas.
    }

    /**
     * @param  list<int>  $liderRows  Filas con avance en BG (se aplica 0.0% al final).
     */
    private function writeDataRow(Worksheet $sheet, int $row, object $r, array &$liderRows): void
    {
        $esLider = $r->_esLider ?? true;

        $solicitado = (float) ($r->_sumTotalPedido ?? $r->TotalPedido ?? 0);
        $saldo = (float) ($r->_sumSaldoPedido ?? $r->SaldoPedido ?? 0);
        $produccion = (float) ($r->_sumProduccion ?? $r->Produccion ?? 0);
        $rollosProg = $esLider ? ($r->_sumTotalRollos ?? $r->TotalRollos ?? 0) : null;

        $faltan = null;
        $avance = null;
        $rollosXTejer = null;
        if ($esLider) {
            $faltan = $solicitado - $saldo;
            $avance = $solicitado > 0 ? $saldo / $solicitado : null;
            $tiras = (float) ($r->NoTiras ?? 0);
            $reps = (float) ($r->Repeticiones ?? 0);
            $f = (float) $faltan;
            $rollosXTejer = ($tiras > 0 && $reps > 0 && $f > 0)
                ? (int) ceil($f / ($tiras * $reps))
                : null;
        }

        $raz = $r->Rasurado ?? '';
        $razNorm = mb_strtolower(trim((string) $raz), 'UTF-8');
        $esRasurada = in_array($razNorm, ['si', 'sí', 'yes'], true);

        $this->writeMaybeNumeric($sheet, "A{$row}", $r->NoTelarId);
        $this->writeString($sheet, "B{$row}", $r->NoExisteBase ?? '');
        $this->writeExcelDate($sheet, "C{$row}", $this->excelDate($r->FechaInicio ?? null));
        $this->writeMaybeNumeric($sheet, "E{$row}", $r->NoProduccion);
        $this->writeExcelDate($sheet, "F{$row}", $this->excelDate($r->FechaCreacion ?? null));
        $this->writeExcelDate($sheet, "G{$row}", $this->excelDate($r->EntregaCte ?? null));
        $this->writeString($sheet, "H{$row}", $r->SalonTejidoId);
        $this->writeMaybeNumeric($sheet, "I{$row}", $r->NoTelarId);
        $this->writeMaybeNumeric($sheet, "J{$row}", $r->Prioridad ?? '');
        $this->writeString($sheet, "K{$row}", $r->NombreProducto);
        $this->writeString($sheet, "L{$row}", $r->TamanoClave);
        $this->writeString($sheet, "M{$row}", $r->ItemId);
        $this->writeString($sheet, "N{$row}", $r->Tolerancia ?? '');
        $this->writeString($sheet, "O{$row}", $r->CodigoDibujo ?? '');
        $this->writeExcelDate($sheet, "P{$row}", $this->excelDate($r->EntregaProduc ?? null));
        $this->writeString($sheet, "Q{$row}", $r->FlogsId ?? '');
        $this->writeString($sheet, "R{$row}", $r->Clave ?? '');

        if ($esLider) {
            $sheet->setCellValueExplicit("S{$row}", $solicitado, DataType::TYPE_NUMERIC);
        } else {
            $this->writeString($sheet, "S{$row}", 'ABIERTO');
        }

        $this->writeMaybeNumeric($sheet, "T{$row}", $r->Peine ?? '');
        $this->writeMaybeNumeric($sheet, "U{$row}", $r->Ancho ?? '');
        $this->writeMaybeNumeric($sheet, "V{$row}", $r->LargoCrudo ?? '');
        $this->writeMaybeNumeric($sheet, "W{$row}", $r->PesoCrudo ?? '');
        $this->writeMaybeNumeric($sheet, "X{$row}", $r->Luchaje ?? '');
        $this->writeMaybeNumeric($sheet, "Y{$row}", $r->CalibreTrama2 ?? '');
        $this->writeString($sheet, "Z{$row}", $r->FibraTrama ?? '');
        $this->writeString($sheet, "AA{$row}", $r->ObsModelo ?? '');
        $this->writeString($sheet, "AB{$row}", $r->MedidaPlano ?? '');
        $sheet->setCellValue("AC{$row}", '');
        $this->writeString($sheet, "AD{$row}", $r->TipoRizo ?? '');
        $this->writeString($sheet, "AE{$row}", $r->AlturaRizo ?? '');
        $this->writeString($sheet, "AF{$row}", $r->ObsModelo ?? '');
        $this->writeMaybeNumeric($sheet, "AG{$row}", $r->VelocidadSTD ?? '');

        $this->writeMaybeNumeric($sheet, "AH{$row}", $r->CalibreRizo2 ?? '');
        $this->writeMaybeNumeric($sheet, "AI{$row}", $r->CuentaRizo ?? '');
        $this->writeString($sheet, "AJ{$row}", $r->FibraRizo ?? '');
        $sheet->setCellValue("AK{$row}", '');

        $this->writeMaybeNumeric($sheet, "AL{$row}", $r->CalibrePie2 ?? '');
        $this->writeMaybeNumeric($sheet, "AM{$row}", $r->CuentaPie ?? '');
        $this->writeString($sheet, "AN{$row}", $r->FibraPie ?? '');
        $sheet->setCellValue("AO{$row}", '');

        $this->writeMaybeNumeric($sheet, "AP{$row}", $r->C1 ?? '');
        $this->writeString($sheet, "AQ{$row}", $r->ObsC1 ?? '');
        $this->writeMaybeNumeric($sheet, "AR{$row}", $r->C2 ?? '');
        $this->writeString($sheet, "AS{$row}", $r->ObsC2 ?? '');
        $this->writeMaybeNumeric($sheet, "AT{$row}", $r->C3 ?? '');
        $this->writeString($sheet, "AU{$row}", $r->ObsC3 ?? '');
        $this->writeMaybeNumeric($sheet, "AV{$row}", $r->C4 ?? '');
        $this->writeString($sheet, "AW{$row}", $r->ObsC4 ?? '');
        $this->writeMaybeNumeric($sheet, "AX{$row}", $r->MedidaCenefa ?? '');
        $this->writeMaybeNumeric($sheet, "AY{$row}", $r->MedIniRizoCenefa ?? '');
        $this->writeString($sheet, "AZ{$row}", $esRasurada ? 'SÍ' : (string) $raz);
        $this->writeMaybeNumeric($sheet, "BA{$row}", $r->NoTiras ?? '');
        $this->writeMaybeNumeric($sheet, "BB{$row}", $r->Repeticiones ?? '');

        if ($esLider) {
            $sheet->setCellValueExplicit("BC{$row}", (float) $rollosProg, DataType::TYPE_NUMERIC);
        } else {
            $this->writeString($sheet, "BC{$row}", 'ABIERTO');
        }

        if ($esLider) {
            $sheet->setCellValueExplicit("BD{$row}", $produccion, DataType::TYPE_NUMERIC);
        } else {
            $sheet->setCellValue("BD{$row}", '');
        }

        if ($esLider) {
            $sheet->setCellValueExplicit("BE{$row}", $saldo, DataType::TYPE_NUMERIC);
        } else {
            $this->writeString($sheet, "BE{$row}", 'ABIERTO');
        }

        if ($esLider) {
            $sheet->setCellValueExplicit("BF{$row}", (float) $faltan, DataType::TYPE_NUMERIC);
            if ($avance !== null && $solicitado > 0) {
                $sheet->setCellValueExplicit("BG{$row}", $avance, DataType::TYPE_NUMERIC);
                $liderRows[] = $row;
            } else {
                $sheet->setCellValue("BG{$row}", '');
            }
            if ($rollosXTejer !== null) {
                $sheet->setCellValueExplicit("BH{$row}", (float) $rollosXTejer, DataType::TYPE_NUMERIC);
            } else {
                $sheet->setCellValue("BH{$row}", '');
            }
        } else {
            $this->writeString($sheet, "BF{$row}", '—');
            $this->writeString($sheet, "BG{$row}", '—');
            $this->writeString($sheet, "BH{$row}", '—');
        }

        $this->writeString($sheet, "BJ{$row}", $r->Observaciones ?? '');

        $this->applyTelarFill($sheet, $row, $r);
        if (! empty($r->NoExisteBase)) {
            $sheet->getStyle("B{$row}")->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('FEE2E2');
        }
    }

    private function writeString(Worksheet $sheet, string $coord, mixed $value): void
    {
        $sheet->setCellValueExplicit(
            $coord,
            $value === null || $value === '' ? '' : (string) $value,
            DataType::TYPE_STRING
        );
    }

    private function writeMaybeNumeric(Worksheet $sheet, string $coord, mixed $value): void
    {
        if ($value === null || $value === '') {
            $sheet->setCellValue($coord, '');

            return;
        }
        if (is_int($value) || is_float($value)) {
            $sheet->setCellValueExplicit($coord, $value, DataType::TYPE_NUMERIC);

            return;
        }
        $s = trim((string) $value);
        if ($s === '') {
            $sheet->setCellValue($coord, '');

            return;
        }
        if (is_numeric($s)) {
            $sheet->setCellValueExplicit($coord, 0 + $s, DataType::TYPE_NUMERIC);

            return;
        }
        $this->writeString($sheet, $coord, $s);
    }

    private function writeExcelDate(Worksheet $sheet, string $coord, ?float $serial): void
    {
        if ($serial === null) {
            $sheet->setCellValue($coord, '');

            return;
        }
        $sheet->setCellValueExplicit($coord, $serial, DataType::TYPE_NUMERIC);
    }

    /**
     * Formatos por columna (pocas llamadas a getStyle). Sin FORMAT_TEXT masivo: ya usamos TYPE_STRING.
     * Rangos contiguos en una sola getStyle para reducir clonación de estilos en plantillas pesadas.
     */
    private function applyColumnNumberFormats(Worksheet $sheet, int $firstRow, int $lastRow): void
    {
        if ($lastRow < $firstRow) {
            return;
        }
        $fmtDate = NumberFormat::FORMAT_DATE_DDMMYYYY;
        $fmtInt = '#,##0';
        $fmtDec = '#,##0.###';

        foreach (['C', 'F', 'G', 'P'] as $col) {
            $sheet->getStyle("{$col}{$firstRow}:{$col}{$lastRow}")->getNumberFormat()->setFormatCode($fmtDate);
        }

        foreach (['A', 'I', 'E', 'J'] as $col) {
            $sheet->getStyle("{$col}{$firstRow}:{$col}{$lastRow}")->getNumberFormat()->setFormatCode('0');
        }

        $sheet->getStyle("T{$firstRow}:Y{$lastRow}")->getNumberFormat()->setFormatCode($fmtDec);
        $sheet->getStyle("AG{$firstRow}:AI{$lastRow}")->getNumberFormat()->setFormatCode($fmtDec);
        $sheet->getStyle("AL{$firstRow}:AM{$lastRow}")->getNumberFormat()->setFormatCode($fmtDec);
        foreach (['AP', 'AR', 'AT', 'AV', 'AX', 'AY'] as $col) {
            $sheet->getStyle("{$col}{$firstRow}:{$col}{$lastRow}")->getNumberFormat()->setFormatCode($fmtDec);
        }

        foreach (['S', 'BC', 'BE'] as $col) {
            $sheet->getStyle("{$col}{$firstRow}:{$col}{$lastRow}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_GENERAL);
        }

        foreach (['BA', 'BB', 'BD', 'BF', 'BH'] as $col) {
            $sheet->getStyle("{$col}{$firstRow}:{$col}{$lastRow}")->getNumberFormat()->setFormatCode($fmtInt);
        }

        $sheet->getStyle("BG{$firstRow}:BG{$lastRow}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_GENERAL);
    }

    private function applyTelarFill(Worksheet $sheet, int $row, object $r): void
    {
        $tipo = TelarSalonResolver::normalizeSalon($r->SalonTejidoId ?? null, $r->NoTelarId ?? null);
        $rgb = match ($tipo) {
            'SMIT' => 'DBEAFE',
            'JACQUARD' => 'FFEDD5',
            default => null,
        };
        if ($rgb !== null) {
            $sheet->getStyle("A{$row}")->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB($rgb);
        }
    }

    private function excelDate(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return ExcelDate::PHPToExcel(Carbon::parse($value));
        } catch (\Throwable) {
            return null;
        }
    }

    private function needsTelarSeparator(object $prev, object $r): bool
    {
        $sameTelar = trim((string) ($prev->NoTelarId ?? '')) === trim((string) ($r->NoTelarId ?? ''));
        $ordPrev = trim((string) ($prev->OrdCompartida ?? ''));
        $ordCurr = trim((string) ($r->OrdCompartida ?? ''));
        $sameShared = ($prev->_esGrupoVinculado ?? false)
            && ($r->_esGrupoVinculado ?? false)
            && $ordPrev !== ''
            && $ordPrev === $ordCurr;

        return ! ($sameTelar || $sameShared);
    }
}
