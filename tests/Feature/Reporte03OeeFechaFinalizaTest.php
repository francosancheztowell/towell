<?php

namespace Tests\Feature;

use App\Exports\ReportesUrdidoExport;
use App\Models\Engomado\EngProduccionEngomado;
use App\Models\Engomado\EngProgramaEngomado;
use App\Models\Urdido\UrdProduccionUrdido;
use App\Models\Urdido\UrdProgramaUrdido;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;
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

    public function test_exporte_03_oee_agrupa_y_ordena_por_fecha_finaliza_en_excel(): void
    {
        $this->actingAs($this->createUsuario(['area' => 'Urdido']));

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
        ]);

        UrdProduccionUrdido::create([
            'Folio' => 'URD-B',
            'Fecha' => '2026-03-10',
            'NoJulio' => 'JU-10',
            'KgNeto' => 20,
            'Metros1' => 120,
            'NomEmpl1' => 'Operador B',
        ]);

        UrdProduccionUrdido::create([
            'Folio' => 'URD-C',
            'Fecha' => '2026-03-11',
            'NoJulio' => 'JU-11',
            'KgNeto' => 30,
            'Metros1' => 130,
            'NomEmpl1' => 'Operador C',
        ]);

        EngProduccionEngomado::create([
            'Folio' => 'ENG-A',
            'Fecha' => '2026-03-01',
            'NoJulio' => 'EN-12',
            'KgNeto' => 12,
            'Metros1' => 90,
            'NomEmpl1' => 'Eng A',
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

                $porFecha = $this->extractPorFecha($export);

                $this->assertSame(['2026-03-10', '2026-03-12'], array_keys($porFecha));
                $this->assertSame('URD-B', $porFecha['2026-03-10']['porMaquina'][0]['filas'][0]['orden']);
                $this->assertSame('ENG-B', $porFecha['2026-03-10']['engomado']['WP2']['filas'][0]['orden']);
                $this->assertSame('URD-A', $porFecha['2026-03-12']['porMaquina'][0]['filas'][0]['orden']);
                $this->assertSame('ENG-A', $porFecha['2026-03-12']['engomado']['WP3']['filas'][0]['orden']);
                $this->assertArrayNotHasKey('2026-03-11', $porFecha);

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

    private function createReporte03Tables(): void
    {
        $schema = Schema::connection('sqlsrv');

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
        });
    }

    private function extractPorFecha(ReportesUrdidoExport $export): array
    {
        $reflection = new \ReflectionClass($export);
        $property = $reflection->getProperty('porFecha');
        $property->setAccessible(true);

        return $property->getValue($export);
    }
}
