<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Planeacion\MatrizCalibresService;
use App\ValueObjects\Planeacion\MatrizCalibreClave;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class MatrizCalibresServiceTest extends TestCase
{
    private MatrizCalibresService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.sqlsrv', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        config()->set('database.default', 'sqlsrv');

        DB::purge('sqlsrv');
        DB::connection('sqlsrv')->getPdo();

        Schema::connection('sqlsrv')->create('CatMatrizCalibres', function (Blueprint $table): void {
            $table->increments('Id');
            $table->string('Tipo', 60);
            $table->float('Calibre')->nullable();
            $table->string('FibraId', 60)->nullable();
            $table->string('Cuenta', 60)->nullable();
            $table->string('ItemId', 60);
            $table->string('ConfigId', 60);
            $table->string('InventSizeId', 60);
            $table->string('InventColorId', 60);
            $table->unique(['Tipo', 'Calibre', 'FibraId', 'Cuenta']);
        });

        Schema::connection('sqlsrv')->create('CatCodificados', function (Blueprint $table): void {
            $table->increments('Id');
            $table->string('OrdenTejido', 60);
            $table->string('TelarId', 60)->nullable();
            $table->string('BomId', 20)->nullable();
            $table->string('BomName', 60)->nullable();
        });

        Schema::connection('sqlsrv')->create('CatLMat', function (Blueprint $table): void {
            $table->increments('Id');
            $table->string('Orden', 60);
            $table->string('Salon', 60)->nullable();
            $table->string('Nombre', 60)->nullable();
            $table->string('Descrip', 255)->nullable();
            $table->string('PesoCrudo', 60)->nullable();
            $table->string('ItemId', 60);
            $table->string('ConfigId', 60)->nullable();
            $table->string('InventSizeId', 60)->nullable();
            $table->string('InventColorId', 60)->nullable();
            $table->string('InventLocationId', 60)->nullable();
            $table->float('Qty');
            $table->float('Porcentaje')->nullable();
            $table->string('ItemIdCrudo', 60)->nullable();
            $table->string('InventSizeCrudo', 60)->nullable();
            $table->date('FechaRegistro')->nullable();
            $table->string('HoraRegistro', 20)->nullable();
            $table->string('UsuarioRegistro', 60)->nullable();
        });

        $this->service = new MatrizCalibresService;
    }

    public function test_normaliza_calibre_textos_y_cuenta_para_rizo(): void
    {
        $clave = MatrizCalibreClave::fromArray([
            'tipo' => ' rizo ',
            'calibre' => '10.14',
            'fibraId' => ' algodón ',
            'cuenta' => ' 30/1 ',
        ]);

        $this->assertSame('RIZO', $clave->tipo);
        $this->assertSame(10.1, $clave->calibre);
        $this->assertSame('ALGODÓN', $clave->fibraId);
        $this->assertSame('30/1', $clave->cuenta);
    }

    public function test_trama_ignora_cuenta_y_rizo_la_exige(): void
    {
        $trama = MatrizCalibreClave::fromArray([
            'Tipo' => 'TRAMA',
            'Calibre' => 16.08,
            'FibraId' => 'PES',
            'Cuenta' => 'NO DEBE PARTICIPAR',
        ]);

        $this->assertSame(16.1, $trama->calibre);
        $this->assertNull($trama->cuenta);
        $this->assertNull(MatrizCalibreClave::tryFromArray([
            'Tipo' => 'PIE',
            'Calibre' => 16.1,
            'FibraId' => 'PES',
        ]));
    }

    public function test_pie_acepta_fibra_o_calibre_y_exige_al_menos_uno(): void
    {
        $soloFibra = MatrizCalibreClave::fromArray([
            'Tipo' => 'PIE',
            'FibraId' => ' A12 ',
            'Cuenta' => ' 3156 ',
        ]);
        $soloCalibre = MatrizCalibreClave::fromArray([
            'Tipo' => 'PIE',
            'Calibre' => 12.1,
            'Cuenta' => '3156',
        ]);

        $this->assertNull($soloFibra->calibre);
        $this->assertSame('A12', $soloFibra->fibraId);
        $this->assertSame(12.1, $soloCalibre->calibre);
        $this->assertNull($soloCalibre->fibraId);
        $this->assertNull(MatrizCalibreClave::tryFromArray([
            'Tipo' => 'PIE',
            'Cuenta' => '3156',
        ]));
    }

    public function test_rizo_y_trama_siguen_requiriendo_calibre(): void
    {
        $this->assertNull(MatrizCalibreClave::tryFromArray([
            'Tipo' => 'RIZO',
            'FibraId' => 'A12',
            'Cuenta' => '3156',
        ]));
        $this->assertNull(MatrizCalibreClave::tryFromArray([
            'Tipo' => 'TRAMA',
            'FibraId' => 'OPEN',
        ]));
    }

    public function test_reutiliza_y_actualiza_una_sola_equivalencia_global(): void
    {
        $primeraClave = MatrizCalibreClave::fromArray([
            'Tipo' => 'trama',
            'Calibre' => 10.14,
            'FibraId' => ' pes ',
        ]);
        $mismaClaveOtraOrden = MatrizCalibreClave::fromArray([
            'Tipo' => 'TRAMA',
            'Calibre' => 10.11,
            'FibraId' => 'PES',
        ]);

        $this->service->aprender($primeraClave, $this->salida('ITEM-A'));
        $actualizada = $this->service->aprender($mismaClaveOtraOrden, $this->salida('ITEM-B'));

        $this->assertNotNull($actualizada);
        $this->assertSame(1, DB::connection('sqlsrv')->table('CatMatrizCalibres')->count());
        $this->assertSame('ITEM-B', $this->service->buscar($primeraClave)?->ItemId);
    }

    public function test_cuentas_distintas_crean_equivalencias_distintas_de_rizo(): void
    {
        foreach (['30/1', '40/1'] as $cuenta) {
            $clave = MatrizCalibreClave::fromArray([
                'Tipo' => 'RIZO',
                'Calibre' => 12.1,
                'FibraId' => 'ALG',
                'Cuenta' => $cuenta,
            ]);
            $this->service->aprender($clave, $this->salida('ITEM-'.$cuenta));
        }

        $this->assertSame(2, DB::connection('sqlsrv')->table('CatMatrizCalibres')->count());
    }

    public function test_crud_fusiona_el_registro_si_la_nueva_clave_ya_existe(): void
    {
        $primero = $this->service->guardarRegistroCompleto([
            'Tipo' => 'TRAMA',
            'Calibre' => 10.1,
            'FibraId' => 'ALG',
            ...$this->salida('ITEM-A'),
        ]);
        $this->service->guardarRegistroCompleto([
            'Tipo' => 'TRAMA',
            'Calibre' => 20.1,
            'FibraId' => 'PES',
            ...$this->salida('ITEM-B'),
        ]);

        $resultado = $this->service->guardarRegistroCompleto([
            'Tipo' => 'TRAMA',
            'Calibre' => 20.1,
            'FibraId' => 'PES',
            ...$this->salida('ITEM-ACTUALIZADO'),
        ], $primero);

        $this->assertSame(1, DB::connection('sqlsrv')->table('CatMatrizCalibres')->count());
        $this->assertSame('ITEM-ACTUALIZADO', $resultado->ItemId);
        $this->assertSame('PES', $resultado->FibraId);
    }

    public function test_no_aprende_una_salida_incompleta(): void
    {
        $clave = MatrizCalibreClave::fromArray([
            'Tipo' => 'TRAMA',
            'Calibre' => 10.1,
            'FibraId' => 'PES',
        ]);

        $resultado = $this->service->aprender($clave, [
            'ItemId' => 'ITEM-A',
            'ConfigId' => '',
            'InventSizeId' => '20-10/1',
            'InventColorId' => '1000',
        ]);

        $this->assertNull($resultado);
        $this->assertSame(0, DB::connection('sqlsrv')->table('CatMatrizCalibres')->count());
    }

    public function test_get_individual_devuelve_la_equivalencia_reutilizable(): void
    {
        $clave = MatrizCalibreClave::fromArray([
            'Tipo' => 'RIZO',
            'Calibre' => 12.1,
            'FibraId' => 'ALG',
            'Cuenta' => '30/1',
        ]);
        $this->service->aprender($clave, $this->salida('ITEM-RIZO'));

        $this->withoutMiddleware()->getJson(route('planeacion.lmat.matriz-calibre', [
            'tipo' => 'rizo',
            'calibre' => '12.14',
            'fibraId' => 'alg',
            'cuenta' => '30/1',
        ]))
            ->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('data.ItemId', 'ITEM-RIZO');
    }

    public function test_get_de_rizo_exige_cuenta(): void
    {
        $this->withoutMiddleware()->getJson(route('planeacion.lmat.matriz-calibre', [
            'tipo' => 'RIZO',
            'calibre' => '12.1',
            'fibraId' => 'ALG',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cuenta');
    }

    public function test_get_de_pie_encuentra_equivalencia_sin_calibre(): void
    {
        $clave = MatrizCalibreClave::fromArray([
            'Tipo' => 'PIE',
            'FibraId' => 'A12',
            'Cuenta' => '3156',
        ]);
        $this->service->aprender($clave, $this->salida('JU-ENG-PI-C'));

        $this->withoutMiddleware()->getJson(route('planeacion.lmat.matriz-calibre', [
            'tipo' => 'PIE',
            'fibraId' => 'A12',
            'cuenta' => '3156',
        ]))
            ->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('data.Calibre', null)
            ->assertJsonPath('data.ItemId', 'JU-ENG-PI-C');
    }

    public function test_get_de_pie_encuentra_equivalencia_sin_fibra(): void
    {
        $clave = MatrizCalibreClave::fromArray([
            'Tipo' => 'PIE',
            'Calibre' => 10.1,
            'Cuenta' => '4112',
        ]);
        $this->service->aprender($clave, $this->salida('JU-ENG-PI-C'));

        $this->withoutMiddleware()->getJson(route('planeacion.lmat.matriz-calibre', [
            'tipo' => 'PIE',
            'calibre' => 10.1,
            'cuenta' => '4112',
        ]))
            ->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('data.FibraId', null)
            ->assertJsonPath('data.ItemId', 'JU-ENG-PI-C');
    }

    public function test_crud_permite_crear_pie_sin_calibre(): void
    {
        $this->withoutMiddleware()->postJson(
            route('planeacion.catalogos.matrizcalibres.store'),
            [
                'Tipo' => 'PIE',
                'FibraId' => 'A12',
                'Cuenta' => '3156',
                ...$this->salida('JU-ENG-PI-C'),
            ],
        )
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.Calibre', null);

        $this->assertDatabaseHas('CatMatrizCalibres', [
            'Tipo' => 'PIE',
            'Calibre' => null,
            'FibraId' => 'A12',
            'Cuenta' => '3156',
        ], 'sqlsrv');
    }

    public function test_guardar_lmat_aprende_pie_sin_calibre(): void
    {
        DB::connection('sqlsrv')->table('CatCodificados')->insert([
            'OrdenTejido' => 'ORD-100',
            'TelarId' => '305',
        ]);
        $payload = $this->payloadLMat('JU-ENG-PI-C');
        $payload['filas'][0]['matrizTipo'] = 'PIE';
        $payload['filas'][0]['matrizCalibre'] = null;
        $payload['filas'][0]['matrizFibraId'] = 'A12';
        $payload['filas'][0]['matrizCuenta'] = '3156';

        $this->withoutMiddleware()
            ->postJson(route('planeacion.lmat.guardar'), $payload)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('CatMatrizCalibres', [
            'Tipo' => 'PIE',
            'Calibre' => null,
            'FibraId' => 'A12',
            'Cuenta' => '3156',
            'ItemId' => 'JU-ENG-PI-C',
        ], 'sqlsrv');
    }

    public function test_guardar_lmat_aprende_pie_sin_fibra(): void
    {
        DB::connection('sqlsrv')->table('CatCodificados')->insert([
            'OrdenTejido' => 'ORD-100',
            'TelarId' => '305',
        ]);
        $payload = $this->payloadLMat('JU-ENG-PI-C');
        $payload['filas'][0]['matrizTipo'] = 'PIE';
        $payload['filas'][0]['matrizCalibre'] = 10.1;
        $payload['filas'][0]['matrizFibraId'] = null;
        $payload['filas'][0]['matrizCuenta'] = '4112';

        $this->withoutMiddleware()
            ->postJson(route('planeacion.lmat.guardar'), $payload)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('CatMatrizCalibres', [
            'Tipo' => 'PIE',
            'Calibre' => 10.1,
            'FibraId' => null,
            'Cuenta' => '4112',
            'ItemId' => 'JU-ENG-PI-C',
        ], 'sqlsrv');
    }

    public function test_guardar_y_actualizar_lmat_tambien_actualiza_la_equivalencia_global(): void
    {
        DB::connection('sqlsrv')->table('CatCodificados')->insert([
            'OrdenTejido' => 'ORD-100',
            'TelarId' => '305',
        ]);

        $payload = $this->payloadLMat('ITEM-A');
        $this->withoutMiddleware()
            ->postJson(route('planeacion.lmat.guardar'), $payload)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(1, DB::connection('sqlsrv')->table('CatLMat')->count());
        $this->assertSame(1, DB::connection('sqlsrv')->table('CatMatrizCalibres')->count());
        $this->assertSame('ITEM-A', DB::connection('sqlsrv')->table('CatMatrizCalibres')->value('ItemId'));

        $this->withoutMiddleware()
            ->postJson(route('planeacion.lmat.guardar'), $this->payloadLMat('ITEM-B'))
            ->assertOk();

        $this->assertSame(1, DB::connection('sqlsrv')->table('CatLMat')->count());
        $this->assertSame(1, DB::connection('sqlsrv')->table('CatMatrizCalibres')->count());
        $this->assertSame('ITEM-B', DB::connection('sqlsrv')->table('CatMatrizCalibres')->value('ItemId'));
        $this->assertSame('ITEM-B', DB::connection('sqlsrv')->table('CatLMat')->value('ItemId'));
    }

    /**
     * @return array{ItemId: string, ConfigId: string, InventSizeId: string, InventColorId: string}
     */
    private function salida(string $itemId): array
    {
        return [
            'ItemId' => $itemId,
            'ConfigId' => 'ENTERO',
            'InventSizeId' => '20-10/1',
            'InventColorId' => '1000',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadLMat(string $itemId): array
    {
        return [
            'orden' => 'ORD-100',
            'salon' => 'SMIT',
            'telarId' => '305',
            'nombre' => 'LMAT-100',
            'descrip' => 'L.Mat de prueba',
            'pesoCrudo' => '737',
            'itemIdCrudo' => 'CRUDO-1',
            'inventSizeCrudo' => '50X100',
            'filas' => [[
                'itemId' => $itemId,
                'configId' => 'ENTERO',
                'inventSizeId' => '20-10/1',
                'inventColorId' => '1000',
                'inventLocationId' => 'A-PTE-LISO',
                'qty' => 0.5,
                'porcentaje' => 50,
                'matrizTipo' => 'TRAMA',
                'matrizCalibre' => 10.1,
                'matrizFibraId' => 'PES',
                'matrizCuenta' => null,
            ]],
        ];
    }
}
