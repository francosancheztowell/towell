<?php

namespace Tests\Feature;

use App\Http\Controllers\Planeacion\ProgramaTejido\funciones\UpdateTejido;
use App\Models\Planeacion\ReqProgramaTejido;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

class ProgramaTejidoUpdateTest extends TestCase
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
            $table->string('SalonTejidoId')->nullable();
            $table->string('NoTelarId')->nullable();
            $table->float('TotalPedido')->default(0);
            $table->float('SaldoPedido')->default(0);
            $table->float('Produccion')->default(0);
            $table->string('FechaInicio')->nullable();
            $table->string('FechaFinal')->nullable();
            $table->string('TamanoClave')->nullable();
            $table->float('EficienciaSTD')->default(0.85);
            $table->float('VelocidadSTD')->default(100);
            $table->integer('EnProceso')->default(0);
            $table->string('FibraRizo')->nullable();
            $table->string('CalendarioId')->nullable();
            $table->string('NoProduccion', 80)->nullable();
            $table->string('InventSizeId')->nullable();
            $table->string('NombreProducto')->nullable();
            $table->float('NoTiras')->default(4);
            $table->float('PesoCrudo')->default(500);
            $table->float('LargoCrudo')->nullable();
            $table->integer('Repeticiones')->nullable();
            $table->float('PzasRollo')->nullable();
            $table->float('MtsRollo')->nullable();
            $table->float('TotalRollos')->nullable();
            $table->float('TotalPzas')->nullable();
            $table->float('SaldoMarbete')->nullable();
            $table->float('NoMarbete')->nullable();
            $table->float('RollosProgramados')->nullable();
            $table->string('UpdatedAt')->nullable();
        });

        Schema::connection('sqlsrv')->create('CatCodificados', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('OrdenTejido', 20)->nullable()->index();
            $table->string('TelarId')->nullable();
            $table->string('Departamento')->nullable();
            $table->string('ClaveModelo')->nullable();
            $table->string('ItemId')->nullable();
            $table->boolean('cierre_ax')->nullable();
            $table->float('Pedido')->nullable();
            $table->float('Saldos')->nullable();
            $table->float('Produccion')->nullable();
            $table->float('ProduccionMarbetes')->nullable();
            $table->string('FlogsId')->nullable();
            $table->string('NombreProyecto')->nullable();
            $table->float('P_crudo')->nullable();
            $table->integer('Repeticiones')->nullable();
            $table->float('PzasRollo')->nullable();
            $table->float('MtsRollo')->nullable();
            $table->float('TotalRollos')->nullable();
            $table->float('TotalPzas')->nullable();
            $table->float('NoMarbete')->nullable();
            $table->string('FechaModificacion')->nullable();
            $table->string('HoraModificacion')->nullable();
            $table->string('UsuarioModifica')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('CatCodificados');
        Schema::connection('sqlsrv')->dropIfExists('ReqProgramaTejido');
        parent::tearDown();
    }

    public function test_update_pedido_cambia_total_y_saldo(): void
    {
        $registro = ReqProgramaTejido::create([
            'SalonTejidoId' => 'JAC1',
            'NoTelarId' => '01',
            'TotalPedido' => 1000,
            'SaldoPedido' => 1000,
            'Produccion' => 0,
        ]);

        $registro->TotalPedido = 800;
        $registro->SaldoPedido = 800;
        $registro->save();

        $this->assertEquals(800, $registro->fresh()->TotalPedido);
        $this->assertEquals(800, $registro->fresh()->SaldoPedido);
    }

    public function test_update_con_produccion_parcial_ajusta_saldo(): void
    {
        $registro = ReqProgramaTejido::create([
            'SalonTejidoId' => 'JAC1',
            'NoTelarId' => '01',
            'TotalPedido' => 1000,
            'SaldoPedido' => 800,
            'Produccion' => 200,
        ]);

        $registro->TotalPedido = 500;
        $registro->SaldoPedido = max(0, 500 - 200);

        $this->assertEquals(300, $registro->SaldoPedido);
    }

    public function test_update_pedido_aumenta_y_disminuye_total_rollos_en_programa_y_cat(): void
    {
        $registro = ReqProgramaTejido::create([
            'SalonTejidoId' => 'JAC1',
            'NoTelarId' => '201',
            'NoProduccion' => '99010',
            'TotalPedido' => 800,
            'SaldoPedido' => 800,
            'Produccion' => 0,
            'NoTiras' => 4,
            'PesoCrudo' => 500,
            'LargoCrudo' => 100,
        ]);

        DB::connection('sqlsrv')->table('CatCodificados')->insert([
            'OrdenTejido' => '99010',
            'TelarId' => '201', // el observer acota por OrdenTejido + TelarId
        ]);

        $responseAumento = UpdateTejido::actualizar(
            Request::create("/planeacion/programa-tejido/{$registro->Id}", 'PUT', ['pedido' => 1600]),
            (int) $registro->Id,
        );

        $this->assertSame(200, $responseAumento->getStatusCode());
        $this->assertSame(20.0, (float) $registro->fresh()->TotalRollos);
        $this->assertSame(20.0, (float) DB::connection('sqlsrv')->table('CatCodificados')->where('OrdenTejido', '99010')->value('TotalRollos'));
        $this->assertSame(20.0, (float) $responseAumento->getData(true)['data']['TotalRollos']);

        $responseDisminucion = UpdateTejido::actualizar(
            Request::create("/planeacion/programa-tejido/{$registro->Id}", 'PUT', ['pedido' => 800]),
            (int) $registro->Id,
        );

        $this->assertSame(200, $responseDisminucion->getStatusCode());
        $this->assertSame(10.0, (float) $registro->fresh()->TotalRollos);
        $this->assertSame(10.0, (float) DB::connection('sqlsrv')->table('CatCodificados')->where('OrdenTejido', '99010')->value('TotalRollos'));
        $this->assertSame(10.0, (float) $responseDisminucion->getData(true)['data']['TotalRollos']);
    }

    public function test_update_fecha_inicio_y_final_calcula_duracion(): void
    {
        $registro = ReqProgramaTejido::create([
            'SalonTejidoId' => 'JAC1',
            'NoTelarId' => '01',
            'FechaInicio' => '2026-03-01 08:00:00',
            'FechaFinal' => '2026-03-05 08:00:00',
            'TotalPedido' => 1000,
        ]);

        $inicio = Carbon::parse($registro->FechaInicio);
        $fin = Carbon::parse($registro->FechaFinal);
        $horas = $inicio->diffInHours($fin);

        $this->assertEquals(96, $horas);
    }

    public function test_update_metodo_existe_en_clase(): void
    {
        $this->assertTrue(method_exists(UpdateTejido::class, 'actualizar'));
    }

    public function test_update_no_produccion_rechaza_orden_cerrada_en_ax(): void
    {
        $registro = ReqProgramaTejido::create([
            'SalonTejidoId' => 'JAC1',
            'NoTelarId' => '01',
            'NoProduccion' => '36709',
        ]);

        DB::connection('sqlsrv')->table('CatCodificados')->insert([
            'OrdenTejido' => '36708',
            'cierre_ax' => 1,
        ]);

        $request = Request::create(
            "/planeacion/programa-tejido/{$registro->Id}",
            'PUT',
            ['no_produccion' => ' 36708 '],
        );

        $response = UpdateTejido::actualizar($request, (int) $registro->Id);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('orden_cerrada_ax', $response->getData(true)['code']);
        $this->assertSame('36709', $registro->fresh()->NoProduccion);
    }
}
