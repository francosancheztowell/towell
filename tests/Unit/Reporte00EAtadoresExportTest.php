<?php

namespace Tests\Unit;

use App\Exports\Reporte00EAtadoresExport;
use App\Exports\Reporte00EAtadoresRangoExport;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

class Reporte00EAtadoresExportTest extends TestCase
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

    public function test_render_semana_individual_preserva_formulas_merges_y_estilos(): void
    {
        $weekStart = CarbonImmutable::parse('2024-12-30');
        $this->insertWeekRecords($weekStart, [
            ['codigo' => '101', 'nombre' => 'ALFA', 'turno' => 1],
            ['codigo' => '102', 'nombre' => 'BETA', 'turno' => 1],
            ['codigo' => '103', 'nombre' => 'GAMMA', 'turno' => 1],
        ]);

        $workbook = IOFactory::load(resource_path('templates/Reporte_00E_Atadores.xlsx'));
        $sheet = $workbook->getSheet(0);

        $export = new Reporte00EAtadoresExport($weekStart);
        $footerRow = $export->renderIntoSheet($sheet);

        $this->assertSame(52, $footerRow);
        $this->assertSame('103', (string) $sheet->getCell('C16')->getValue());
        $this->assertContains('C16:C21', array_keys($sheet->getMergeCells()));
        $this->assertSame('SEMANA', $sheet->getCell('B52')->getValue());
        $this->assertSame(1, (int) $sheet->getCell('C52')->getValue());
        $this->assertSame('=COUNT(I16:I21,U16:U21,AG16:AG21,AS16:AS21,BE16:BE21,BQ16:BQ21,CC16:CC21)', (string) $sheet->getCell('CJ16')->getValue());
        $this->assertSame($sheet->getStyle('B10')->exportArray(), $sheet->getStyle('B16')->exportArray());
    }

    public function test_export_rango_reutiliza_snapshot_de_plantilla_en_secciones_adicionales(): void
    {
        $firstWeek = CarbonImmutable::parse('2024-12-30');
        $secondWeek = CarbonImmutable::parse('2025-01-06');

        $this->insertWeekRecords($firstWeek, [
            ['codigo' => '201', 'nombre' => 'UNO', 'turno' => 1],
            ['codigo' => '202', 'nombre' => 'DOS', 'turno' => 2],
        ]);
        $this->insertWeekRecords($secondWeek, [
            ['codigo' => '301', 'nombre' => 'TRES', 'turno' => 1],
            ['codigo' => '302', 'nombre' => 'CUATRO', 'turno' => 3],
        ]);

        $binary = Excel::raw(
            new Reporte00EAtadoresRangoExport($firstWeek, $secondWeek),
            ExcelFormat::XLSX
        );

        $path = storage_path('framework/testing/oee-atadores-rango-'.uniqid('', true).'.xlsx');
        $this->tempFiles[] = $path;
        file_put_contents($path, $binary);

        $workbook = IOFactory::load($path);
        $sheet = $workbook->getSheet(0);

        $this->assertSame('SEMANA', $sheet->getCell('B46')->getValue());
        $this->assertSame(1, (int) $sheet->getCell('C46')->getValue());
        $this->assertSame('SEMANA', $sheet->getCell('B92')->getValue());
        $this->assertSame(2, (int) $sheet->getCell('C92')->getValue());
        $this->assertSame('2025-01-06', ExcelDate::excelToDateTimeObject($sheet->getCell('H48')->getValue())->format('Y-m-d'));
        $this->assertContains('A48:A79', array_keys($sheet->getMergeCells()));
        $this->assertContains('A80:A85', array_keys($sheet->getMergeCells()));
        $this->assertSame($sheet->getStyle('B3')->exportArray(), $sheet->getStyle('B49')->exportArray());
        $this->assertSame('=I92+U92+AG92+AS92+BE92+BQ92+CC92', (string) $sheet->getCell('CI92')->getValue());
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
                'NoTelarId' => (string) (300 + $index),
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
}
