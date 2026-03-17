<?php

namespace Tests\Unit;

use App\Exports\ControlMermaExport;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class ControlMermaExportTest extends TestCase
{
    public function test_export_uses_template_and_rewrites_formulas_safely(): void
    {
        $book = $this->loadWorkbook(collect([
            $this->makeRow(
                folio: '00129',
                maquinaDisplay: 'WP2 / MC1  1',
                urdSlots: [
                    ['label' => 'RB', 'count' => 5],
                    ['label' => 'MS', 'count' => 2],
                    ['label' => 'PC', 'count' => 1],
                ],
                engSlots: [
                    ['label' => 'JR', 'count' => 2],
                    ['label' => null, 'count' => null],
                    ['label' => null, 'count' => null],
                ],
            ),
        ]));

        $sheet = $book->getSheet(0);

        $this->assertSame('Control Merma', $sheet->getTitle());
        $this->assertSame('d-mmm', $sheet->getStyle('A6')->getNumberFormat()->getFormatCode());
        $this->assertSame('WP2 / MC1  1', $sheet->getCell('B6')->getValue());
        $this->assertSame('00129', $sheet->getCell('F6')->getValue());
        $this->assertSame('RB', $sheet->getCell('I6')->getValue());
        $this->assertSame(5, $sheet->getCell('J6')->getValue());
        $this->assertSame('MS', $sheet->getCell('L6')->getValue());
        $this->assertSame(2, $sheet->getCell('M6')->getValue());
        $this->assertSame('PC', $sheet->getCell('O6')->getValue());
        $this->assertSame(1, $sheet->getCell('P6')->getValue());
        $this->assertSame('JR', $sheet->getCell('S6')->getValue());
        $this->assertSame(2, $sheet->getCell('T6')->getValue());
        $this->assertSame('=IF(COUNTA(D6:E6)=0,"",SUM(D6:E6))', $sheet->getCell('C6')->getValue());
        $this->assertStringContainsString('IFERROR', $sheet->getCell('K6')->getValue());
        $this->assertStringContainsString('AB6', $sheet->getCell('U6')->getValue());
        $this->assertNull($sheet->getCell('F7')->getValue());
        $this->assertSame('=SUM(C6:C19)', $sheet->getCell('C20')->getValue());
    }

    public function test_export_extends_template_when_rows_exceed_visible_capacity(): void
    {
        $rows = collect();
        for ($index = 1; $index <= 16; $index++) {
            $rows->push($this->makeRow(
                folio: str_pad((string) $index, 5, '0', STR_PAD_LEFT),
                maquinaDisplay: sprintf('WP2 / MC1  %d', $index)
            ));
        }

        $book = $this->loadWorkbook($rows);
        $sheet = $book->getSheet(0);

        $this->assertSame('00016', $sheet->getCell('F21')->getValue());
        $this->assertSame('Merma Punta', $sheet->getCell('F22')->getValue());
        $this->assertSame('=SUM(C6:C21)', $sheet->getCell('C22')->getValue());
        $this->assertStringContainsString('IFERROR', $sheet->getCell('K21')->getValue());
        $this->assertSame(15.6, $sheet->getRowDimension(20)->getRowHeight());
    }

    private function loadWorkbook(Collection $rows): Spreadsheet
    {
        $binary = Excel::raw(new ControlMermaExport($rows), ExcelFormat::XLSX);
        $tempFile = tempnam(sys_get_temp_dir(), 'control-merma-test-');

        file_put_contents($tempFile, $binary);

        $spreadsheet = IOFactory::load($tempFile);
        @unlink($tempFile);

        return $spreadsheet;
    }

    private function makeRow(
        string $folio,
        string $maquinaDisplay,
        array $urdSlots = [
            ['label' => 'RB', 'count' => 5],
            ['label' => null, 'count' => null],
            ['label' => null, 'count' => null],
        ],
        array $engSlots = [
            ['label' => null, 'count' => null],
            ['label' => null, 'count' => null],
            ['label' => null, 'count' => null],
        ]
    ): array {
        return [
            'fecha' => Carbon::parse('2026-03-10'),
            'maquina_label' => 'WP2',
            'maquina_urdido_label' => 'MC1',
            'maquina_full_label' => 'WP2 / MC1',
            'maquina_seq' => 1,
            'maquina_display' => $maquinaDisplay,
            'folio' => $folio,
            'cuenta' => '3776',
            'hilo' => 12,
            'merma_sin_goma' => 8.0,
            'merma_con_goma' => 2.0,
            'urd_slots' => $urdSlots,
            'eng_slots' => $engSlots,
        ];
    }
}
