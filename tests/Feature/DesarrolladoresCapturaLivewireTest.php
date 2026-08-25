<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Desarrolladores\Captura;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Features\SupportTesting\Testable;
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
            // Detalle de hilos: es lo que alimenta la tabla "Detalles de la Orden".
            $table->string('CalibreTrama')->nullable();
            $table->float('CalibreTrama2')->nullable();
            $table->integer('PasadasTrama')->nullable();
            $table->string('CalibreComb1')->nullable();
            $table->float('CalibreComb12')->nullable();
            $table->string('FibraComb1')->nullable();
            $table->integer('PasadasComb1')->nullable();
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

        $schema->create('TejCatMatrizDesarrolladores', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Codigo', 20);
            $table->string('CodigoInterno', 20);
            $table->float('Divisor');
            $table->string('Nombre', 60);
            $table->boolean('Vigente')->default(true);
            $table->timestamps();
        });

        DB::connection('sqlsrv')->table('TejCatMatrizDesarrolladores')->insert([
            ['Codigo' => '10/1', 'CodigoInterno' => '10.1', 'Divisor' => 10, 'Nombre' => 'HILO 10/1', 'Vigente' => 1],
            // Mismo calibre, mismo divisor: no hay nada que elegir en la columna Hilo.
            ['Codigo' => '10/1T', 'CodigoInterno' => '10.1', 'Divisor' => 10, 'Nombre' => 'HILO 10/1 TENIDO', 'Vigente' => 1],
            // Mismo calibre que 12/1 pero DISTINTO divisor: ahi si hay que elegir.
            ['Codigo' => '12/1X', 'CodigoInterno' => '12.1', 'Divisor' => 6, 'Nombre' => 'HILO 12/1 DOBLE', 'Vigente' => 1],
            ['Codigo' => '12/1', 'CodigoInterno' => '12.1', 'Divisor' => 12, 'Nombre' => 'HILO 12/1', 'Vigente' => 1],
            ['Codigo' => '600/1', 'CodigoInterno' => '600.1', 'Divisor' => 8.86, 'Nombre' => 'HILO 600', 'Vigente' => 1],
            ['Codigo' => '8/1', 'CodigoInterno' => '8.1', 'Divisor' => 8, 'Nombre' => 'HILO 8/1', 'Vigente' => 0],
        ]);
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

    // ── Pagina completa ───────────────────────────────────────────────────

    /**
     * Los tests de Livewire::test renderizan el componente pero no el layout.
     * Esto recorre la ruta real: middleware, controlador, layout y el componente
     * montado dentro, que es donde saldria un fallo de integracion.
     */
    public function test_la_pagina_completa_responde_y_monta_el_componente(): void
    {
        $this->autenticar();
        $this->sembrarTelarConOrden();

        $this->get('/tejedores/desarrolladores')
            ->assertOk()
            ->assertSee('Seleccionar Telar');
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

    // ── Confirmacion de lo irreversible ───────────────────────────────────

    public function test_un_cambio_de_telar_no_se_guarda_sin_confirmar(): void
    {
        $this->autenticar();
        $id = $this->sembrarTelarConOrden();

        $componente = Livewire::test(Captura::class)
            ->set('telarId', '101')
            ->call('seleccionar', $id)
            ->set('telarDestino', 'S1|205');

        $this->assertContains('Confirma lo que va a pasar al guardar.', $componente->instance()->problemas());

        $componente->call('guardar')->assertDispatched('aviso', tipo: 'error');
    }

    public function test_un_guardado_corriente_no_pide_confirmacion(): void
    {
        $this->autenticar();
        $id = $this->sembrarTelarConOrden();

        $componente = Livewire::test(Captura::class)
            ->set('telarId', '101')
            ->call('seleccionar', $id);

        $this->assertFalse($componente->instance()->requiereConfirmacion());
    }

    // ── Aislamiento entre operadores ──────────────────────────────────────

    /**
     * Los catalogos se memoizan en una clave compartida por todos los usuarios, asi
     * que nada que dependa de la sesion puede viajar dentro de ese arreglo: el nombre
     * del desarrollador acababa precargado en la pantalla del siguiente operador, y es
     * el que se escribe en el registro de produccion.
     */
    public function test_el_nombre_precargado_es_el_del_usuario_en_sesion(): void
    {
        $this->autenticar();
        $primero = Livewire::test(Captura::class)->get('form.Desarrollador');

        $otro = $this->createUsuario(['area' => 'Otra', 'nombre' => 'OPERADOR DOS']);
        $this->actingAs($otro, 'web');

        $segundo = Livewire::test(Captura::class)->get('form.Desarrollador');

        $this->assertSame('OPERADOR DOS', $segundo);
        $this->assertNotSame($primero, $segundo);
    }

    /**
     * El checkbox se marca "sucio" en cuanto el operador lo pulsa, y desde ahi el
     * navegador ignora el atributo checked que llega en el morph: el tick de la fila
     * anterior se quedaba pegado y se veian dos seleccionadas. Se arregla haciendo que
     * la llave cambie con el estado, para que Livewire reemplace el nodo. Este test
     * cuida ese mecanismo; el sintoma en si es de DOM y no se ve desde aqui.
     */
    public function test_la_llave_del_checkbox_cambia_con_la_seleccion(): void
    {
        $this->autenticar();
        $primera = $this->sembrarTelarConOrden();
        $segunda = (int) DB::connection('sqlsrv')->table('ReqProgramaTejido')->insertGetId([
            'NoProduccion' => '90002',
            'NoTelarId' => '101',
            'SalonTejidoId' => 'S1',
            'TamanoClave' => 'TAM2',
            'NombreProducto' => 'TOALLA CHICA',
            'EnProceso' => false,
            'FechaInicio' => '2026-03-11 06:00:00',
        ]);

        Livewire::test(Captura::class)
            ->set('telarId', '101')
            ->call('seleccionar', $primera)
            ->assertSee('sel-'.$primera.'-on', false)
            ->assertSee('sel-'.$segunda.'-off', false)
            ->call('seleccionar', $segunda)
            ->assertSee('sel-'.$primera.'-off', false)
            ->assertSee('sel-'.$segunda.'-on', false);
    }

    // ── Doble guardado ────────────────────────────────────────────────────

    /**
     * Deshabilitar el boton mientras viaja tapa el caso normal, no el real: dos clicks
     * muy seguidos, o dos pestanas, mandan dos peticiones que el servidor atiende igual.
     * El candado vive fuera de la peticion y es lo unico que garantiza una sola escritura.
     */
    public function test_con_el_candado_tomado_el_guardado_no_se_ejecuta(): void
    {
        $this->autenticar();
        $id = $this->sembrarTelarConOrdenConDetalle('10.1', 10);

        $componente = $this->capturaCompleta($id);

        // Simula la peticion gemela que ya esta dentro del guardado.
        $candado = Cache::lock('desarrolladores:guardar:programa:101:'.$id, 15);
        $this->assertTrue($candado->get(), 'El candado deberia estar libre antes.');

        try {
            $componente->call('guardar')
                ->assertDispatched('aviso', tipo: 'warning');
        } finally {
            $candado->release();
        }

        // Y una vez liberado, el guardado vuelve a intentarse con normalidad.
        $this->assertTrue(Cache::lock('desarrolladores:guardar:programa:101:'.$id, 15)->get());
    }

    // ── Catalogo de calibres ──────────────────────────────────────────────

    /** El renglon de la orden se empata con su hilo del catalogo por el par (Calibre, Hilo). */
    public function test_el_detalle_resuelve_su_hilo_del_catalogo(): void
    {
        $this->autenticar();
        $id = $this->sembrarTelarConOrdenConDetalle('10.1', 10);

        $componente = Livewire::test(Captura::class)
            ->set('telarId', '101')
            ->call('seleccionar', $id);

        $detalles = $componente->get('detalles');
        $catalogo = DB::connection('sqlsrv')->table('TejCatMatrizDesarrolladores')
            ->where('Codigo', '10/1')->first();

        $this->assertSame((int) $catalogo->Id, (int) $detalles[0]['CalibreId']);
        $this->assertFalse($detalles[0]['noVigente']);
    }

    /** Un calibre que no esta en el catalogo vigente marca el renglon y bloquea el guardado. */
    public function test_un_calibre_no_vigente_bloquea_el_guardado(): void
    {
        $this->autenticar();
        // 8.1 existe en el catalogo pero con Vigente = 0.
        $id = $this->sembrarTelarConOrdenConDetalle('8.1', 8);

        $componente = Livewire::test(Captura::class)
            ->set('telarId', '101')
            ->call('seleccionar', $id);

        $this->assertTrue($componente->get('detalles')[0]['noVigente']);

        $this->assertStringContainsString('ya no esta vigente', implode(' ', $componente->instance()->problemas()));

        $componente->call('guardar')->assertDispatched('aviso', tipo: 'error');
    }

    /**
     * La garantia de fondo: un solo select escribe las DOS columnas. Capturadas por
     * separado se desparejaban y la formula de L.Mat calculaba con el divisor de otro hilo.
     */
    public function test_elegir_un_hilo_llena_calibre_e_hilo_a_la_vez(): void
    {
        $this->autenticar();
        $id = $this->sembrarTelarConOrdenConDetalle('10.1', 10);

        $seiscientos = DB::connection('sqlsrv')->table('TejCatMatrizDesarrolladores')
            ->where('Codigo', '600/1')->value('Id');

        $detalles = Livewire::test(Captura::class)
            ->set('telarId', '101')
            ->call('seleccionar', $id)
            ->call('elegirCalibre', 0, $seiscientos)
            ->get('detalles');

        $this->assertSame('600.1', $detalles[0]['Calibre'], 'Calibre recibe el codigo interno.');
        $this->assertSame('8.86', $detalles[0]['Hilo'], 'Hilo recibe el divisor del MISMO renglon.');
        $this->assertFalse($detalles[0]['noVigente']);
    }

    /**
     * Lo que sale hacia CatCodificados sigue siendo NUMERICO. Si el select mandara el
     * codigo de AX ("600/1T"), applyPayload lo descartaria sin avisar y la formula de
     * L.Mat calcularia la trama en cero. Este test es el que protege a L.Mat.
     */
    public function test_lo_que_se_envia_sigue_siendo_numerico(): void
    {
        $this->autenticar();
        $id = $this->sembrarTelarConOrdenConDetalle('10.1', 10);

        $detalles = Livewire::test(Captura::class)
            ->set('telarId', '101')
            ->call('seleccionar', $id)
            ->get('detalles');

        $this->assertTrue(is_numeric($detalles[0]['Calibre']), 'Calibre debe ser numerico, no "10/1".');
        $this->assertTrue(is_numeric($detalles[0]['Hilo']), 'Hilo debe ser numerico.');
    }

    private function sembrarTelarConOrdenConDetalle(string $calibre, float $hilo): int
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
            'CalibreComb1' => $calibre,
            'CalibreComb12' => $hilo,
            'FibraComb1' => 'ALGODON',
            'PasadasComb1' => 12,
        ]);
    }

    /** Una captura sin nada pendiente: es lo unico que deja llegar a guardar(). */
    private function capturaCompleta(int $id): Testable
    {
        return Livewire::test(Captura::class)
            ->set('telarId', '101')
            ->call('seleccionar', $id)
            ->set('form.NumeroJulioRizo', 'J-1')
            ->set('form.EficienciaInicio', 80)
            ->set('form.EficienciaFinal', 85)
            ->set('form.AlturaRizo', '5')
            ->set('codificacion', str_split('ABCDEFGHIJKL'))
            ->set('detalles.0.Pasadas', 12);
    }

    // ── Boton de guardar ──────────────────────────────────────────────────

    /** Con la captura completa no queda nada pendiente y el boton se habilita. */
    public function test_una_captura_completa_no_tiene_pendientes(): void
    {
        $this->autenticar();
        $id = $this->sembrarTelarConOrdenConDetalle('10.1', 10);

        $this->assertSame([], $this->capturaCompleta($id)->instance()->problemas());
    }

    /**
     * Un renglon sin pasadas no lo rechaza el servidor: descarta el detalle ENTERO en
     * silencio, porque detalle_* y pasadas[] dejan de emparejarse. Tiene que frenarse aqui.
     */
    public function test_un_renglon_sin_pasadas_bloquea_el_guardado(): void
    {
        $this->autenticar();
        $id = $this->sembrarTelarConOrdenConDetalle('10.1', 10);

        $problemas = $this->capturaCompleta($id)->set('detalles.0.Pasadas', '')->instance()->problemas();

        $this->assertStringContainsString('falta el numero de pasadas', implode(' ', $problemas));
    }

    // ── Slots del detalle ─────────────────────────────────────────────────

    /** Al quitar C2, C3 pasa a ser C2: los combos nunca dejan un hueco en la orden. */
    public function test_al_eliminar_una_combinacion_las_siguientes_se_recorren(): void
    {
        $this->autenticar();
        $id = $this->sembrarTelarConOrdenConDetalle('10.1', 10);

        $componente = Livewire::test(Captura::class)
            ->set('telarId', '101')
            ->call('seleccionar', $id)
            ->call('agregarFila')
            ->call('agregarFila');

        $this->assertSame(
            ['PasadasComb1', 'PasadasComb2', 'PasadasComb3'],
            array_column($componente->get('detalles'), 'slot')
        );

        $componente->call('eliminarFila', 1);

        $this->assertSame(
            ['PasadasComb1', 'PasadasComb2'],
            array_column($componente->get('detalles'), 'slot')
        );
    }

    /** La trama no es una combinacion: no se elimina y no ocupa uno de los cinco slots. */
    public function test_la_trama_no_se_elimina_y_no_gasta_slot(): void
    {
        $this->autenticar();
        $id = $this->sembrarTelarConOrdenConTrama();

        $componente = Livewire::test(Captura::class)
            ->set('telarId', '101')
            ->call('seleccionar', $id)
            ->call('eliminarFila', 0);

        $this->assertSame('PasadasTrama', $componente->get('detalles')[0]['slot']);

        for ($i = 0; $i < 6; $i++) {
            $componente->call('agregarFila');
        }

        // Trama + las cinco combinaciones, ni una mas.
        $this->assertSame(
            ['PasadasTrama', 'PasadasComb1', 'PasadasComb2', 'PasadasComb3', 'PasadasComb4', 'PasadasComb5'],
            array_column($componente->get('detalles'), 'slot')
        );
    }

    private function sembrarTelarConOrdenConTrama(): int
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
            'CalibreTrama' => '10.1',
            'CalibreTrama2' => 10,
            'PasadasTrama' => 20,
        ]);
    }

    // ── Fibra y color desde AX ────────────────────────────────────────────

    /** Los tres campos salen del articulo del hilo en AX; el nombre viene con el codigo. */
    public function test_fibra_y_color_salen_del_catalogo_de_ax(): void
    {
        $this->autenticar();
        $id = $this->sembrarTelarConOrdenConDetalle('10.1', 10);
        $this->fingirCatalogoAx();

        $componente = Livewire::test(Captura::class)
            ->set('telarId', '101')
            ->call('seleccionar', $id);

        $opciones = $componente->instance()->opcionesFila($componente->get('detalles')[0]);

        // La fibra que traia la orden ('ALGODON') no esta en AX, pero no se pierde.
        $this->assertSame(['ALGODON', 'Alg-Open', 'POL-ALG'], $opciones['fibras']);
        $this->assertSame(['1000', '91140'], array_column($opciones['colores'], 'InventColorId'));

        $detalles = $componente->call('elegirColor', 0, '91140')->get('detalles');

        $this->assertSame('91140', $detalles[0]['CodColor']);
        $this->assertSame('VERDE BOTELLA', $detalles[0]['NombreColor']);
    }

    /** Al cambiar de hilo, la fibra y el color del anterior dejan de aplicar. */
    public function test_cambiar_de_hilo_limpia_fibra_y_color(): void
    {
        $this->autenticar();
        $id = $this->sembrarTelarConOrdenConDetalle('10.1', 10);
        $this->fingirCatalogoAx();

        $seiscientos = DB::connection('sqlsrv')->table('TejCatMatrizDesarrolladores')
            ->where('Codigo', '600/1')->value('Id');

        $detalles = Livewire::test(Captura::class)
            ->set('telarId', '101')
            ->call('seleccionar', $id)
            ->call('elegirColor', 0, '91140')
            ->call('elegirCalibre', 0, $seiscientos)
            ->get('detalles');

        $this->assertSame('', $detalles[0]['Fibra']);
        $this->assertSame('', $detalles[0]['CodColor']);
        $this->assertSame('', $detalles[0]['NombreColor']);
    }

    /**
     * AX no existe en pruebas y el servicio es final: se siembra su respuesta en la
     * misma llave de cache que usa el componente, que ademas es el camino real.
     */
    private function fingirCatalogoAx(): void
    {
        Cache::put('desarrolladores.ax.'.md5('10/1'), ['10/1' => [
            'configs' => ['Alg-Open', 'POL-ALG'],
            'tamanos' => [],
            'colores' => [
                ['InventColorId' => '1000', 'Name' => 'HILO 10/1'],
                ['InventColorId' => '91140', 'Name' => 'VERDE BOTELLA'],
            ],
        ]], now()->addMinutes(30));
    }

    /** Altura de rizo es obligatoria: sin ella el servidor rechaza el guardado entero. */
    public function test_sin_altura_de_rizo_no_se_puede_guardar(): void
    {
        $this->autenticar();
        $id = $this->sembrarTelarConOrdenConDetalle('10.1', 10);

        $problemas = $this->capturaCompleta($id)->set('form.AlturaRizo', '')->instance()->problemas();

        $this->assertContains('Falta la altura de rizo.', $problemas);
    }

    // ── Hilo cuando el calibre tiene mas de uno ───────────────────────────

    /** Un calibre con un solo divisor no ofrece nada que elegir: Hilo sigue derivado. */
    public function test_un_calibre_con_un_solo_hilo_no_ofrece_seleccion(): void
    {
        $this->autenticar();
        $id = $this->sembrarTelarConOrdenConDetalle('10.1', 10);

        $componente = Livewire::test(Captura::class)
            ->set('telarId', '101')
            ->call('seleccionar', $id);

        // 10/1 y 10/1T comparten calibre Y divisor: una sola opcion, no un select.
        $this->assertCount(1, $componente->instance()->hilosDelCalibre('10.1'));
    }

    /** Con dos divisores para el mismo calibre, el operador elige y las dos columnas se mueven juntas. */
    public function test_un_calibre_con_dos_hilos_se_puede_elegir(): void
    {
        $this->autenticar();
        $id = $this->sembrarTelarConOrdenConDetalle('12.1', 12);

        $componente = Livewire::test(Captura::class)
            ->set('telarId', '101')
            ->call('seleccionar', $id);

        $opciones = $componente->instance()->hilosDelCalibre('12.1');

        $this->assertSame(['6', '12'], array_column($opciones, 'Divisor'));
        $this->assertSame('12', $componente->get('detalles')[0]['Hilo']);

        $detalles = $componente->call('elegirHilo', 0, '6')->get('detalles');

        // Cambia el divisor y con el el renglon del catalogo, no solo la columna Hilo.
        $this->assertSame('6', $detalles[0]['Hilo']);
        $this->assertSame('12.1', $detalles[0]['Calibre']);
        $this->assertSame(
            (int) DB::connection('sqlsrv')->table('TejCatMatrizDesarrolladores')->where('Codigo', '12/1X')->value('Id'),
            (int) $detalles[0]['CalibreId']
        );
    }
}
