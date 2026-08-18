<?php

namespace Tests\Unit;

use App\Exports\ProgramaAtadoresExport;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

class ProgramaAtadoresExportTest extends TestCase
{
    use UsesSqlsrvSqlite;

    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqlsrvSqlite();
        $this->createProgramaAtadoresTables();
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

    public function test_export_muestra_horas_solo_con_hora_y_minuto(): void
    {
        DB::connection('sqlsrv')->table('AtaMontadoTelas')->insert([
            'Fecha' => '2026-08-17',
            'Turno' => '1',
            'Tipo' => 'Rizo',
            'NoTelarId' => '301',
            'HoraParo' => '06:30:00.0000000',
            'HoraArranque' => '08:10:15.123',
            'HrInicio' => '07:00',
            'NoJulio' => 'J1',
            'NoProduccion' => 'P1',
        ]);

        $binary = Excel::raw(
            new ProgramaAtadoresExport('2026-08-17', '2026-08-17'),
            ExcelFormat::XLSX
        );

        $path = storage_path('framework/testing/programa-atadores-'.uniqid('', true).'.xlsx');
        $this->tempFiles[] = $path;
        file_put_contents($path, $binary);

        $sheet = IOFactory::load($path)->getSheet(0);

        $this->assertSame('Hora Paro', $sheet->getCell('F1')->getValue());
        $this->assertSame('Hora Arranque', $sheet->getCell('G1')->getValue());
        $this->assertSame('Hr. Inicio', $sheet->getCell('H1')->getValue());
        $this->assertSame('06:30', $sheet->getCell('F2')->getValue());
        $this->assertSame('08:10', $sheet->getCell('G2')->getValue());
        $this->assertSame('07:00', $sheet->getCell('H2')->getValue());
    }

    private function createProgramaAtadoresTables(): void
    {
        $schema = Schema::connection('sqlsrv');

        $schema->create('AtaMontadoTelas', function (Blueprint $table) {
            $table->increments('Id');
            $table->date('Fecha')->nullable();
            $table->string('Turno')->nullable();
            $table->string('Tipo')->nullable();
            $table->string('NoTelarId')->nullable();
            $table->float('MergaKg')->nullable();
            $table->string('HoraParo')->nullable();
            $table->string('HoraArranque')->nullable();
            $table->string('HrInicio')->nullable();
            $table->string('NoJulio')->nullable();
            $table->string('NoProduccion')->nullable();
        });

        $schema->create('AtaMontadoMaquinas', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('NoJulio')->nullable();
            $table->string('NoProduccion')->nullable();
            $table->string('MaquinaId')->nullable();
            $table->integer('Estado')->nullable();
            $table->string('CveEmpl')->nullable();
            $table->string('NomEmpleado')->nullable();
        });

        $schema->create('AtaMontadoActividades', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('NoJulio')->nullable();
            $table->string('NoProduccion')->nullable();
            $table->string('ActividadId')->nullable();
            $table->integer('Estado')->nullable();
            $table->string('CveEmpl')->nullable();
            $table->string('NomEmpl')->nullable();
        });

        $schema->create('AtaMaquinas', function (Blueprint $table) {
            $table->string('MaquinaId')->primary();
        });

        $schema->create('AtaActividades', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('ActividadId')->nullable();
        });
    }
}
