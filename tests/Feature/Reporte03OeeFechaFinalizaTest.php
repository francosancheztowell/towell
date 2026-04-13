<?php

namespace Tests\Feature;

use App\Exports\ReportesUrdidoExport;
use App\Models\Engomado\CatDefectosUrdEng;
use App\Models\Engomado\EngProduccionEngomado;
use App\Models\Engomado\EngProgramaEngomado;
use App\Models\Urdido\UrdProduccionUrdido;
use App\Models\Urdido\UrdProgramaUrdido;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

class Reporte03OeeFechaFinalizaTest extends TestCase
{
    use UsesSqlsrvSqlite;

    private int $initialOutputBufferLevel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->initialOutputBufferLevel = ob_get_level();
        $this->useSqlsrvSqlite();
        $this->createAuthTable();
        $this->createReporte03Tables();

        $reportsRoot = storage_path('framework/testing/reports-urdido');

        if (! is_dir($reportsRoot)) {
            mkdir($reportsRoot, 0777, true);
        }

        config()->set('filesystems.disks.reports_urdido.root', $reportsRoot);
    }

    protected function tearDown(): void
    {
        while (ob_get_level() > $this->initialOutputBufferLevel) {
            ob_end_clean();
        }

        parent::tearDown();
    }

    public function test_reporte_03_oee_filtra_por_fecha_finaliza_en_pantalla(): void
    {
        $this->actingAs($this->createUsuario(['area' => 'Urdido']));

        UrdProgramaUrdido::create([
            'Folio' => 'URD-IN',
            'MaquinaId' => 'Mc Coy 2',
            'Status' => 'En Proceso',
            'FechaFinaliza' => '2026-03-10',
        ]);

        UrdProgramaUrdido::create([
            'Folio' => 'URD-OUT',
            'MaquinaId' => 'Mc Coy 1',
            'Status' => 'Finalizado',
            'FechaFinaliza' => '2026-04-02',
        ]);

        UrdProgramaUrdido::create([
            'Folio' => 'URD-NULL',
            'MaquinaId' => 'Mc Coy 3',
            'Status' => 'Finalizado',
            'FechaFinaliza' => null,
        ]);

        UrdProduccionUrdido::create([
            'Folio' => 'URD-IN',
            'Fecha' => '2026-02-25',
            'NoJulio' => 'J-100',
            'KgNeto' => 18.5,
            'Metros1' => 120,
            'NomEmpl1' => 'Operador Visible',
        ]);

        UrdProduccionUrdido::create([
            'Folio' => 'URD-OUT',
            'Fecha' => '2026-03-12',
            'NoJulio' => 'J-200',
            'KgNeto' => 22,
            'Metros1' => 140,
            'NomEmpl1' => 'No Debe Entrar',
        ]);

        UrdProduccionUrdido::create([
            'Folio' => 'URD-NULL',
            'Fecha' => '2026-03-15',
            'NoJulio' => 'J-300',
            'KgNeto' => 25,
            'Metros1' => 150,
            'NomEmpl1' => 'Sin Fecha Finaliza',
        ]);

        $response = $this->get(route('urdido.reportes.urdido.03-oee', [
            'fecha_ini' => '2026-03-01',
            'fecha_fin' => '2026-03-31',
            'solo_finalizados' => '0',
        ]));

        $response->assertOk();
        $response->assertViewHas('porMaquina', function (array $porMaquina) {
            return isset($porMaquina['MC2'])
                && ($porMaquina['MC2']['filas'][0]['orden'] ?? null) === 'URD-IN'
                && ! isset($porMaquina['MC1'])
                && ! isset($porMaquina['MC3']);
        });
        $response->assertViewHas('totalKg', 18.5);
        $response->assertSee('URD-IN');
        $response->assertDontSee('URD-OUT');
        $response->assertDontSee('URD-NULL');
    }

    public function test_exporte_03_oee_agrupa_y_ordena_por_fecha_finaliza_y_fecha_defecto_en_excel(): void
    {
        $this->actingAs($this->createUsuario(['area' => 'Urdido']));

        CatDefectosUrdEng::create([
            'Clave' => 'RHS',
            'Penalizacion' => 1.5,
            'Defecto' => 'Hilos sueltos',
            'CincoS' => null,
            'Seguridad' => 'EPP',
            'Activo' => true,
        ]);

        CatDefectosUrdEng::create([
            'Clave' => 'RHCE',
            'Penalizacion' => 2,
            'Defecto' => 'Hilos colgando',
            'CincoS' => 'Basura',
            'Seguridad' => null,
            'Activo' => true,
        ]);

        CatDefectosUrdEng::create([
            'Clave' => 'N',
            'Penalizacion' => 5,
            'Defecto' => 'Nudo mal',
            'CincoS' => 'Rack/Ventilador',
            'Seguridad' => 'Amonestacion',
            'Activo' => true,
        ]);

        UrdProgramaUrdido::create([
            'Folio' => 'URD-A',
            'MaquinaId' => 'Mc Coy 1',
            'Status' => 'Finalizado',
            'FechaFinaliza' => '2026-03-12',
        ]);

        UrdProgramaUrdido::create([
            'Folio' => 'URD-B',
            'MaquinaId' => 'Mc Coy 2',
            'Status' => 'Finalizado',
            'FechaFinaliza' => '2026-03-10',
        ]);

        UrdProgramaUrdido::create([
            'Folio' => 'URD-C',
            'MaquinaId' => 'Mc Coy 3',
            'Status' => 'En Proceso',
            'FechaFinaliza' => '2026-03-11',
        ]);

        EngProgramaEngomado::create([
            'Folio' => 'ENG-A',
            'MaquinaEng' => 'West Point 3',
            'Status' => 'Finalizado',
            'FechaFinaliza' => '2026-03-12',
        ]);

        EngProgramaEngomado::create([
            'Folio' => 'ENG-B',
            'MaquinaEng' => 'West Point 2',
            'Status' => 'Finalizado',
            'FechaFinaliza' => '2026-03-10',
        ]);

        EngProgramaEngomado::create([
            'Folio' => 'ENG-C',
            'MaquinaEng' => 'West Point 2',
            'Status' => 'Finalizado',
            'FechaFinaliza' => null,
        ]);

        UrdProduccionUrdido::create([
            'Folio' => 'URD-A',
            'Fecha' => '2026-02-28',
            'NoJulio' => 'JU-12',
            'KgNeto' => 15,
            'Metros1' => 100,
            'NomEmpl1' => 'Operador A',
            'ClaveDefecto' => 1,
            'Penalizacion' => 1.5,
            'OperadorDefecto' => 'Fuera Rango',
            'FechaDefecto' => '2026-04-01 10:00:00',
        ]);

        UrdProduccionUrdido::create([
            'Folio' => 'URD-B',
            'Fecha' => '2026-03-10',
            'NoJulio' => 'JU-10',
            'KgNeto' => 20,
            'Metros1' => 120,
            'NomEmpl1' => 'Operador B',
            'ClaveDefecto' => 2,
            'Penalizacion' => 2,
            'OperadorDefecto' => 'Operador B Defecto',
            'FechaDefecto' => '2026-03-10 08:30:00',
        ]);

        UrdProduccionUrdido::create([
            'Folio' => 'URD-C',
            'Fecha' => '2026-03-11',
            'NoJulio' => 'JU-11',
            'KgNeto' => 30,
            'Metros1' => 130,
            'NomEmpl1' => 'Operador C',
            'ClaveDefecto' => 3,
            'Penalizacion' => 5,
            'OperadorDefecto' => 'Operador C Defecto',
            'FechaDefecto' => '2026-03-11 09:15:00',
        ]);

        EngProduccionEngomado::create([
            'Folio' => 'ENG-A',
            'Fecha' => '2026-03-01',
            'NoJulio' => 'EN-12',
            'KgNeto' => 12,
            'Metros1' => 90,
            'NomEmpl1' => 'Eng A',
            'ClaveDefecto' => 1,
            'Penalizacion' => 1.5,
            'OperadorDefecto' => 'Eng A Defecto',
            'FechaDefecto' => '2026-03-12 07:00:00',
        ]);

        EngProduccionEngomado::create([
            'Folio' => 'ENG-B',
            'Fecha' => '2026-02-27',
            'NoJulio' => 'EN-10',
            'KgNeto' => 10,
            'Metros1' => 80,
            'NomEmpl1' => 'Eng B',
        ]);

        EngProduccionEngomado::create([
            'Folio' => 'ENG-C',
            'Fecha' => '2026-03-09',
            'NoJulio' => 'EN-09',
            'KgNeto' => 8,
            'Metros1' => 70,
            'NomEmpl1' => 'Eng C',
        ]);

        Excel::shouldReceive('store')
            ->once()
            ->withArgs(function ($export, $filename, $disk) {
                $this->assertInstanceOf(ReportesUrdidoExport::class, $export);
                $this->assertSame('03-0EE URD-ENG-2026.xlsx', $filename);
                $this->assertSame('local', $disk);

                $porFecha = $this->extractProperty($export, 'porFecha');
                $defectosData = $this->extractProperty($export, 'defectosData');

                $this->assertSame(['2026-03-10', '2026-03-12'], array_keys($porFecha));
                $this->assertSame('URD-B', $porFecha['2026-03-10']['porMaquina'][0]['filas'][0]['orden']);
                $this->assertSame('ENG-B', $porFecha['2026-03-10']['engomado']['WP2']['filas'][0]['orden']);
                $this->assertSame('URD-A', $porFecha['2026-03-12']['porMaquina'][0]['filas'][0]['orden']);
                $this->assertSame('ENG-A', $porFecha['2026-03-12']['engomado']['WP3']['filas'][0]['orden']);
                $this->assertArrayNotHasKey('2026-03-11', $porFecha);

                $this->assertSame(['URD-B', 'URD-C', 'ENG-A'], array_column($defectosData['calidad_rows'], 'orden'));
                $this->assertSame(['RHCE', 'N', 'RHS'], array_column($defectosData['calidad_rows'], 'defecto'));
                $this->assertSame(
                    ['Basura', 'Rack/Ventilador', 'Amonestacion', 'EPP'],
                    array_column($defectosData['seguridad_rows'], 'defecto')
                );
                $this->assertSame(
                    ['Operador B', 'Operador C', 'Eng A Defecto'],
                    $defectosData['footer_operators']
                );
                $this->assertSame('URD', $defectosData['calidad_rows'][0]['area']);
                $this->assertSame('ENG', $defectosData['calidad_rows'][2]['area']);
                $this->assertSame(5.0, $defectosData['calidad_rows'][1]['penalizar']);

                return true;
            })
            ->andReturnTrue();

        Excel::shouldReceive('download')
            ->once()
            ->withArgs(function ($export, $filename) {
                $this->assertInstanceOf(ReportesUrdidoExport::class, $export);
                $this->assertSame('reporte-urdido-20260301-20260331.xlsx', $filename);

                return true;
            })
            ->andReturn(response('ok', 200));

        $response = $this->get(route('urdido.reportes.urdido.excel', [
            'fecha_ini' => '2026-03-01',
            'fecha_fin' => '2026-03-31',
        ]));

        $response->assertOk();
        $this->assertSame('ok', $response->getContent());
    }

    public function test_reportes_urdido_export_genera_hoja_3_extensible_con_footer_dinamico(): void
    {
        $footerOperators = array_map(
            static fn (int $index): string => sprintf('Operador %02d', $index),
            range(1, 13)
        );

        $defectosData = [
            'calidad_rows' => [],
            'seguridad_rows' => [],
            'footer_operators' => $footerOperators,
        ];

        for ($index = 1; $index <= 27; $index++) {
            $defectosData['calidad_rows'][] = [
                'fecha' => '2026-03-'.sprintf('%02d', (($index - 1) % 28) + 1),
                'area' => $index % 2 === 0 ? 'ENG' : 'URD',
                'orden' => 'Q-'.$index,
                'julio' => 'JQ-'.$index,
                'defecto' => 'CAL-'.$index,
                'ope' => $footerOperators[($index - 1) % count($footerOperators)],
                'penalizar' => ($index % 5) + 1,
            ];
        }

        for ($index = 1; $index <= 22; $index++) {
            $defectosData['seguridad_rows'][] = [
                'fecha' => '2026-03-'.sprintf('%02d', (($index - 1) % 28) + 1),
                'area' => $index % 2 === 0 ? 'ENG' : 'URD',
                'orden' => 'S-'.$index,
                'julio' => 'JS-'.$index,
                'defecto' => 'SEG-'.$index,
                'ope' => $footerOperators[($index - 1) % count($footerOperators)],
                'penalizar' => ($index % 4) + 1,
            ];
        }

        $contenido = Excel::raw(new ReportesUrdidoExport([], $defectosData), ExcelFormat::XLSX);
        $tempBase = tempnam(sys_get_temp_dir(), 'reporte03_');
        $tempPath = $tempBase.'.xlsx';

        if ($tempBase !== false && file_exists($tempBase)) {
            unlink($tempBase);
        }

        file_put_contents($tempPath, $contenido);

        try {
            $workbook = IOFactory::load($tempPath);
            $this->assertSame(3, $workbook->getSheetCount());

            $sheet = $workbook->getSheetByName('Defectos');
            $this->assertNotNull($sheet);

            $this->assertSame(27, $sheet->getCell('A40')->getValue());
            $this->assertSame('Q-27', $sheet->getCell('D40')->getValue());
            $this->assertSame('CAL-27', $sheet->getCell('F40')->getValue());

            $this->assertSame(1, $sheet->getCell('A41')->getValue());
            $this->assertSame('S-1', $sheet->getCell('D41')->getValue());
            $this->assertSame('SEG-22', $sheet->getCell('F62')->getValue());
            $this->assertSame(22, $sheet->getCell('A62')->getValue());

            $this->assertSame('Operador 01', $sheet->getCell('B64')->getValue());
            $this->assertSame('Operador 07', $sheet->getCell('H64')->getValue());
            $this->assertSame('Operador 08', $sheet->getCell('B68')->getValue());
            $this->assertSame('Operador 13', $sheet->getCell('G68')->getValue());
            $this->assertNull($sheet->getCell('H68')->getValue());
            $this->assertSame('=B64', $sheet->getCell('B72')->getValue());

            $this->assertSame(
                '=IF(B64="","",SUMIF($G$14:$G$40,B64,$H$14:$H$40))',
                $sheet->getCell('B65')->getValue()
            );
            $this->assertSame(
                '=IF(B72="","",SUMIF($G$41:$G$62,B72,$H$41:$H$62))',
                $sheet->getCell('B73')->getValue()
            );
        } finally {
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
        }
    }

    private function createReporte03Tables(): void
    {
        $schema = Schema::connection('sqlsrv');

        $schema->create('CatDefectosUrdEng', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Clave')->nullable();
            $table->float('Penalizacion')->nullable();
            $table->string('Defecto')->nullable();
            $table->string('CincoS')->nullable();
            $table->string('Seguridad')->nullable();
            $table->boolean('Activo')->default(true);
        });

        $schema->create('UrdProgramaUrdido', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Folio')->nullable();
            $table->string('MaquinaId')->nullable();
            $table->string('Cuenta')->nullable();
            $table->float('Calibre')->nullable();
            $table->string('Fibra')->nullable();
            $table->string('Status')->nullable();
            $table->date('FechaFinaliza')->nullable();
        });

        $schema->create('EngProgramaEngomado', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Folio')->nullable();
            $table->string('MaquinaEng')->nullable();
            $table->string('Status')->nullable();
            $table->date('FechaFinaliza')->nullable();
        });

        $schema->create('UrdProduccionUrdido', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Folio')->nullable();
            $table->date('Fecha')->nullable();
            $table->string('NoJulio')->nullable();
            $table->float('KgNeto')->nullable();
            $table->string('CveEmpl1')->nullable();
            $table->string('NomEmpl1')->nullable();
            $table->float('Metros1')->nullable();
            $table->string('CveEmpl2')->nullable();
            $table->string('NomEmpl2')->nullable();
            $table->float('Metros2')->nullable();
            $table->string('CveEmpl3')->nullable();
            $table->string('NomEmpl3')->nullable();
            $table->float('Metros3')->nullable();
            $table->float('Penalizacion')->nullable();
            $table->string('OperadorDefecto')->nullable();
            $table->integer('NoEmplDefecto')->nullable();
            $table->integer('ClaveDefecto')->nullable();
            $table->dateTime('FechaDefecto')->nullable();
        });

        $schema->create('EngProduccionEngomado', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Folio')->nullable();
            $table->date('Fecha')->nullable();
            $table->string('NoJulio')->nullable();
            $table->float('KgNeto')->nullable();
            $table->string('CveEmpl1')->nullable();
            $table->string('NomEmpl1')->nullable();
            $table->float('Metros1')->nullable();
            $table->float('Metros2')->nullable();
            $table->float('Metros3')->nullable();
            $table->float('Penalizacion')->nullable();
            $table->string('OperadorDefecto')->nullable();
            $table->integer('NoEmplDefecto')->nullable();
            $table->integer('ClaveDefecto')->nullable();
            $table->dateTime('FechaDefecto')->nullable();
        });
    }

    private function extractProperty(ReportesUrdidoExport $export, string $propertyName): mixed
    {
        $reflection = new \ReflectionClass($export);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);

        return $property->getValue($export);
    }
}
