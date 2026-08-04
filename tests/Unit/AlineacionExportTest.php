<?php

namespace Tests\Unit;

use App\Exports\AlineacionExport;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class AlineacionExportTest extends TestCase
{
    public function test_export_separates_telars_215_and_299_and_expands_long_observations(): void
    {
        $longObservation = str_repeat('Observación extensa para validar el ajuste de renglón. ', 10);
        $book = $this->loadWorkbook([
            ['NoTelarId' => '201', 'Observaciones' => 'Corta'],
            ['NoTelarId' => '215', 'Observaciones' => ''],
            ['NoTelarId' => '299', 'Observaciones' => $longObservation],
            ['NoTelarId' => '305', 'Observaciones' => 'Smit'],
        ]);
        $sheet = $book->getSheet(0);

        $this->assertSame(201, $sheet->getCell('A13')->getValue());
        $this->assertSame(215, $sheet->getCell('A14')->getValue());
        $this->assertNull($sheet->getCell('A15')->getValue());
        $this->assertNull($sheet->getCell('A16')->getValue());
        $this->assertSame(299, $sheet->getCell('A17')->getValue());
        $this->assertSame(305, $sheet->getCell('A18')->getValue());
        $this->assertSame(24.0, $sheet->getRowDimension(15)->getRowHeight());
        $this->assertSame(24.0, $sheet->getRowDimension(16)->getRowHeight());
        $this->assertSame(95.0, $sheet->getRowDimension(13)->getRowHeight());
        $this->assertGreaterThan(95.0, $sheet->getRowDimension(17)->getRowHeight());
        $this->assertTrue($sheet->getStyle('B17')->getAlignment()->getWrapText());
        $this->assertSame(60.0, $sheet->getColumnDimension('B')->getWidth());
    }

    public function test_export_reduces_left_margin_and_reserves_space_on_the_right(): void
    {
        $sheet = $this->loadWorkbook([
            ['NoTelarId' => '201', 'Observaciones' => 'Prueba'],
        ])->getSheet(0);

        $this->assertSame(0.1, $sheet->getPageMargins()->getLeft());
        $this->assertSame(0.5, $sheet->getPageMargins()->getRight());
        $this->assertFalse($sheet->getPageSetup()->getHorizontalCentered());
        $this->assertSame('A1:B13', $sheet->getPageSetup()->getPrintArea());
    }

    /** @param array<int, array<string, mixed>> $items */
    private function loadWorkbook(array $items): Spreadsheet
    {
        $export = new AlineacionExport(
            $items,
            ['NoTelarId', 'Observaciones'],
            ['NoTelarId' => 'Telar', 'Observaciones' => 'Observaciones'],
            [],
            [],
            'NoTelarId'
        );
        $binary = Excel::raw($export, ExcelFormat::XLSX);
        $tempFile = tempnam(sys_get_temp_dir(), 'alineacion-export-test-');

        file_put_contents($tempFile, $binary);
        $spreadsheet = IOFactory::load($tempFile);
        @unlink($tempFile);

        return $spreadsheet;
    }
}
