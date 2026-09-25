<?php

declare(strict_types=1);

namespace Tests\Feature\Perf;

use App\Models\Sistema\SYSRoles;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/**
 * PERF-04: moduleNameForRoute() hacía hasta 3 queries a SYSRoles (una con LIKE '%x%') en cada
 * llamada. Ahora: memo por request + cache (prefijo modulos_v3, TTL 3600) invalidada al
 * escribir SYSRoles. Mismos resultados que antes.
 */
final class ModuleNameForRouteTest extends TestCase
{
    use UsesSqlsrvSqlite;

    private int $queries = 0;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('cache.default', 'array');
        $this->useSqlsrvSqlite();

        // LEN() de SQL Server (ignora espacios al final) para el ORDER BY de las búsquedas.
        DB::connection('sqlsrv')->getPdo()->sqliteCreateFunction('LEN', fn ($v) => mb_strlen(rtrim((string) $v)), 1);

        Schema::connection('sqlsrv')->create('SYSRoles', function (Blueprint $table) {
            $table->increments('idrol');
            $table->string('orden');
            $table->string('modulo');
            $table->string('Ruta')->nullable();
            $table->timestamps();
        });

        DB::connection('sqlsrv')->table('SYSRoles')->insert([
            ['orden' => '1', 'modulo' => 'Urdido', 'Ruta' => '/urdido'],
            ['orden' => '2', 'modulo' => 'Producción Urdido', 'Ruta' => '/urdido/modulo-produccion-urdido'],
            ['orden' => '3', 'modulo' => 'OT diarias', 'Ruta' => '/mecanicos/reportes/ot-diarias-v2'],
        ]);

        DB::connection('sqlsrv')->listen(function ($query) {
            if (str_contains($query->sql, 'SYSRoles')) {
                $this->queries++;
            }
        });
    }

    private function contarQueries(callable $fn): int
    {
        $this->queries = 0;
        $fn();

        return $this->queries;
    }

    private function nuevoRequest(): void
    {
        // Lo que hace un request nuevo: el contenedor no trae la memoria del anterior.
        app()->forgetInstance('permisos.modulo_ruta');
    }

    public function test_resuelve_igual_que_antes_exacta_prefijo_ultima_parte_y_nulo(): void
    {
        $this->assertSame('Producción Urdido', moduleNameForRoute('urdido/modulo-produccion-urdido'));
        $this->assertSame('Producción Urdido', moduleNameForRoute('/urdido/modulo-produccion'), 'Prefijo: la ruta más larga.');
        $this->assertSame('OT diarias', moduleNameForRoute('reportes/ot-diarias'), 'Por la última parte.');
        $this->assertNull(moduleNameForRoute('no/existe'));

        foreach (['urdido/modulo-produccion-urdido', '/urdido/modulo-produccion', 'reportes/ot-diarias', 'no/existe'] as $ruta) {
            $this->assertSame(buscarModuloPorRuta('/'.ltrim($ruta, '/')), moduleNameForRoute($ruta));
        }
    }

    public function test_cuenta_queries_antes_y_despues(): void
    {
        // Antes (sin caché): cada llamada paga sus queries; el peor caso, 3.
        $this->assertSame(3, $this->contarQueries(fn () => buscarModuloPorRuta('/reportes/ot-diarias')));
        $this->assertSame(3, $this->contarQueries(fn () => buscarModuloPorRuta('/no/existe')));

        // Después: la 1.ª llamada consulta; la 2.ª del mismo request y la de un request nuevo, 0.
        $this->assertSame(3, $this->contarQueries(fn () => moduleNameForRoute('reportes/ot-diarias')));
        $this->assertSame(0, $this->contarQueries(fn () => moduleNameForRoute('reportes/ot-diarias')));
        $this->nuevoRequest();
        $this->assertSame(0, $this->contarQueries(fn () => moduleNameForRoute('reportes/ot-diarias')));

        // El "no encontrado" también se cachea.
        $this->assertSame(3, $this->contarQueries(fn () => moduleNameForRoute('no/existe')));
        $this->nuevoRequest();
        $this->assertSame(0, $this->contarQueries(fn () => moduleNameForRoute('no/existe')));
    }

    public function test_usa_el_prefijo_de_modulo_service(): void
    {
        moduleNameForRoute('urdido');

        $this->assertStringStartsWith('modulos_v3_testing_', moduleNameForRouteCachePrefix());
        $this->assertNotNull(cache()->get(moduleNameForRouteCachePrefix().'_version'));
    }

    public function test_crear_editar_o_borrar_un_modulo_invalida_la_cache(): void
    {
        $this->assertNull(moduleNameForRoute('tejido/nuevo'));

        $modulo = SYSRoles::create(['orden' => '9', 'modulo' => 'Tejido nuevo', 'Ruta' => '/tejido/nuevo']);
        $this->nuevoRequest();
        $this->assertSame('Tejido nuevo', moduleNameForRoute('tejido/nuevo'));

        $modulo->update(['modulo' => 'Tejido renombrado']);
        $this->assertSame('Tejido renombrado', moduleNameForRoute('tejido/nuevo'), 'También en el mismo request.');

        $modulo->delete();
        $this->assertNull(moduleNameForRoute('tejido/nuevo'));
    }
}
