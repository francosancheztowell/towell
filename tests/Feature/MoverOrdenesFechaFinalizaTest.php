<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Planeacion\Utilerias\MoverOrdenesController;
use App\Models\Planeacion\Catalogos\CatCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/**
 * BUG-006: al sincronizar CatCodificados por cambio de salón,
 * Mover Órdenes no debe anular FechaFinaliza.
 */
class MoverOrdenesFechaFinalizaTest extends TestCase
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
            $table->integer('Posicion')->nullable();
            $table->dateTime('FechaInicio')->nullable();
            $table->dateTime('FechaFinal')->nullable();
            $table->dateTime('FechaArranque')->nullable();
            $table->dateTime('FechaFinaliza')->nullable();
            $table->float('TotalPedido')->nullable();
            $table->float('Produccion')->nullable();
            $table->float('SaldoPedido')->nullable();
            $table->integer('OrdCompartida')->nullable();
            $table->boolean('OrdCompartidaLider')->nullable();
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
            $table->integer('OrdCompartida')->nullable();
            $table->boolean('OrdCompartidaLider')->nullable();
        });

        // PT-02: el observer ya no se traga los fallos (hallazgos 4 y 5 de PT-01); estas tablas
        // existen en live y el fixture las necesita para no depender del catch silencioso.
        $this->createTablaDesdeModelo(\App\Models\Planeacion\ReqProgramaTejidoLine::class);
    }

    protected function tearDown(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('CatCodificados');
        Schema::connection('sqlsrv')->dropIfExists('ReqProgramaTejido');
        parent::tearDown();
    }

    public function test_sincronizar_cat_codificados_al_mover_no_anula_fecha_finaliza(): void
    {
        $fechaFinaliza = '2026-03-18 16:00:00';

        $programa = ReqProgramaTejido::query()->create([
            'NoProduccion' => '37001',
            'NoTelarId' => '201',
            'SalonTejidoId' => 'SMIT',
            'EnProceso' => false,
            'Posicion' => 1,
            'FechaInicio' => '2026-03-18 08:00:00',
            'FechaFinal' => '2026-03-18 14:00:00',
            'FechaArranque' => '2026-03-18 08:00:00',
            'FechaFinaliza' => $fechaFinaliza,
            'TotalPedido' => 100,
            'Produccion' => 40,
            'SaldoPedido' => 60,
        ]);

        CatCodificados::query()->create([
            'OrdenTejido' => '37001',
            'TelarId' => '201',
            'FechaArranque' => '2026-03-18 08:00:00',
            'FechaFinaliza' => $fechaFinaliza,
            'Pedido' => 100,
            'Produccion' => 40,
            'Saldos' => 60,
        ]);

        $metodo = new ReflectionMethod(MoverOrdenesController::class, 'sincronizarCatCodificados');
        $metodo->invoke(new MoverOrdenesController, [(int) $programa->Id]);

        $programa->refresh();
        $cat = CatCodificados::query()->where('OrdenTejido', '37001')->firstOrFail();

        $this->assertSame($fechaFinaliza, optional($programa->FechaFinaliza)->format('Y-m-d H:i:s'));
        $this->assertSame($fechaFinaliza, optional($cat->FechaFinaliza)->format('Y-m-d H:i:s'));
    }
}
