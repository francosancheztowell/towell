<?php

// Arnés local (19-05): Laravel con todas las conexiones sqlsrv* apuntando a sqlite en archivo.
// Igual que 19-01-arnes/boot.php más una capa que traduce el SQL de SQL Server que el módulo
// escribe a mano (WITH (NOLOCK), SET TRANSACTION…, CONVERT, CAST AS DATE, DATE_FORMAT).
// REPO: el árbol a servir (ARNES_REPO permite apuntar a un worktree del "antes").
// ARNES_DATOS: dónde quedan los sqlite (fuera del repo).
define('ARNES', __DIR__);
define('REPO', getenv('ARNES_REPO') ?: dirname(__DIR__, 4));
define('HARNESS_DIR', getenv('ARNES_DATOS') ?: sys_get_temp_dir().'/towell-arnes-1905');
if (! is_dir(HARNESS_DIR)) {
    mkdir(HARNESS_DIR, 0777, true);
}
require REPO.'/vendor/autoload.php';

/** SQLite que acepta el dialecto de SQL Server que aparece en las consultas crudas del módulo. */
class ArnesSqliteConnection extends Illuminate\Database\SQLiteConnection
{
    public static function traducir(string $sql): string
    {
        if (preg_match('/^\s*SET\s+(TRANSACTION|NOCOUNT|DATEFORMAT|LOCK_TIMEOUT)\b/i', $sql)) {
            return 'SELECT 1';
        }
        // ISNULL es palabra reservada en sqlite (x ISNULL): la función equivalente es IFNULL.
        $sql = preg_replace('/\bISNULL\s*\(/i', 'IFNULL(', $sql);
        $sql = preg_replace('/\bWITH\s*\(\s*(NOLOCK|UPDLOCK|ROWLOCK|READPAST|HOLDLOCK)(\s*,\s*\w+)*\s*\)/i', '', $sql);
        // CONVERT(VARCHAR(10), x, 23) / CONVERT(DATE, x) / CONVERT(INT, x)
        $sql = preg_replace('/\bCONVERT\s*\(\s*N?VARCHAR\s*\(\s*\d+\s*\)\s*,\s*([^,()]+)\s*,\s*\d+\s*\)/i', 'substr($1, 1, 10)', $sql);
        $sql = preg_replace('/\bCONVERT\s*\(\s*DATE\s*,\s*([^()]+?)\s*\)/i', 'date($1)', $sql);
        $sql = preg_replace('/\bCAST\s*\(\s*([^()]+?)\s+AS\s+DATE\s*\)/i', 'date($1)', $sql);
        $sql = preg_replace('/\bCAST\s*\(\s*([^()]+?)\s+AS\s+DATETIME2?\s*\)/i', 'datetime($1)', $sql);

        return $sql;
    }

    protected function run($query, $bindings, Closure $callback)
    {
        return parent::run(self::traducir((string) $query), $bindings, $callback);
    }
}

Illuminate\Database\Connection::resolverFor('sqlite', fn ($pdo, $db, $prefix, $config) => new ArnesSqliteConnection($pdo, $db, $prefix, $config));

function harness_config(): void
{
    $main = HARNESS_DIR.'/main.sqlite';
    foreach (['sqlsrv', 'sqlsrv_ti', 'sqlsrv_tow_pro', 'sqlsrv_tow_tow', 'sqlsrv_Reportes_Towell', 'sqlite'] as $c) {
        config()->set("database.connections.$c", ['driver' => 'sqlite', 'database' => $main, 'prefix' => '', 'foreign_key_constraints' => false]);
    }
    // La misma conexión que usan los modelos: dos PDO sobre el mismo archivo se bloquean en transacciones.
    config()->set('database.default', 'sqlsrv');
    config()->set('cache.default', 'array');
    config()->set('session.driver', 'file');
    config()->set('queue.default', 'sync');
    config()->set('monitoreo.enabled', false);
    config()->set('app.debug', true);
    config()->set('debugbar.enabled', false);
}

function harness_attach(): void
{
    foreach (['sqlsrv', 'sqlsrv_ti', 'sqlsrv_tow_pro', 'sqlsrv_tow_tow', 'sqlsrv_Reportes_Towell', 'sqlite'] as $c) {
        $db = Illuminate\Support\Facades\DB::connection($c);
        harness_funciones($db->getPdo());
        $names = array_column($db->select('PRAGMA database_list'), 'name');
        // dbo + INFORMATION_SCHEMA.COLUMNS (lo consultan SSYSFoliosSecuencia y otros): los llena setup.php.
        foreach (['dbo' => 'dbo', 'INFORMATION_SCHEMA' => 'information_schema'] as $alias => $archivo) {
            if (! in_array($alias, $names, true)) {
                $db->statement("ATTACH DATABASE '".HARNESS_DIR."/{$archivo}.sqlite' AS {$alias}");
            }
        }
    }
}

/** Funciones de SQL Server (y de MySQL, en las ramas "no sqlsrv") que aparecen en consultas crudas. */
function harness_funciones(PDO $pdo): void
{
    $fecha = fn (string $formato) => fn ($v) => $v === null ? null : (int) date($formato, strtotime((string) $v));
    $pdo->sqliteCreateFunction('ISNUMERIC', fn ($v) => is_numeric($v) ? 1 : 0, 1);
    $pdo->sqliteCreateFunction('ISNULL', fn ($a, $b) => $a ?? $b, 2);
    $pdo->sqliteCreateFunction('GETDATE', fn () => date('Y-m-d H:i:s'), 0);
    $pdo->sqliteCreateFunction('LEN', fn ($v) => $v === null ? null : strlen(rtrim((string) $v)), 1);
    $pdo->sqliteCreateFunction('DATE_FORMAT', fn ($v, $f) => $v === null ? null : date(str_replace(['%Y', '%m', '%d', '%H', '%i', '%s'], ['Y', 'm', 'd', 'H', 'i', 's'], (string) $f), strtotime((string) $v)), 2);
    $pdo->sqliteCreateFunction('YEAR', $fecha('Y'), 1);
    $pdo->sqliteCreateFunction('MONTH', $fecha('n'), 1);
    $pdo->sqliteCreateFunction('DAY', $fecha('j'), 1);
}
