<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Planeacion\ProgramaTejido\LiberarOrdenesController;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/**
 * Contrato HTTP de obtenerFlogSugerido: el controller valida querystring
 * y delega la resolución a LiberarFlogSugeridoService.
 */
class LiberarFlogSugeridoTest extends TestCase
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

        $this->attachDboFlogTables();
    }

    private function attachDboFlogTables(): void
    {
        $conexion = DB::connection('sqlsrv_ti');
        if (! in_array('dbo', array_column($conexion->select('PRAGMA database_list'), 'name'), true)) {
            $conexion->statement("ATTACH DATABASE ':memory:' AS dbo");
        }

        $conexion->statement('CREATE TABLE IF NOT EXISTS dbo."TwFlogsTable" (
            "IDFLOG" TEXT, "NAMEPROYECT" TEXT, "CUSTNAME" TEXT, "ESTADOFLOG" INTEGER
        )');
        $conexion->statement('CREATE TABLE IF NOT EXISTS dbo."TwFlogsItemLine" (
            "IDFLOG" TEXT, "ITEMID" TEXT, "INVENTSIZEID" TEXT
        )');
    }

    private function sembrarFlog(string $idFlog, string $itemId, string $inventSizeId, int $estado, string $nombre): void
    {
        DB::connection('sqlsrv_ti')->table('dbo.TwFlogsTable')->insert([
            'IDFLOG' => $idFlog,
            'NAMEPROYECT' => $nombre,
            'CUSTNAME' => 'CLIENTE',
            'ESTADOFLOG' => $estado,
        ]);
        DB::connection('sqlsrv_ti')->table('dbo.TwFlogsItemLine')->insert([
            'IDFLOG' => $idFlog,
            'ITEMID' => $itemId,
            'INVENTSIZEID' => $inventSizeId,
        ]);
    }

    private function flog(array $query)
    {
        $request = Request::create(
            '/planeacion/programa-tejido/liberar-ordenes/flog-sugerido',
            'GET',
            $query
        );

        return (new LiberarOrdenesController)->obtenerFlogSugerido($request);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function flogRoutes(): array
    {
        return [
            'programa-tejido' => ['programa-tejido.liberar-ordenes.flog'],
            'muestras' => ['muestras.liberar-ordenes.flog'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('flogRoutes')]
    public function test_ruta_flog_sugerido_existe_y_exige_auth(string $name): void
    {
        $route = Route::getRoutes()->getByName($name);

        $this->assertNotNull($route, "No se encontro la ruta [{$name}].");
        $this->assertInstanceOf(IlluminateRoute::class, $route);
        $this->assertContains('auth', $route->gatherMiddleware(), "Middleware auth faltante en [{$name}].");
        $this->assertSame(LiberarOrdenesController::class, $route->getControllerClass());
        $this->assertSame('obtenerFlogSugerido', $route->getActionMethod());
    }

    public function test_sin_item_o_talla_devuelve_422(): void
    {
        $sinItem = $this->flog(['inventSizeId' => 'STD'])->getData(true);
        $this->assertFalse($sinItem['success']);
        $this->assertSame(422, $this->flog(['inventSizeId' => 'STD'])->getStatusCode());

        $sinTalla = $this->flog(['itemId' => 'IT100'])->getData(true);
        $this->assertFalse($sinTalla['success']);
        $this->assertStringContainsString('obligatorios', $sinTalla['message']);
    }

    public function test_elige_el_flog_vigente_de_mayor_numero_final(): void
    {
        $this->sembrarFlog('CE-99', 'IT100', 'STD', 3, 'VIEJO');
        $this->sembrarFlog('CE-100', 'IT100', 'STD', 5, 'NUEVO');
        $this->sembrarFlog('CE-200', 'IT100', 'STD', 1, 'CERRADO');

        $response = $this->flog(['itemId' => 'IT100', 'inventSizeId' => 'STD']);

        $this->assertSame(200, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertTrue($data['success']);
        $this->assertSame('CE-100', $data['data']['flogsId']);
        $this->assertSame('NUEVO', $data['data']['nombreProyecto']);
    }

    public function test_sin_coincidencia_devuelve_data_null(): void
    {
        $this->sembrarFlog('CE-1', 'IT200', 'FEL', 3, 'OTRO');

        $data = $this->flog(['itemId' => 'IT100', 'inventSizeId' => 'STD'])->getData(true);

        $this->assertTrue($data['success']);
        $this->assertNull($data['data']);
    }

    public function test_item_con_relleno_en_ax_sigue_empatando(): void
    {
        $this->sembrarFlog('CE-8', '  IT100  ', ' STD ', 4, 'RELLENO');

        $data = $this->flog(['itemId' => 'IT100', 'inventSizeId' => 'STD'])->getData(true);

        $this->assertTrue($data['success']);
        $this->assertSame('CE-8', $data['data']['flogsId']);
    }

    public function test_ax_caido_devuelve_500(): void
    {
        DB::purge('sqlsrv_ti');
        config()->set('database.connections.sqlsrv_ti', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        DB::purge('sqlsrv_ti');

        $response = $this->flog(['itemId' => 'IT100', 'inventSizeId' => 'STD']);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertFalse($response->getData(true)['success']);
        $this->assertStringContainsString('Error al buscar el flog', $response->getData(true)['message']);
    }
}
