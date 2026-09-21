<?php

namespace Tests\Unit;

use App\Models\Planeacion\ReqProgramaTejido;
use App\Services\Tejido\InventarioTrama\NuevoRequerimientoService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

class NuevoRequerimientoServiceTest extends TestCase
{
    use UsesSqlsrvSqlite;

    private int $queries = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqlsrvSqlite();

        $default = config('database.default');
        $schema = Schema::connection($default);

        $schema->create('InvSecuenciaTrama', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('NoTelar')->nullable();
            $table->string('TipoTelar')->nullable();
            $table->integer('Secuencia')->nullable();
        });

        $schema->create('TejTrama', function (Blueprint $table) {
            $table->string('Folio')->primary();
            $table->date('Fecha')->nullable();
            $table->string('Status')->nullable();
            $table->string('Turno')->nullable();
            $table->string('numero_empleado')->nullable();
            $table->string('nombreEmpl')->nullable();
        });

        $schema->create('TejTramaConsumos', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Folio')->nullable();
            $table->string('NoTelarId')->nullable();
            $table->string('SalonTejidoId')->nullable();
            $table->string('NoProduccion')->nullable();
            $table->float('CalibreTrama')->nullable();
            $table->string('NombreProducto')->nullable();
            $table->string('FibraTrama')->nullable();
            $table->string('CodColorTrama')->nullable();
            $table->string('ColorTrama')->nullable();
            $table->float('Cantidad')->nullable();
        });

        $this->createTablaDesdeModelo(ReqProgramaTejido::class);

        $this->createTablaDbo('SSYSFoliosSecuencias', [
            'Id' => 'INTEGER',
            'modulo' => 'TEXT',
            'prefijo' => 'TEXT',
            'consecutivo' => 'INTEGER',
        ]);
        DB::table('dbo.SSYSFoliosSecuencias')->insert([
            'modulo' => 'Trama',
            'prefijo' => 'TR',
            'consecutivo' => 7,
        ]);

        DB::listen(function () {
            $this->queries++;
        });
    }

    public function test_construir_vm_resuelve_karl_mayer_con_cuatro_barras(): void
    {
        DB::table('InvSecuenciaTrama')->insert([
            ['NoTelar' => '201', 'TipoTelar' => 'JACQUARD', 'Secuencia' => 1],
            ['NoTelar' => '318', 'TipoTelar' => 'ITEMA', 'Secuencia' => 2],
            ['NoTelar' => '401', 'TipoTelar' => 'KARL MAYER', 'Secuencia' => 3],
        ]);

        DB::table('ReqProgramaTejido')->insert([
            'SalonTejidoId' => 'JACQUARD',
            'NoTelarId' => '201',
            'EnProceso' => 1,
            'CalibreTrama' => 20.5,
            'CalibreComb1' => '30/1',
            'NombreProducto' => 'Toalla Rizo',
        ]);
        DB::table('ReqProgramaTejido')->insert([
            'SalonTejidoId' => 'ITEMA',
            'NoTelarId' => '318',
            'EnProceso' => 1,
            'CalibreTrama' => 25.0,
        ]);
        DB::table('ReqProgramaTejido')->insert([
            'SalonTejidoId' => 'KARL MAYER',
            'NoTelarId' => '401',
            'EnProceso' => 1,
            'CalibreBarra1' => 100.0,
            'CalibreBarra2' => 200.0,
            'CalibreBarra3' => 300.0,
            'CalibreBarra4' => 400.0,
            'FibraBarra1' => 'FIL 100',
        ]);

        $vm = app(NuevoRequerimientoService::class)->construirVm(null);

        $km = collect($vm['telares'])->firstWhere('numero', '401');
        $this->assertNotNull($km);
        $this->assertTrue($km['es_karl_mayer']);
        $this->assertSame('karl-mayer', $km['tipo']);
        $this->assertSame([1, 2, 3, 4], array_column($km['rows'], 'barra'));
        $this->assertSame([100.0, 200.0, 300.0, 400.0], array_column($km['rows'], 'calibre'));

        $jac = collect($vm['telares'])->firstWhere('numero', '201');
        $this->assertNotNull($jac);
        $this->assertFalse($jac['es_karl_mayer']);
        $this->assertContains(20.5, array_column($jac['rows'], 'calibre'));
    }

    public function test_construir_vm_no_escala_consultas_con_el_numero_de_telares(): void
    {
        $secuencia = [];
        $programa = [];
        for ($i = 0; $i < 30; $i++) {
            $telar = (string) (200 + $i);
            $secuencia[] = ['NoTelar' => $telar, 'TipoTelar' => 'JACQUARD', 'Secuencia' => $i];
            $programa[] = ['SalonTejidoId' => 'JACQUARD', 'NoTelarId' => $telar, 'EnProceso' => 1, 'CalibreTrama' => 10.0];
        }
        DB::table('InvSecuenciaTrama')->insert($secuencia);
        DB::table('ReqProgramaTejido')->insert($programa);

        $this->queries = 0;
        $vm = app(NuevoRequerimientoService::class)->construirVm(null);

        $this->assertCount(30, $vm['telares']);
        $this->assertLessThanOrEqual(8, $this->queries, "index() no debe disparar una consulta por telar (se ejecutaron {$this->queries})");
    }

    public function test_guardar_sincroniza_altas_cambios_y_bajas(): void
    {
        $this->createAuthTable();
        $usuario = $this->createUsuario();
        $this->actingAs($usuario);

        DB::table('TejTrama')->insert([
            'Folio' => 'TR00007',
            'Status' => 'En Proceso',
            'Turno' => '1',
        ]);

        $service = app(NuevoRequerimientoService::class);

        $resultado = $service->guardar([
            ['telar' => '201', 'salon' => 'JACQUARD', 'orden' => 'OP-1', 'producto' => 'Toalla', 'calibre' => 20.5, 'fibra' => 'FIL', 'cod_color' => 'C1', 'color' => 'Rojo', 'cantidad' => 4],
        ], 'TR00007');

        $this->assertSame('TR00007', $resultado['folio']);
        $this->assertCount(1, $resultado['consumos']);

        $id = $resultado['consumos'][0]['id'];

        $resultado = $service->guardar([
            ['telar' => '201', 'salon' => 'JACQUARD', 'orden' => 'OP-1', 'producto' => 'Toalla', 'calibre' => 20.5, 'fibra' => 'FIL', 'cod_color' => 'C2', 'color' => 'Azul', 'cantidad' => 9],
        ], 'TR00007');

        $this->assertCount(1, $resultado['consumos'], 'Cambiar color no debe duplicar el consumo');
        $this->assertSame('C2', $resultado['consumos'][0]['cod_color']);
        $this->assertNotSame($id, $resultado['consumos'][0]['id'], 'La fila vieja debe borrarse y reemplazarse');

        $resultado = $service->guardar([], 'TR00007');
        $this->assertCount(0, $resultado['consumos'], 'Guardar sin filas debe borrar las existentes');
    }
}
