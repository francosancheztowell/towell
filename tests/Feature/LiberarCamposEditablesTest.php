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
 * Contrato HTTP de guardarCamposEditables: el controller sella auditoría
 * y delega validación/persistencia a LiberarCamposEditablesService.
 * Rutas espejo: programa-tejido + muestras.
 */
class LiberarCamposEditablesTest extends TestCase
{
    use UsesSqlsrvSqlite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');
        config()->set('planeacion.programa_tejido_table', 'ReqProgramaTejido');

        $schema = Schema::connection('sqlsrv');
        $schema->create('ReqProgramaTejido', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('NoProduccion')->nullable();
            $table->string('SalonTejidoId')->nullable();
            $table->string('NoTelarId')->nullable();
            $table->string('ItemId')->nullable();
            $table->string('InventSizeId')->nullable();
            $table->string('NombreProducto')->nullable();
            $table->float('Densidad')->nullable();
            $table->float('TotalRollos')->nullable();
            $table->float('MtsRollo')->nullable();
            $table->float('PzasRollo')->nullable();
            $table->float('TotalPzas')->nullable();
            $table->integer('Repeticiones')->nullable();
            $table->integer('SaldoMarbete')->nullable();
            $table->integer('NoTiras')->nullable();
            $table->string('CombinaTram')->nullable();
            $table->boolean('CreaProd')->nullable();
            $table->dateTime('CreatedAt')->nullable();
            $table->dateTime('UpdatedAt')->nullable();
        });

        $schema->create('CatCodificados', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('OrdenTejido')->nullable();
            $table->string('TelarId')->nullable();
            $table->float('Densidad')->nullable();
            $table->float('TotalRollos')->nullable();
            $table->string('CombinaTram')->nullable();
            $table->integer('NoTiras')->nullable();
            $table->boolean('CreaProd')->nullable();
        });

        $schema->create('ReqProgramaTejidoLine', function (Blueprint $table) {
            $table->increments('Id');
            $table->integer('ProgramaId')->nullable();
            $table->date('Fecha')->nullable();
        });
    }

    protected function tearDown(): void
    {
        foreach (['ReqProgramaTejidoLine', 'CatCodificados', 'ReqProgramaTejido'] as $tabla) {
            Schema::connection('sqlsrv')->dropIfExists($tabla);
        }
        parent::tearDown();
    }

    private function sembrarRegistro(array $overrides = []): int
    {
        return (int) DB::connection('sqlsrv')->table('ReqProgramaTejido')->insertGetId(array_merge([
            'NoProduccion' => '77001',
            'SalonTejidoId' => 'JACQUARD',
            'NoTelarId' => '201',
            'ItemId' => 'IT100',
            'InventSizeId' => 'STD',
            'NombreProducto' => 'MB-ARIA',
            'Densidad' => 0.1,
            'NoTiras' => 3,
            'CreaProd' => 0,
        ], $overrides));
    }

    private function guardar(array $payload)
    {
        $request = Request::create(
            '/planeacion/programa-tejido/liberar-ordenes/guardar-campos',
            'POST',
            $payload
        );

        return (new LiberarOrdenesController)->guardarCamposEditables($request);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function camposRoutes(): array
    {
        return [
            'programa-tejido' => ['programa-tejido.liberar-ordenes.guardar-campos'],
            'muestras' => ['muestras.liberar-ordenes.guardar-campos'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('camposRoutes')]
    public function test_ruta_guardar_campos_existe_y_exige_auth(string $name): void
    {
        $route = Route::getRoutes()->getByName($name);

        $this->assertNotNull($route, "No se encontro la ruta [{$name}].");
        $this->assertInstanceOf(IlluminateRoute::class, $route);
        $this->assertContains('auth', $route->gatherMiddleware(), "Middleware auth faltante en [{$name}].");
        $this->assertSame(LiberarOrdenesController::class, $route->getControllerClass());
        $this->assertSame('guardarCamposEditables', $route->getActionMethod());
    }

    public function test_payload_invalido_devuelve_422_con_errors(): void
    {
        $response = $this->guardar([
            'id' => 'no-es-entero',
            'field' => 'CampoInventado',
        ]);

        $this->assertSame(422, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertFalse($data['success']);
        $this->assertSame('Datos inválidos.', $data['message']);
        $this->assertArrayHasKey('errors', $data);
        $this->assertArrayHasKey('id', $data['errors']);
        $this->assertArrayHasKey('field', $data['errors']);
    }

    public function test_id_inexistente_falla_validacion_exists(): void
    {
        $response = $this->guardar([
            'id' => 99999,
            'field' => 'Densidad',
            'value' => 0.5,
        ]);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertFalse($response->getData(true)['success']);
        $this->assertSame('Datos inválidos.', $response->getData(true)['message']);
    }

    public function test_no_tiras_fuera_de_karl_mayer_devuelve_422(): void
    {
        $id = $this->sembrarRegistro(['SalonTejidoId' => 'JACQUARD', 'NoTelarId' => '201']);

        $response = $this->guardar([
            'id' => $id,
            'field' => 'NoTiras',
            'value' => 6,
        ]);

        $this->assertSame(422, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertFalse($data['success']);
        $this->assertSame('Las tiras solo se pueden editar en órdenes de Karl Mayer.', $data['message']);
        $this->assertSame(3, (int) DB::connection('sqlsrv')->table('ReqProgramaTejido')->where('Id', $id)->value('NoTiras'));
    }

    public function test_no_tiras_cero_en_karl_mayer_devuelve_422(): void
    {
        $id = $this->sembrarRegistro([
            'SalonTejidoId' => 'KARL MAYER',
            'NoTelarId' => '401',
            'NoTiras' => 2,
        ]);

        $response = $this->guardar([
            'id' => $id,
            'field' => 'NoTiras',
            'value' => 0,
        ]);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertFalse($response->getData(true)['success']);
        $this->assertSame('Las tiras deben ser mayores a cero.', $response->getData(true)['message']);
        $this->assertSame(2, (int) DB::connection('sqlsrv')->table('ReqProgramaTejido')->where('Id', $id)->value('NoTiras'));
    }

    public function test_densidad_sincroniza_cat_codificados_y_no_toca_crea_prod(): void
    {
        $id = $this->sembrarRegistro(['CreaProd' => 0]);
        DB::connection('sqlsrv')->table('CatCodificados')->insert([
            'OrdenTejido' => '77001',
            'TelarId' => '201',
            'Densidad' => 0.1,
            'CreaProd' => 0,
        ]);

        $response = $this->guardar([
            'id' => $id,
            'field' => 'Densidad',
            'value' => 0.4321,
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->getData(true)['success']);
        $this->assertSame('Campo actualizado correctamente.', $response->getData(true)['message']);
        $this->assertEqualsWithDelta(
            0.4321,
            (float) DB::connection('sqlsrv')->table('ReqProgramaTejido')->where('Id', $id)->value('Densidad'),
            0.0001
        );
        $this->assertEqualsWithDelta(
            0.4321,
            (float) DB::connection('sqlsrv')->table('CatCodificados')->where('OrdenTejido', '77001')->value('Densidad'),
            0.0001
        );
        $this->assertSame(0, (int) DB::connection('sqlsrv')->table('ReqProgramaTejido')->where('Id', $id)->value('CreaProd'));
        $this->assertSame(0, (int) DB::connection('sqlsrv')->table('CatCodificados')->where('OrdenTejido', '77001')->value('CreaProd'));
    }

    public function test_combina_trama_mapea_a_combina_tram(): void
    {
        $id = $this->sembrarRegistro();
        DB::connection('sqlsrv')->table('CatCodificados')->insert([
            'OrdenTejido' => '77001',
            'TelarId' => '201',
            'CombinaTram' => 'VIEJO',
        ]);

        $response = $this->guardar([
            'id' => $id,
            'field' => 'CombinaTrama',
            'value' => '  NUEVO-C1  ',
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->getData(true)['success']);
        $this->assertSame(
            'NUEVO-C1',
            DB::connection('sqlsrv')->table('ReqProgramaTejido')->where('Id', $id)->value('CombinaTram')
        );
        $this->assertSame(
            'NUEVO-C1',
            DB::connection('sqlsrv')->table('CatCodificados')->where('OrdenTejido', '77001')->value('CombinaTram')
        );
    }

    public function test_total_rollos_redondea_hacia_arriba(): void
    {
        $id = $this->sembrarRegistro(['TotalRollos' => 1]);
        DB::connection('sqlsrv')->table('CatCodificados')->insert([
            'OrdenTejido' => '77001',
            'TelarId' => '201',
            'TotalRollos' => 1,
        ]);

        $response = $this->guardar([
            'id' => $id,
            'field' => 'TotalRollos',
            'value' => 3.1,
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->getData(true)['success']);
        $this->assertEqualsWithDelta(
            4.0,
            (float) DB::connection('sqlsrv')->table('ReqProgramaTejido')->where('Id', $id)->value('TotalRollos'),
            0.0001
        );
        $this->assertEqualsWithDelta(
            4.0,
            (float) DB::connection('sqlsrv')->table('CatCodificados')->where('OrdenTejido', '77001')->value('TotalRollos'),
            0.0001
        );
    }
}
