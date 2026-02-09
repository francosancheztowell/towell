<?php

namespace App\Exports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class KaizenExport implements FromArray, WithEvents, WithTitle
{
    protected array $filasEngomado;
    protected array $filasUrdido;

    private const MESES = [
        1 => 'ENERO', 2 => 'FEBRERO', 3 => 'MARZO', 4 => 'ABRIL', 5 => 'MAYO', 6 => 'JUNIO',
        7 => 'JULIO', 8 => 'AGOSTO', 9 => 'SEPTIEMBRE', 10 => 'OCTUBRE', 11 => 'NOVIEMBRE', 12 => 'DICIEMBRE',
    ];

    public function __construct(array $filasEngomado, array $filasUrdido)
    {
        $this->filasEngomado = $filasEngomado;
        $this->filasUrdido = $filasUrdido;
    }

    public function array(): array
    {
        return [['']];
    }

    public function title(): string
    {
        return 'Kaizen';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $initialSheet = $event->sheet->getDelegate();
                $book = $initialSheet->getParent();
                $sheetIndex = $book->getIndex($initialSheet);

                $templateBook = $this->loadTemplateBook();

                $book->removeSheetByIndex($sheetIndex);

                $sheetEng = $templateBook->getSheet(0);
                $sheetEng->setTitle('AX ENGOMADO');
                $book->addExternalSheet($sheetEng, $sheetIndex);
                $this->fillKaizenSheet($book->getSheet($sheetIndex), $this->filasEngomado);

                $sheetUrd = $templateBook->getSheet(0);
                $sheetUrd->setTitle('AX URDIDO');
                $book->addExternalSheet($sheetUrd, $sheetIndex + 1);
                $this->fillKaizenSheet($book->getSheet($sheetIndex + 1), $this->filasUrdido);
            },
        ];
    }

    private function loadTemplateBook(): Spreadsheet
    {
        $candidates = [
            resource_path('templates/kaizen.xlsx'),
            storage_path('app/templates/kaizen.xlsx'),
            storage_path('app/kaizen.xlsx'),
        ];
        foreach ($candidates as $path) {
            if (is_string($path) && $path !== '' && is_file($path)) {
                return IOFactory::load($path);
            }
        }
        throw new RuntimeException('No se encontró la plantilla kaizen.xlsx en resources/templates/ o storage/app/templates/.');
    }

    private function fillKaizenSheet(Worksheet $sheet, array $filas): void
    {
        $startRow = 2;
        foreach ($filas as $idx => $f) {
            $row = $startRow + $idx;
            $sheet->setCellValueByColumnAndRow(1, $row, $f['fecha_mod'] ?? '');
            $sheet->setCellValueByColumnAndRow(2, $row, $f['anio'] ?? '');
            $sheet->setCellValueByColumnAndRow(3, $row, $f['mes'] ?? '');
            $sheet->setCellValueByColumnAndRow(4, $row, $f['codigo'] ?? '');
            $sheet->setCellValueByColumnAndRow(5, $row, $f['localidad'] ?? '');
            $sheet->setCellValueByColumnAndRow(6, $row, $f['estado'] ?? 'Terminado');
            $sheet->setCellValueByColumnAndRow(7, $row, $f['lote'] ?? '');
            $sheet->setCellValueByColumnAndRow(8, $row, $f['calibre'] ?? '');
            $sheet->setCellValueByColumnAndRow(9, $row, $f['cantidad'] ?? '');
            $sheet->setCellValueByColumnAndRow(10, $row, $f['configuracion'] ?? '');
            $sheet->setCellValueByColumnAndRow(11, $row, $f['tamano'] ?? '');
            $sheet->setCellValueByColumnAndRow(12, $row, $f['mts'] ?? '');
        }
    }
}
