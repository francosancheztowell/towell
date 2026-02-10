<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Table;
use PhpOffice\PhpSpreadsheet\Worksheet\Table\TableStyle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BpmEngomadoExport implements FromArray, WithEvents, WithTitle
{
    private const HEADERS = [
        '#',
        'Folio',
        'Status',
        'Fecha',
        'ClaveEntrega',
        'NombreEntrega',
        'Turno Entrega',
        'ClaveRecibe',
        'NombreRecibe',
        'Turno Recibe',
        'ClaveAutoriza',
        'Nombre Autoriza',
        'Orden',
        'Actividad',
        'Valor',
    ];

    protected Collection $filas;

    public function __construct(Collection $filas)
    {
        $this->filas = $filas;
    }

    public function array(): array
    {
        return [['']];
    }

    public function title(): string
    {
        return 'BPM Engomado';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $this->setHeaders($sheet);
                $this->fillData($sheet);
                $this->applyLayout($sheet);
            },
        ];
    }

    private function setHeaders(Worksheet $sheet): void
    {
        foreach (self::HEADERS as $col => $value) {
            $sheet->setCellValueByColumnAndRow($col + 1, 1, $value);
        }
    }

    private function fillData(Worksheet $sheet): void
    {
        $row = 2;
        foreach ($this->filas as $item) {
            $sheet->setCellValue("A{$row}", $item->InicioFolio ?? '');
            $sheet->setCellValue("B{$row}", (string) ($item->Folio ?? ''));
            $sheet->setCellValue("C{$row}", (string) ($item->Status ?? ''));
            $sheet->setCellValue("D{$row}", $this->formatDateValue($item->Fecha ?? null));
            $sheet->setCellValue("E{$row}", $item->CveEmplEnt ?? '');
            $sheet->setCellValue("F{$row}", (string) ($item->NombreEmplEnt ?? ''));
            $sheet->setCellValue("G{$row}", (string) ($item->TurnoEntrega ?? ''));
            $sheet->setCellValue("H{$row}", $item->CveEmplRec ?? '');
            $sheet->setCellValue("I{$row}", (string) ($item->NombreEmplRec ?? ''));
            $sheet->setCellValue("J{$row}", (string) ($item->TurnoRecibe ?? ''));
            $sheet->setCellValue("K{$row}", $item->CveEmplAutoriza ?? '');
            $sheet->setCellValue("L{$row}", (string) ($item->NombreEmplAutoriza ?? ''));
            $sheet->setCellValue("M{$row}", (int) ($item->Orden ?? 0));
            $sheet->setCellValue("N{$row}", (string) ($item->Actividad ?? ''));
            $sheet->setCellValue("O{$row}", (string) ($item->ValorTexto ?? 'S/N'));

            $this->applyStatusBadge($sheet, $row, (string) ($item->Status ?? ''));
            $this->applyValorBadge($sheet, $row, (string) ($item->ValorTexto ?? 'S/N'));
            $row++;
        }
    }

    private function applyLayout(Worksheet $sheet): void
    {
        $lastRow = max(2, $this->filas->count() + 1);
        $lastCol = Coordinate::stringFromColumnIndex(count(self::HEADERS));
        $range = "A1:{$lastCol}{$lastRow}";

        $sheet->getStyle('A1:O1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => Color::COLOR_WHITE]],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF0E7490'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $sheet->getStyle($range)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FFD1D5DB'],
                ],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);

        $this->syncExcelTable($sheet, $lastRow, $lastCol);
        $sheet->freezePane('A2');

        $widths = [
            'A' => 6, 'B' => 12, 'C' => 14, 'D' => 12, 'E' => 14, 'F' => 28, 'G' => 12, 'H' => 14,
            'I' => 28, 'J' => 12, 'K' => 14, 'L' => 28, 'M' => 8, 'N' => 48, 'O' => 12,
        ];
        foreach ($widths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        foreach (['A', 'B', 'C', 'D', 'E', 'G', 'H', 'J', 'K', 'M', 'O'] as $col) {
            $sheet->getStyle("{$col}1:{$col}{$lastRow}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
    }

    private function syncExcelTable(Worksheet $sheet, int $lastRow, string $lastCol): void
    {
        $range = "A1:{$lastCol}{$lastRow}";
        $tableName = 'TablaBpmEngomado';
        $table = $sheet->getTableByName($tableName);

        if ($table) {
            $table->setRange($range);
            return;
        }

        $newTable = new Table($range, $tableName);
        $newTable->setStyle(
            (new TableStyle())->setTheme(TableStyle::TABLE_STYLE_MEDIUM2)
        );
        $sheet->addTable($newTable);
    }

    private function applyStatusBadge(Worksheet $sheet, int $row, string $status): void
    {
        $normalized = mb_strtolower(trim($status), 'UTF-8');
        $cell = "C{$row}";

        if ($normalized === 'autorizado') {
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

        if ($normalized === 'terminado') {
            $sheet->getStyle($cell)->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFFEF3C7'],
                ],
                'font' => [
                    'bold' => true,
                    'color' => ['argb' => 'FF92400E'],
                ],
            ]);
            return;
        }

        if ($normalized === 'creado') {
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

    private function applyValorBadge(Worksheet $sheet, int $row, string $valor): void
    {
        $cell = "O{$row}";
        $v = mb_strtolower(trim($valor), 'UTF-8');

        if ($v === '☑') {
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

        if ($v === '☒') {
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
            return;
        }

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
    }

    private function formatDateValue(mixed $value): string
    {
        if (empty($value)) {
            return '';
        }

        if ($value instanceof Carbon) {
            return $value->format('d/m/Y');
        }

        try {
            return Carbon::parse((string) $value)->format('d/m/Y');
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }
}
