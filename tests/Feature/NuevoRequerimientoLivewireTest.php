<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\InventarioTrama\NuevoRequerimiento;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Models\Tejido\TejTramaConsumos;
use App\Services\Tejido\InventarioTrama\CatalogoTramaService;
use App\Services\Tejido\InventarioTrama\NuevoRequerimientoService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

class NuevoRequerimientoLivewireTest extends TestCase
{
    use UsesSqlsrvSqlite;

    private const CALIBRES = [['ItemId' => '20'], ['ItemId' => '600/1T']];

    private const FIBRAS = [['ConfigId' => 'FIL'], ['ConfigId' => 'ALG']];

    private const COLORES = [
        ['InventColorId' => 'C1', 'Name' => 'Rojo'],
        ['InventColorId' => 'C2', 'Name' => null],
    ];

    private MockInterface $catalogo;

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

        // El catalogo de calibres/fibras/colores sale de TI_PRO (sqlsrv_ti); abrirModal() y
        // updatedModalCalibre() lo consultan. Se mockea para no depender del ERP. byDefault()
        // deja que cada test lo reemplace con expectativas estrictas (once/never/with).
        $this->catalogo = $this->mock(CatalogoTramaService::class, function (MockInterface $mock) {
            $mock->shouldReceive('calibres')->andReturn(self::CALIBRES)->byDefault();
            $mock->shouldReceive('fibras')->andReturn(self::FIBRAS)->byDefault();
            $mock->shouldReceive('colores')->andReturn(self::COLORES)->byDefault();
        });

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

    public function test_cargar_la_pagina_no_consulta_el_catalogo_de_ti(): void
    {
        // Si mount() tocara TI_PRO, la pantalla entera dependeria del ERP para abrir.
        $this->catalogo->shouldNotReceive('calibres', 'fibras', 'colores');

        Livewire::test(NuevoRequerimiento::class)
            ->assertSet('modalAbierto', false)
            ->assertSet('calibres', []);
    }

    public function test_abrir_modal_consulta_calibres_una_vez_y_los_mapea(): void
    {
        $this->catalogo->shouldReceive('calibres')->once()->withNoArgs()->andReturn(self::CALIBRES);
        $this->catalogo->shouldNotReceive('fibras', 'colores');

        Livewire::test(NuevoRequerimiento::class)
            ->call('abrirModal', '201')
            ->assertSet('calibres', [
                ['value' => '20', 'label' => '20'],
                ['value' => '600/1T', 'label' => '600/1T'],
            ])
            ->assertSet('fibras', [])
            ->assertSet('colores', []);
    }

    public function test_elegir_calibre_consulta_fibras_y_colores_de_ese_calibre(): void
    {
        $this->catalogo->shouldReceive('fibras')->once()->with('600/1T')->andReturn(self::FIBRAS);
        $this->catalogo->shouldReceive('colores')->once()->with('600/1T')->andReturn(self::COLORES);

        Livewire::test(NuevoRequerimiento::class)
            ->call('abrirModal', '201')
            ->set('modalCalibre', '600/1T')
            ->assertSet('fibras', ['FIL', 'ALG'])
            ->assertSet('colores', [
                ['value' => 'C1', 'label' => 'C1 - Rojo', 'name' => 'Rojo'],
                ['value' => 'C2', 'label' => 'C2 - ', 'name' => ''],
            ]);
    }

    public function test_vaciar_el_calibre_no_consulta_ti_y_limpia_las_listas(): void
    {
        // Solo la seleccion de '600/1T' debe llegar al catalogo; la de '' no.
        $this->catalogo->shouldReceive('fibras')->once()->with('600/1T')->andReturn(self::FIBRAS);
        $this->catalogo->shouldReceive('colores')->once()->with('600/1T')->andReturn(self::COLORES);

        Livewire::test(NuevoRequerimiento::class)
            ->call('abrirModal', '201')
            ->set('modalCalibre', '600/1T')
            ->set('modalCalibre', '')
            ->assertSet('fibras', [])
            ->assertSet('colores', []);
    }

    public function test_cambiar_de_calibre_descarta_fibra_y_color_ya_elegidos(): void
    {
        Livewire::test(NuevoRequerimiento::class)
            ->call('abrirModal', '201')
            ->set('modalCalibre', '600/1T')
            ->set('modalFibra', 'FIL')
            ->set('modalCodColor', 'C1')
            ->assertSet('modalNombreColor', 'Rojo')
            ->set('modalCalibre', '20')
            ->assertSet('modalFibra', '')
            ->assertSet('modalCodColor', '')
            ->assertSet('modalNombreColor', '');
    }

    public function test_elegir_codigo_de_color_toma_el_nombre_del_catalogo(): void
    {
        Livewire::test(NuevoRequerimiento::class)
            ->call('abrirModal', '201')
            ->set('modalCalibre', '600/1T')
            ->set('modalCodColor', 'C1')
            ->assertSet('modalNombreColor', 'Rojo')
            ->set('modalCodColor', 'NO-EXISTE')
            ->assertSet('modalNombreColor', '');
    }

    public function test_agregar_guarda_calibre_transformado_cantidad_y_folio(): void
    {
        $componente = Livewire::test(NuevoRequerimiento::class)
            ->call('abrirModal', '201')
            ->set('modalCalibre', '600/1T')
            ->set('modalFibra', 'ALG')
            ->set('modalCodColor', 'C1')
            ->set('modalCantidad', 5)
            ->call('agregarRequerimiento')
            ->assertDispatched('aviso', tipo: 'success', texto: 'Nuevo requerimiento agregado');

        $consumo = TejTramaConsumos::where('FibraTrama', 'ALG')->sole();

        // '600/1T' -> '600.1': la diagonal pasa a punto y se quitan las letras.
        $this->assertEqualsWithDelta(600.1, (float) $consumo->CalibreTrama, 0.0001);
        $this->assertEqualsWithDelta(5.0, (float) $consumo->Cantidad, 0.0001);
        $this->assertSame('C1', $consumo->CodColorTrama);
        $this->assertSame('Rojo', $consumo->ColorTrama);
        $this->assertSame('201', (string) $consumo->NoTelarId);
        $this->assertSame($componente->get('folioActual'), $consumo->Folio);
    }

    /** @return array<string, array{array<string, string>, string}> */
    public static function camposFaltantes(): array
    {
        return [
            'sin calibre' => [[], 'Selecciona un calibre'],
            'sin fibra' => [['modalCalibre' => '600/1T'], 'Selecciona una fibra'],
            'sin codigo de color' => [['modalCalibre' => '600/1T', 'modalFibra' => 'FIL'], 'Selecciona un código de color'],
            'sin nombre de color' => [
                ['modalCalibre' => '600/1T', 'modalFibra' => 'FIL', 'modalCodColor' => 'C1', 'modalNombreColor' => ''],
                'El nombre de color es requerido',
            ],
        ];
    }

    /** @param  array<string, string>  $campos */
    #[DataProvider('camposFaltantes')]
    public function test_agregar_con_campos_faltantes_avisa_y_no_guarda(array $campos, string $aviso): void
    {
        $componente = Livewire::test(NuevoRequerimiento::class)->call('abrirModal', '201');

        foreach ($campos as $campo => $valor) {
            $componente->set($campo, $valor);
        }

        $antes = TejTramaConsumos::count();

        $componente->call('agregarRequerimiento')
            ->assertDispatched('aviso', tipo: 'warning', texto: $aviso)
            ->assertNotDispatched('aviso', tipo: 'success')
            ->assertSet('modalAbierto', true);

        $this->assertSame($antes, TejTramaConsumos::count());
    }

    public function test_cerrar_modal_limpia_todo_lo_elegido(): void
    {
        Livewire::test(NuevoRequerimiento::class)
            ->call('abrirModal', '201')
            ->set('modalCalibre', '600/1T')
            ->set('modalFibra', 'FIL')
            ->set('modalCodColor', 'C1')
            ->set('modalCantidad', 4)
            ->call('cerrarModal')
            ->assertSet('modalCalibre', '')
            ->assertSet('modalFibra', '')
            ->assertSet('modalCodColor', '')
            ->assertSet('modalNombreColor', '')
            ->assertSet('modalCantidad', 0)
            ->assertSet('fibras', [])
            ->assertSet('colores', []);
    }

    public function test_reabrir_el_modal_vuelve_a_pedir_calibres_y_arranca_limpio(): void
    {
        $this->catalogo->shouldReceive('calibres')->twice()->andReturn(self::CALIBRES);

        Livewire::test(NuevoRequerimiento::class)
            ->call('abrirModal', '201')
            ->set('modalCalibre', '600/1T')
            ->set('modalFibra', 'FIL')
            ->call('cerrarModal')
            ->call('abrirModal', '201')
            ->assertSet('telarModal', '201')
            ->assertSet('modalCalibre', '')
            ->assertSet('modalFibra', '')
            ->assertSet('fibras', []);
    }
}
