<?php

namespace Tests\Unit;

use App\Http\Controllers\Tejedores\Desarrolladores\Funciones\CatCodificadosDesarrolladorService;
use App\Http\Controllers\Tejedores\Desarrolladores\Funciones\NotificacionTelegramDesarrolladorService;
use App\Http\Controllers\Tejedores\Desarrolladores\Funciones\ProcesarMuestrasDesarrolladorService;
use App\Models\Planeacion\Muestras;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Caracterizacion del guardado de muestras, que hasta ahora no tenia ninguna
 * cobertura. Fija el comportamiento observable antes de unificar este servicio
 * con el de programa: si la fusion cambia algo de esto, estos tests lo dicen.
 */
class ProcesarMuestrasStoreTest extends TestCase
{
    protected ProcesarMuestrasDesarrolladorService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlsrv');
        Config::set('database.connections.sqlsrv', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        // Sin token no se intenta salir a la red desde el test.
        Config::set('services.telegram.bot_token', null);

        DB::purge('sqlsrv');
        DB::connection('sqlsrv')->getPdo();

        // Varias tablas del sistema llevan el prefijo de esquema dbo.; SQLite solo lo
        // entiende si existe una base adjunta con ese nombre.
        DB::connection('sqlsrv')->statement("ATTACH DATABASE ':memory:' AS dbo");

        Schema::connection('sqlsrv')->create('MuestrasPrograma', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('NoProduccion')->nullable();
            $table->string('NoTelarId')->nullable();
            $table->string('SalonTejidoId')->nullable();
            $table->string('TamanoClave')->nullable();
            $table->string('NombreProducto')->nullable();
            $table->boolean('EnProceso')->default(false);
            $table->integer('Posicion')->nullable();
            $table->dateTime('FechaInicio')->nullable();
            $table->dateTime('FechaFinal')->nullable();
            $table->integer('OrdCompartida')->nullable();
            $table->float('TotalPedido')->nullable();
            $table->float('Produccion')->nullable();
            $table->float('SaldoPedido')->nullable();
        });

        Schema::connection('sqlsrv')->create('CatCodificados', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('OrdenTejido')->nullable();
            $table->string('TelarId')->nullable();
            $table->string('Departamento')->nullable();
            $table->string('ClaveModelo')->nullable();
            $table->string('JulioRizo')->nullable();
            $table->string('JulioPie')->nullable();
            $table->integer('Total')->nullable();
            $table->integer('EfiInicial')->nullable();
            $table->integer('EfiFinal')->nullable();
            $table->string('HrInicio')->nullable();
            $table->string('HrTermino')->nullable();
            $table->string('RespInicio')->nullable();
            $table->string('CodigoDibujo')->nullable();
            $table->integer('PasadasTramaFondoC1')->nullable();
            $table->integer('PasadasComb1')->nullable();
            $table->integer('PasadasComb2')->nullable();
            $table->integer('Pedido')->nullable();
            $table->integer('MinutosCambio')->nullable();
            $table->integer('LongitudLuchaTot')->nullable();
            $table->float('DesperdicioTrama')->nullable();
            // Columnas del detalle: sin ellas applyPayload las descarta y el test no
            // podria distinguir "se escribio la orden" de "se escribio lo capturado".
            $table->string('CalibreComb1')->nullable();
            $table->string('FibraComb1')->nullable();
            $table->string('CodColorC1')->nullable();
            $table->string('NomColorC1')->nullable();
            $table->string('CalibreComb12')->nullable();
            $table->string('AlturaRizo')->nullable();
        });

        Schema::connection('sqlsrv')->create('ReqModelosCodificados', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('SalonTejidoId')->nullable();
            $table->string('TamanoClave')->nullable();
            $table->string('CodigoDibujo')->nullable();
            $table->string('OrdenTejido')->nullable();
            $table->string('AlturaRizo')->nullable();
        });

        Schema::connection('sqlsrv')->create('dbo.SYSMensajes', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Token')->nullable();
            $table->boolean('Activo')->default(false);
            $table->boolean('DesarrolladoresPrue')->default(false);
            $table->boolean('Desarrolladores')->default(false);
        });

        Schema::connection('sqlsrv')->create('TelTelaresOperador', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('SalonTejidoId')->nullable();
            $table->string('NoTelarId')->nullable();
        });

        $this->service = new ProcesarMuestrasDesarrolladorService(
            new NotificacionTelegramDesarrolladorService(modulo: 'DesarrolladoresPrue'),
            new CatCodificadosDesarrolladorService
        );
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function crearMuestra(array $extra = []): Muestras
    {
        return Muestras::query()->create(array_merge([
            'NoProduccion' => '55001',
            'NoTelarId' => '101',
            'SalonTejidoId' => 'S1',
            'TamanoClave' => 'TAM1',
            'NombreProducto' => 'TOALLA',
            'EnProceso' => false,
            'FechaInicio' => '2026-03-10 06:00:00',
        ], $extra));
    }

    /**
     * A diferencia de la captura de programa, muestras NO crea CatCodificados:
     * resolveCanonical() busca el renglon de la orden y si no existe no escribe nada.
     * Por eso hay que sembrarlo.
     */
    private function sembrarCatCodificados(string $orden = '55001'): int
    {
        return DB::connection('sqlsrv')->table('CatCodificados')->insertGetId([
            'OrdenTejido' => $orden,
        ]);
    }

    private function guardar(array $extra = [])
    {
        $datos = array_merge([
            'NoTelarId' => '101',
            'NoProduccion' => '55001',
            'NumeroJulioRizo' => 'JR-1',
            'TotalPasadasDibujo' => 120,
            'CodificacionModelo' => 'AB12CDEF34',
            'Desarrollador' => 'JUAN',
            'EficienciaInicio' => 70,
            'EficienciaFinal' => 85,
            'AlturaRizo' => '5',
        ], $extra);

        return $this->service->store(Request::create('/desarrolladores-muestras', 'POST', $datos));
    }

    // ── El bug que motivo todo esto ───────────────────────────────────────

    public function test_borra_la_muestra_procesada_y_no_otra_del_mismo_telar(): void
    {
        $enProceso = $this->crearMuestra([
            'NoProduccion' => '55000',
            'EnProceso' => true,
            'FechaInicio' => '2026-03-01 06:00:00',
        ]);
        $aProcesar = $this->crearMuestra(['NoProduccion' => '55001']);

        $this->guardar(['NoProduccion' => '55001']);

        $this->assertNull(
            Muestras::query()->find($aProcesar->Id),
            'La muestra procesada debe consumirse.'
        );
        $this->assertNotNull(
            Muestras::query()->find($enProceso->Id),
            'La otra muestra del telar no debe tocarse: ese era el bug.'
        );
    }

    // ── Lo que el guardado deja escrito ───────────────────────────────────

    public function test_guarda_la_captura_en_cat_codificados(): void
    {
        $this->crearMuestra();
        $this->sembrarCatCodificados();

        $this->guardar([
            'NumeroJulioRizo' => 'JR-9',
            'NumeroJulioPie' => 'JP-4',
            'TotalPasadasDibujo' => 250,
            'EficienciaInicio' => 80,
        ]);

        $registro = DB::connection('sqlsrv')->table('CatCodificados')
            ->where('OrdenTejido', '55001')->first();

        $this->assertNotNull($registro, 'El guardado debe dejar un registro en CatCodificados.');
        $this->assertSame('JR-9', $registro->JulioRizo);
        $this->assertSame('JP-4', $registro->JulioPie);
        $this->assertSame(250, (int) $registro->Total);
        $this->assertSame('JUAN', $registro->RespInicio, 'El desarrollador se guarda en RespInicio.');
    }

    public function test_el_codigo_de_dibujo_recibe_el_sufijo_del_telar(): void
    {
        $this->crearMuestra();
        $this->sembrarCatCodificados();

        $this->guardar(['CodificacionModelo' => 'ab12cdef34']);

        $codigo = DB::connection('sqlsrv')->table('CatCodificados')
            ->where('OrdenTejido', '55001')->value('CodigoDibujo');

        $this->assertSame('AB12CDEF34.JC5', $codigo, 'Telar 101 va por debajo de 300: lleva sufijo.');
    }

    public function test_las_pasadas_enviadas_se_guardan_en_sus_columnas(): void
    {
        $this->crearMuestra();
        $this->sembrarCatCodificados();

        $this->guardar(['pasadas' => ['PasadasComb1' => 10, 'PasadasComb2' => 20]]);

        $registro = DB::connection('sqlsrv')->table('CatCodificados')
            ->where('OrdenTejido', '55001')->first();

        $this->assertSame(10, (int) $registro->PasadasComb1);
        $this->assertSame(20, (int) $registro->PasadasComb2);
    }

    public function test_una_clave_de_pasadas_ajena_no_escribe_ninguna_columna(): void
    {
        $this->crearMuestra();
        $this->sembrarCatCodificados();

        $this->guardar(['pasadas' => ['PasadasComb1' => 10, 'Pedido' => 999]]);

        $registro = DB::connection('sqlsrv')->table('CatCodificados')
            ->where('OrdenTejido', '55001')->first();

        $this->assertSame(10, (int) $registro->PasadasComb1);
        $this->assertNotSame(999, (int) $registro->Pedido, 'pasadas[] no debe poder escribir Pedido.');
    }

    public function test_sin_renglon_previo_en_cat_codificados_no_crea_ninguno(): void
    {
        $this->crearMuestra();

        $this->guardar();

        $this->assertSame(
            0,
            DB::connection('sqlsrv')->table('CatCodificados')->count(),
            'Muestras solo actualiza el renglon existente; nunca lo da de alta.'
        );
    }

    // ── Validacion ────────────────────────────────────────────────────────

    public function test_una_orden_que_no_existe_en_el_telar_se_rechaza(): void
    {
        $this->crearMuestra(['NoTelarId' => '999']);

        $respuesta = $this->guardar(['NoProduccion' => '55001', 'NoTelarId' => '101']);

        $this->assertNotNull($respuesta);
        $this->assertSame(1, Muestras::query()->count(), 'No debe borrar nada si la orden no corresponde.');
    }

    public function test_falta_el_julio_rizo_y_no_guarda(): void
    {
        $this->crearMuestra();

        try {
            $this->service->store(Request::create('/desarrolladores-muestras', 'POST', [
                'NoTelarId' => '101',
                'NoProduccion' => '55001',
                'TotalPasadasDibujo' => 120,
                'CodificacionModelo' => 'AB12CDEF34',
            ]));
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('NumeroJulioRizo', $e->errors());
        }

        $this->assertSame(0, DB::connection('sqlsrv')->table('CatCodificados')->count());
        $this->assertSame(1, Muestras::query()->count());
    }

    /**
     * El defecto principal que arreglo este cambio: muestras no validaba ni leia los
     * detalle_*, asi que el operador editaba calibre/hilo/fibra/color, veia "Datos
     * guardados correctamente" y se escribia el detalle original de la orden.
     */
    public function test_muestras_guarda_el_detalle_que_edito_el_operador(): void
    {
        $this->crearMuestra();
        $this->sembrarCatCodificados();

        $this->guardar([
            'pasadas' => ['PasadasComb1' => 7],
            'detalle_calibre' => ['NUEVO'],
            'detalle_hilo' => [12],
            'detalle_fibra' => ['ALGODON'],
            'detalle_codcolor' => ['CC9'],
            'detalle_nombrecolor' => ['AZUL'],
        ]);

        $registro = DB::connection('sqlsrv')->table('CatCodificados')
            ->where('OrdenTejido', '55001')->first();

        $this->assertSame('NUEVO', $registro->CalibreComb1, 'Se descartaba lo capturado y se escribia lo de la orden.');
        $this->assertSame('ALGODON', $registro->FibraComb1);
        $this->assertSame('CC9', $registro->CodColorC1);
        $this->assertSame('AZUL', $registro->NomColorC1);
    }

    /** La regla de longitud del codigo la tenia solo la captura de programa. */
    public function test_muestras_rechaza_un_codigo_de_dibujo_demasiado_corto(): void
    {
        $this->crearMuestra();
        $this->sembrarCatCodificados();

        $respuesta = $this->service->store(Request::create('/desarrolladores-muestras', 'POST', [
            'NoTelarId' => '101',
            'NoProduccion' => '55001',
            'NumeroJulioRizo' => 'JR-1',
            'TotalPasadasDibujo' => 120,
            'CodificacionModelo' => 'AB12',
        ], [], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']));

        $this->assertSame(422, $respuesta->getStatusCode());
        $this->assertArrayHasKey('CodificacionModelo', $respuesta->getData(true)['errors']);
        $this->assertSame(1, Muestras::query()->count(), 'Un codigo invalido no debe consumir la muestra.');
    }

    /** Las dos eficiencias son obligatorias: la pantalla ya las marcaba con asterisco. */
    public function test_sin_eficiencias_no_guarda(): void
    {
        $this->crearMuestra();
        $this->sembrarCatCodificados();

        $respuesta = $this->service->store(Request::create('/desarrolladores-muestras', 'POST', [
            'NoTelarId' => '101',
            'NoProduccion' => '55001',
            'NumeroJulioRizo' => 'JR-1',
            'TotalPasadasDibujo' => 120,
            'CodificacionModelo' => 'AB12CDEF34',
        ], [], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']));

        $errores = $respuesta->getData(true)['errors'];

        $this->assertSame(422, $respuesta->getStatusCode());
        $this->assertArrayHasKey('EficienciaInicio', $errores);
        $this->assertArrayHasKey('EficienciaFinal', $errores);
        $this->assertSame(1, Muestras::query()->count(), 'Sin eficiencias no debe consumirse la muestra.');
    }

    /**
     * AlturaRizo se escribe en las DOS tablas: Saldos lee rmc.AlturaRizo directo y
     * Alineacion usa CatCodificados con respaldo en rmc, asi que escribir solo una las
     * dejaria en desacuerdo.
     */
    public function test_la_altura_de_rizo_se_guarda_en_cat_codificados_y_en_modelos(): void
    {
        $this->crearMuestra();
        // ClaveModelo es la condicion: actualizarModeloDestinoSiCorresponde la toma de
        // CatCodificados y, si viene vacia, ni siquiera busca el renglon de modelos.
        DB::connection('sqlsrv')->table('CatCodificados')->insert([
            'OrdenTejido' => '55001', 'ClaveModelo' => 'TAM1',
        ]);
        // CodigoDibujo vacio a proposito: en muestras el renglon de modelos solo se
        // toca si aun no tiene codigo. Con codigo, el metodo sale antes de escribir.
        DB::connection('sqlsrv')->table('ReqModelosCodificados')->insert([
            'SalonTejidoId' => 'S1', 'TamanoClave' => 'TAM1', 'CodigoDibujo' => null,
        ]);

        $this->guardar(['AlturaRizo' => '8.6']);

        $this->assertSame('8.6', DB::connection('sqlsrv')->table('CatCodificados')
            ->where('OrdenTejido', '55001')->value('AlturaRizo'));
        $this->assertSame('8.6', DB::connection('sqlsrv')->table('ReqModelosCodificados')
            ->where('TamanoClave', 'TAM1')->value('AlturaRizo'));
    }

    /**
     * La guarda que muestras tiene y programa no: si el renglon de modelos ya trae
     * CodigoDibujo, no se toca --y la altura tampoco llega ahi.
     */
    public function test_en_muestras_un_modelo_con_codigo_no_recibe_la_altura(): void
    {
        $this->crearMuestra();
        DB::connection('sqlsrv')->table('CatCodificados')->insert([
            'OrdenTejido' => '55001', 'ClaveModelo' => 'TAM1',
        ]);
        DB::connection('sqlsrv')->table('ReqModelosCodificados')->insert([
            'SalonTejidoId' => 'S1', 'TamanoClave' => 'TAM1', 'CodigoDibujo' => 'YA-TIENE',
        ]);

        $this->guardar(['AlturaRizo' => '8.6']);

        $this->assertSame('8.6', DB::connection('sqlsrv')->table('CatCodificados')
            ->where('OrdenTejido', '55001')->value('AlturaRizo'));
        $this->assertNull(DB::connection('sqlsrv')->table('ReqModelosCodificados')
            ->where('TamanoClave', 'TAM1')->value('AlturaRizo'));
    }

    /** Dos decimales o mas de 10 no pasan: la columna es de texto y aceptaria cualquier cosa. */
    public function test_una_altura_de_rizo_invalida_no_guarda(): void
    {
        $this->crearMuestra();
        $this->sembrarCatCodificados();

        foreach (['8.66', '11'] as $valor) {
            $respuesta = $this->service->store(Request::create('/desarrolladores-muestras', 'POST', [
                'NoTelarId' => '101',
                'NoProduccion' => '55001',
                'NumeroJulioRizo' => 'JR-1',
                'TotalPasadasDibujo' => 120,
                'CodificacionModelo' => 'AB12CDEF34',
                'EficienciaInicio' => 70,
                'EficienciaFinal' => 85,
                'AlturaRizo' => $valor,
            ], [], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']));

            $this->assertSame(422, $respuesta->getStatusCode(), "Se esperaba rechazo para {$valor}.");
            $this->assertArrayHasKey('AlturaRizo', $respuesta->getData(true)['errors']);
        }
    }
}
