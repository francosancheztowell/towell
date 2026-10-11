<?php

declare(strict_types=1);

namespace Tests\Feature\Mejoras;

use App\Http\Controllers\Planeacion\CatLMat\CatLMatController;
use App\Http\Controllers\Planeacion\Utilerias\FinalizarOrdenesController;
use App\Http\Controllers\Planeacion\Utilerias\MoverOrdenesController;
use App\Models\Planeacion\Catalogos\CatCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use Carbon\Carbon;
use Illuminate\Database\Connection;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/**
 * Rama claude/mejora-query-planeacion-urdeng: búsquedas de CatCodificados por OrdenTejido con
 * el scope ordenesTejido (CAST(? AS varchar)) y Mover Órdenes con una sola lectura por telar.
 * Fija la conducta que tenían antes: mismas filas, mismo contrato JSON, mismas posiciones.
 */
class QueryPlanTejidoPlaneacionTest extends TestCase
{
    use UsesSqlsrvSqlite;

    protected function setUp(): void
    {
        parent::setUp();
        // ISNULL(x, y) de SQL Server es palabra reservada en sqlite (no se puede registrar como
        // función): solo en este test, la conexión sqlite reescribe ISNULL( a IFNULL( para el bump
        // de Posicion de Mover Órdenes.
        Connection::resolverFor('sqlite', fn ($pdo, $db, $prefix, $config) => new class($pdo, $db, $prefix, $config) extends SQLiteConnection
        {
            public function affectingStatement($query, $bindings = [])
            {
                return parent::affectingStatement(str_replace('ISNULL(', 'IFNULL(', $query), $bindings);
            }
        });
        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');
        config()->set('planeacion.programa_tejido_table', 'ReqProgramaTejido');
        $this->createTablaDesdeModelo(ReqProgramaTejido::class);
        $this->createTablaDesdeModelo(CatCodificados::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Connection::resolverFor('sqlite', fn ($pdo, $db, $prefix, $config) => new SQLiteConnection($pdo, $db, $prefix, $config));
    }

    private function programa(array $datos): int
    {
        return (int) DB::connection('sqlsrv')->table('ReqProgramaTejido')->insertGetId($datos);
    }

    public function test_finalizar_ordenes_cruza_fecha_tejido_por_orden_con_el_scope(): void
    {
        $this->programa(['NoProduccion' => '36160', 'NoTelarId' => '201', 'SalonTejidoId' => 'JACQUARD', 'Posicion' => 1]);
        $this->programa(['NoProduccion' => '36161', 'NoTelarId' => '201', 'SalonTejidoId' => 'JACQUARD', 'Posicion' => 2]);
        $this->programa(['NoProduccion' => '36999', 'NoTelarId' => '202', 'SalonTejidoId' => 'JACQUARD', 'Posicion' => 1]);
        DB::connection('sqlsrv')->table('CatCodificados')->insert([
            ['OrdenTejido' => '36160', 'FechaTejido' => '2026-09-01 00:00:00'],
            ['OrdenTejido' => '36999', 'FechaTejido' => '2026-09-05 00:00:00'],
        ]);

        $request = Request::create('/planeacion/utileria/finalizar/ordenes', 'GET', ['telar' => '201', 'salon' => 'JACQUARD']);
        $json = app(FinalizarOrdenesController::class)->getOrdenesByTelar($request)->getData(true);

        $this->assertTrue($json['success']);
        $porOrden = array_column($json['ordenes'], 'fechaCambio', 'noOrden');
        $this->assertSame(['36160' => '01/09/2026', '36161' => ''], $porOrden);
    }

    public function test_finalizar_ordenes_sin_cat_codificados_deja_fecha_vacia(): void
    {
        $this->programa(['NoProduccion' => '40001', 'NoTelarId' => '201', 'SalonTejidoId' => 'JACQUARD', 'Posicion' => 1]);

        $request = Request::create('/x', 'GET', ['telar' => '201', 'salon' => 'JACQUARD']);
        $json = app(FinalizarOrdenesController::class)->getOrdenesByTelar($request)->getData(true);

        $this->assertTrue($json['success']);
        $this->assertSame('', $json['ordenes'][0]['fechaCambio']);
    }

    public function test_lmat_registro_por_orden_recorta_espacios_y_encuentra_la_orden(): void
    {
        DB::connection('sqlsrv')->table('CatCodificados')->insert([
            ['OrdenTejido' => '36160', 'TelarId' => '201'],
            ['OrdenTejido' => '36161', 'TelarId' => '202'],
        ]);

        $ok = app(CatLMatController::class)->getRegistroCatCodificadosPorOrden(' 36160 ');
        $this->assertSame(200, $ok->getStatusCode());
        $this->assertSame('201', (string) $ok->getData(true)['data']['TelarId']);

        $no = app(CatLMatController::class)->getRegistroCatCodificadosPorOrden('99999');
        $this->assertSame(404, $no->getStatusCode());
        $this->assertFalse($no->getData(true)['success']);
    }

    /** Scope con '' no debe traer filas con OrdenTejido NULL (where('OrdenTejido', '') tampoco las traía). */
    public function test_scope_con_orden_vacia_no_trae_ordenes_nulas(): void
    {
        DB::connection('sqlsrv')->table('CatCodificados')->insert([
            ['OrdenTejido' => null, 'TelarId' => '201'],
            ['OrdenTejido' => '36160', 'TelarId' => '201'],
        ]);

        $this->assertSame(0, CatCodificados::query()->ordenesTejido([''])->count());
        $this->assertSame(1, CatCodificados::query()->ordenesTejido(['36160'])->count());
    }

    /**
     * Mover Órdenes: una sola lectura con lock para el telar. Las posiciones quedan en el orden
     * pedido (ids como string, como llegan del JSON), los del telar que no se mueven siguen con
     * el bump y un id que ya no existe se salta sin romper el resto.
     */
    public function test_mover_ordenes_asigna_posiciones_en_el_orden_pedido_con_una_lectura(): void
    {
        $a = $this->programa(['NoProduccion' => 'A', 'NoTelarId' => '201', 'SalonTejidoId' => 'JACQUARD', 'Posicion' => 1]);
        $b = $this->programa(['NoProduccion' => 'B', 'NoTelarId' => '201', 'SalonTejidoId' => 'JACQUARD', 'Posicion' => 2]);
        $c = $this->programa(['NoProduccion' => 'C', 'NoTelarId' => '201', 'SalonTejidoId' => 'JACQUARD', 'Posicion' => 3]);
        $fuera = $this->programa(['NoProduccion' => 'Z', 'NoTelarId' => '201', 'SalonTejidoId' => 'JACQUARD', 'Posicion' => 4]);
        $otroTelar = $this->programa(['NoProduccion' => 'Y', 'NoTelarId' => '202', 'SalonTejidoId' => 'JACQUARD', 'Posicion' => 1]);

        $estado = [];
        foreach ([$a, $b, $c] as $id) {
            $estado[$id] = ['salon' => 'JACQUARD', 'telar' => '201', 'enProceso' => false];
        }
        $telaresAfectados = [];
        $idsAfectados = [];
        $idsCambioSalon = [];

        $metodo = new ReflectionMethod(MoverOrdenesController::class, 'procesarMovimientoPorTelar');
        $selects = 0;
        DB::connection('sqlsrv')->listen(function ($q) use (&$selects): void {
            if (stripos(ltrim($q->sql), 'select') === 0 && stripos($q->sql, 'ReqProgramaTejido') !== false) {
                $selects++;
            }
        });
        $args = [
            ['salon' => 'JACQUARD', 'telar' => '201', 'ordenes' => [(string) $c, (string) $a, '999999', (string) $b]],
            'ReqProgramaTejido',
            Carbon::parse('2026-10-10 08:00:00'),
            $estado,
            &$telaresAfectados,
            &$idsAfectados,
            &$idsCambioSalon,
        ];
        $metodo->invokeArgs(app(MoverOrdenesController::class), $args);
        $selectsDelMovimiento = $selects;

        $pos = DB::connection('sqlsrv')->table('ReqProgramaTejido')->pluck('Posicion', 'Id')->map(fn ($p) => (int) $p)->all();
        $this->assertSame(1, $pos[$c]);
        $this->assertSame(2, $pos[$a]);
        // índice 2 es el id inexistente: B queda en 4, como antes (la posición es el índice + 1).
        $this->assertSame(4, $pos[$b]);
        $this->assertSame(10004, $pos[$fuera], 'el que no se mueve conserva el bump');
        $this->assertSame(1, $pos[$otroTelar], 'otro telar no se toca');
        $this->assertSame([$c, $a, $b], $idsAfectados['JACQUARD|201']);
        $this->assertSame([], $idsCambioSalon);
        $this->assertSame(1, $selectsDelMovimiento, 'una sola lectura de ReqProgramaTejido para el telar');
    }
}
