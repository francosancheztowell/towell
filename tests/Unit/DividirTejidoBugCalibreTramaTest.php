<?php

namespace Tests\Unit;

use App\Models\Planeacion\ReqModelosCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

class DividirTejidoBugCalibreTramaTest extends TestCase
{
    use UsesSqlsrvSqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');
        config()->set('planeacion.programa_tejido_table', 'ReqProgramaTejido');

        Schema::connection('sqlsrv')->create('ReqModelosCodificados', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('TamanoClave')->nullable();
            $table->string('SalonTejidoId')->nullable();
            $table->string('ItemId')->nullable();
            $table->string('InventSizeId')->nullable();
            $table->string('Nombre')->nullable();
            $table->string('NombreProyecto')->nullable();
            $table->string('FlogsId')->nullable();
            $table->string('FibraRizo')->nullable();
            $table->string('FibraId')->nullable();
            $table->float('CalibreRizo')->nullable();
            $table->float('CalibreRizo2')->nullable();
            $table->float('CalibrePie')->nullable();
            $table->float('CalibrePie2')->nullable();
            $table->float('CalibreTrama')->nullable();
            $table->float('CalibreTrama2')->nullable();
            $table->float('NoTiras')->nullable();
            $table->float('Luchaje')->nullable();
            $table->float('PesoCrudo')->nullable();
            $table->integer('MedidaPlano')->nullable();
            $table->integer('Peine')->nullable();
            $table->float('AnchoToalla')->nullable();
            $table->integer('LargoToalla')->nullable();
            $table->string('FibraTrama')->nullable();
            $table->string('FibraPie')->nullable();
            $table->string('CuentaRizo')->nullable();
            $table->string('CuentaPie')->nullable();
            $table->string('CodColorTrama')->nullable();
            $table->string('ColorTrama')->nullable();
        });

        Schema::connection('sqlsrv')->create('ReqProgramaTejido', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('TamanoClave')->nullable();
            $table->string('SalonTejidoId')->nullable();
            $table->string('NoTelarId')->nullable();
            $table->string('ItemId')->nullable();
            $table->string('InventSizeId')->nullable();
            $table->string('NombreProducto')->nullable();
            $table->string('NombreProyecto')->nullable();
            $table->string('FlogsId')->nullable();
            $table->string('FibraRizo')->nullable();
            $table->float('CalibreRizo')->nullable();
            $table->float('CalibreRizo2')->nullable();
            $table->float('CalibrePie')->nullable();
            $table->float('CalibrePie2')->nullable();
            $table->float('CalibreTrama')->nullable();
            $table->float('CalibreTrama2')->nullable();
            $table->float('NoTiras')->nullable();
            $table->float('Luchaje')->nullable();
            $table->float('PesoCrudo')->nullable();
            $table->integer('MedidaPlano')->nullable();
            $table->integer('Peine')->nullable();
            $table->float('AnchoToalla')->nullable();
            $table->float('Ancho')->nullable();
            $table->integer('LargoCrudo')->nullable();
            $table->string('FibraTrama')->nullable();
            $table->string('FibraPie')->nullable();
            $table->string('CuentaRizo')->nullable();
            $table->string('CuentaPie')->nullable();
            $table->string('CodColorTrama')->nullable();
            $table->string('ColorTrama')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('ReqProgramaTejido');
        Schema::connection('sqlsrv')->dropIfExists('ReqModelosCodificados');
        parent::tearDown();
    }

    public function test_inversion_correcta_cuando_modelo_tiene_ambos_calibres(): void
    {
        $modelo = ReqModelosCodificados::create([
            'TamanoClave' => 'TEST-INV-1',
            'SalonTejidoId' => 'JAC1',
            'CalibreTrama' => 10,
            'CalibreTrama2' => 20,
        ]);

        $registro = new ReqProgramaTejido([
            'SalonTejidoId' => 'JAC1',
            'NoTelarId' => '01',
        ]);

        if ($modelo->CalibreTrama !== null) {
            $registro->CalibreTrama2 = (float) $modelo->CalibreTrama;
        }
        if ($modelo->CalibreTrama2 !== null) {
            $registro->CalibreTrama = (float) $modelo->CalibreTrama2;
        }

        $this->assertEquals(20, $registro->CalibreTrama);
        $this->assertEquals(10, $registro->CalibreTrama2);
    }

    public function test_solo_se_asigna_calibre_trama2_cuando_modelo_tiene_solo_calibre_trama(): void
    {
        $modelo = ReqModelosCodificados::create([
            'TamanoClave' => 'TEST-INV-2',
            'SalonTejidoId' => 'JAC1',
            'CalibreTrama' => 10,
            'CalibreTrama2' => null,
        ]);

        $registro = new ReqProgramaTejido([
            'SalonTejidoId' => 'JAC1',
            'NoTelarId' => '01',
        ]);

        if ($modelo->CalibreTrama !== null) {
            $registro->CalibreTrama2 = (float) $modelo->CalibreTrama;
        }
        if ($modelo->CalibreTrama2 !== null) {
            $registro->CalibreTrama = (float) $modelo->CalibreTrama2;
        }

        $this->assertEquals(10, $registro->CalibreTrama2);
        $this->assertEquals(0.0, $registro->CalibreTrama);
    }

    public function test_solo_se_asigna_calibre_trama_cuando_modelo_tiene_solo_calibre_trama2(): void
    {
        $modelo = ReqModelosCodificados::create([
            'TamanoClave' => 'TEST-INV-3',
            'SalonTejidoId' => 'JAC1',
            'CalibreTrama' => null,
            'CalibreTrama2' => 20,
        ]);

        $registro = new ReqProgramaTejido([
            'SalonTejidoId' => 'JAC1',
            'NoTelarId' => '01',
        ]);

        if ($modelo->CalibreTrama !== null) {
            $registro->CalibreTrama2 = (float) $modelo->CalibreTrama;
        }
        if ($modelo->CalibreTrama2 !== null) {
            $registro->CalibreTrama = (float) $modelo->CalibreTrama2;
        }

        $this->assertEquals(20, $registro->CalibreTrama);
        $this->assertEquals(0.0, $registro->CalibreTrama2);
    }

    public function test_registro_previamente_con_calibres_no_se_sobrescribe_incorrectamente(): void
    {
        $modelo = ReqModelosCodificados::create([
            'TamanoClave' => 'TEST-INV-4',
            'SalonTejidoId' => 'JAC1',
            'CalibreTrama' => 10,
            'CalibreTrama2' => 20,
        ]);

        $registro = new ReqProgramaTejido([
            'SalonTejidoId' => 'JAC1',
            'NoTelarId' => '01',
            'CalibreTrama' => 99,
            'CalibreTrama2' => 88,
        ]);

        if ($modelo->CalibreTrama !== null) {
            $registro->CalibreTrama2 = (float) $modelo->CalibreTrama;
        }
        if ($modelo->CalibreTrama2 !== null) {
            $registro->CalibreTrama = (float) $modelo->CalibreTrama2;
        }

        $this->assertEquals(20, $registro->CalibreTrama);
        $this->assertEquals(10, $registro->CalibreTrama2);
    }
}
