<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Planeacion\Catalogos\CatLMat;
use App\Models\Sistema\Usuario;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/**
 * BUG-008: guardar L.Mat debe exigir % total = 100 en el servidor,
 * no solo en el modal.
 */
class CatLMatGuardarPorcentajeTest extends TestCase
{
    use UsesSqlsrvSqlite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');

        config()->set('database.connections.sqlsrv_ti', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        DB::purge('sqlsrv_ti');

        Schema::connection('sqlsrv')->create('CatLMat', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Orden')->nullable();
            $table->string('Salon')->nullable();
            $table->string('Nombre')->nullable();
            $table->string('Descrip')->nullable();
            $table->string('PesoCrudo')->nullable();
            $table->string('ItemId')->nullable();
            $table->string('ConfigId')->nullable();
            $table->string('InventSizeId')->nullable();
            $table->string('InventColorId')->nullable();
            $table->string('NombreColor')->nullable();
            $table->string('InventLocationId')->nullable();
            $table->float('Qty')->nullable();
            $table->float('Porcentaje')->nullable();
            $table->string('ItemIdCrudo')->nullable();
            $table->string('InventSizeCrudo')->nullable();
            $table->integer('Luchaje')->nullable();
            $table->string('CodigoDibujo')->nullable();
            $table->date('FechaRegistro')->nullable();
            $table->string('HoraRegistro')->nullable();
            $table->string('UsuarioRegistro')->nullable();
        });

        Schema::connection('sqlsrv')->create('CatCodificados', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('OrdenTejido')->nullable();
            $table->string('TelarId')->nullable();
            $table->string('BomId')->nullable();
            $table->string('BomName')->nullable();
            $table->boolean('ActualizaLmat')->nullable();
            $table->integer('Luchaje')->nullable();
            $table->string('CodigoDibujo')->nullable();
            $table->float('Total')->nullable();
            foreach ([1, 2, 3, 4] as $n) {
                $table->integer("PasadasBarra{$n}")->nullable();
                $table->string("CalibreBarra{$n}", 50)->nullable();
                $table->float("CalibreBarra{$n}2")->nullable();
            }
        });

        Schema::connection('sqlsrv_ti')->create('InventTable', function (Blueprint $table) {
            $table->string('ItemId');
            $table->string('DATAAREAID')->nullable();
        });
        Schema::connection('sqlsrv_ti')->create('ConfigTable', function (Blueprint $table) {
            $table->string('ItemId');
            $table->string('ConfigId');
            $table->string('DATAAREAID')->nullable();
            $table->integer('TwVigente')->default(1);
        });
        Schema::connection('sqlsrv_ti')->create('InventSize', function (Blueprint $table) {
            $table->string('ItemId');
            $table->string('InventSizeId');
            $table->string('DATAAREAID')->nullable();
            $table->integer('TwVigente')->default(1);
        });
        Schema::connection('sqlsrv_ti')->create('InventColor', function (Blueprint $table) {
            $table->string('ItemId');
            $table->string('InventColorId');
            $table->string('DATAAREAID')->nullable();
            $table->integer('TwVigente')->default(1);
        });

        $this->sembrarCatalogoAx('HIL-01');
    }

    protected function tearDown(): void
    {
        foreach (['CatLMat', 'CatCodificados'] as $tabla) {
            Schema::connection('sqlsrv')->dropIfExists($tabla);
        }
        foreach (['InventTable', 'ConfigTable', 'InventSize', 'InventColor'] as $tabla) {
            Schema::connection('sqlsrv_ti')->dropIfExists($tabla);
        }
        parent::tearDown();
    }

    public function test_guardar_lmat_rechaza_cuando_el_porcentaje_total_no_es_100(): void
    {
        CatLMat::query()->create([
            'Orden' => 'ORD-LMAT-90',
            'ItemId' => 'HIL-01',
            'ConfigId' => 'ENTERO',
            'InventSizeId' => '20-10/1',
            'InventColorId' => '1000',
            'Qty' => 1.0,
            'Porcentaje' => 100,
        ]);

        $this->actingAs($this->usuarioConPermisoCodificacion())
            ->postJson(route('planeacion.lmat.guardar'), $this->payloadLmat('ORD-LMAT-90', [
                $this->filaLmat(60),
                $this->filaLmat(30),
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['filas']);

        $this->assertSame(1, CatLMat::query()->where('Orden', 'ORD-LMAT-90')->count());
        $this->assertSame(100.0, (float) CatLMat::query()->where('Orden', 'ORD-LMAT-90')->value('Porcentaje'));
    }

    public function test_guardar_lmat_acepta_cuando_el_porcentaje_total_es_100(): void
    {
        $this->actingAs($this->usuarioConPermisoCodificacion())
            ->postJson(route('planeacion.lmat.guardar'), $this->payloadLmat('ORD-LMAT-100', [
                $this->filaLmat(60),
                $this->filaLmat(40),
            ]))
            ->assertOk()
            ->assertJsonPath('success', true);

        $filas = CatLMat::query()->where('Orden', 'ORD-LMAT-100')->orderBy('Id')->get();
        $this->assertCount(2, $filas);
        $this->assertEqualsWithDelta(100.0, (float) $filas->sum('Porcentaje'), 0.001);
    }

    public function test_guardar_lmat_karl_mayer_guarda_pasadas_de_barra_sin_rango_de_total(): void
    {
        DB::connection('sqlsrv')->table('CatCodificados')->insert([
            'OrdenTejido' => 'ORD-KM',
            'TelarId' => '401',
            'Total' => 100,
            'PasadasBarra1' => 60,
            'CalibreBarra2' => '70',
        ]);

        // 60+50+60+70 = 240, fuera de ±30% de Total=100: en KM no debe bloquear.
        $payload = $this->payloadLmat('ORD-KM', [$this->filaLmat(60), $this->filaLmat(40)]) + [
            'telarId' => '401',
            'pasadas' => ['PasadasBarra1' => 60, 'PasadasBarra2' => 50, 'PasadasBarra3' => 60, 'PasadasBarra4' => 70],
            'formula' => ['CalibreBarra12' => 16, 'CalibreBarra22' => 75.99],
        ];

        $this->actingAs($this->usuarioConPermisoCodificacion())
            ->postJson(route('planeacion.lmat.guardar'), $payload)
            ->assertOk();

        $registro = DB::connection('sqlsrv')->table('CatCodificados')->where('OrdenTejido', 'ORD-KM')->first();
        $this->assertSame([60, 50, 60, 70], array_map(
            static fn (int $n): int => (int) $registro->{"PasadasBarra{$n}"},
            [1, 2, 3, 4],
        ));
        $this->assertEqualsWithDelta(75.99, (float) $registro->CalibreBarra22, 0.0001);
        // El calibre de catálogo (tamaño AX 70/1) no se toca.
        $this->assertSame('70', $registro->CalibreBarra2);
    }

    public function test_karl_mayer_reemplaza_su_lmat_generica_y_jacquard_sin_bom_id_no(): void
    {
        DB::connection('sqlsrv')->table('CatCodificados')->insert([
            ['OrdenTejido' => 'ORD-KM-BOM', 'TelarId' => '401', 'BomId' => 'TEJ FEL LI GEN KM2-K'],
            ['OrdenTejido' => 'ORD-JAC-BOM', 'TelarId' => '205', 'BomId' => null],
        ]);

        foreach ([['ORD-KM-BOM', 'KM', '401'], ['ORD-JAC-BOM', 'JACQUARD', '205']] as [$orden, $salon, $telar]) {
            $this->actingAs($this->usuarioConPermisoCodificacion())
                ->postJson(route('planeacion.lmat.guardar'), [
                    'salon' => $salon,
                    'telarId' => $telar,
                    'actualizaLmat' => true,
                ] + $this->payloadLmat($orden, [$this->filaLmat(100)]))
                ->assertOk();
        }

        $bom = fn (string $orden) => DB::connection('sqlsrv')->table('CatCodificados')->where('OrdenTejido', $orden)->value('BomId');
        $this->assertSame('TEJHIL01', $bom('ORD-KM-BOM'));
        $this->assertNull($bom('ORD-JAC-BOM'));
    }

    public function test_guardar_lmat_guarda_el_luchaje_capturado_en_codificacion_y_en_catlmat(): void
    {
        DB::connection('sqlsrv')->table('CatCodificados')->insert(['OrdenTejido' => 'ORD-LUCH', 'Luchaje' => 30]);

        $this->actingAs($this->usuarioConPermisoCodificacion())
            ->postJson(route('planeacion.lmat.guardar'), ['luchaje' => 35] + $this->payloadLmat('ORD-LUCH', [$this->filaLmat(100)]))
            ->assertOk();

        $this->assertSame(35, (int) DB::connection('sqlsrv')->table('CatCodificados')->where('OrdenTejido', 'ORD-LUCH')->value('Luchaje'));
        $this->assertSame(35, (int) CatLMat::query()->where('Orden', 'ORD-LUCH')->value('Luchaje'));
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array<string, mixed>
     */
    private function payloadLmat(string $orden, array $filas): array
    {
        return [
            'orden' => $orden,
            'salon' => 'JACQUARD',
            'nombre' => 'TEJHIL01',
            'descrip' => 'L.Mat prueba',
            'filas' => $filas,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function filaLmat(float $porcentaje): array
    {
        return [
            'itemId' => 'HIL-01',
            'configId' => 'ENTERO',
            'inventSizeId' => '20-10/1',
            'inventColorId' => '1000',
            'qty' => 1.25,
            'porcentaje' => $porcentaje,
        ];
    }

    private function sembrarCatalogoAx(string $itemId): void
    {
        DB::connection('sqlsrv_ti')->table('InventTable')->insert([
            'ItemId' => $itemId,
            'DATAAREAID' => 'PRO',
        ]);
        DB::connection('sqlsrv_ti')->table('ConfigTable')->insert([
            'ItemId' => $itemId,
            'ConfigId' => 'ENTERO',
            'DATAAREAID' => 'PRO',
            'TwVigente' => 1,
        ]);
        DB::connection('sqlsrv_ti')->table('InventSize')->insert([
            'ItemId' => $itemId,
            'InventSizeId' => '20-10/1',
            'DATAAREAID' => 'PRO',
            'TwVigente' => 1,
        ]);
        DB::connection('sqlsrv_ti')->table('InventColor')->insert([
            'ItemId' => $itemId,
            'InventColorId' => '1000',
            'DATAAREAID' => 'PRO',
            'TwVigente' => 1,
        ]);
    }

    private function usuarioConPermisoCodificacion(): Usuario
    {
        $usuario = new Usuario(['nombre' => 'Codificación L.Mat']);
        $usuario->idusuario = 999008;

        // La ruta lmat.guardar gatea por idrol (169 = Codificacion), no por nombre.
        $idrol = 169;
        app()->instance('permisos.roles', collect([
            'codificación' => (object) ['idrol' => $idrol, 'modulo' => 'Codificación'],
        ]));
        app()->instance('permisos.usuario.'.$usuario->idusuario, collect([
            $idrol => (object) [
                'acceso' => 1,
                'crear' => 0,
                'modificar' => 1,
                'eliminar' => 0,
                'registrar' => 0,
            ],
        ]));

        return $usuario;
    }
}
