<?php

namespace Tests;

use App\Observers\ReqProgramaTejidoObserver;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Los caches del observer son static y PHPUnit corre en un solo proceso: lo que
        // cachea un test se lo queda el siguiente. Un test que crea su propia tabla
        // 'CatCodificados' con menos columnas dejaba a otro sin sincronizar 'Pedido',
        // en silencio y solo dentro de la suite completa.
        ReqProgramaTejidoObserver::flushCaches();

        // Ningun test abre un SQL Server real (Towell, TI_PRO, TOW_PRO): sin red cada intento se
        // come 15s+ de TCP timeout, y con red escribe en produccion sin rollback. El objeto
        // conexion se sigue creando (los modelos le piden la gramatica para formatear fechas);
        // lo que falla al instante es abrir el PDO. Una conexion reapuntada a sqlite no se toca.
        // La lista sale de config/database.php para que una conexion nueva quede cubierta sola.
        $sqlServer = array_keys(array_filter(
            config('database.connections'),
            fn (array $config) => ($config['driver'] ?? null) === 'sqlsrv'
        ));

        foreach ($sqlServer as $conexion) {
            DB::extend($conexion, function (array $config, string $name) {
                $connection = $this->app['db.factory']->make($config, $name);

                if (($config['driver'] ?? null) !== 'sqlite') {
                    $bloqueo = fn () => throw new \RuntimeException("El test intento conectar al SQL Server real '{$name}'. Mockea el servicio o reapunta la conexion a sqlite.");
                    $connection->setPdo($bloqueo)->setReadPdo($bloqueo);
                }

                return $connection;
            });
        }
    }
}
