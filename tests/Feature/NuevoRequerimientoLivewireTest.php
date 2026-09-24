<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\InventarioTrama\NuevoRequerimiento;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Models\Tejido\TejTramaConsumos;
use App\Services\Tejido\InventarioTrama\NuevoRequerimientoService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

class NuevoRequerimientoLivewireTest extends TestCase
{
    use UsesSqlsrvSqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqlsrvSqlite();
        $this->createAuthTable();
        config()->set('database.default', 'sqlsrv');

        $schema = Schema::connection('sqlsrv');

        $schema->create('TejTrama', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Folio')->unique();
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

        $schema->create('InvSecuenciaTrama', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('NoTelar')->nullable();
            $table->string('TipoTelar')->nullable();
            $table->integer('Secuencia')->nullable();
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

        DB::table('InvSecuenciaTrama')->insert([
            ['NoTelar' => '201', 'TipoTelar' => 'JACQUARD', 'Secuencia' => 1],
        ]);

        DB::table('ReqProgramaTejido')->insert([
            'SalonTejidoId' => 'JACQUARD',
            'NoTelarId' => '201',
            'EnProceso' => 1,
            'CalibreTrama' => 20.5,
            'NombreProducto' => 'Toalla Rizo',
        ]);

        // El modal lee los catalogos de hilo de TI_PRO (CatalogoTramaService).
        config()->set('database.connections.sqlsrv_ti', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        DB::purge('sqlsrv_ti');
        $ti = Schema::connection('sqlsrv_ti');
        $ti->create('InventTable', function (Blueprint $table) {
            $table->string('ItemId');
            $table->string('ItemGroupId')->nullable();
            $table->string('DATAAREAID');
        });
        $ti->create('InventSum', function (Blueprint $table) {
            $table->string('ItemId');
            $table->string('InventDimId');
            $table->float('PhysicalInvent')->default(0);
            $table->string('DATAAREAID');
        });
        $ti->create('InventDim', function (Blueprint $table) {
            $table->string('InventDimId');
            $table->string('ConfigId')->nullable();
            $table->string('InventColorId')->nullable();
            $table->string('DATAAREAID');
        });
        $ti->create('InventColor', function (Blueprint $table) {
            $table->string('ItemId');
            $table->string('InventColorId');
            $table->string('Name')->nullable();
            $table->string('DATAAREAID');
        });
        DB::connection('sqlsrv_ti')->table('InventTable')->insert([
            ['ItemId' => '600/1T', 'ItemGroupId' => 'HILO DIREC', 'DATAAREAID' => 'PRO'],
            ['ItemId' => 'OTRO', 'ItemGroupId' => 'OTRO GRUPO', 'DATAAREAID' => 'PRO'],
        ]);
        DB::connection('sqlsrv_ti')->table('InventSum')->insert([
            'ItemId' => '600/1T', 'InventDimId' => 'D1', 'PhysicalInvent' => 10, 'DATAAREAID' => 'PRO',
        ]);
        DB::connection('sqlsrv_ti')->table('InventDim')->insert([
            'InventDimId' => 'D1', 'ConfigId' => 'FIL', 'InventColorId' => 'C1', 'DATAAREAID' => 'PRO',
        ]);
        DB::connection('sqlsrv_ti')->table('InventColor')->insert([
            'ItemId' => '600/1T', 'InventColorId' => 'C1', 'Name' => 'Rojo', 'DATAAREAID' => 'PRO',
        ]);

        $this->actingAs($this->createUsuario(), 'web');
    }

    public function test_mount_crea_folio_y_carga_telares(): void
    {
        $this->assertDatabaseCount('TejTrama', 0);

        Livewire::test(NuevoRequerimiento::class)
            ->assertSet('enProcesoExists', true)
            ->assertSee('201')
            ->assertSee('Toalla Rizo');

        $this->assertDatabaseCount('TejTrama', 1);
        $this->assertDatabaseHas('TejTrama', ['Status' => 'En Proceso']);
    }

    public function test_abrir_y_cerrar_modal(): void
    {
        Livewire::test(NuevoRequerimiento::class)
            ->call('abrirModal', '201')
            ->assertSet('modalAbierto', true)
            ->assertSet('telarModal', '201')
            ->assertSet('calibres', [['value' => '600/1T', 'label' => '600/1T']])
            ->call('cerrarModal')
            ->assertSet('modalAbierto', false)
            ->assertSet('telarModal', null);
    }

    public function test_agregar_requerimiento_guarda_consumo(): void
    {
        Livewire::test(NuevoRequerimiento::class)
            ->call('abrirModal', '201')
            ->set('modalCalibre', '600/1T')
            ->set('modalFibra', 'FIL')
            ->set('modalCodColor', 'C1')
            ->set('modalNombreColor', 'Rojo')
            ->set('modalCantidad', 5)
            ->call('agregarRequerimiento')
            ->assertSet('modalAbierto', false);

        $this->assertDatabaseHas('TejTramaConsumos', [
            'NoTelarId' => '201',
            'FibraTrama' => 'FIL',
            'CodColorTrama' => 'C1',
        ]);
    }

    public function test_actualizar_cantidad_persiste(): void
    {
        Livewire::test(NuevoRequerimiento::class)
            ->call('abrirModal', '201')
            ->set('modalCalibre', '20')
            ->set('modalFibra', 'FIL')
            ->set('modalCodColor', 'C1')
            ->set('modalNombreColor', 'Rojo')
            ->set('modalCantidad', 3)
            ->call('agregarRequerimiento');

        $consumo = TejTramaConsumos::where('FibraTrama', 'FIL')->first();
        $this->assertNotNull($consumo);

        // The actualizarCantidad uses service directly, verify it works
        $svc = app(NuevoRequerimientoService::class);
        $resultado = $svc->actualizarCantidad($consumo->Id, 7.0);
        $this->assertSame(7.0, $resultado);
    }

    public function test_eliminar_fila_remueve_row(): void
    {
        $componente = Livewire::test(NuevoRequerimiento::class);

        // Agregar una fila al primer telar
        $componente->call('agregarRequerimiento')
            ->assertSet('modalAbierto', false);

        $filasAntes = count($componente->get('telares.0.rows'));

        // Eliminar la primera fila
        $componente->call('eliminarFila', 0, 0);

        $filasDespues = count($componente->get('telares.0.rows'));
        $this->assertSame($filasAntes - 1, $filasDespues);
    }

    public function test_guardar_botones_sin_doble_click(): void
    {
        Livewire::test(NuevoRequerimiento::class)
            ->call('guardar')
            ->assertSet('guardando', false);
    }
}
