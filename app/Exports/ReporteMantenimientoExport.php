<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Table;
use PhpOffice\PhpSpreadsheet\Worksheet\Table\TableStyle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class ReporteMantenimientoExport implements FromArray, WithEvents, WithTitle
{
    private const HEADERS = [
        'Folio',
        'Estatus',
        'Fecha',
        'Fecha Fin',
        'Hora Inicio',
        'HoraFin',
        'Diferencia',
        'Departamento',
        'Maquina',
        'TipoFalla',
        'Falla',
        'ClaveEmpleado',
        'NombreEmpleado',
        'Turno',
        'Obs',
        'CveAtendio',
        'NomAtendio',
        'ObsCierre',
    ];

    protected Collection $registros;

    public function __construct(Collection $registros)
    {
        $this->registros = $registros;
    }

    public function array(): array
    {
        return [['']];
    }

    public function title(): string
    {
        return 'Reporte Mantenimiento';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $initialSheet = $event->sheet->getDelegate();
                $book = $initialSheet->getParent();
                $sheetIndex = $book->getIndex($initialSheet);

                $templateBook = $this->loadTemplateBook();
                $sheet = $templateBook->getSheet(0);
                $sheet->setTitle('ManFallasParos');
                $book->removeSheetByIndex($sheetIndex);
                $book->addExternalSheet($sheet, $sheetIndex);
                $targetSheet = $book->getSheet($sheetIndex);

                $this->setHeaders($targetSheet);
                $this->fillData($targetSheet, $this->registros);
                $this->syncTableFormat($targetSheet, $this->registros->count());
                $this->applyVisualStyles($targetSheet, $this->registros->count());
            },
        ];
    }

    private function loadTemplateBook(): Spreadsheet
    {
        $candidates = [
            resource_path('templates/ReporteMantenimiento.xlsx'),
            storage_path('app/templates/ReporteMantenimiento.xlsx'),
            storage_path('app/ReporteMantenimiento.xlsx'),
        ];

        foreach ($candidates as $path) {
            if (is_string($path) && $path !== '' && is_file($path)) {
                return IOFactory::load($path);
            }
        }

        throw new RuntimeException(
            'No se encontró la plantilla ReporteMantenimiento.xlsx. '
            . 'Colócala en resources/templates/ o en storage/app/templates/.'
        );
    }

    private function setHeaders(Worksheet $sheet): void
    {
        foreach (self::HEADERS as $col => $val) {
            $sheet->setCellValueByColumnAndRow($col + 1, 1, $val);
        }

        // Limpia columna heredada del template (HoraFin2) cuando exista.
        $sheet->setCellValueByColumnAndRow(19, 1, '');
    }

    private function syncTableFormat(Worksheet $sheet, int $dataRowCount): void
    {
        $numCols = count(self::HEADERS);
        $lastRow = 1 + max(1, $dataRowCount); // al menos header + 1 fila para que Excel reconozca la tabla
        $lastCol = Coordinate::stringFromColumnIndex($numCols);
        $range = 'A1:' . $lastCol . $lastRow;

        $table = $sheet->getTableByName('Tabla2');
        if (!$table) {
            $tables = $sheet->getTableCollection();
            $table = count($tables) > 0 ? $tables[0] : null;
        }

        if ($table) {
            $table->setRange($range);
            return;
        }

        $newTable = new Table($range, 'TablaMantenimiento');
        $newTable->setStyle(
            (new TableStyle())->setTheme(TableStyle::TABLE_STYLE_MEDIUM2)
        );
        $sheet->addTable($newTable);
    }

    private function fillData(Worksheet $sheet, Collection $registros): void
    {
        $startRow = 2;
        foreach ($registros as $idx => $r) {
            $row = $startRow + $idx;
            $cols = [
                $r->Folio ?? '',
                $r->Estatus ?? '',
                $this->formatDateValue($r->Fecha ?? null),
                $this->formatDateValue($r->FechaFin ?? null),
                $this->formatTimeValue($r->Hora ?? null),
                $this->formatTimeValue($r->HoraFin ?? null),
                $this->buildDifferenceText($r->Fecha ?? null, $r->Hora ?? null, $r->FechaFin ?? null, $r->HoraFin ?? null),
                $r->Depto ?? '',
                $r->MaquinaId ?? '',
                $r->TipoFallaId ?? '',
                $this->resolveFallaDescripcion($r),
                $r->CveEmpl ?? '',
                $r->NomEmpl ?? '',
                $r->Turno ?? '',
                $r->Obs ?? '',
                $r->CveAtendio ?? '',
                $r->NomAtendio ?? '',
                $r->ObsCierre ?? '',
            ];

            foreach ($cols as $col => $val) {
                $sheet->setCellValueByColumnAndRow($col + 1, $row, $val);
            }

            // Resalta diferencia cuando el registro esta finalizado.
            if ($this->isFinalizado($r->Estatus ?? null)) {
                $sheet->getStyle("G{$row}")->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FFDCFCE7'],
                    ],
                    'font' => [
                        'bold' => true,
                        'color' => ['argb' => Color::COLOR_DARKGREEN],
                    ],
                ]);
            }

            $this->applyStatusBadge($sheet, $row, $r->Estatus ?? null);
            $this->applyTipoFallaBadge($sheet, $row, $r->TipoFallaId ?? null);
        }
    }

    private function applyVisualStyles(Worksheet $sheet, int $dataRowCount): void
    {
        $lastRow = 1 + max(1, $dataRowCount);
        $lastCol = Coordinate::stringFromColumnIndex(count(self::HEADERS));
        $range = "A1:{$lastCol}{$lastRow}";

        // Mantener todo el texto dentro de celdas y legible.
        $sheet->getStyle($range)->getAlignment()->setWrapText(true);
        $sheet->getStyle($range)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        // Header fijo al hacer scroll.
        $sheet->freezePane('A2');

        // Anchos de columna pensados para lectura en pantalla y papel.
        $widths = [
            'A' => 12, 'B' => 14, 'C' => 12, 'D' => 12, 'E' => 11, 'F' => 11,
            'G' => 14, 'H' => 14, 'I' => 12, 'J' => 12, 'K' => 24, 'L' => 14,
            'M' => 20, 'N' => 8, 'O' => 28, 'P' => 12, 'Q' => 20, 'R' => 28,
        ];
        foreach ($widths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        // Centrados para columnas cortas.
        foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'L', 'N', 'P'] as $col) {
            $sheet->getStyle("{$col}1:{$col}{$lastRow}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
    }

    private function applyStatusBadge(Worksheet $sheet, int $row, mixed $estatus): void
    {
        $cell = "B{$row}";
        if ($this->isFinalizado($estatus)) {
            $sheet->getStyle($cell)->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFDCFCE7'],
                ],
                'font' => [
                    'bold' => true,
                    'color' => ['argb' => Color::COLOR_DARKGREEN],
                ],
            ]);
            return;
        }

        if ($this->isActivo($estatus)) {
            $sheet->getStyle($cell)->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFDBEAFE'],
                ],
                'font' => [
                    'bold' => true,
                    'color' => ['argb' => 'FF1E3A8A'],
                ],
            ]);
        }
    }

    private function applyTipoFallaBadge(Worksheet $sheet, int $row, mixed $tipoFalla): void
    {
        $cell = "J{$row}";
        $tipo = $this->normalizeText((string) ($tipoFalla ?? ''));

        if (str_contains($tipo, 'tiempo') || str_contains($tipo, 'muerto')) {
            $sheet->getStyle($cell)->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFE5E7EB'],
                ],
                'font' => [
                    'bold' => true,
                    'color' => ['argb' => 'FF374151'],
                ],
            ]);
            return;
        }

        if (str_contains($tipo, 'electrico') || str_contains($tipo, 'electrica')) {
            $sheet->getStyle($cell)->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFDBEAFE'],
                ],
                'font' => [
                    'bold' => true,
                    'color' => ['argb' => 'FF1E3A8A'],
                ],
            ]);
            return;
        }

        if (str_contains($tipo, 'mecanico') || str_contains($tipo, 'mecanica')) {
            $sheet->getStyle($cell)->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFFEE2E2'],
                ],
                'font' => [
                    'bold' => true,
                    'color' => ['argb' => 'FF991B1B'],
                ],
            ]);
        }
    }

    private function resolveFallaDescripcion(mixed $registro): string
    {
        $descripcion = trim((string) ($registro->Descripcion ?? ''));
        if ($descripcion !== '') {
            return $descripcion;
        }

        return (string) ($registro->Falla ?? '');
    }

    private function normalizeText(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $from = ['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ'];
        $to = ['a', 'e', 'i', 'o', 'u', 'u', 'n'];
        return str_replace($from, $to, $text);
    }

    private function formatDateValue(mixed $value): string
    {
        if (empty($value)) {
            return '';
        }

        if ($value instanceof Carbon) {
            return $value->format('Y-m-d');
        }

        try {
            return Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }

    private function formatTimeValue(mixed $value): string
    {
        if (empty($value)) {
            return '';
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return '';
        }

        // Preservar HH:mm o HH:mm:ss sin alterar texto si no parsea.
        if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $raw) === 1) {
            return $raw;
        }

        try {
            return Carbon::parse($raw)->format('H:i:s');
        } catch (\Throwable $e) {
            return $raw;
        }
    }

    private function buildDifferenceText(mixed $fechaIni, mixed $horaIni, mixed $fechaFin, mixed $horaFin): string
    {
        if (empty($fechaIni) || empty($horaIni) || empty($fechaFin) || empty($horaFin)) {
            return '';
        }

        $start = $this->buildDateTime($fechaIni, $horaIni);
        $end = $this->buildDateTime($fechaFin, $horaFin);
        if ($start === null || $end === null) {
            return '';
        }

        $negative = $end->lt($start);
        $diff = $negative ? $end->diffAsCarbonInterval($start) : $start->diffAsCarbonInterval($end);
        $days = (int) $diff->days;
        $hours = (int) $diff->h;
        $minutes = (int) $diff->i;

        $text = "{$days}d {$hours}h {$minutes}m";
        return $negative ? '-' . $text : $text;
    }

    private function buildDateTime(mixed $date, mixed $time): ?Carbon
    {
        try {
            $d = $this->formatDateValue($date);
            $t = $this->formatTimeValue($time);
            if ($d === '' || $t === '') {
                return null;
            }

            return Carbon::parse($d . ' ' . $t);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function isFinalizado(mixed $estatus): bool
    {
        if ($estatus === null) {
            return false;
        }

        return stripos((string) $estatus, 'finaliz') !== false;
    }

    private function isActivo(mixed $estatus): bool
    {
        if ($estatus === null) {
            return false;
        }

        return stripos((string) $estatus, 'activo') !== false;
    }
}
