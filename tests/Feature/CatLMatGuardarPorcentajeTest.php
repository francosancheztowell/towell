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
