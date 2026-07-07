<?php

namespace Tests\Unit;

use App\Http\Controllers\Tejedores\Desarrolladores\Funciones\CatCodificadosDesarrolladorService;
use App\Http\Controllers\Tejedores\Desarrolladores\Funciones\MovimientoDesarrolladorService;
use App\Http\Controllers\Tejedores\Desarrolladores\Funciones\NotificacionTelegramDesarrolladorService;
use App\Http\Controllers\Tejedores\Desarrolladores\Funciones\ProcesarDesarrolladorService;
use App\Models\Planeacion\ReqModelosCodificados;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Tests\TestCase;

class ProcesarDesarrolladorDetalleTest extends TestCase
{
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

        DB::purge('sqlsrv');
        DB::connection('sqlsrv')->getPdo();

        Schema::connection('sqlsrv')->create('ReqModelosCodificados', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('TamanoClave')->nullable();
            $table->string('SalonTejidoId')->nullable();
            $table->string('NoTelarId')->nullable();
            $table->string('OrdenTejido')->nullable();
            $table->string('CodigoDibujo')->nullable();
            $table->float('AnchoPeineTrama')->nullable();
            $table->integer('LogLuchaTotal')->nullable();
            $table->integer('Total')->nullable();
            $table->dateTime('FechaCumplimiento')->nullable();
        });
    }

    private function service(): ProcesarDesarrolladorService
    {
        $catCodificadosService = new CatCodificadosDesarrolladorService();
        $movimientoService = new MovimientoDesarrolladorService($catCodificadosService);
        $telegramService = $this->createMock(NotificacionTelegramDesarrolladorService::class);

        return new ProcesarDesarrolladorService($movimientoService, $telegramService, $catCodificadosService);
    }

    private function invokeAplicarDetalle(array $detallePayload, array $validated): array
    {
        $reflection = new ReflectionClass($this->service());
        $method = $reflection->getMethod('aplicarDetalleDesdeRequest');
        $method->setAccessible(true);

        return $method->invoke($this->service(), $detallePayload, $validated);
    }

    public function test_sin_detalle_en_request_conserva_los_valores_de_la_orden(): void
    {
        $detalleDeLaOrden = ['Tra' => 12, 'FibraId' => 'ORIGINAL', 'CodColorTrama' => '01', 'ColorTrama' => 'BLANCO'];

        $resultado = $this->invokeAplicarDetalle($detalleDeLaOrden, ['pasadas' => ['PasadasTramaFondoC1' => 120]]);

        $this->assertSame($detalleDeLaOrden, $resultado);
    }

    public function test_edicion_de_trama_sobrescribe_calibre_hilo_fibra_codcolor_nombrecolor(): void
    {
        $detalleDeLaOrden = ['Tra' => 12, 'FibraId' => 'VIEJO', 'CodColorTrama' => '01', 'ColorTrama' => 'BLANCO', 'HiloAX' => null];

        $validated = [
            'pasadas' => ['PasadasTramaFondoC1' => 120],
            'detalle_calibre' => ['20'],
            'detalle_hilo' => ['20.5'],
            'detalle_fibra' => ['ANILLO'],
            'detalle_codcolor' => ['05'],
            'detalle_nombrecolor' => ['AZUL'],
        ];

        $resultado = $this->invokeAplicarDetalle($detalleDeLaOrden, $validated);

        $this->assertSame('20', $resultado['Tra']);
        $this->assertSame('20', $resultado['CalTramaFondoC1']);
        $this->assertSame('20.5', $resultado['HiloAX']);
        $this->assertSame('ANILLO', $resultado['FibraId']);
        $this->assertSame('05', $resultado['CodColorTrama']);
        $this->assertSame('AZUL', $resultado['ColorTrama']);
    }

    public function test_edicion_de_combinacion_2_sobrescribe_solo_esa_combinacion(): void
    {
        $detalleDeLaOrden = ['CalibreComb2' => 10, 'FibraComb2' => 'VIEJO', 'CodColorC2' => '02', 'NomColorC2' => 'ROJO'];

        $validated = [
            'pasadas' => ['PasadasComb2' => 80],
            'detalle_calibre' => ['30'],
            'detalle_hilo' => [null],
            'detalle_fibra' => ['POLIESTER'],
            'detalle_codcolor' => ['09'],
            'detalle_nombrecolor' => ['VERDE'],
        ];

        $resultado = $this->invokeAplicarDetalle($detalleDeLaOrden, $validated);

        $this->assertSame('30', $resultado['CalibreComb2']);
        $this->assertSame('POLIESTER', $resultado['FibraComb2']);
        $this->assertSame('09', $resultado['CodColorC2']);
        $this->assertSame('VERDE', $resultado['NomColorC2']);
    }

    public function test_si_no_se_puede_emparejar_fila_con_pasadas_no_sobrescribe_nada(): void
    {
        $detalleDeLaOrden = ['Tra' => 12, 'FibraId' => 'ORIGINAL'];

        // 2 filas de detalle pero solo 1 llave de pasadas: no hay forma segura de emparejar.
        $validated = [
            'pasadas' => ['PasadasTramaFondoC1' => 120],
            'detalle_calibre' => ['20', '30'],
            'detalle_hilo' => ['20.5', '10'],
            'detalle_fibra' => ['ANILLO', 'POLIESTER'],
            'detalle_codcolor' => ['05', '09'],
            'detalle_nombrecolor' => ['AZUL', 'VERDE'],
        ];

        $resultado = $this->invokeAplicarDetalle($detalleDeLaOrden, $validated);

        $this->assertSame($detalleDeLaOrden, $resultado);
    }

    public function test_codigo_dibujo_ya_no_bloquea_actualizacion_del_modelo(): void
    {
        ReqModelosCodificados::query()->create([
            'TamanoClave' => 'CLAVE-TEST-1',
            'SalonTejidoId' => 'JAC',
            'CodigoDibujo' => 'CODIGO-VIEJO.JC5',
        ]);

        $service = $this->service();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('actualizarModeloDestinoSiCorresponde');
        $method->setAccessible(true);

        $method->invoke(
            $service,
            'CLAVE-TEST-1',
            'JAC',
            '150',
            ['NoProduccion' => 'ORD-1', 'TramaAnchoPeine' => null, 'TotalPasadasDibujo' => 100],
            [],
            [],
            'CODIGO-NUEVO.JC5',
            180
        );

        $actualizado = ReqModelosCodificados::query()->where('TamanoClave', 'CLAVE-TEST-1')->first();
        $this->assertSame('CODIGO-NUEVO.JC5', $actualizado->CodigoDibujo);
    }
}
