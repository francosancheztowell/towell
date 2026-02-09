<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class RoturasMillonExport implements FromArray, WithEvents, WithTitle
{
    protected array $filas;

    public function __construct(array $filas)
    {
        $this->filas = $filas;
    }

    public function array(): array
    {
        return [['']];
    }

    public function title(): string
    {
        return 'Roturas x Millón';
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
                $templateSheet->setTitle('Roturas x Millón');

                $book->removeSheetByIndex($sheetIndex);
                $book->addExternalSheet($templateSheet, $sheetIndex);

                $sheet = $book->getSheet($sheetIndex);
                $this->fillSheet($sheet);
            },
        ];
    }

    private function loadTemplateBook(): Spreadsheet
    {
        $candidates = [
            resource_path('templates/RoturasxMillon.xlsx'),
            storage_path('app/templates/RoturasxMillon.xlsx'),
        ];
        foreach ($candidates as $path) {
            if (is_string($path) && $path !== '' && is_file($path)) {
                return IOFactory::load($path);
            }
        }
        throw new RuntimeException('No se encontró la plantilla RoturasxMillon.xlsx en resources/templates/.');
    }

    private function fillSheet(Worksheet $sheet): void
    {
        $startRow = 2;
        foreach ($this->filas as $idx => $f) {
            $row = $startRow + $idx;
            $sheet->setCellValueByColumnAndRow(1, $row, $f['maq'] ?? '');           // A: MAQ
            $sheet->setCellValueByColumnAndRow(2, $row, $f['fecha'] ?? '');          // B: FECHA
            $sheet->setCellValueByColumnAndRow(3, $row, $f['orden'] ?? '');          // C: ORDEN
            $sheet->setCellValueByColumnAndRow(4, $row, $f['proveedor'] ?? '');      // D: PROVEEDOR
            $sheet->setCellValueByColumnAndRow(5, $row, $f['cuenta'] ?? '');         // E: CUENTA
            $sheet->setCellValueByColumnAndRow(6, $row, $f['calibre'] ?? '');        // F: CALIBRE
            $sheet->setCellValueByColumnAndRow(7, $row, $f['tipo'] ?? '');           // G: TIPO
            $sheet->setCellValueByColumnAndRow(8, $row, $f['metros_julio'] ?? 0);   // H: METROS X JULIO
            $sheet->setCellValueByColumnAndRow(9, $row, $f['total_julios'] ?? 0);   // I: TOTAL JULIOS
            $sheet->setCellValueByColumnAndRow(10, $row, $f['hilos_julio'] ?? 0);   // J: HILOS X JULIO
            $sheet->setCellValueByColumnAndRow(11, $row, 1000000);                  // K: MILLÓN
            $sheet->setCellValue("L{$row}", "=H{$row}*I{$row}");                   // L: METROS ORDEN
            $sheet->setCellValue("M{$row}", "=(L{$row}*J{$row})/K{$row}");         // M: MILLÓN DE METROS ANALIZADOS
            $sheet->setCellValueByColumnAndRow(14, $row, $f['rot_hilatura'] ?? 0);  // N: ROTURAS HILATURA
            $sheet->setCellValueByColumnAndRow(15, $row, $f['rot_maquina'] ?? 0);   // O: ROTURAS MAQUINA
            $sheet->setCellValueByColumnAndRow(16, $row, $f['rot_operacion'] ?? 0); // P: ROTURAS OPERACIÓN
            $sheet->setCellValueByColumnAndRow(17, $row, $f['transferencia'] ?? 0); // Q: TRANSFERENCIA
            $sheet->setCellValue("R{$row}", "=N{$row}+O{$row}+P{$row}+Q{$row}");  // R: TOTAL ROTURAS
        }
    }
}
