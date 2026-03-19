<?php

namespace Tests\Unit;

use App\Http\Controllers\Tejedores\Desarrolladores\Funciones\MovimientoDesarrolladorService;
use App\Models\Planeacion\Catalogos\CatCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MovimientoDesarrolladorServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.connections.sqlsrv', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        DB::purge('sqlsrv');
        DB::connection('sqlsrv')->getPdo();

        Schema::connection('sqlsrv')->create('ReqProgramaTejido', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('NoProduccion')->nullable();
            $table->string('NoTelarId')->nullable();
            $table->dateTime('FechaInicio')->nullable();
            $table->dateTime('FechaArranque')->nullable();
            $table->dateTime('FechaFinaliza')->nullable();
        });

        Schema::connection('sqlsrv')->create('CatCodificados', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('OrdenTejido')->nullable();
            $table->string('TelarId')->nullable();
            $table->dateTime('FechaArranque')->nullable();
            $table->dateTime('FechaFinaliza')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_actualizar_fechas_arranque_finaliza_sincroniza_fecha_finaliza_actual_en_cat_codificados(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-18 18:45:00'));

        $programa = ReqProgramaTejido::on('sqlsrv')->create([
            'NoProduccion' => 'ORD-100',
            'NoTelarId' => '101',
            'FechaInicio' => '2026-03-18 08:00:00',
            'FechaArranque' => null,
            'FechaFinaliza' => null,
        ]);

        CatCodificados::on('sqlsrv')->create([
            'OrdenTejido' => 'ORD-100',
            'TelarId' => '101',
            'FechaArranque' => null,
            'FechaFinaliza' => null,
        ]);

        $service = new MovimientoDesarrolladorService();

        $resultado = $service->actualizarFechasArranqueFinaliza($programa, null, 'now');

        $this->assertTrue($resultado);

        $programa->refresh();
        $registro = CatCodificados::on('sqlsrv')->first();

        $this->assertSame('2026-03-18 08:00:00', optional($programa->FechaArranque)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-18 18:45:00', optional($programa->FechaFinaliza)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-18 08:00:00', optional($registro->FechaArranque)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-18 18:45:00', optional($registro->FechaFinaliza)->format('Y-m-d H:i:s'));
    }

    public function test_actualizar_fechas_arranque_finaliza_sincroniza_fecha_arranque_actual_en_cat_codificados(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-18 19:10:00'));

        $programa = ReqProgramaTejido::on('sqlsrv')->create([
            'NoProduccion' => 'ORD-200',
            'NoTelarId' => '202',
            'FechaInicio' => '2026-03-18 09:00:00',
            'FechaArranque' => null,
            'FechaFinaliza' => '2026-03-18 17:00:00',
        ]);

        CatCodificados::on('sqlsrv')->create([
            'OrdenTejido' => 'ORD-200',
            'TelarId' => '202',
            'FechaArranque' => null,
            'FechaFinaliza' => '2026-03-18 17:00:00',
        ]);

        $service = new MovimientoDesarrolladorService();

        $resultado = $service->actualizarFechasArranqueFinaliza($programa, 'now', null);

        $this->assertTrue($resultado);

        $programa->refresh();
        $registro = CatCodificados::on('sqlsrv')->first();

        $this->assertSame('2026-03-18 19:10:00', optional($programa->FechaArranque)->format('Y-m-d H:i:s'));
        $this->assertNull($programa->FechaFinaliza);
        $this->assertSame('2026-03-18 19:10:00', optional($registro->FechaArranque)->format('Y-m-d H:i:s'));
        $this->assertNull($registro->FechaFinaliza);
    }

    public function test_actualizar_fechas_arranque_finaliza_actualiza_cat_codificados_por_orden_aunque_no_coincida_telar(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-18 20:00:00'));

        $programa = ReqProgramaTejido::on('sqlsrv')->create([
            'NoProduccion' => 'ORD-300',
            'NoTelarId' => '303',
            'FechaInicio' => '2026-03-18 10:00:00',
            'FechaArranque' => null,
            'FechaFinaliza' => null,
        ]);

        CatCodificados::on('sqlsrv')->create([
            'OrdenTejido' => 'ORD-300',
            'TelarId' => '999',
            'FechaArranque' => null,
            'FechaFinaliza' => null,
        ]);

        $service = new MovimientoDesarrolladorService();

        $resultado = $service->actualizarFechasArranqueFinaliza($programa, 'now', null);

        $this->assertTrue($resultado);

        $registro = CatCodificados::on('sqlsrv')->first();

        $this->assertSame('2026-03-18 20:00:00', optional($registro->FechaArranque)->format('Y-m-d H:i:s'));
        $this->assertNull($registro->FechaFinaliza);
    }
}
