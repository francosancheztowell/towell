<?php

namespace Tests\Unit;

use App\Http\Controllers\Mantenimiento\MantenimientoParosController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

class MantenimientoParosControllerTest extends TestCase
{
    use UsesSqlsrvSqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');

        $schema = Schema::connection('sqlsrv');

        $this->createAuthTable();

        $schema->create('TelTelaresOperador', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('numero_empleado')->nullable();
            $table->string('nombreEmpl')->nullable();
            $table->string('NoTelarId')->nullable();
            $table->string('Turno')->nullable();
            $table->string('SalonTejidoId')->nullable();
            $table->boolean('Supervisor')->nullable();
        });

        $schema->create('ReqProgramaTejido', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('NoProduccion')->nullable();
            $table->string('NombreProducto')->nullable();
            $table->date('FechaInicio')->nullable();
            $table->string('SalonTejidoId')->nullable();
            $table->string('NoTelarId')->nullable();
            $table->integer('EnProceso')->nullable();
        });
    }

    public function test_maquinas_de_calidad_muestra_todos_los_telares_disponibles(): void
    {
        $usuario = $this->createUsuario([
            'numero_empleado' => '1001',
            'area' => 'Calidad',
        ]);

        $this->actingAs($usuario, 'web');

        \DB::connection('sqlsrv')->table('TelTelaresOperador')->insert([
            [
                'numero_empleado' => '1001',
                'nombreEmpl' => 'Usuario Uno',
                'NoTelarId' => 'T-01',
                'Turno' => '1',
                'SalonTejidoId' => 'Smith',
                'Supervisor' => 0,
            ],
            [
                'numero_empleado' => '2002',
                'nombreEmpl' => 'Usuario Dos',
                'NoTelarId' => 'T-02',
                'Turno' => '2',
                'SalonTejidoId' => 'Jacquard',
                'Supervisor' => 0,
            ],
            [
                'numero_empleado' => '3003',
                'nombreEmpl' => 'Usuario Tres',
                'NoTelarId' => 'T-03',
                'Turno' => '3',
                'SalonTejidoId' => 'KarlMayer',
                'Supervisor' => 1,
            ],
            [
                'numero_empleado' => '1001',
                'nombreEmpl' => 'Usuario Uno',
                'NoTelarId' => 'T-01',
                'Turno' => '1',
                'SalonTejidoId' => 'Smith',
                'Supervisor' => 0,
            ],
        ]);

        $controller = new MantenimientoParosController;
        $response = $controller->maquinas('Calidad');
        $payload = $response->getData(true);

        $this->assertTrue($payload['success']);
        $this->assertSame(
            ['T-01', 'T-02', 'T-03'],
            collect($payload['data'])->pluck('MaquinaId')->all()
        );
    }

    public function test_orden_trabajo_de_calidad_ignora_el_filtro_por_salon(): void
    {
        $usuario = $this->createUsuario([
            'numero_empleado' => '1001',
            'area' => 'Calidad',
        ]);

        $this->actingAs($usuario, 'web');

        \DB::connection('sqlsrv')->table('ReqProgramaTejido')->insert([
            [
                'NoProduccion' => 'OP-100',
                'NombreProducto' => 'Toalla 500',
                'FechaInicio' => '2026-03-20',
                'SalonTejidoId' => 'SMIT',
                'NoTelarId' => 'T-99',
                'EnProceso' => 1,
            ],
            [
                'NoProduccion' => 'OP-090',
                'NombreProducto' => 'Toalla 300',
                'FechaInicio' => '2026-03-18',
                'SalonTejidoId' => 'JACQUARD',
                'NoTelarId' => 'T-99',
                'EnProceso' => 1,
            ],
        ]);

        $controller = new MantenimientoParosController;
        $response = $controller->ordenTrabajo('Calidad', 'T-99');
        $payload = $response->getData(true);

        $this->assertTrue($payload['success']);
        $this->assertCount(2, $payload['data']);
        $this->assertSame('OP-100', $payload['data'][0]['Orden_Prod']);
    }
}
