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
        $this->assertSame('NOMBRE REPORTES 5S', $semana?->getCell('D27')->getValue());
        $this->assertSame('5´S - SEGURIDAD', $semana?->getCell('Q3')->getValue());
        $this->assertSame('ACCIONES QUE RESTAN PUNTOS', $semana?->getCell('S3')->getValue());
        $this->assertSame('USO DE ATADORA USTER EN LA SEMANA 1', $semana?->getCell('R45')->getValue());
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

    public function test_actualizar_archivo_crea_semana_nueva_desde_plantilla_canonica(): void
    {
        $this->insertWeekRecords(CarbonImmutable::parse('2025-01-13'), [
            ['codigo' => '201', 'nombre' => 'UNO', 'turno' => 1],
            ['codigo' => '202', 'nombre' => 'DOS', 'turno' => 2],
        ]);

        $path = $this->createWorkbookFixture();
        $workbook = IOFactory::load($path);
        $detalle = $workbook->getSheetByName('DETALLE');
        $this->assertNotNull($detalle);
        $this->seedDetalleSection($detalle, 93, 3, false, '9003');

        $semana02 = new Worksheet($workbook, 'SEMANA 02');
        $workbook->addSheet($semana02);
        $this->seedSemanaSheet($semana02);
        $semana02->setCellValue('B3', 'DISTORSION');
        $semana02->setCellValue('Q44', 'USO DE ATADORA USTER EN LA SEMANA 99');

        IOFactory::createWriter($workbook, 'Xlsx')->save($path);

        $service = new OeeAtadoresFileService($path);
        $service->actualizarArchivo(
            CarbonImmutable::parse('2025-01-13'),
            CarbonImmutable::parse('2025-01-13')
        );

        $updated = IOFactory::load($path);
        $semana03 = $updated->getSheetByName('SEMANA 03');
        $this->assertNotNull($semana03);
        $this->assertSame('CLAVE ATADOR', $semana03?->getCell('B3')->getValue());
        $this->assertSame('5´S - SEGURIDAD', $semana03?->getCell('P3')->getValue());
        $this->assertSame('USO DE ATADORA USTER EN LA SEMANA 3', $semana03?->getCell('Q44')->getValue());
        $this->assertSame('NOMBRE REPORTES 5S', $semana03?->getCell('D26')->getValue());
    }

    public function test_actualizar_archivo_no_regenera_semanas_no_solicitadas(): void
    {
        $this->insertWeekRecords(CarbonImmutable::parse('2024-12-30'), [
            ['codigo' => '101', 'nombre' => 'ALFA', 'turno' => 1],
            ['codigo' => '102', 'nombre' => 'BETA', 'turno' => 2],
        ]);

        $path = $this->createWorkbookFixture();
        $workbook = IOFactory::load($path);

        $semana02 = new Worksheet($workbook, 'SEMANA 02');
        $workbook->addSheet($semana02);
        $this->seedSemanaSheet($semana02);
        $semana02->setCellValue('B3', 'DISTORSION SEMANA 02');
        $semana02->setCellValue('Q44', 'USO DE ATADORA USTER EN LA SEMANA 99');

        IOFactory::createWriter($workbook, 'Xlsx')->save($path);

        $service = new OeeAtadoresFileService($path);
        $service->actualizarArchivo(
            CarbonImmutable::parse('2024-12-30'),
            CarbonImmutable::parse('2024-12-30')
        );

        $updated = IOFactory::load($path);
        $semana02Actualizada = $updated->getSheetByName('SEMANA 02');
        $this->assertNotNull($semana02Actualizada);
        $this->assertSame('DISTORSION SEMANA 02', $semana02Actualizada?->getCell('B3')->getValue());
        $this->assertSame('USO DE ATADORA USTER EN LA SEMANA 99', $semana02Actualizada?->getCell('Q44')->getValue());

        $concentrado = $updated->getSheetByName('CONCENTRADO ENERO');
        $this->assertStringContainsString("'SEMANA 02'!", (string) $concentrado?->getCell('C10')->getValue());
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
        $sheet->mergeCells('B2:M2');
        $sheet->setCellValue('B3', 'CLAVE ATADOR');
        $sheet->setCellValue('C3', 'ATADOR');
        $sheet->setCellValue('D3', 'CALIDAD');
        $sheet->setCellValue('E3', 'TIEMPO DE ATADO');
        $sheet->setCellValue('F3', 'ESTANDAR < 1:30');
        $sheet->setCellValue('I3', 'EFICIENCIA');
        $sheet->setCellValue('J3', 'CALIDAD');
        $sheet->setCellValue('K3', 'MERMA');
        $sheet->setCellValue('L3', 'ATADOR');
        $sheet->setCellValue('M3', '5´S - SEGURIDAD');
        $sheet->setCellValue('C17', 'EFIC. ATADOR');
        $sheet->setCellValue('C18', 'EFIC. X AUXILIAR');
        $sheet->setCellValue('C19', 'CALIDAD/5S SEGURIDAD');
        $sheet->setCellValue('C20', 'MERMA (PROMEDIO)');
        $sheet->setCellValue('C21', '% X MERMA');
        $sheet->setCellValue('C22', 'OEE');
        $sheet->setCellValue('C13', 'PROMEDIO GRAL.');
        $sheet->setCellValue('D26', 'NOMBRE REPORTES 5S');
        $sheet->setCellValue('E26', 'SANCION PUNTOS');
        $sheet->setCellValue('F26', 'MOTIVO-FECHA');
        $sheet->mergeCells('F26:J26');
        $sheet->setCellValue('P3', '5´S - SEGURIDAD');
        $sheet->setCellValue('Q3', 100);
        $sheet->setCellValue('R3', 'ACCIONES QUE RESTAN PUNTOS');
        $sheet->setCellValue('Q4', 70);
        $sheet->setCellValue('R4', 'MANIPULACIÓN DE INFORMACION EN REGISTRO DE ATADOS');
        $sheet->setCellValue('Q5', 50);
        $sheet->setCellValue('R5', 'MAL AJUSTE DE PIEZAS');
        $sheet->setCellValue('Q6', 50);
        $sheet->setCellValue('R6', 'MOVER PARAMETROS DE MODELO');
        $sheet->setCellValue('Q7', 50);
        $sheet->setCellValue('R7', 'INDISCIPLINA');
        $sheet->setCellValue('Q8', 30);
        $sheet->setCellValue('R8', 'RECIBIR AMONESTACION');
        $sheet->setCellValue('L44', 'USO DE ATADORA USTER = PUNTOS');
        $sheet->mergeCells('L44:M45');
        $sheet->setCellValue('Q44', 'USO DE ATADORA USTER EN LA SEMANA 42');
        $sheet->mergeCells('Q44:R44');
        $sheet->setCellValue('O45', 'JUAN MARTIN');
        $sheet->setCellValue('Q45', 'UTILIZO ATADORA USTER EN "1 ATADOS DE 12 REALIZADOS EN LA SEMANA"');
        $sheet->mergeCells('Q45:S45');
        $sheet->setCellValue('L46', '3 OCASIONES');
        $sheet->setCellValue('M46', 100);
        $sheet->setCellValue('O46', 'JOSE MIGUEL');
        $sheet->setCellValue('Q46', 'UTILIZO ATADORA USTER EN "1 ATADOS DE 08 REALIZADOS EN LA SEMANA"');
        $sheet->mergeCells('Q46:S46');
        $sheet->setCellValue('L47', '2 OCASIONES');
        $sheet->setCellValue('M47', 90);
        $sheet->setCellValue('O47', 'JOSE ALVARO');
        $sheet->setCellValue('Q47', 'UTILIZO ATADORA USTER EN "0 ATADOS DE 14 REALIZADOS EN LA SEMANA"');
        $sheet->mergeCells('Q47:S47');
        $sheet->setCellValue('L48', '1 OCASIÓN');
        $sheet->setCellValue('M48', 80);
        $sheet->setCellValue('O48', 'ANTONIO');
        $sheet->setCellValue('Q48', 'UTILIZO ATADORA USTER EN "3 ATADOS DE 13 REALIZADOS EN LA SEMANA"');
        $sheet->mergeCells('Q48:S48');
        $sheet->setCellValue('L49', '0 OCASIONES');
        $sheet->setCellValue('M49', 70);
        $sheet->setCellValue('O49', 'PABLO');
        $sheet->setCellValue('Q49', 'UTILIZO ATADORA USTER EN "0 ATADOS DE 13 REALIZADOS EN LA SEMANA"');
        $sheet->mergeCells('Q49:S49');
        $sheet->mergeCells('Q50:S50');
        $sheet->setCellValue('P51', 'NOTA: ATADOR QUE NO OCUPE ATADORA USTER SERA AFECTADO EN SU VALOR OEE AL FINAL DE LA SEMANA');
        $sheet->mergeCells('P51:R52');
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
        $sheet->getRowDimension(2)->setRowHeight(35.25);
        $sheet->getRowDimension(3)->setRowHeight(45);
        $sheet->getRowDimension(16)->setRowHeight(33.75);
        $sheet->getRowDimension(17)->setRowHeight(21);
        $sheet->getColumnDimension('B')->setWidth(10.71);
        $sheet->getColumnDimension('C')->setWidth(22.28);
        $sheet->getColumnDimension('D')->setWidth(15.14);
        $sheet->getColumnDimension('E')->setWidth(13.14);
        $sheet->getColumnDimension('F')->setWidth(14.14);
        $sheet->getColumnDimension('G')->setWidth(13.57);
        $sheet->getColumnDimension('H')->setWidth(13.85);
        $sheet->getColumnDimension('L')->setWidth(23.42);
        $sheet->getColumnDimension('M')->setWidth(13.42);
        $sheet->getColumnDimension('P')->setWidth(15.85);
        $sheet->getColumnDimension('Q')->setWidth(13);
        $sheet->getColumnDimension('R')->setWidth(68.71);
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
