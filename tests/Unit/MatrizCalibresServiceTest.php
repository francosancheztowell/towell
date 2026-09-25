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
        config()->set('database.connections.sqlsrv_ti', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        config()->set('database.default', 'sqlsrv');

        DB::purge('sqlsrv');
        DB::connection('sqlsrv')->getPdo();
        DB::purge('sqlsrv_ti');
        DB::connection('sqlsrv_ti')->getPdo();

        Schema::connection('sqlsrv_ti')->create('InventTable', function (Blueprint $table): void {
            $table->string('ItemId', 60);
            $table->string('ItemGroupId', 60)->nullable();
            $table->string('DATAAREAID', 10);
            $table->integer('TwVigente')->default(1);
        });
        Schema::connection('sqlsrv_ti')->create('InventColor', function (Blueprint $table): void {
            $table->string('ItemId', 60);
            $table->string('InventColorId', 60);
            $table->string('Name', 120)->nullable();
            $table->string('DATAAREAID', 10);
            $table->integer('TwVigente')->default(1);
        });
        Schema::connection('sqlsrv_ti')->create('ConfigTable', function (Blueprint $table): void {
            $table->string('ItemId', 60);
            $table->string('ConfigId', 60);
            $table->string('DATAAREAID', 10);
            $table->integer('TwVigente')->default(1);
        });
        Schema::connection('sqlsrv_ti')->create('InventSize', function (Blueprint $table): void {
            $table->string('ItemId', 60);
            $table->string('InventSizeId', 60);
            $table->string('NAME', 120)->nullable();
            $table->string('DATAAREAID', 10);
            $table->integer('TwVigente')->default(1);
        });

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
            $table->integer('Luchaje')->nullable();
            $table->string('CodigoDibujo', 30)->nullable();
            $table->integer('Total')->nullable();
            $table->integer('PasadasTramaFondoC1')->nullable();
            $table->integer('PasadasComb1')->nullable();
            $table->integer('PasadasComb2')->nullable();
            $table->integer('PasadasComb3')->nullable();
            $table->integer('PasadasComb4')->nullable();
            $table->integer('PasadasComb5')->nullable();
            $table->float('Largo')->nullable();
            $table->float('TramaAnchoPeine')->nullable();
            $table->float('CalibrePie2')->nullable();
            $table->float('CalTramaFondoC1')->nullable();
            $table->float('CalibreComb12')->nullable();
            $table->float('CalibreComb22')->nullable();
            $table->float('CalibreComb32')->nullable();
            $table->float('CalibreComb42')->nullable();
            $table->float('CalibreComb52')->nullable();
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
            $table->string('NombreColor', 60)->nullable();
            $table->string('InventLocationId', 60)->nullable();
            $table->float('Qty');
            $table->float('Porcentaje')->nullable();
            $table->string('ItemIdCrudo', 60)->nullable();
            $table->string('InventSizeCrudo', 60)->nullable();
            $table->integer('Luchaje')->nullable();
            $table->string('CodigoDibujo', 30)->nullable();
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

    public function test_barra_karl_mayer_es_una_clave_por_posicion_y_exige_cuenta(): void
    {
        $barra = MatrizCalibreClave::fromArray([
            'Tipo' => 'barra1',
            'Calibre' => 370,
            'FibraId' => 'FIL. 370 VOLUMINIZADO',
            'Cuenta' => '2028',
        ]);

        $this->assertSame('BARRA1', $barra->tipo);
        $this->assertSame('2028', $barra->cuenta);
        $this->assertNull(MatrizCalibreClave::tryFromArray([
            'Tipo' => 'BARRA2',
            'Calibre' => 75,
            'FibraId' => 'FIL',
        ]));
        $this->assertNull(MatrizCalibreClave::tryFromArray([
            'Tipo' => 'BARRA5',
            'Calibre' => 75,
            'FibraId' => 'FIL',
            'Cuenta' => '2104',
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

    public function test_crud_barra_se_crea_edita_cambia_de_barra_y_elimina(): void
    {
        $base = [
            'Tipo' => 'BARRA1',
            'Calibre' => 370,
            'FibraId' => 'fil. 370 voluminizado',
            'Cuenta' => '2028',
            'ItemId' => 'JULIO-URDIDO',
            'ConfigId' => 'ALG-OPEN',
            'InventSizeId' => '2028-370/1',
            'InventColorId' => '1000',
        ];
        $id = $this->withoutMiddleware()
            ->postJson(route('planeacion.catalogos.matrizcalibres.store'), $base)
            ->assertOk()
            ->assertJsonPath('data.Tipo', 'BARRA1')
            ->assertJsonPath('data.FibraId', 'FIL. 370 VOLUMINIZADO')
            ->json('data.Id');

        // Editar la salida sin tocar la clave: sigue siendo la misma fila BARRA1.
        $this->withoutMiddleware()
            ->putJson(route('planeacion.catalogos.matrizcalibres.update', $id), [...$base, 'ConfigId' => 'Poly-Fil-T', 'InventSizeId' => '2028-370/2'])
            ->assertOk()
            ->assertJsonPath('data.Id', $id)
            ->assertJsonPath('data.Tipo', 'BARRA1')
            ->assertJsonPath('data.ConfigId', 'Poly-Fil-T');

        // Editar la clave (calibre y cuenta) y moverla a otra barra.
        $this->withoutMiddleware()
            ->putJson(route('planeacion.catalogos.matrizcalibres.update', $id), [...$base, 'Tipo' => 'barra2', 'Calibre' => 75.04, 'Cuenta' => '2104'])
            ->assertOk()
            ->assertJsonPath('data.Tipo', 'BARRA2')
            ->assertJsonPath('data.Cuenta', '2104');
        $fila = DB::connection('sqlsrv')->table('CatMatrizCalibres')->find($id);
        $this->assertSame('BARRA2', $fila->Tipo);
        $this->assertEqualsWithDelta(75.0, (float) $fila->Calibre, 0.0001);

        // La L.Mat la encuentra con la clave nueva y ya no con la vieja.
        $this->withoutMiddleware()->getJson(route('planeacion.lmat.matriz-calibre', [
            'tipo' => 'BARRA2', 'calibre' => 75, 'fibraId' => 'FIL. 370 VOLUMINIZADO', 'cuenta' => '2104',
        ]))->assertOk()->assertJsonPath('found', true);
        $this->withoutMiddleware()->getJson(route('planeacion.lmat.matriz-calibre', [
            'tipo' => 'BARRA1', 'calibre' => 370, 'fibraId' => 'FIL. 370 VOLUMINIZADO', 'cuenta' => '2028',
        ]))->assertOk()->assertJsonPath('found', false);

        $this->withoutMiddleware()
            ->deleteJson(route('planeacion.catalogos.matrizcalibres.destroy', $id))
            ->assertOk();
        $this->assertSame(0, DB::connection('sqlsrv')->table('CatMatrizCalibres')->count());
    }

    public function test_crud_barra_exige_cuenta_y_dice_que_campo_falta(): void
    {
        $response = $this->withoutMiddleware()->postJson(route('planeacion.catalogos.matrizcalibres.store'), [
            'Tipo' => 'BARRA3',
            'Calibre' => 75,
            'FibraId' => 'FIL',
            ...$this->salida('JULIO-URDIDO'),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('Cuenta');

        // La pantalla solo muestra "message": no puede quedarse en el genérico.
        $this->assertNotSame('Error de validación', $response->json('message'));
        $this->assertSame(0, DB::connection('sqlsrv')->table('CatMatrizCalibres')->count());

        $this->withoutMiddleware()->getJson(route('planeacion.lmat.matriz-calibre', [
            'tipo' => 'BARRA3', 'calibre' => 75, 'fibraId' => 'FIL',
        ]))->assertUnprocessable()->assertJsonValidationErrors('cuenta');
    }

    public function test_crud_rechaza_tipos_invalidos_y_trama_con_cuenta(): void
    {
        foreach (['BARRA5', 'BARRA', 'KM', ''] as $tipo) {
            $this->withoutMiddleware()->postJson(route('planeacion.catalogos.matrizcalibres.store'), [
                'Tipo' => $tipo, 'Calibre' => 75, 'FibraId' => 'FIL', 'Cuenta' => '2104', ...$this->salida('X'),
            ])->assertUnprocessable()->assertJsonValidationErrors('Tipo');
        }

        $this->withoutMiddleware()->postJson(route('planeacion.catalogos.matrizcalibres.store'), [
            'Tipo' => 'TRAMA', 'Calibre' => 10, 'FibraId' => 'OPEN', 'Cuenta' => '2104', ...$this->salida('10/1'),
        ])->assertUnprocessable()->assertJsonValidationErrors('Cuenta');

        // Salida incompleta tampoco se guarda.
        $this->withoutMiddleware()->postJson(route('planeacion.catalogos.matrizcalibres.store'), [
            'Tipo' => 'BARRA1', 'Calibre' => 370, 'FibraId' => 'FIL', 'Cuenta' => '2028',
            'ItemId' => 'JULIO-URDIDO', 'ConfigId' => '', 'InventSizeId' => '2028-370/1', 'InventColorId' => '1000',
        ])->assertUnprocessable()->assertJsonValidationErrors('ConfigId');

        $this->assertSame(0, DB::connection('sqlsrv')->table('CatMatrizCalibres')->count());
    }

    public function test_crud_editar_una_barra_a_la_clave_de_otra_las_fusiona(): void
    {
        $barra = ['Calibre' => 75, 'FibraId' => 'FIL', 'Cuenta' => '2139'];
        $a = $this->withoutMiddleware()->postJson(route('planeacion.catalogos.matrizcalibres.store'),
            ['Tipo' => 'BARRA3', ...$barra, ...$this->salida('JULIO-A')])->json('data.Id');
        $b = $this->withoutMiddleware()->postJson(route('planeacion.catalogos.matrizcalibres.store'),
            ['Tipo' => 'BARRA4', ...$barra, ...$this->salida('JULIO-B')])->json('data.Id');
        $this->assertNotSame($a, $b);

        // Misma cuenta/calibre/fibra en otra barra es otra clave: no se fusionan al crear.
        $this->assertSame(2, DB::connection('sqlsrv')->table('CatMatrizCalibres')->count());

        $this->withoutMiddleware()->putJson(route('planeacion.catalogos.matrizcalibres.update', $a),
            ['Tipo' => 'BARRA4', ...$barra, ...$this->salida('JULIO-FINAL')])
            ->assertOk()
            ->assertJsonPath('data.Id', $b)
            ->assertJsonPath('data.ItemId', 'JULIO-FINAL');
        $this->assertSame(1, DB::connection('sqlsrv')->table('CatMatrizCalibres')->count());
    }

    public function test_guardar_lmat_aprende_pie_sin_calibre(): void
    {
        DB::connection('sqlsrv')->table('CatCodificados')->insert([
            'OrdenTejido' => 'ORD-100',
            'TelarId' => '305',
        ]);
        $this->seedCatalogoAx('JU-ENG-PI-C', false);
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
        $this->seedCatalogoAx('JU-ENG-PI-C', false);
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

    public function test_guardar_lmat_conserva_cualquier_cantidad_positiva(): void
    {
        DB::connection('sqlsrv')->table('CatCodificados')->insert([
            'OrdenTejido' => 'ORD-100',
            'TelarId' => '305',
        ]);
        $this->seedCatalogoAx('ITEM-NORMAL');
        $this->seedCatalogoAx('600/1');
        $payload = $this->payloadLMat('ITEM-NORMAL');
        $payload['filas'][0]['porcentaje'] = 50;
        $filaPequena = $payload['filas'][0];
        $filaPequena['itemId'] = '600/1';
        $filaPequena['qty'] = 0.0001;
        $filaPequena['matrizCalibre'] = 600.1;
        $filaPequena['porcentaje'] = 50;
        $payload['filas'][] = $filaPequena;

        $this->withoutMiddleware()
            ->postJson(route('planeacion.lmat.guardar'), $payload)
            ->assertOk()
            ->assertJsonPath('success', true);

        $qtyGuardada = (float) DB::connection('sqlsrv')
            ->table('CatLMat')
            ->where('Orden', 'ORD-100')
            ->where('ItemId', '600/1')
            ->value('Qty');

        $this->assertEqualsWithDelta(0.0001, $qtyGuardada, 0.000000001);
        $this->assertSame(2, DB::connection('sqlsrv')->table('CatLMat')->count());
        $this->assertSame(2, DB::connection('sqlsrv')->table('CatMatrizCalibres')->count());
    }

    public function test_nombre_color_se_resuelve_por_articulo_e_invent_color_id(): void
    {
        DB::connection('sqlsrv_ti')->table('InventColor')->insert([
            ['ItemId' => '10/1', 'InventColorId' => '1000', 'Name' => 'HILO 10/1', 'DATAAREAID' => 'PRO'],
            ['ItemId' => '10/1', 'InventColorId' => '2000', 'Name' => 'HILO 10/1 AZUL', 'DATAAREAID' => 'PRO'],
            ['ItemId' => '12/1', 'InventColorId' => '2000', 'Name' => 'OTRO ARTICULO', 'DATAAREAID' => 'PRO'],
        ]);

        $this->withoutMiddleware()
            ->getJson(route('planeacion.lmat.colores', [
                'itemId' => '10/1',
                'inventColorId' => '2000',
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.InventColorId', '2000')
            ->assertJsonPath('data.0.Name', 'HILO 10/1 AZUL');
    }

    public function test_catalogos_materiales_se_cargan_en_un_solo_lote_por_articulos(): void
    {
        DB::connection('sqlsrv_ti')->table('ConfigTable')->insert([
            ['ItemId' => '10/1', 'ConfigId' => 'ENTERO', 'DATAAREAID' => 'PRO'],
            ['ItemId' => '10/1', 'ConfigId' => 'HILO', 'DATAAREAID' => 'PRO'],
            ['ItemId' => '12/1', 'ConfigId' => 'DOBLE', 'DATAAREAID' => 'PRO'],
        ]);
        DB::connection('sqlsrv_ti')->table('InventSize')->insert([
            ['ItemId' => '10/1', 'InventSizeId' => 'ENTERO', 'NAME' => 'Entero', 'DATAAREAID' => 'PRO'],
            ['ItemId' => '12/1', 'InventSizeId' => 'MEDIO', 'NAME' => 'Medio', 'DATAAREAID' => 'PRO'],
        ]);
        DB::connection('sqlsrv_ti')->table('InventColor')->insert([
            ['ItemId' => '10/1', 'InventColorId' => '1000', 'Name' => 'HILO 10/1', 'DATAAREAID' => 'PRO'],
            ['ItemId' => '12/1', 'InventColorId' => '2000', 'Name' => 'HILO 12/1 AZUL', 'DATAAREAID' => 'PRO'],
        ]);

        $response = $this->withoutMiddleware()->getJson(route('planeacion.lmat.catalogos-materiales', [
            'itemIds' => ['10/1', '12/1'],
        ]));

        $response->assertOk()->assertJsonPath('success', true);
        $data = $response->json('data');
        $this->assertSame(['ENTERO'], $data['10/1']['configs']);
        $this->assertSame('ENTERO', $data['10/1']['tamanos'][0]['InventSizeId']);
        $this->assertSame('HILO 12/1 AZUL', $data['12/1']['colores'][0]['Name']);
    }

    public function test_matriz_calibres_resuelve_varias_claves_en_un_solo_lote(): void
    {
        $claveGuardada = MatrizCalibreClave::fromArray([
            'Tipo' => 'TRAMA',
            'Calibre' => 10.1,
            'FibraId' => 'PES',
        ]);
        $this->service->aprender($claveGuardada, $this->salida('10/1'));

        $response = $this->withoutMiddleware()->postJson(route('planeacion.lmat.matriz-calibre.lote'), [
            'claves' => [
                ['key' => 'TRAMA|10.1|PES|', 'tipo' => 'TRAMA', 'calibre' => 10.1, 'fibraId' => 'PES', 'cuenta' => null],
                ['key' => 'TRAMA|12.1|ALG|', 'tipo' => 'TRAMA', 'calibre' => 12.1, 'fibraId' => 'ALG', 'cuenta' => null],
            ],
        ]);

        $response->assertOk()->assertJsonPath('success', true);
        $data = $response->json('data');
        $this->assertSame('10/1', $data['TRAMA|10.1|PES|']['ItemId']);
        $this->assertNull($data['TRAMA|12.1|ALG|']);
    }

    public function test_guardar_lmat_rechaza_cantidad_cero_sin_hacer_guardado_parcial(): void
    {
        DB::connection('sqlsrv')->table('CatCodificados')->insert([
            'OrdenTejido' => 'ORD-100',
            'TelarId' => '305',
        ]);
        $payload = $this->payloadLMat('ITEM-A');
        $payload['filas'][0]['qty'] = 0;

        $this->withoutMiddleware()
            ->postJson(route('planeacion.lmat.guardar'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('filas.0.qty');

        $this->assertSame(0, DB::connection('sqlsrv')->table('CatLMat')->count());
    }

    public function test_guardar_lmat_rechaza_fila_sin_articulo_sin_hacer_guardado_parcial(): void
    {
        DB::connection('sqlsrv')->table('CatCodificados')->insert([
            'OrdenTejido' => 'ORD-100',
            'TelarId' => '305',
        ]);
        $payload = $this->payloadLMat('');

        $this->withoutMiddleware()
            ->postJson(route('planeacion.lmat.guardar'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('filas.0.itemId');

        $this->assertSame(0, DB::connection('sqlsrv')->table('CatLMat')->count());
    }

    public function test_guardar_lmat_actualiza_solo_las_pasadas_enviadas(): void
    {
        DB::connection('sqlsrv')->table('CatCodificados')->insert([
            'OrdenTejido' => 'ORD-100',
            'TelarId' => '305',
            'PasadasTramaFondoC1' => 1200,
            'PasadasComb1' => 80,
            'PasadasComb2' => 60,
            'PasadasComb3' => 40,
        ]);
        $this->seedCatalogoAx('ITEM-A');
        $payload = $this->payloadLMat('ITEM-A');
        $payload['pasadas'] = [
            'PasadasTramaFondoC1' => 1350,
            'PasadasComb1' => 95,
            'PasadasComb2' => 0,
        ];

        $this->withoutMiddleware()
            ->postJson(route('planeacion.lmat.guardar'), $payload)
            ->assertOk()
            ->assertJsonPath('success', true);

        $registro = DB::connection('sqlsrv')
            ->table('CatCodificados')
            ->where('OrdenTejido', 'ORD-100')
            ->first();

        $this->assertSame(1350, $registro->PasadasTramaFondoC1);
        $this->assertSame(95, $registro->PasadasComb1);
        $this->assertSame(0, $registro->PasadasComb2);
        $this->assertSame(40, $registro->PasadasComb3);
    }

    public function test_guardar_lmat_persiste_parametros_editables_de_formula_en_catcodificados(): void
    {
        DB::connection('sqlsrv')->table('CatCodificados')->insert([
            'OrdenTejido' => 'ORD-100',
            'TelarId' => '305',
            'Largo' => 160,
            'TramaAnchoPeine' => null,
            'CalibrePie2' => 12,
            'CalTramaFondoC1' => 12,
            'CalibreComb12' => 3,
        ]);
        $this->seedCatalogoAx('ITEM-A');
        $payload = $this->payloadLMat('ITEM-A');
        $payload['formula'] = [
            'Largo' => 165,
            'TramaAnchoPeine' => 93,
            'CalibrePie2' => 10,
            'CalTramaFondoC1' => 10,
            'CalibreComb12' => 10,
            'CalibreComb22' => 3.5,
        ];

        $this->withoutMiddleware()
            ->postJson(route('planeacion.lmat.guardar'), $payload)
            ->assertOk()
            ->assertJsonPath('success', true);

        $registro = DB::connection('sqlsrv')
            ->table('CatCodificados')
            ->where('OrdenTejido', 'ORD-100')
            ->first();

        $this->assertEquals(165.0, $registro->Largo);
        $this->assertEquals(93.0, $registro->TramaAnchoPeine);
        $this->assertEquals(10.0, $registro->CalibrePie2);
        $this->assertEquals(10.0, $registro->CalTramaFondoC1);
        $this->assertEquals(10.0, $registro->CalibreComb12);
        $this->assertEquals(3.5, $registro->CalibreComb22);
        $this->assertNull($registro->CalibreComb32);
    }

    public function test_guardar_lmat_limita_total_pasadas_a_mas_menos_treinta_por_ciento(): void
    {
        DB::connection('sqlsrv')->table('CatCodificados')->insert([
            'OrdenTejido' => 'ORD-100',
            'TelarId' => '305',
            'Total' => 984,
            'PasadasTramaFondoC1' => 800,
            'PasadasComb1' => 184,
        ]);
        $this->seedCatalogoAx('ITEM-A');
        $payloadParaTotal = function (int $totalPasadas): array {
            $payload = $this->payloadLMat('ITEM-A');
            $payload['pasadas'] = [
                'PasadasTramaFondoC1' => $totalPasadas,
                'PasadasComb1' => 0,
                'PasadasComb2' => 0,
                'PasadasComb3' => 0,
                'PasadasComb4' => 0,
                'PasadasComb5' => 0,
            ];

            return $payload;
        };

        $this->withoutMiddleware()
            ->postJson(route('planeacion.lmat.guardar'), $payloadParaTotal(687))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('pasadas');

        $this->withoutMiddleware()
            ->postJson(route('planeacion.lmat.guardar'), $payloadParaTotal(688))
            ->assertOk();

        $this->withoutMiddleware()
            ->postJson(route('planeacion.lmat.guardar'), $payloadParaTotal(1279))
            ->assertOk();

        $this->withoutMiddleware()
            ->postJson(route('planeacion.lmat.guardar'), $payloadParaTotal(1280))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('pasadas');

        $this->assertSame(
            1279,
            (int) DB::connection('sqlsrv')->table('CatCodificados')->value('PasadasTramaFondoC1'),
        );
    }

    public function test_guardar_y_actualizar_lmat_tambien_actualiza_la_equivalencia_global(): void
    {
        DB::connection('sqlsrv')->table('CatCodificados')->insert([
            'OrdenTejido' => 'ORD-100',
            'TelarId' => '305',
        ]);

        $this->seedCatalogoAx('ITEM-A');
        $this->seedCatalogoAx('ITEM-B');
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

    public function test_guardar_lmat_rechaza_fila_sin_tamano(): void
    {
        DB::connection('sqlsrv')->table('CatCodificados')->insert([
            'OrdenTejido' => 'ORD-100',
            'TelarId' => '305',
        ]);
        $this->seedCatalogoAx('ITEM-A');
        $payload = $this->payloadLMat('ITEM-A');
        $payload['filas'][0]['inventSizeId'] = '';

        $this->withoutMiddleware()
            ->postJson(route('planeacion.lmat.guardar'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('filas.0.inventSizeId');

        $this->assertSame(0, DB::connection('sqlsrv')->table('CatLMat')->count());
    }

    public function test_guardar_lmat_rechaza_fila_sin_color(): void
    {
        DB::connection('sqlsrv')->table('CatCodificados')->insert([
            'OrdenTejido' => 'ORD-100',
            'TelarId' => '305',
        ]);
        $this->seedCatalogoAx('ITEM-A');
        $payload = $this->payloadLMat('ITEM-A');
        $payload['filas'][0]['inventColorId'] = '';

        $this->withoutMiddleware()
            ->postJson(route('planeacion.lmat.guardar'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('filas.0.inventColorId');

        $this->assertSame(0, DB::connection('sqlsrv')->table('CatLMat')->count());
    }

    public function test_guardar_lmat_km_editada_persiste_todo_y_actualizar_no_duplica(): void
    {
        Schema::connection('sqlsrv')->table('CatCodificados', function (Blueprint $table): void {
            $table->string('Departamento', 60)->nullable();
            $table->boolean('ActualizaLmat')->nullable();
            foreach ([1, 2, 3, 4] as $n) {
                $table->integer("PasadasBarra{$n}")->nullable();
                $table->string("CalibreBarra{$n}", 50)->nullable();
                $table->float("CalibreBarra{$n}2")->nullable();
            }
        });
        DB::connection('sqlsrv')->table('CatCodificados')->insert([
            'OrdenTejido' => 'KM-1', 'TelarId' => '401', 'Departamento' => 'KM', 'BomId' => 'TEJ FEL LI GEN KM2-K',
            'Luchaje' => 0, 'Total' => 0, 'PasadasBarra1' => 4410, 'PasadasBarra2' => 2010,
            'CalibreBarra2' => '70', 'CalibreBarra22' => 75.99,
        ]);
        foreach (['2160-16/1', '2160-70/1'] as $tamano) {
            DB::connection('sqlsrv_ti')->table('InventSize')->insert(['ItemId' => 'JULIO-URDIDO', 'InventSizeId' => $tamano, 'DATAAREAID' => 'PRO', 'TwVigente' => 1]);
        }
        DB::connection('sqlsrv_ti')->table('InventTable')->insert(['ItemId' => 'JULIO-URDIDO', 'ItemGroupId' => 'JUL-URD', 'DATAAREAID' => 'PRO', 'TwVigente' => 1]);
        foreach (['ALG-OPEN', 'Poly-Fil-T'] as $config) {
            DB::connection('sqlsrv_ti')->table('ConfigTable')->insert(['ItemId' => 'JULIO-URDIDO', 'ConfigId' => $config, 'DATAAREAID' => 'PRO', 'TwVigente' => 1]);
        }
        DB::connection('sqlsrv_ti')->table('InventColor')->insert(['ItemId' => 'JULIO-URDIDO', 'InventColorId' => '1000', 'Name' => 'CRUDO', 'DATAAREAID' => 'PRO', 'TwVigente' => 1]);

        // Lo que manda el modal tras editar pasadas, calibre, config, tamaño, luchaje y peso.
        $fila = fn (int $n, string $config, string $tamano, float $qty, float $pct, float $calibre) => [
            'itemId' => 'JULIO-URDIDO', 'configId' => $config, 'inventSizeId' => $tamano, 'inventColorId' => '1000',
            'inventLocationId' => 'A-EP-TEJID', 'qty' => $qty, 'porcentaje' => $pct,
            'matrizTipo' => "BARRA{$n}", 'matrizCalibre' => $calibre, 'matrizFibraId' => 'ALGODON', 'matrizCuenta' => '2160',
        ];
        $payload = [
            'orden' => 'KM-1', 'salon' => 'KM', 'telarId' => '401', 'nombre' => 'TEJ FEL 612 KM III', 'descrip' => 'FEL 612',
            'pesoCrudo' => '800', 'itemIdCrudo' => '7535', 'inventSizeCrudo' => 'FEL', 'luchaje' => 35, 'actualizaLmat' => true,
            'pasadas' => ['PasadasBarra1' => 4410, 'PasadasBarra2' => 2500, 'PasadasBarra3' => 2970, 'PasadasBarra4' => 3850],
            'formula' => ['CalibreBarra12' => 16, 'CalibreBarra22' => 75.99, 'CalibreBarra32' => 20, 'CalibreBarra42' => 16],
            'filas' => [
                $fila(1, 'Poly-Fil-T', '2160-16/1', 0.3161, 39.50, 16),
                $fila(2, 'Poly-Fil-T', '2160-70/1', 0.0377, 4.72, 70),
                $fila(3, 'ALG-OPEN', '2160-16/1', 0.1703, 21.29, 16),
                $fila(4, 'ALG-OPEN', '2160-70/1', 0.2759, 34.49, 16),
            ],
        ];

        foreach ([1, 2] as $vez) { // 1 = Guardar, 2 = Actualizar con lo mismo
            $this->withoutMiddleware()->postJson(route('planeacion.lmat.guardar'), $payload)
                ->assertOk()->assertJsonPath('success', true);
        }

        $lineas = DB::connection('sqlsrv')->table('CatLMat')->where('Orden', 'KM-1')->orderBy('Id')->get();
        $this->assertCount(4, $lineas, 'Actualizar reemplaza las líneas, no las duplica');
        $this->assertSame(['Poly-Fil-T', 'Poly-Fil-T', 'ALG-OPEN', 'ALG-OPEN'], $lineas->pluck('ConfigId')->all());
        $this->assertSame('2160-70/1', $lineas[3]->InventSizeId);
        $this->assertEqualsWithDelta(0.8, $lineas->sum('Qty'), 0.0001);
        $this->assertSame(35, (int) $lineas[0]->Luchaje);

        $cat = DB::connection('sqlsrv')->table('CatCodificados')->where('OrdenTejido', 'KM-1')->first();
        $this->assertSame(2500, (int) $cat->PasadasBarra2);
        $this->assertEqualsWithDelta(20.0, (float) $cat->CalibreBarra32, 0.0001);
        $this->assertSame('70', $cat->CalibreBarra2, 'el calibre de catálogo no se toca');
        $this->assertSame(35, (int) $cat->Luchaje);
        $this->assertSame('TEJ FEL 612 KM III', $cat->BomId, 'KM reemplaza su L.Mat genérica');

        $matriz = DB::connection('sqlsrv')->table('CatMatrizCalibres')->orderBy('Tipo')->get();
        $this->assertSame(['BARRA1', 'BARRA2', 'BARRA3', 'BARRA4'], $matriz->pluck('Tipo')->all(), 'una clave por barra, sin duplicar al actualizar');
        $this->assertEqualsWithDelta(70.0, (float) $matriz[1]->Calibre, 0.0001, 'la matriz usa el calibre de catálogo, no 75.99');
        $this->assertSame('2160-70/1', $matriz[3]->InventSizeId);
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
                // guardarLmat exige suma de % = 100; una sola fila debe cerrar el total.
                'porcentaje' => 100,
                'matrizTipo' => 'TRAMA',
                'matrizCalibre' => 10.1,
                'matrizFibraId' => 'PES',
                'matrizCuenta' => null,
            ]],
        ];
    }

    /**
     * Inserta el artículo y sus catálogos (config, tamaño, color) en AX
     * para que la validación de guardarLmat lo permita.
     */
    private function seedCatalogoAx(string $itemId, bool $inventTable = true): void
    {
        if ($inventTable) {
            DB::connection('sqlsrv_ti')->table('InventTable')->insert([
                'ItemId' => $itemId,
                'ItemGroupId' => 'HILO DIREC',
                'DATAAREAID' => 'PRO',
                'TwVigente' => 1,
            ]);
        }

        DB::connection('sqlsrv_ti')->table('ConfigTable')->insert([
            'ItemId' => $itemId,
            'ConfigId' => 'ENTERO',
            'DATAAREAID' => 'PRO',
            'TwVigente' => 1,
        ]);

        DB::connection('sqlsrv_ti')->table('InventSize')->insert([
            'ItemId' => $itemId,
            'InventSizeId' => '20-10/1',
            'NAME' => 'Tamaño prueba',
            'DATAAREAID' => 'PRO',
            'TwVigente' => 1,
        ]);

        DB::connection('sqlsrv_ti')->table('InventColor')->insert([
            'ItemId' => $itemId,
            'InventColorId' => '1000',
            'Name' => 'Color prueba',
            'DATAAREAID' => 'PRO',
            'TwVigente' => 1,
        ]);
    }
}
