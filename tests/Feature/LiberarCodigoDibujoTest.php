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
 * Contrato HTTP de obtenerCodigoDibujo: el controller valida querystring
 * y delega la resolución a LiberarCodigoDibujoResolver.
 */
class LiberarCodigoDibujoTest extends TestCase
{
    use UsesSqlsrvSqlite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');

        Schema::connection('sqlsrv')->create('CatCodificados', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('ItemId')->nullable();
            $table->string('InventSizeId')->nullable();
            $table->string('Departamento')->nullable();
            $table->string('CodigoDibujo')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('CatCodificados');
        parent::tearDown();
    }

    private function sembrar(string $itemId, string $inventSizeId, string $departamento, string $codigo): void
    {
        DB::connection('sqlsrv')->table('CatCodificados')->insert([
            'ItemId' => $itemId,
            'InventSizeId' => $inventSizeId,
            'Departamento' => $departamento,
            'CodigoDibujo' => $codigo,
        ]);
    }

    private function codigo(array $query)
    {
        $request = Request::create(
            '/planeacion/programa-tejido/liberar-ordenes/codigo-dibujo',
            'GET',
            $query
        );

        return (new LiberarOrdenesController)->obtenerCodigoDibujo($request);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function codigoDibujoRoutes(): array
    {
        return [
            'programa-tejido' => ['programa-tejido.liberar-ordenes.codigo-dibujo'],
            'muestras' => ['muestras.liberar-ordenes.codigo-dibujo'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('codigoDibujoRoutes')]
    public function test_ruta_codigo_dibujo_existe_y_exige_auth(string $name): void
    {
        $route = Route::getRoutes()->getByName($name);

        $this->assertNotNull($route, "No se encontro la ruta [{$name}].");
        $this->assertInstanceOf(IlluminateRoute::class, $route);
        $this->assertContains('auth', $route->gatherMiddleware(), "Middleware auth faltante en [{$name}].");
        $this->assertSame(LiberarOrdenesController::class, $route->getControllerClass());
        $this->assertSame('obtenerCodigoDibujo', $route->getActionMethod());
    }

    public function test_sin_combinations_devuelve_mapa_vacio(): void
    {
        $data = $this->codigo([])->getData(true);

        $this->assertTrue($data['success']);
        $this->assertSame([], $data['data']);
    }

    public function test_elige_el_codigo_del_salon_y_omite_sin_coincidencia(): void
    {
        $this->sembrar('IT100', 'STD', 'JACQUARD', 'DIB-JAC');
        $this->sembrar('IT100', 'STD', 'SMIT', 'DIB-SMIT');
        $this->sembrar('IT200', 'FEL', 'JACQUARD', 'DIB-200');

        $response = $this->codigo([
            'combinations' => 'IT100::STD::JACQUARD,IT100::STD::SMIT,IT999::XL::JACQUARD',
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertTrue($data['success']);
        $this->assertSame('DIB-JAC', $data['data']['IT100|STD|JACQUARD']);
        $this->assertSame('DIB-SMIT', $data['data']['IT100|STD|SMIT']);
        $this->assertArrayNotHasKey('IT999|XL|JACQUARD', $data['data']);
    }

    public function test_formato_legado_de_un_dos_puntos_sigue_resolviendo_por_item_y_talla(): void
    {
        $this->sembrar('IT100', 'STD', 'SMIT', 'DIB-LEGADO');

        $data = $this->codigo(['combinations' => 'IT100:STD'])->getData(true);

        $this->assertTrue($data['success']);
        $this->assertSame('DIB-LEGADO', $data['data']['IT100|STD|']);
    }

    public function test_catalogo_caido_devuelve_500(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('CatCodificados');

        $response = $this->codigo(['combinations' => 'IT100::STD::JACQUARD']);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertFalse($response->getData(true)['success']);
        $this->assertStringContainsString('Código de Dibujo', $response->getData(true)['message']);
    }
}
