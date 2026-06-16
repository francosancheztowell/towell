<?php

namespace Tests\Feature;

use App\Http\Controllers\Planeacion\ProgramaTejido\funciones\EliminarTejido;
use App\Models\Planeacion\Catalogos\CatCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/**
 * Cobertura del sellado de FechaFinaliza al eliminar una orden desde el
 * programa de tejido. Verifica que, al borrar un registro con NoProduccion,
 * CatCodificados quede con FechaFinaliza sellada (no huérfano).
 */
class ProgramaTejidoEliminarSellaFechaFinalizaTest extends TestCase
{
    use UsesSqlsrvSqlite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');
        config()->set('planeacion.programa_tejido_table', 'ReqProgramaTejido');

        Schema::connection('sqlsrv')->create('ReqProgramaTejido', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('NoProduccion')->nullable();
            $table->string('NoTelarId')->nullable();
            $table->string('SalonTejidoId')->nullable();
            $table->boolean('EnProceso')->default(false);
            $table->string('Reprogramar')->nullable();
            $table->string('OrdCompartida')->nullable();
            $table->boolean('OrdCompartidaLider')->nullable();
            $table->string('Ultimo')->nullable();
            $table->integer('Posicion')->nullable();
            $table->dateTime('FechaInicio')->nullable();
            $table->dateTime('FechaFinal')->nullable();
            $table->dateTime('FechaArranque')->nullable();
            $table->dateTime('FechaFinaliza')->nullable();
            $table->float('TotalPedido')->nullable();
            $table->float('Produccion')->nullable();
            $table->float('SaldoPedido')->nullable();
        });

        Schema::connection('sqlsrv')->create('CatCodificados', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('OrdenTejido')->nullable();
            $table->string('TelarId')->nullable();
            $table->dateTime('FechaArranque')->nullable();
            $table->dateTime('FechaFinaliza')->nullable();
            $table->float('Pedido')->nullable();
            $table->float('Produccion')->nullable();
            $table->float('Saldos')->nullable();
            $table->string('OrdCompartida')->nullable();
            $table->boolean('OrdCompartidaLider')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Schema::connection('sqlsrv')->dropIfExists('CatCodificados');
        Schema::connection('sqlsrv')->dropIfExists('ReqProgramaTejido');
        parent::tearDown();
    }

    public function test_eliminar_registro_unico_con_no_produccion_sella_fecha_finaliza_en_cat_codificados(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 14:30:00'));

        $registro = ReqProgramaTejido::query()->create([
            'NoProduccion' => '36999',
            'NoTelarId' => '201',
            'SalonTejidoId' => 'JACQUARD',
            'EnProceso' => false,
            'Posicion' => 1,
            'FechaInicio' => '2026-06-10 08:00:00',
            'FechaFinal' => '2026-06-12 08:00:00',
        ]);

        CatCodificados::query()->create([
            'OrdenTejido' => '36999',
            'TelarId' => '201',
            'FechaFinaliza' => null,
        ]);

        $response = EliminarTejido::eliminar($registro->Id);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->getData(true)['success']);

        // El registro de programa fue eliminado
        $this->assertNull(ReqProgramaTejido::query()->whereKey($registro->Id)->first());

        // CatCodificados quedó sellado (no huérfano)
        $cat = CatCodificados::query()->where('OrdenTejido', '36999')->firstOrFail();
        $this->assertSame('2026-06-15 14:30:00', optional($cat->FechaFinaliza)->format('Y-m-d H:i:s'));
    }
}
