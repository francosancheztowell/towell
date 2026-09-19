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
 * Contrato HTTP de tipo-hilo y opciones-hilos: el controller valida
 * querystring y delega a LiberarHilosCatalogo.
 */
class LiberarHilosCatalogoTest extends TestCase
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

        Schema::connection('sqlsrv_ti')->create('INVENTTABLE', function (Blueprint $table) {
            $table->string('ITEMID');
            $table->string('TwTipoHiloId')->nullable();
        });
        Schema::connection('sqlsrv_ti')->create('TwTipoHilo', function (Blueprint $table) {
            $table->string('TipoHilo')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Schema::connection('sqlsrv_ti')->dropIfExists('INVENTTABLE');
        Schema::connection('sqlsrv_ti')->dropIfExists('TwTipoHilo');
        parent::tearDown();
    }

    private function sembrarInvent(string $itemId, ?string $tipoHilo): void
    {
        DB::connection('sqlsrv_ti')->table('INVENTTABLE')->insert([
            'ITEMID' => $itemId,
            'TwTipoHiloId' => $tipoHilo,
        ]);
    }

    private function tipoHilo(array $query)
    {
        $request = Request::create(
            '/planeacion/programa-tejido/liberar-ordenes/tipo-hilo',
            'GET',
            $query
        );

        return (new LiberarOrdenesController)->obtenerTipoHilo($request);
    }

    private function opcionesHilos()
    {
        return (new LiberarOrdenesController)->obtenerOpcionesHilos();
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function hilosRoutes(): array
    {
        return [
            'programa-tejido tipo-hilo' => ['programa-tejido.liberar-ordenes.tipo-hilo', 'obtenerTipoHilo'],
            'muestras tipo-hilo' => ['muestras.liberar-ordenes.tipo-hilo', 'obtenerTipoHilo'],
            'programa-tejido opciones-hilos' => ['programa-tejido.liberar-ordenes.opciones-hilos', 'obtenerOpcionesHilos'],
            'muestras opciones-hilos' => ['muestras.liberar-ordenes.opciones-hilos', 'obtenerOpcionesHilos'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('hilosRoutes')]
    public function test_ruta_hilos_existe_y_exige_auth(string $name, string $method): void
    {
        $route = Route::getRoutes()->getByName($name);

        $this->assertNotNull($route, "No se encontro la ruta [{$name}].");
        $this->assertInstanceOf(IlluminateRoute::class, $route);
        $this->assertContains('auth', $route->gatherMiddleware(), "Middleware auth faltante en [{$name}].");
        $this->assertSame(LiberarOrdenesController::class, $route->getControllerClass());
        $this->assertSame($method, $route->getActionMethod());
    }

    public function test_sin_item_ids_devuelve_mapa_vacio(): void
    {
        $response = $this->tipoHilo([]);

        $this->assertSame(200, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertTrue($data['success']);
        $this->assertSame([], $data['data']);
    }

    public function test_mapea_item_sin_sufijo_al_tipo_de_inventtable(): void
    {
        $this->sembrarInvent('IT100-1', 'ALGODON');
        $this->sembrarInvent('3-100-1', 'POLIESTER');
        $this->sembrarInvent('IT999-1', 'OTRO');

        $response = $this->tipoHilo(['itemIds' => 'IT100, 3-100']);

        $this->assertSame(200, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertTrue($data['success']);
        $this->assertSame('ALGODON', $data['data']['IT100']);
        $this->assertSame('POLIESTER', $data['data']['3-100']);
        $this->assertArrayNotHasKey('IT999', $data['data']);
    }

    public function test_item_sin_coincidencia_no_aparece_en_el_mapa(): void
    {
        $this->sembrarInvent('IT200-1', 'VISCOSA');

        $data = $this->tipoHilo(['itemIds' => 'IT100'])->getData(true);

        $this->assertTrue($data['success']);
        $this->assertSame([], $data['data']);
    }

    public function test_ax_caido_en_tipo_hilo_devuelve_500(): void
    {
        Schema::connection('sqlsrv_ti')->dropIfExists('INVENTTABLE');

        $response = $this->tipoHilo(['itemIds' => 'IT100']);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertFalse($response->getData(true)['success']);
        $this->assertSame('Error al obtener Tipo Hilo.', $response->getData(true)['message']);
    }

    public function test_opciones_devuelve_tipos_unicos_ordenados_y_sin_vacios(): void
    {
        DB::connection('sqlsrv_ti')->table('TwTipoHilo')->insert([
            ['TipoHilo' => '  VISCOSA  '],
            ['TipoHilo' => 'ALGODON'],
            ['TipoHilo' => 'ALGODON'],
            ['TipoHilo' => ''],
            ['TipoHilo' => '   '],
            ['TipoHilo' => 'POLIESTER'],
        ]);

        $response = $this->opcionesHilos();

        $this->assertSame(200, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertTrue($data['success']);
        $this->assertSame(['ALGODON', 'POLIESTER', 'VISCOSA'], $data['data']);
    }

    public function test_ax_caido_en_opciones_devuelve_500(): void
    {
        Schema::connection('sqlsrv_ti')->dropIfExists('TwTipoHilo');

        $response = $this->opcionesHilos();

        $this->assertSame(500, $response->getStatusCode());
        $this->assertFalse($response->getData(true)['success']);
        $this->assertSame('Error al obtener opciones de hilos.', $response->getData(true)['message']);
    }
}
