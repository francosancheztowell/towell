<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Planeacion\CatCodificados\CatCodificacionController;
use App\Models\Planeacion\Catalogos\CatCodificados;
use App\Models\Sistema\Usuario;
use App\Services\Planeacion\Liberar\LiberarBomCrudoResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/**
 * BUG-007: la lista L.Mat de Cat (Peso muestra) usa LiberarBomCrudoResolver
 * (EXISTS, sin limit 50, salón de Cat o todos los alias AX).
 */
class CatCodificacionLmatQueryTest extends TestCase
{
    use UsesSqlsrvSqlite;

    private LiberarBomCrudoResolver $resolver;

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

        Schema::connection('sqlsrv')->create('CatCodificados', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('OrdenTejido')->nullable();
            $table->string('Departamento')->nullable();
            $table->integer('TelarId')->nullable();
            $table->string('ItemId')->nullable();
            $table->string('InventSizeId')->nullable();
            $table->string('Nombre')->nullable();
            $table->string('ClaveModelo')->nullable();
            $table->boolean('ActualizaLmat')->nullable();
            $table->float('PesoMuestra')->nullable();
            $table->string('AlturaRizo')->nullable();
            $table->string('BomId')->nullable();
            $table->string('BomName')->nullable();
        });

        Schema::connection('sqlsrv_ti')->create('BOMTABLE', function (Blueprint $table) {
            $table->string('BOMID');
            $table->string('NAME')->nullable();
            $table->string('ITEMGROUPID')->nullable();
            $table->string('TWINVENTSIZEID')->nullable();
            $table->string('TWSALON')->nullable();
            $table->integer('Vigente')->default(1);
        });
        Schema::connection('sqlsrv_ti')->create('BOMVERSION', function (Blueprint $table) {
            $table->string('BOMID');
            $table->string('ITEMID');
        });

        $this->resolver = new LiberarBomCrudoResolver;
    }

    protected function tearDown(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('CatCodificados');
        foreach (['BOMTABLE', 'BOMVERSION'] as $tabla) {
            Schema::connection('sqlsrv_ti')->dropIfExists($tabla);
        }
        parent::tearDown();
    }

    public function test_lista_lmat_colapsa_versiones_ax_como_el_resolver(): void
    {
        $this->sembrarBom('BOM-MULTI-03', 'IT700', 'STD', 'JACQUARD', versiones: 3);
        $this->sembrarBom('BOM-AJENO-99', 'IT999', 'STD');
        $this->sembrarCat('ORD-MULTI', 'IT700', 'STD', 'JACQUARD');

        $lista = $this->listaLmat('ORD-MULTI');

        $this->assertCount(1, $lista);
        $this->assertSame('BOM-MULTI-03', $lista[0]['bomId']);
        $this->assertSame('LISTA MATERIALES BOM-MULTI-03', $lista[0]['bomName']);
        $this->assertSame(
            $this->mapearResolver($this->resolver->query('IT700', 'STD', 'JACQUARD')->get()),
            $lista
        );
    }

    public function test_lista_lmat_no_trunca_a_cincuenta(): void
    {
        for ($i = 1; $i <= 51; $i++) {
            $this->sembrarBom(sprintf('BOM-%02d', $i), 'IT100', 'STD', 'JACQUARD');
        }
        $this->sembrarCat('ORD-51', 'IT100', 'STD', 'JACQUARD');

        $lista = $this->listaLmat('ORD-51');

        $this->assertCount(51, $lista);
        $this->assertSame(
            $this->mapearResolver($this->resolver->query('IT100', 'STD', 'JACQUARD')->get()),
            $lista
        );
    }

    public function test_con_salon_de_cat_filtra_como_el_resolver(): void
    {
        $this->sembrarBom('BOM-JAC', 'IT100', 'STD', 'JACQUARD');
        $this->sembrarBom('BOM-SMIT', 'IT100', 'STD', 'ITEMA');
        $this->sembrarCat('ORD-SALON', 'IT100', 'STD', 'JACQUARD', telarId: 205);

        $lista = $this->listaLmat('ORD-SALON');

        $this->assertSame(['BOM-JAC'], array_column($lista, 'bomId'));
        $this->assertSame(
            $this->mapearResolver($this->resolver->query('IT100', 'STD', 'JACQUARD')->get()),
            $lista
        );
    }

    public function test_sin_salon_usa_todos_los_alias_ax_del_resolver(): void
    {
        $this->sembrarBom('BOM-JAC', 'IT100', 'STD', 'JACQUARD');
        $this->sembrarBom('BOM-SMIT', 'IT100', 'STD', 'ITEMA');
        $this->sembrarCat('ORD-SIN-SALON', 'IT100', 'STD', null, telarId: 100);

        $lista = $this->listaLmat('ORD-SIN-SALON');

        $this->assertSame(['BOM-JAC', 'BOM-SMIT'], array_column($lista, 'bomId'));
        $this->assertSame(
            $this->mapearResolver($this->resolver->query('IT100', 'STD', null)->get()),
            $lista
        );
    }

    public function test_query_lmat_desde_ti_devuelve_el_mismo_shape_que_el_resolver(): void
    {
        $this->sembrarBom('BOM-JAC', 'IT200', 'FEL', 'JACQUARD', versiones: 2);

        $controller = $this->app->make(CatCodificacionController::class);
        $method = new \ReflectionMethod($controller, 'queryLmatDesdeTi');
        $lista = $method->invoke($controller, 'IT200', 'FEL', 'JACQUARD');

        $this->assertSame(
            $this->mapearResolver($this->resolver->query('IT200', 'FEL', 'JACQUARD')->get()),
            $lista
        );
        $this->assertSame([
            ['bomId' => 'BOM-JAC', 'bomName' => 'LISTA MATERIALES BOM-JAC'],
        ], $lista);
    }

    /**
     * @return list<array{bomId: string, bomName: string}>
     */
    private function listaLmat(string $orden): array
    {
        $usuario = new Usuario(['nombre' => 'Cat L.Mat']);
        $usuario->idusuario = 990007;

        $response = $this->actingAs($usuario)
            ->getJson(route('planeacion.codificacion.catcodificados-por-orden', $orden));

        $response->assertOk()->assertJsonPath('s', true);
        $lista = $response->json('d.listaLmat');
        $this->assertIsArray($lista);

        return $lista;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     * @return list<array{bomId: string, bomName: string}>
     */
    private function mapearResolver($rows): array
    {
        return $rows
            ->map(fn ($r) => [
                'bomId' => $r->bomId !== null ? (string) $r->bomId : '',
                'bomName' => $r->bomName !== null ? (string) $r->bomName : '',
            ])
            ->filter(fn (array $row) => $row['bomId'] !== '')
            ->unique('bomId')
            ->values()
            ->all();
    }

    private function sembrarCat(
        string $orden,
        string $itemId,
        string $inventSizeId,
        ?string $departamento,
        ?int $telarId = null
    ): void {
        CatCodificados::query()->create([
            'OrdenTejido' => $orden,
            'Departamento' => $departamento,
            'TelarId' => $telarId,
            'ItemId' => $itemId,
            'InventSizeId' => $inventSizeId,
            'Nombre' => 'Modelo '.$itemId,
            'BomId' => '',
            'BomName' => '',
        ]);
    }

    private function sembrarBom(
        string $bomId,
        string $itemId,
        string $inventSizeId,
        string $salon = 'JACQUARD',
        int $versiones = 1
    ): void {
        DB::connection('sqlsrv_ti')->table('BOMTABLE')->insert([
            'BOMID' => $bomId,
            'NAME' => 'LISTA MATERIALES '.$bomId,
            'ITEMGROUPID' => 'CRUDO',
            'TWINVENTSIZEID' => $inventSizeId,
            'TWSALON' => $salon,
            'Vigente' => 1,
        ]);
        for ($i = 0; $i < $versiones; $i++) {
            DB::connection('sqlsrv_ti')->table('BOMVERSION')->insert([
                'BOMID' => $bomId,
                'ITEMID' => $itemId.'-1',
            ]);
        }
    }
}
