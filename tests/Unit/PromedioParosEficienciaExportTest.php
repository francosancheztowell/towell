<?php

namespace Tests\Unit;

use App\Exports\PromedioParosEficienciaExport;
use Carbon\Carbon;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class PromedioParosEficienciaExportTest extends TestCase
{
    public function test_export_respects_template_order_and_writes_metrics_rows(): void
    {
        $book = $this->loadWorkbook([
            'days' => [
                $this->makeDay('2026-03-03'),
            ],
            'metrics' => [
                '2026-03-03' => [
                    1 => [
                        '201' => [
                            'eficiencia' => 95.67,
                            'paros_trama' => 12,
                            'paros_urdimbre' => 3,
                            'paros_rizo' => 4,
                            'paros_otros' => 7,
                            'marcas' => 120,
                            'rpm' => 300,
                        ],
                        '299' => [
                            'paros_trama' => 5,
                            'paros_urdimbre' => 2,
                            'paros_rizo' => 1,
                            'paros_otros' => 3,
                            'marcas' => 99,
                            'rpm' => 250,
                        ],
                        '207' => [
                            'eficiencia' => 100.12,
                            'paros_trama' => 8,
                            'paros_urdimbre' => 4,
                            'paros_rizo' => 2,
                            'paros_otros' => 1,
                            'marcas' => 88,
                            'rpm' => 275,
                        ],
                    ],
                ],
            ],
        ]);

        $sheet = $book->getSheet(0);

        $this->assertSame('Promedio Paros y Eficiencia', $sheet->getTitle());
        $this->assertSame('MA 1T', $sheet->getCell('A8')->getValue());
        $this->assertSame('2026-03-03', ExcelDate::excelToDateTimeObject($sheet->getCell('A2')->getValue())->format('Y-m-d'));
        $this->assertStringContainsString('IFERROR', $sheet->getCell('C2')->getValue());
        $this->assertStringContainsString('C11', $sheet->getCell('C2')->getValue());
        $this->assertStringContainsString('C12', $sheet->getCell('C2')->getValue());
        $this->assertSame(12, $sheet->getCell('C3')->getValue());
        $this->assertSame(3, $sheet->getCell('C5')->getValue());
        $this->assertSame(4, $sheet->getCell('C7')->getValue());
        $this->assertSame(7, $sheet->getCell('C9')->getValue());
        $this->assertSame(120, $sheet->getCell('C11')->getValue());
        $this->assertSame(300.0, $sheet->getCell('C12')->getValue());
        $this->assertSame(5, $sheet->getCell('N3')->getValue());
        $this->assertSame(99, $sheet->getCell('N11')->getValue());
        $this->assertSame(250.0, $sheet->getCell('N12')->getValue());
        $this->assertStringContainsString('IFERROR', $sheet->getCell('N2')->getValue());
        $this->assertStringContainsString('AL10', $sheet->getCell('AL2')->getValue());
        $this->assertStringContainsString('AL11', $sheet->getCell('AL2')->getValue());
        $this->assertSame(8, $sheet->getCell('AL3')->getValue());
        $this->assertSame(4, $sheet->getCell('AL5')->getValue());
        $this->assertSame(2, $sheet->getCell('AL6')->getValue());
        $this->assertSame(1, $sheet->getCell('AL8')->getValue());
        $this->assertSame(88, $sheet->getCell('AL10')->getValue());
        $this->assertSame(275.0, $sheet->getCell('AL11')->getValue());
        $this->assertSame('0', $sheet->getStyle('C2')->getNumberFormat()->getFormatCode());
        $this->assertSame('0.##', $sheet->getStyle('C12')->getNumberFormat()->getFormatCode());
        $this->assertSame('0', $sheet->getStyle('AL2')->getNumberFormat()->getFormatCode());
        $this->assertSame('FFD9E2F3', $sheet->getStyle('B2')->getFill()->getStartColor()->getARGB());
        $this->assertSame('FFD9E2F3', $sheet->getStyle('AK2')->getFill()->getStartColor()->getARGB());
        $this->assertSame(
            IOFactory::load(resource_path('templates/PromedioParosMarcas.xlsx'))->getTheme()->getThemeColors()['accent1'],
            $book->getTheme()->getThemeColors()['accent1']
        );
        $this->assertNull($sheet->getCell('A35')->getValue());
        $this->assertStringContainsString('IFERROR', $sheet->getCell('C36')->getValue());
        $this->assertNull($sheet->getCell('C37')->getValue());
    }

    public function test_export_duplicates_standard_day_structure_when_range_exceeds_template_capacity(): void
    {
        $days = [];
        $metrics = [];

        foreach (range(0, 8) as $offset) {
            $date = Carbon::parse('2026-03-03')->addDays($offset);
            $dateKey = $date->format('Y-m-d');
            $days[] = $this->makeDay($dateKey);
            $metrics[$dateKey] = [
                1 => [
                    '201' => [
                        'paros_trama' => 10 + $offset,
                        'paros_urdimbre' => 2,
                        'paros_rizo' => 1,
                        'paros_otros' => 3,
                        'marcas' => 100 + $offset,
                        'rpm' => 300 + $offset,
                    ],
                ],
            ];
        }

        $book = $this->loadWorkbook([
            'days' => $days,
            'metrics' => $metrics,
        ]);

        $sheet = $book->getSheet(0);

        $this->assertSame('2026-03-11', ExcelDate::excelToDateTimeObject($sheet->getCell('A273')->getValue())->format('Y-m-d'));
        $this->assertSame('MI 1T', $sheet->getCell('A280')->getValue());
        $this->assertSame(18, $sheet->getCell('C275')->getValue());
        $this->assertSame(108, $sheet->getCell('C283')->getValue());
        $this->assertSame(308.0, $sheet->getCell('C284')->getValue());
        $this->assertStringContainsString('IFERROR', $sheet->getCell('C274')->getValue());
        $this->assertStringContainsString('C283', $sheet->getCell('C274')->getValue());
        $this->assertStringContainsString('C284', $sheet->getCell('C274')->getValue());
        $this->assertCount(0, $sheet->getConditionalStyles('C274'));
    }

    public function test_export_covers_efficiency_for_the_three_turns_of_same_day(): void
    {
        $book = $this->loadWorkbook([
            'days' => [
                $this->makeDay('2026-03-03'),
            ],
            'metrics' => [
                '2026-03-03' => [
                    1 => [
                        '201' => [
                            'eficiencia' => 96.44,
                            'paros_trama' => 12,
                            'paros_urdimbre' => 3,
                            'paros_rizo' => 4,
                            'paros_otros' => 7,
                            'marcas' => 120,
                            'rpm' => 300,
                        ],
                        '207' => [
                            'eficiencia' => 101.5,
                            'paros_trama' => 8,
                            'paros_urdimbre' => 4,
                            'paros_rizo' => 2,
                            'paros_otros' => 1,
                            'marcas' => 88,
                            'rpm' => 275,
                        ],
                    ],
                    2 => [
                        '201' => [
                            'eficiencia' => 97.38,
                            'paros_trama' => 22,
                            'paros_urdimbre' => 5,
                            'paros_rizo' => 6,
                            'paros_otros' => 8,
                            'marcas' => 150,
                            'rpm' => 310,
                        ],
                        '207' => [
                            'eficiencia' => 98.92,
                            'paros_trama' => 18,
                            'paros_urdimbre' => 9,
                            'paros_rizo' => 3,
                            'paros_otros' => 2,
                            'marcas' => 98,
                            'rpm' => 280,
                        ],
                    ],
                    3 => [
                        '201' => [
                            'eficiencia' => 99.11,
                            'paros_trama' => 32,
                            'paros_urdimbre' => 7,
                            'paros_rizo' => 8,
                            'paros_otros' => 9,
                            'marcas' => 180,
                            'rpm' => 320,
                        ],
                        '207' => [
                            'eficiencia' => 102.26,
                            'paros_trama' => 28,
                            'paros_urdimbre' => 11,
                            'paros_rizo' => 4,
                            'paros_otros' => 3,
                            'marcas' => 108,
                            'rpm' => 290,
                        ],
                    ],
                ],
            ],
        ]);

        $sheet = $book->getSheet(0);

        $this->assertSame('MA 1T', $sheet->getCell('A8')->getValue());
        $this->assertSame('MA 2T', $sheet->getCell('A19')->getValue());
        $this->assertSame('MA 3T', $sheet->getCell('A30')->getValue());

        $this->assertStringContainsString('C11', $sheet->getCell('C2')->getValue());
        $this->assertStringContainsString('C22', $sheet->getCell('C13')->getValue());
        $this->assertStringContainsString('C33', $sheet->getCell('C24')->getValue());

        $this->assertStringContainsString('AL10', $sheet->getCell('AL2')->getValue());
        $this->assertStringContainsString('AL21', $sheet->getCell('AL13')->getValue());
        $this->assertStringContainsString('AL32', $sheet->getCell('AL24')->getValue());

        $this->assertSame(120, $sheet->getCell('C11')->getValue());
        $this->assertSame(300.0, $sheet->getCell('C12')->getValue());
        $this->assertSame(150, $sheet->getCell('C22')->getValue());
        $this->assertSame(310.0, $sheet->getCell('C23')->getValue());
        $this->assertSame(180, $sheet->getCell('C33')->getValue());
        $this->assertSame(320.0, $sheet->getCell('C34')->getValue());

        $this->assertSame(88, $sheet->getCell('AL10')->getValue());
        $this->assertSame(275.0, $sheet->getCell('AL11')->getValue());
        $this->assertSame(98, $sheet->getCell('AL21')->getValue());
        $this->assertSame(280.0, $sheet->getCell('AL22')->getValue());
        $this->assertSame(108, $sheet->getCell('AL32')->getValue());
        $this->assertSame(290.0, $sheet->getCell('AL33')->getValue());

        $this->assertCount(0, $sheet->getConditionalStyles('C2'));
        $this->assertCount(0, $sheet->getConditionalStyles('C13'));
        $this->assertCount(0, $sheet->getConditionalStyles('C24'));
        $this->assertCount(0, $sheet->getConditionalStyles('AL2'));
        $this->assertCount(0, $sheet->getConditionalStyles('AL13'));
        $this->assertCount(0, $sheet->getConditionalStyles('AL24'));
        $this->assertSame('0', $sheet->getStyle('C24')->getNumberFormat()->getFormatCode());
        $this->assertSame('0', $sheet->getStyle('AL24')->getNumberFormat()->getFormatCode());
    }

    private function loadWorkbook(array $report): Spreadsheet
    {
        $binary = Excel::raw(new PromedioParosEficienciaExport($report), ExcelFormat::XLSX);
        $tempFile = tempnam(sys_get_temp_dir(), 'promedio-paros-');

        file_put_contents($tempFile, $binary);

        $spreadsheet = IOFactory::load($tempFile);
        @unlink($tempFile);

        return $spreadsheet;
    }

    private function makeDay(string $date): array
    {
        $carbon = Carbon::parse($date);
        $dayCode = match ($carbon->dayOfWeekIso) {
            1 => 'LU',
            2 => 'MA',
            3 => 'MI',
            4 => 'JU',
            5 => 'VI',
            6 => 'SA',
            7 => 'DO',
        };

        return [
            'date' => $carbon,
            'date_key' => $carbon->format('Y-m-d'),
            'day_code' => $dayCode,
            'turn_labels' => [
                1 => $dayCode . ' 1T',
                2 => $dayCode . ' 2T',
                3 => $dayCode . ' 3T',
            ],
        ];
    }
}
