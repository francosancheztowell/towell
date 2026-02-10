<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Table;
use PhpOffice\PhpSpreadsheet\Worksheet\Table\TableStyle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class ReporteMantenimientoExport implements FromArray, WithEvents, WithTitle
{
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
        $headers = [
            'Folio', 'Estatus', 'Fecha', 'Hora', 'Departamento', 'Maquina', 'TipoFalla', 'Falla',
            'HoraFin', 'ClaveEmpl', 'NombreEn', 'Turno', 'Obs', 'CveAtendio', 'NomAtend',
            'ObsCierre', 'FechaFin',
        ];

        foreach ($headers as $col => $val) {
            $sheet->setCellValueByColumnAndRow($col + 1, 1, $val);
        }
    }

    private function syncTableFormat(Worksheet $sheet, int $dataRowCount): void
    {
        $numCols = 17;
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
                $r->Fecha ? (is_string($r->Fecha) ? $r->Fecha : $r->Fecha->format('Y-m-d')) : '',
                $r->Hora ?? '',
                $r->Depto ?? '',
                $r->MaquinaId ?? '',
                $r->TipoFallaId ?? '',
                $r->Falla ?? '',
                $r->HoraFin ?? '',
                $r->CveEmpl ?? '',
                $r->NomEmpl ?? '',
                $r->Turno ?? '',
                $r->Obs ?? '',
                $r->CveAtendio ?? '',
                $r->NomAtendio ?? '',
                $r->ObsCierre ?? '',
                $r->FechaFin ? (is_string($r->FechaFin) ? $r->FechaFin : $r->FechaFin->format('Y-m-d')) : '',
            ];

            foreach ($cols as $col => $val) {
                $sheet->setCellValueByColumnAndRow($col + 1, $row, $val);
            }
        }
    }
}
