<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/**
 * El guard de Tests\TestCase::setUp() que impide abrir un SQL Server real desde un test.
 * Si esto se rompe, la suite vuelve a colgarse ~15s por intento sin red (o a escribir en
 * produccion con red).
 */
final class SqlServerRealGuardTest extends TestCase
{
    use UsesSqlsrvSqlite;

    /** Tiempo maximo para fallar: un intento TCP real tarda 15s, esto debe ser instantaneo. */
    private const LIMITE_SEGUNDOS = 1.0;

    /** @return array<string, array{string}> */
    public static function conexionesSqlServer(): array
    {
        return [
            'towell' => ['sqlsrv'],
            'ti pro' => ['sqlsrv_ti'],
            'tow pro' => ['sqlsrv_tow_pro'],
            'tow tow' => ['sqlsrv_tow_tow'],
            'reportes towell' => ['sqlsrv_Reportes_Towell'],
        ];
    }

    public function test_cubre_exactamente_las_conexiones_sqlsrv_de_la_config(): void
    {
        $enConfig = array_keys(array_filter(
            config('database.connections'),
            fn (array $config) => ($config['driver'] ?? null) === 'sqlsrv'
        ));

        // Si alguien agrega un SQL Server a config/database.php, este test obliga a sumarlo
        // al data provider y con eso a probar que el guard lo bloquea.
        $this->assertEqualsCanonicalizing(array_column(self::conexionesSqlServer(), 0), $enConfig);
    }

    #[DataProvider('conexionesSqlServer')]
    public function test_abrir_el_pdo_falla_al_instante_con_el_nombre_de_la_conexion(string $conexion): void
    {
        $this->assertFallaRapido(
            fn () => DB::connection($conexion)->getPdo(),
            RuntimeException::class,
            "SQL Server real '{$conexion}'"
        );
    }

    #[DataProvider('conexionesSqlServer')]
    public function test_el_pdo_de_lectura_tambien_esta_bloqueado(string $conexion): void
    {
        $this->assertFallaRapido(
            fn () => DB::connection($conexion)->getReadPdo(),
            RuntimeException::class,
            "SQL Server real '{$conexion}'"
        );
    }

    #[DataProvider('conexionesSqlServer')]
    public function test_una_consulta_falla_con_query_exception_que_explica_la_causa(string $conexion): void
    {
        $excepcion = $this->assertFallaRapido(
            fn () => DB::connection($conexion)->table('InventTable')->where('ItemId', 'X')->get(),
            QueryException::class,
            "SQL Server real '{$conexion}'"
        );

        $this->assertInstanceOf(RuntimeException::class, $excepcion->getPrevious());
    }

    #[DataProvider('conexionesSqlServer')]
    public function test_escribir_falla_sin_llegar_al_servidor(string $conexion): void
    {
        $this->assertFallaRapido(
            fn () => DB::connection($conexion)->table('TejTrama')->insert(['Folio' => 'X']),
            QueryException::class,
            "SQL Server real '{$conexion}'"
        );
    }

    #[DataProvider('conexionesSqlServer')]
    public function test_abrir_una_transaccion_falla(string $conexion): void
    {
        $this->assertFallaRapido(
            fn () => DB::connection($conexion)->beginTransaction(),
            RuntimeException::class,
            "SQL Server real '{$conexion}'"
        );
    }

    #[DataProvider('conexionesSqlServer')]
    public function test_el_objeto_conexion_sigue_sirviendo_sin_abrir_el_pdo(string $conexion): void
    {
        // Los modelos piden la gramatica para serializar fechas sin consultar nada; si el guard
        // tronara al crear la conexion, esos tests fallarian sin haber tocado la red.
        $connection = DB::connection($conexion);

        $this->assertSame('sqlsrv', $connection->getDriverName());
        $this->assertSame($conexion, $connection->getName());
        $this->assertNotSame('', $connection->getQueryGrammar()->getDateFormat());
        $this->assertStringContainsString('[InventTable]', $connection->table('InventTable')->toSql());
    }

    public function test_reapuntar_sqlsrv_ti_a_sqlite_despues_del_setup_funciona(): void
    {
        config()->set('database.connections.sqlsrv_ti', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        DB::purge('sqlsrv_ti');

        $ti = DB::connection('sqlsrv_ti');
        $ti->statement('CREATE TABLE InventTable (ItemId TEXT)');
        $ti->table('InventTable')->insert(['ItemId' => '600/1T']);

        $this->assertSame(['600/1T'], $ti->table('InventTable')->pluck('ItemId')->all());
    }

    public function test_el_trait_libera_sqlsrv_pero_los_erp_siguen_bloqueados(): void
    {
        $this->useSqlsrvSqlite();

        $this->assertSame([['uno' => 1]], array_map(
            fn ($fila) => (array) $fila,
            DB::connection('sqlsrv')->select('select 1 as uno')
        ));

        $this->assertFallaRapido(
            fn () => DB::connection('sqlsrv_ti')->getPdo(),
            RuntimeException::class,
            "SQL Server real 'sqlsrv_ti'"
        );
    }

    public function test_el_guard_se_reinstala_en_cada_test(): void
    {
        // Otro test de esta clase reapunta sqlsrv_ti a sqlite; cada test arranca con la app
        // nueva, asi que aqui debe volver a estar bloqueada sin importar el orden.
        $this->assertSame('sqlsrv', config('database.connections.sqlsrv_ti.driver'));

        $this->assertFallaRapido(
            fn () => DB::connection('sqlsrv_ti')->getPdo(),
            RuntimeException::class,
            "SQL Server real 'sqlsrv_ti'"
        );
    }

    /**
     * @param  class-string<\Throwable>  $tipo
     */
    private function assertFallaRapido(callable $accion, string $tipo, string $mensaje): \Throwable
    {
        $inicio = microtime(true);

        try {
            $accion();
        } catch (\Throwable $e) {
            $segundos = microtime(true) - $inicio;

            $this->assertInstanceOf($tipo, $e, $e->getMessage());
            $this->assertStringContainsString($mensaje, $e->getMessage());
            $this->assertLessThan(self::LIMITE_SEGUNDOS, $segundos, "Tardo {$segundos}s: parece que intento conectar.");

            return $e;
        }

        $this->fail("Se esperaba {$tipo} y la accion no fallo.");
    }
}
