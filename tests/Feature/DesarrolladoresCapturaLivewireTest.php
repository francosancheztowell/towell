<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Desarrolladores\Captura;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

class DesarrolladoresCapturaLivewireTest extends TestCase
{
    use UsesSqlsrvSqlite;

    private const MODULO = 'Desarrolladores';

    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');
        $this->createAuthTable();
        Cache::flush();

        // El modelo Usuario apunta a dbo.SYSUsuario; SQLite solo entiende ese prefijo
        // si existe una base adjunta llamada dbo.
        DB::connection('sqlsrv')->statement("ATTACH DATABASE ':memory:' AS dbo");
        Schema::connection('sqlsrv')->create('dbo.SYSUsuario', function (Blueprint $table) {
            $table->increments('idusuario');
            $table->string('nombre')->nullable();
            $table->string('numero_empleado')->nullable();
            $table->string('area')->nullable();
            $table->string('turno')->nullable();
        });

        $schema = Schema::connection('sqlsrv');

        $schema->create('ReqProgramaTejido', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('NoProduccion')->nullable();
            $table->string('NoTelarId')->nullable();
            $table->string('SalonTejidoId')->nullable();
            $table->string('TamanoClave')->nullable();
            $table->string('NombreProducto')->nullable();
            $table->boolean('EnProceso')->default(false);
            $table->dateTime('FechaInicio')->nullable();
        });

        $schema->create('TelTelaresOperador', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('NoTelarId')->nullable();
            $table->string('SalonTejidoId')->nullable();
        });

        $schema->create('AtaMontadoTelas', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('NoJulio')->nullable();
            $table->string('Tipo')->nullable();
            $table->string('NoTelarId')->nullable();
            $table->string('InventSizeId')->nullable();
            $table->string('ConfigId')->nullable();
            $table->dateTime('Fecha')->nullable();
        });

        $schema->create('ReqModelosCodificados', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('SalonTejidoId')->nullable();
            $table->string('TamanoClave')->nullable();
            $table->string('CodigoDibujo')->nullable();
        });

        $schema->create('CatCodificados', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('OrdenTejido')->nullable();
            $table->string('TelarId')->nullable();
            $table->string('JulioRizo')->nullable();
            $table->string('JulioPie')->nullable();
            $table->integer('EfiInicial')->nullable();
            $table->integer('EfiFinal')->nullable();
            $table->float('DesperdicioTrama')->nullable();
        });
    }

    private function autenticar(array $acciones = ['acceso']): void
    {
        $this->actingAs($this->createUsuario(['area' => 'Desarrolladores']), 'web');
        $this->grantModulo(self::MODULO, $acciones);
    }

    private function sembrarTelarConOrden(): int
    {
        DB::connection('sqlsrv')->table('TelTelaresOperador')->insert([
            'NoTelarId' => '101', 'SalonTejidoId' => 'S1',
        ]);

        return (int) DB::connection('sqlsrv')->table('ReqProgramaTejido')->insertGetId([
            'NoProduccion' => '90001',
            'NoTelarId' => '101',
            'SalonTejidoId' => 'S1',
            'TamanoClave' => 'TAM1',
            'NombreProducto' => 'TOALLA GRANDE',
            'EnProceso' => false,
            'FechaInicio' => '2026-03-10 06:00:00',
        ]);
    }

    // ── Permisos ──────────────────────────────────────────────────────────

    public function test_sin_permiso_de_acceso_devuelve_403(): void
    {
        $this->actingAs($this->createUsuario(), 'web');

        Livewire::test(Captura::class)->assertForbidden();
    }

    public function test_el_modo_muestras_exige_su_propio_modulo(): void
    {
        $this->autenticar();

        // Tiene 'Desarrolladores', no 'Desarrolladores Muestras'.
        Livewire::test(Captura::class, ['modo' => 'muestras'])->assertForbidden();
    }

    // ── Pantalla ──────────────────────────────────────────────────────────

    public function test_carga_la_pantalla_con_el_selector_de_telares(): void
    {
        $this->autenticar();
        $this->sembrarTelarConOrden();

        Livewire::test(Captura::class)
            ->assertOk()
            ->assertSee('Seleccionar Telar')
            ->assertSee('101');
    }

    public function test_al_elegir_telar_aparecen_sus_producciones(): void
    {
        $this->autenticar();
        $this->sembrarTelarConOrden();

        Livewire::test(Captura::class)
            ->set('telarId', '101')
            ->assertSee('90001')
            ->assertSee('TOALLA GRANDE');
    }

    public function test_el_formulario_solo_aparece_al_seleccionar_una_produccion(): void
    {
        $this->autenticar();
        $id = $this->sembrarTelarConOrden();

        Livewire::test(Captura::class)
            ->set('telarId', '101')
            ->assertDontSee('Datos del Desarrollador')
            ->call('seleccionar', $id)
            ->assertSee('Datos del Desarrollador')
            ->assertSee('Codificación Modelo');
    }

    public function test_volver_a_pulsar_la_misma_fila_deselecciona(): void
    {
        $this->autenticar();
        $id = $this->sembrarTelarConOrden();

        Livewire::test(Captura::class)
            ->set('telarId', '101')
            ->call('seleccionar', $id)
            ->assertSet('produccionSeleccionada', $id)
            ->call('seleccionar', $id)
            ->assertSet('produccionSeleccionada', null)
            ->assertDontSee('Datos del Desarrollador');
    }

    public function test_cambiar_de_telar_limpia_la_seleccion(): void
    {
        $this->autenticar();
        $id = $this->sembrarTelarConOrden();

        Livewire::test(Captura::class)
            ->set('telarId', '101')
            ->call('seleccionar', $id)
            ->set('telarId', '')
            ->assertSet('produccionSeleccionada', null)
            ->assertSet('telarDestino', '');
    }

    // ── Detalle y total de pasadas ────────────────────────────────────────

    public function test_el_total_de_pasadas_es_la_suma_del_detalle(): void
    {
        $this->autenticar();
        $id = $this->sembrarTelarConOrden();

        $componente = Livewire::test(Captura::class)
            ->set('telarId', '101')
            ->call('seleccionar', $id)
            ->call('agregarFila')
            ->set('detalles.0.Pasadas', 30)
            ->call('agregarFila')
            ->set('detalles.1.Pasadas', 12);

        $this->assertSame(42, $componente->instance()->totalPasadas());
    }

    public function test_no_deja_capturar_mas_de_cinco_combinaciones(): void
    {
        $this->autenticar();
        $id = $this->sembrarTelarConOrden();

        $componente = Livewire::test(Captura::class)
            ->set('telarId', '101')
            ->call('seleccionar', $id);

        for ($i = 0; $i < 6; $i++) {
            $componente->call('agregarFila');
        }

        $this->assertCount(5, $componente->get('detalles'));
    }

    public function test_se_puede_quitar_una_fila_del_detalle(): void
    {
        $this->autenticar();
        $id = $this->sembrarTelarConOrden();

        $componente = Livewire::test(Captura::class)
            ->set('telarId', '101')
            ->call('seleccionar', $id)
            ->call('agregarFila')
            ->call('agregarFila')
            ->call('eliminarFila', 0);

        $this->assertCount(1, $componente->get('detalles'));
    }

    // ── Codificacion ──────────────────────────────────────────────────────

    public function test_las_casillas_forman_el_codigo_en_mayusculas(): void
    {
        $this->autenticar();
        $id = $this->sembrarTelarConOrden();

        $componente = Livewire::test(Captura::class)
            ->set('telarId', '101')
            ->call('seleccionar', $id)
            ->set('codificacion.0', 'a')
            ->set('codificacion.1', 'b')
            ->set('codificacion.2', '1');

        $this->assertSame('AB1', $componente->instance()->codificacionModelo());
    }

    public function test_el_codigo_de_dibujo_precarga_las_casillas_sin_el_sufijo(): void
    {
        $this->autenticar();
        $id = $this->sembrarTelarConOrden();

        DB::connection('sqlsrv')->table('ReqModelosCodificados')->insert([
            'SalonTejidoId' => 'S1', 'TamanoClave' => 'TAM1', 'CodigoDibujo' => 'XY99.JC5',
        ]);

        $componente = Livewire::test(Captura::class)
            ->set('telarId', '101')
            ->call('seleccionar', $id);

        $this->assertSame('XY99', $componente->instance()->codificacionModelo());
    }

    // ── Cambio de telar ───────────────────────────────────────────────────

    public function test_seleccionar_no_cuenta_como_cambio_de_telar(): void
    {
        $this->autenticar();
        $id = $this->sembrarTelarConOrden();

        $componente = Livewire::test(Captura::class)
            ->set('telarId', '101')
            ->call('seleccionar', $id);

        $this->assertSame('S1|101', $componente->get('telarDestino'));
        $this->assertFalse($componente->instance()->hayCambioTelar());
    }

    public function test_elegir_otro_telar_destino_activa_el_cambio(): void
    {
        $this->autenticar();
        $id = $this->sembrarTelarConOrden();

        $componente = Livewire::test(Captura::class)
            ->set('telarId', '101')
            ->call('seleccionar', $id)
            ->set('telarDestino', 'S1|205');

        $this->assertTrue($componente->instance()->hayCambioTelar());
    }

    // ── Precarga ──────────────────────────────────────────────────────────

    public function test_precarga_los_julios_ya_registrados_de_la_orden(): void
    {
        $this->autenticar();
        $id = $this->sembrarTelarConOrden();

        DB::connection('sqlsrv')->table('CatCodificados')->insert([
            'OrdenTejido' => '90001', 'TelarId' => '101',
            'JulioRizo' => 'JR-77', 'JulioPie' => 'JP-88', 'EfiInicial' => 85,
        ]);

        $componente = Livewire::test(Captura::class)
            ->set('telarId', '101')
            ->call('seleccionar', $id);

        $this->assertSame('JR-77', $componente->get('form.NumeroJulioRizo'));
        $this->assertSame('JP-88', $componente->get('form.NumeroJulioPie'));
        $this->assertSame(85, (int) $componente->get('form.EficienciaInicio'));
    }

    public function test_cancelar_limpia_el_formulario(): void
    {
        $this->autenticar();
        $id = $this->sembrarTelarConOrden();

        Livewire::test(Captura::class)
            ->set('telarId', '101')
            ->call('seleccionar', $id)
            ->set('form.HoraInicio', '06:30')
            ->call('cancelar')
            ->assertSet('produccionSeleccionada', null)
            ->assertSet('form.HoraInicio', '');
    }
}
