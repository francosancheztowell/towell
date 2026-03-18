<?php

namespace Tests\Unit;

use App\Http\Controllers\Tejedores\Desarrolladores\Funciones\ConsultasDesarrolladorService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
}
