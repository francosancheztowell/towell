<?php

namespace Tests\Unit;

use App\Http\Controllers\Tejedores\Desarrolladores\Funciones\ConsultasDesarrolladorService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class ConsultasDesarrolladorServiceTest extends TestCase
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

        Schema::connection('sqlsrv')->create('AtaMontadoTelas', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('NoJulio')->nullable();
            $table->string('InventSizeId')->nullable();
            $table->string('Tipo')->nullable();
            $table->string('NoTelarId')->nullable();
            $table->string('Fecha')->nullable();
        });

        Schema::connection('sqlsrv')->create('ReqProgramaTejido', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('SalonTejidoId')->nullable();
            $table->string('NoTelarId')->nullable();
            $table->string('NoProduccion')->nullable();
            $table->string('FechaInicio')->nullable();
            $table->string('TamanoClave')->nullable();
            $table->string('NombreProducto')->nullable();
            $table->integer('EnProceso')->default(0);
        });
    }

    public function test_obtener_julios_por_telar_filtra_por_no_telar_id_y_tipo(): void
    {
        DB::connection('sqlsrv')->table('AtaMontadoTelas')->insert([
            [
                'NoJulio' => 'JR-100',
                'InventSizeId' => 'A',
                'Tipo' => 'Rizo',
                'NoTelarId' => '101',
                'Fecha' => '2026-03-16 10:00:00',
            ],
            [
                'NoJulio' => 'JR-100',
                'InventSizeId' => 'A',
                'Tipo' => 'Rizo',
                'NoTelarId' => '101',
                'Fecha' => '2026-03-15 09:00:00',
            ],
            [
                'NoJulio' => 'JP-200',
                'InventSizeId' => 'B',
                'Tipo' => 'Pie',
                'NoTelarId' => '101',
                'Fecha' => '2026-03-16 08:00:00',
            ],
            [
                'NoJulio' => 'JR-999',
                'InventSizeId' => 'C',
                'Tipo' => 'Rizo',
                'NoTelarId' => '202',
                'Fecha' => '2026-03-16 07:00:00',
            ],
            [
                'NoJulio' => 'JP-999',
                'InventSizeId' => 'D',
                'Tipo' => 'Pie',
                'NoTelarId' => '202',
                'Fecha' => '2026-03-16 06:00:00',
            ],
        ]);

        $service = new ConsultasDesarrolladorService();

        $resultado = $service->obtenerJuliosPorTelar('101');

        $this->assertTrue($resultado['success']);
        $this->assertSame(['JR-100'], collect($resultado['juliosRizo'])->pluck('NoJulio')->all());
        $this->assertSame(['JP-200'], collect($resultado['juliosPie'])->pluck('NoJulio')->all());
    }

    public function test_obtener_telares_destino_es_metodo_publico(): void
    {
        $reflection = new ReflectionMethod(ConsultasDesarrolladorService::class, 'obtenerTelaresDestino');

        $this->assertTrue($reflection->isPublic(), 'obtenerTelaresDestino debe ser un método público');
    }

    public function test_obtener_telares_destino_label_es_solo_no_telar_id(): void
    {
        DB::connection('sqlsrv')->table('ReqProgramaTejido')->insert([
            ['SalonTejidoId' => 'S1', 'NoTelarId' => '101', 'NoProduccion' => null, 'FechaInicio' => null, 'TamanoClave' => null, 'NombreProducto' => null, 'EnProceso' => 0],
            ['SalonTejidoId' => 'S2', 'NoTelarId' => '202', 'NoProduccion' => null, 'FechaInicio' => null, 'TamanoClave' => null, 'NombreProducto' => null, 'EnProceso' => 0],
        ]);

        $service = new ConsultasDesarrolladorService();
        $result = $service->obtenerTelaresDestino();

        $labels = $result->pluck('label')->all();

        $this->assertContains('101', $labels);
        $this->assertContains('202', $labels);

        // Must NOT contain the old 'telar (salon)' format
        foreach ($labels as $label) {
            $this->assertStringNotContainsString('(', $label, "Label should not contain '(' — old format was 'telar (salon)'");
        }
    }

    public function test_obtener_telares_destino_value_es_salon_pipe_telar(): void
    {
        DB::connection('sqlsrv')->table('ReqProgramaTejido')->insert([
            ['SalonTejidoId' => 'S1', 'NoTelarId' => '101', 'NoProduccion' => null, 'FechaInicio' => null, 'TamanoClave' => null, 'NombreProducto' => null, 'EnProceso' => 0],
            ['SalonTejidoId' => 'S2', 'NoTelarId' => '202', 'NoProduccion' => null, 'FechaInicio' => null, 'TamanoClave' => null, 'NombreProducto' => null, 'EnProceso' => 0],
        ]);

        $service = new ConsultasDesarrolladorService();
        $result = $service->obtenerTelaresDestino();

        $values = $result->pluck('value')->all();

        $this->assertContains('S1|101', $values);
        $this->assertContains('S2|202', $values);
    }

    public function test_obtener_telares_destino_excluye_nulos(): void
    {
        DB::connection('sqlsrv')->table('ReqProgramaTejido')->insert([
            // Valid row
            ['SalonTejidoId' => 'S1', 'NoTelarId' => '101', 'NoProduccion' => null, 'FechaInicio' => null, 'TamanoClave' => null, 'NombreProducto' => null, 'EnProceso' => 0],
            // Null salon
            ['SalonTejidoId' => null, 'NoTelarId' => '999', 'NoProduccion' => null, 'FechaInicio' => null, 'TamanoClave' => null, 'NombreProducto' => null, 'EnProceso' => 0],
            // Null telar
            ['SalonTejidoId' => 'S3', 'NoTelarId' => null, 'NoProduccion' => null, 'FechaInicio' => null, 'TamanoClave' => null, 'NombreProducto' => null, 'EnProceso' => 0],
            // Both null
            ['SalonTejidoId' => null, 'NoTelarId' => null, 'NoProduccion' => null, 'FechaInicio' => null, 'TamanoClave' => null, 'NombreProducto' => null, 'EnProceso' => 0],
        ]);

        $service = new ConsultasDesarrolladorService();
        $result = $service->obtenerTelaresDestino();

        $this->assertCount(1, $result, 'Only rows with both SalonTejidoId and NoTelarId non-null should be returned');
        $this->assertSame('S1|101', $result->first()['value']);
    }

    public function test_obtener_producciones_incluye_campo_id(): void
    {
        DB::connection('sqlsrv')->table('ReqProgramaTejido')->insert([
            [
                'SalonTejidoId' => 'S1',
                'NoTelarId' => '101',
                'NoProduccion' => 'ORD-001',
                'FechaInicio' => '2026-03-18 08:00:00',
                'TamanoClave' => '100x50',
                'NombreProducto' => 'Toalla Rizo',
                'EnProceso' => 0,
            ],
        ]);

        $service = new ConsultasDesarrolladorService();
        $result = $service->obtenerProducciones('101');

        $this->assertTrue($result['success']);
        $this->assertNotEmpty($result['producciones']);

        $firstItem = $result['producciones']->first();
        $this->assertArrayHasKey('Id', $firstItem->toArray(), 'obtenerProducciones should include the Id field in its select');
    }

    public function test_obtener_producciones_excluye_filas_sin_orden(): void
    {
        DB::connection('sqlsrv')->table('ReqProgramaTejido')->insert([
            [
                'SalonTejidoId' => 'S1',
                'NoTelarId' => '101',
                'NoProduccion' => null,
                'FechaInicio' => '2026-03-18 08:00:00',
                'TamanoClave' => '100x50',
                'NombreProducto' => 'Sin orden',
                'EnProceso' => 0,
            ],
            [
                'SalonTejidoId' => 'S1',
                'NoTelarId' => '101',
                'NoProduccion' => '',
                'FechaInicio' => '2026-03-19 08:00:00',
                'TamanoClave' => '100x51',
                'NombreProducto' => 'Orden vacía',
                'EnProceso' => 0,
            ],
            [
                'SalonTejidoId' => 'S1',
                'NoTelarId' => '101',
                'NoProduccion' => 'ORD-777',
                'FechaInicio' => '2026-03-20 08:00:00',
                'TamanoClave' => '100x52',
                'NombreProducto' => 'Con orden',
                'EnProceso' => 0,
            ],
        ]);

        $service = new ConsultasDesarrolladorService();
        $result = $service->obtenerProducciones('101');

        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['producciones']);
        $this->assertSame('ORD-777', $result['producciones']->first()->NoProduccion);
    }
}
