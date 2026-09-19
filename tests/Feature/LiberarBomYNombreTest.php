<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Planeacion\ProgramaTejido\LiberarOrdenesController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/**
 * Contrato HTTP de obtenerBomYNombre: el controller solo parsea querystring
 * y delega la resolución a LiberarBomCrudoResolver.
 */
class LiberarBomYNombreTest extends TestCase
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

        $this->sembrarBom('TEJ PROPIA', 'IT100', 'STD');
        $this->sembrarBom('ESTAND JS 1', 'IT100', 'STD');
        $this->sembrarBom('BOM-IT200', 'IT200', 'FEL');
    }

    protected function tearDown(): void
    {
        foreach (['BOMTABLE', 'BOMVERSION'] as $tabla) {
            Schema::connection('sqlsrv_ti')->dropIfExists($tabla);
        }
        parent::tearDown();
    }

    private function sembrarBom(string $bomId, string $itemId, string $inventSizeId, string $salon = 'JACQUARD'): void
    {
        DB::connection('sqlsrv_ti')->table('BOMTABLE')->insert([
            'BOMID' => $bomId,
            'NAME' => 'LISTA '.$bomId,
            'ITEMGROUPID' => 'CRUDO',
            'TWINVENTSIZEID' => $inventSizeId,
            'TWSALON' => $salon,
            'Vigente' => 1,
        ]);
        DB::connection('sqlsrv_ti')->table('BOMVERSION')->insert([
            'BOMID' => $bomId,
            'ITEMID' => $itemId.'-1',
        ]);
    }

    private function bom(array $query)
    {
        $request = Request::create(
            '/planeacion/programa-tejido/liberar-ordenes/bom-sugerencias',
            'GET',
            $query
        );

        return (new LiberarOrdenesController)->obtenerBomYNombre($request);
    }

    public function test_ruta_bom_sugerencias_existe_y_exige_auth(): void
    {
        $route = Route::getRoutes()->getByName('programa-tejido.liberar-ordenes.bom');

        $this->assertNotNull($route);
        $this->assertInstanceOf(IlluminateRoute::class, $route);
        $this->assertContains('auth', $route->gatherMiddleware());
        $this->assertSame(LiberarOrdenesController::class, $route->getControllerClass());
    }

    public function test_sin_item_id_devuelve_lista_vacia(): void
    {
        $data = $this->bom([])->getData(true);

        $this->assertTrue($data['success']);
        $this->assertSame([], $data['data']);
    }

    public function test_busqueda_individual_devuelve_lmat_del_item(): void
    {
        $data = $this->bom([
            'itemId' => 'IT100',
            'inventSizeId' => 'STD',
            'salon' => 'JACQUARD',
        ])->getData(true);

        $this->assertTrue($data['success']);
        $ids = collect($data['data'])->pluck('bomId')->all();
        $this->assertContains('TEJ PROPIA', $ids);
        $this->assertContains('ESTAND JS 1', $ids);
    }

    public function test_combinations_omite_estand_y_agrupa_por_item_talla(): void
    {
        $data = $this->bom([
            'combinations' => 'IT100::STD,IT200::FEL',
        ])->getData(true);

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('IT100|STD', $data['data']);
        $this->assertArrayHasKey('IT200|FEL', $data['data']);
        $this->assertCount(1, $data['data']['IT100|STD']);
        $this->assertSame('TEJ PROPIA', $data['data']['IT100|STD'][0]['bomId']);
        $this->assertSame('BOM-IT200', $data['data']['IT200|FEL'][0]['bomId']);
    }

    public function test_combinations_vacias_o_mal_formadas_devuelven_vacio(): void
    {
        $vacio = $this->bom(['combinations' => '   ,  ,'])->getData(true);
        $this->assertTrue($vacio['success']);
        $this->assertSame([], $vacio['data']);
    }

    public function test_term_filtra_por_bomid(): void
    {
        $data = $this->bom([
            'itemId' => 'IT100',
            'inventSizeId' => 'STD',
            'salon' => 'JACQUARD',
            'term' => 'ESTAND',
        ])->getData(true);

        $ids = collect($data['data'])->pluck('bomId')->all();
        $this->assertSame(['ESTAND JS 1'], $ids);
    }
}
