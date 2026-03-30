<?php

namespace Tests\Unit;

use App\Services\OeeAtadores\OeeAtadoresFileService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

class OeeAtadoresFileServiceTest extends TestCase
{
    use UsesSqlsrvSqlite;

    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqlsrvSqlite();
        $this->createAtaMontadoTelasTable();
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        parent::tearDown();
    }

    public function test_verificar_reconoce_footers_con_formula_en_detalle(): void
    {
        $path = $this->createWorkbookFixture();
        $service = new OeeAtadoresFileService($path);

        $resultado = $service->verificarSemanasConDatos(
            CarbonImmutable::parse('2024-12-30'),
            CarbonImmutable::parse('2025-01-06')
        );

        $this->assertSame([1, 2], $resultado['semanas_rango']);
        $this->assertSame([1, 2], $resultado['semanas_con_datos']);
        $this->assertTrue($resultado['diagnostico'][0]['seccion_encontrada']);
        $this->assertTrue($resultado['diagnostico'][1]['seccion_encontrada']);
        $this->assertSame(46, $resultado['diagnostico'][0]['fila_footer']);
        $this->assertSame(92, $resultado['diagnostico'][1]['fila_footer']);
    }

    public function test_actualizar_archivo_expande_semana_y_repara_referencias_dependientes(): void
    {
        $this->insertWeekRecords(CarbonImmutable::parse('2024-12-30'), [
            ['codigo' => '101', 'nombre' => 'ALFA', 'turno' => 1],
            ['codigo' => '102', 'nombre' => 'BETA', 'turno' => 1],
            ['codigo' => '103', 'nombre' => 'GAMMA', 'turno' => 1],
        ]);

        $path = $this->createWorkbookFixture();
        $service = new OeeAtadoresFileService($path);

        $service->actualizarArchivo(
            CarbonImmutable::parse('2024-12-30'),
            CarbonImmutable::parse('2024-12-30')
        );

        $workbook = IOFactory::load($path);
        $detalle = $workbook->getSheetByName('DETALLE');
        $this->assertNotNull($detalle);

        $this->assertSame(52, $this->findDetalleFooterRow($detalle, 1));
        $this->assertSame(54, $this->findDetalleStartRow($detalle, 2));
        $this->assertSame(2, $this->findDetalleVisualWeek($detalle, 2));

        $totalAtados = $workbook->getSheetByName('TOTAL ATADOS');
        $this->assertSame('=DETALLE!I52', $totalAtados?->getCell('D4')->getValue());
        $this->assertSame('=DETALLE!I98', $totalAtados?->getCell('D6')->getValue());

        $concentrado = $workbook->getSheetByName('CONCENTRADO ENERO');
        $this->assertStringNotContainsString('#REF!', (string) $concentrado?->getCell('C5')->getValue());

        $annual = $workbook->getSheetByName('ATADORES  2025');
        $this->assertSame('JUAN MARTIN', $annual?->getCell('B17')->getValue());
        $this->assertStringNotContainsString('#REF!', (string) $annual?->getCell('E37')->getValue());

        $grafica = $workbook->getSheetByName('grafica');
        $this->assertSame("=CONCATENATE('TOTAL ATADOS'!B4,'TOTAL ATADOS'!C4)", $grafica?->getCell('A2')->getValue());
    }

    public function test_actualizar_archivo_expande_semana_para_mas_de_cinco_atadores(): void
    {
        $this->insertWeekRecords(CarbonImmutable::parse('2024-12-30'), [
            ['codigo' => '303', 'nombre' => 'JUAN MARTIN', 'turno' => 1],
            ['codigo' => '3180', 'nombre' => 'MIGUEL', 'turno' => 1],
            ['codigo' => '470', 'nombre' => 'ANTONIO', 'turno' => 2],
            ['codigo' => '3652', 'nombre' => 'LUIS', 'turno' => 2],
            ['codigo' => '3619', 'nombre' => 'PABLO', 'turno' => 3],
            ['codigo' => '332', 'nombre' => 'JOSE ALVARO', 'turno' => 3],
        ]);

        $path = $this->createWorkbookFixture();
        $service = new OeeAtadoresFileService($path);

        $service->actualizarArchivo(
            CarbonImmutable::parse('2024-12-30'),
            CarbonImmutable::parse('2024-12-30')
        );

        $workbook = IOFactory::load($path);
        $semana = $workbook->getSheetByName('SEMANA 01');
        $this->assertNotNull($semana);
        $this->assertSame('=DETALLE!CK9', $semana?->getCell('B9')->getValue());
        $this->assertSame('EFIC. ATADOR', $semana?->getCell('C18')->getValue());
        $this->assertSame('=C9', $semana?->getCell('I17')->getValue());
        $this->assertStringNotContainsString('#REF!', (string) $semana?->getCell('I23')->getValue());
        $this->assertSame($semana?->getStyle('B5')->exportArray(), $semana?->getStyle('B8')->exportArray());
        $this->assertSame($semana?->getStyle('H17')->exportArray(), $semana?->getStyle('I17')->exportArray());
        $this->assertNotSame($semana?->getStyle('B8')->exportArray(), $semana?->getStyle('B9')->exportArray());

        $concentrado = $workbook->getSheetByName('CONCENTRADO ENERO');
        $this->assertSame('=DETALLE!CL9', $concentrado?->getCell('H4')->getValue());
        $this->assertStringContainsString("'SEMANA 01'!\$B\$4:\$B\$9", (string) $concentrado?->getCell('H5')->getValue());
        $this->assertSame($concentrado?->getStyle('G5')->exportArray(), $concentrado?->getStyle('H5')->exportArray());

        $annual = $workbook->getSheetByName('ATADORES  2025');
        $this->assertStringContainsString("'SEMANA 01'!\$D\$23:\$I\$23", (string) $annual?->getCell('E37')->getValue());
        $this->assertStringContainsString("'SEMANA 01'!\$B\$4:\$B\$9", (string) $annual?->getCell('E37')->getValue());
    }

    private function createAtaMontadoTelasTable(): void
    {
        Schema::connection('sqlsrv')->create('AtaMontadoTelas', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Estatus')->nullable();
            $table->string('Turno')->nullable();
            $table->string('Tipo')->nullable();
            $table->string('NoTelarId')->nullable();
            $table->float('MergaKg')->nullable();
            $table->string('HoraArranque')->nullable();
            $table->float('Calidad')->nullable();
            $table->float('Limpieza')->nullable();
            $table->string('CveTejedor')->nullable();
            $table->string('NomTejedor')->nullable();
            $table->string('HrInicio')->nullable();
            $table->date('FechaArranque')->nullable();
        });
    }

    private function insertWeekRecords(CarbonImmutable $weekStart, array $records): void
    {
        foreach ($records as $index => $record) {
            DB::connection('sqlsrv')->table('AtaMontadoTelas')->insert([
                'Estatus' => 'Autorizado',
                'Turno' => (string) $record['turno'],
                'Tipo' => 'Rizo',
                'NoTelarId' => (string) (200 + $index),
                'MergaKg' => 0.5,
                'HoraArranque' => '08:10:00',
                'Calidad' => 9.5,
                'Limpieza' => 9.8,
                'CveTejedor' => (string) $record['codigo'],
                'NomTejedor' => $record['nombre'],
                'HrInicio' => '07:00:00',
                'FechaArranque' => $weekStart->toDateString(),
            ]);
        }
    }

    private function createWorkbookFixture(): string
    {
        $path = storage_path('framework/testing/oee-atadores-'.uniqid('', true).'.xlsx');
        $this->tempFiles[] = $path;

        $spreadsheet = new Spreadsheet;
        $detalle = $spreadsheet->getActiveSheet();
        $detalle->setTitle('DETALLE');

        $this->seedDetalleSection($detalle, 1, 1, false, '9001');
        $this->seedDetalleSection($detalle, 47, 2, true, '9002');

        $semana = new Worksheet($spreadsheet, 'SEMANA 01');
        $spreadsheet->addSheet($semana);
        $this->seedSemanaSheet($semana);

        $concentrado = new Worksheet($spreadsheet, 'CONCENTRADO ENERO');
        $spreadsheet->addSheet($concentrado);
        $this->seedConcentradoSheet($concentrado);

        $totalAtados = new Worksheet($spreadsheet, 'TOTAL ATADOS');
        $spreadsheet->addSheet($totalAtados);
        $this->seedTotalAtadosSheet($totalAtados);

        $grafica = new Worksheet($spreadsheet, 'grafica');
        $spreadsheet->addSheet($grafica);
        $this->seedGraficaSheet($grafica);

        $annual = new Worksheet($spreadsheet, 'ATADORES  2025');
        $spreadsheet->addSheet($annual);
        $this->seedAnnualSheet($annual);

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($path);

        return $path;
    }

    private function seedDetalleSection(
        Worksheet $sheet,
        int $topRow,
        int $weekNum,
        bool $formulaFooter,
        string $dataKey
    ): void {
        $startRow = $topRow + 1;
        $footerRow = $topRow + 45;

        $sheet->setCellValue("A{$startRow}", 'SEMANA');
        $sheet->setCellValue('B'.($topRow + 3), '1 ER TURNO');
        $sheet->setCellValue('C'.($topRow + 3), $dataKey);
        $sheet->setCellValue("B{$footerRow}", 'SEMANA');
        $sheet->setCellValue("CJ{$footerRow}", 'ATADOS');

        if ($formulaFooter) {
            $sheet->setCellValue("C{$footerRow}", '=C'.($footerRow - 46).'+1');
            $sheet->getCell("C{$footerRow}")->setCalculatedValue($weekNum);
            $sheet->setCellValue('A'.($topRow + 33), '=A'.($topRow - 13).'+1');
        } else {
            $sheet->setCellValue("C{$footerRow}", $weekNum);
            $sheet->setCellValue('A'.($topRow + 33), $weekNum);
        }
    }

    private function seedSemanaSheet(Worksheet $sheet): void
    {
        $sheet->setCellValue('B2', 'SEMANA 01');
        $sheet->setCellValue('C16', 'ATADOR');
        $sheet->setCellValue('C17', 'EFIC. ATADOR');
        $sheet->setCellValue('C18', 'EFIC. X AUXILIAR');
        $sheet->setCellValue('C19', 'CALIDAD/5S SEGURIDAD');
        $sheet->setCellValue('C20', 'MERMA (PROMEDIO)');
        $sheet->setCellValue('C21', '% X MERMA');
        $sheet->setCellValue('C22', 'OEE');
        $sheet->setCellValue('D26', 'NOMBRE REPORTES 5S');
        $sheet->setCellValue('K11', '=#REF!');
        $sheet->setCellValue('D16', '=#REF!');
        $sheet->setCellValue('D17', '=#REF!');
        $sheet->setCellValue('D19', '=#REF!');
        $sheet->setCellValue('D20', '=#REF!');
        $sheet->setCellValue('D21', '=#REF!');
        $sheet->setCellValue('D22', '=#REF!');
        $sheet->setCellValue('B20', '=#REF!');

        $this->applySolidFill($sheet, 'B4:M4', 'FFFFC7CE');
        $this->applySolidFill($sheet, 'B5:M7', 'FFD9EAF7');
        $this->applySolidFill($sheet, 'B8:M8', 'FFC6E0B4');
        $this->applySolidFill($sheet, 'H16:H22', 'FFFFE699');
    }

    private function seedConcentradoSheet(Worksheet $sheet): void
    {
        $sheet->setCellValue('D2', 'ENERO');
        $sheet->setCellValue('B4', 'ENERO');
        $sheet->setCellValue('C5', '=#REF!');
        $sheet->setCellValue('C10', '=#REF!');
        $sheet->getColumnDimension('G')->setWidth(18);
        $this->applySolidFill($sheet, 'G4:G29', 'FFE2F0D9');
        $this->applySolidFill($sheet, 'G32:G37', 'FFE2F0D9');
    }

    private function seedTotalAtadosSheet(Worksheet $sheet): void
    {
        foreach (['D' => 'LU', 'E' => 'MA', 'F' => 'MI', 'G' => 'JU', 'H' => 'VI', 'I' => 'SA', 'J' => 'DO', 'K' => 'TOTAL', 'L' => 'DIFERENCIA', 'M' => 'Indice'] as $column => $label) {
            $sheet->setCellValue("{$column}3", $label);
        }

        $sheet->setCellValue('D4', '=#REF!');
        $sheet->setCellValue('D6', '=#REF!');
    }

    private function seedGraficaSheet(Worksheet $sheet): void
    {
        $sheet->setCellValue('A2', '=#REF!');
        $sheet->setCellValue('B2', '=#REF!');
        $sheet->setCellValue('C2', '=#REF!');
    }

    private function seedAnnualSheet(Worksheet $sheet): void
    {
        $sheet->setCellValue('E1', 'SEMANA 01 2025');
        $sheet->setCellValue('G1', 'SEMANA 02 2025');

        $names = [
            4 => [470, 'ANTONIO'],
            5 => [3619, 'PABLO'],
            6 => [332, 'JOSE ALVARO'],
            7 => [3180, 'MIGUEL'],
            8 => [303, 'JUAN MARTIN'],
            9 => [99, 'RAUL'],
            10 => [4691, 'PEDRO'],
            11 => [402, 'HECTOR'],
        ];

        foreach ($names as $row => [$code, $name]) {
            $sheet->setCellValue("A{$row}", $code);
            $sheet->setCellValue("B{$row}", $name);
        }

        $sheet->setCellValue('B17', '=#REF!');
        $sheet->setCellValue('B18', '=#REF!');
        $sheet->setCellValue('B19', '=#REF!');
        $sheet->setCellValue('B20', '=#REF!');
        $sheet->setCellValue('B21', '=#REF!');
        $sheet->setCellValue('B22', '=#REF!');
        $sheet->setCellValue('B23', '=#REF!');
        $sheet->setCellValue('B24', '=#REF!');
        $sheet->setCellValue('B25', '=#REF!');

        foreach (range(37, 42) as $row) {
            $sheet->setCellValue("E{$row}", '=#REF!');
            $sheet->setCellValue("G{$row}", '=#REF!');
        }

        $oee = 75;
        $bonus = 25;
        for ($row = 39; $row <= 64; $row++, $oee++, $bonus += 15) {
            $sheet->setCellValue("A{$row}", $oee);
            $sheet->setCellValue("B{$row}", $bonus);
        }
    }

    private function findDetalleFooterRow(Worksheet $sheet, int $weekNum): ?int
    {
        for ($row = 1; $row <= $sheet->getHighestRow(); $row++) {
            if ($sheet->getCell("B{$row}")->getValue() !== 'SEMANA') {
                continue;
            }

            if ((int) $sheet->getCell("C{$row}")->getCalculatedValue() !== $weekNum) {
                continue;
            }

            if ($sheet->getCell("CJ{$row}")->getValue() !== 'ATADOS') {
                continue;
            }

            return $row;
        }

        return null;
    }

    private function findDetalleStartRow(Worksheet $sheet, int $weekNum): ?int
    {
        $footerRow = $this->findDetalleFooterRow($sheet, $weekNum);
        if ($footerRow === null) {
            return null;
        }

        for ($row = $footerRow; $row >= 1; $row--) {
            if ($sheet->getCell("A{$row}")->getValue() === 'SEMANA') {
                return $row;
            }
        }

        return null;
    }

    private function findDetalleVisualWeek(Worksheet $sheet, int $weekNum): mixed
    {
        $startRow = $this->findDetalleStartRow($sheet, $weekNum);
        if ($startRow === null) {
            return null;
        }

        $topRow = $startRow - 1;
        $footerRow = $this->findDetalleFooterRow($sheet, $weekNum);
        if ($footerRow === null) {
            return null;
        }

        for ($row = $footerRow; $row >= $topRow; $row--) {
            $value = $sheet->getCell("A{$row}")->getCalculatedValue();
            if ($value === null || $value === '' || $value === 'SEMANA') {
                continue;
            }

            return $value;
        }

        return null;
    }

    private function applySolidFill(Worksheet $sheet, string $range, string $color): void
    {
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID);
        $sheet->getStyle($range)->getFill()->getStartColor()->setARGB($color);
    }
}
