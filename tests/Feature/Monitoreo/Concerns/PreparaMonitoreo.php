<?php

namespace Tests\Feature\Monitoreo\Concerns;

use App\Models\Sistema\Usuario;
use App\Services\ModuloService;
use App\Services\Monitoreo\Monitoreo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesSqlsrvSqlite;

/**
 * Esquema de monitoreo en sqlite para los tests de la fase 11.
 *
 * - `sqlsrv` (sqlite en memoria) con dbo.SYSUsuario y las tablas SYSMon*.
 * - `sqlsrv_monitoreo` en OTRA base en memoria, como en producción es otra
 *   conexión: lo que se escribe ahí no se revierte con las transacciones de `sqlsrv`.
 */
trait PreparaMonitoreo
{
    use UsesSqlsrvSqlite;

    protected function prepararMonitoreo(): void
    {
        $this->useSqlsrvSqlite();
        config()->set('auth.providers.usuarios.model', Usuario::class);
        config()->set('monitoreo.enabled', true);

        DB::connection('sqlsrv')->statement("ATTACH DATABASE ':memory:' AS dbo");
        Schema::connection('sqlsrv')->create('dbo.SYSUsuario', function (Blueprint $table) {
            $table->increments('idusuario');
            $table->string('nombre', 150);
            $table->string('contrasenia', 255);
            $table->string('numero_empleado', 30)->nullable();
            $table->string('area', 50)->nullable();
            $table->string('remember_token', 100)->nullable();
            $table->timestamps();
        });

        config()->set('database.connections.'.Monitoreo::CONEXION_ERRORES, [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        DB::purge(Monitoreo::CONEXION_ERRORES);

        $this->migrarMonitoreo('sqlsrv');
        $this->migrarMonitoreo(Monitoreo::CONEXION_ERRORES);

        // El layout pide el menú del usuario; aquí no hay SYSRoles.
        $this->mock(ModuloService::class, function ($mock) {
            $mock->shouldReceive('getModulosPrincipalesPorUsuario')->zeroOrMoreTimes()->andReturn(new Collection);
            $mock->shouldReceive('rutaPadreDe')->zeroOrMoreTimes()->andReturn('/produccionProceso');
        });
    }

    protected function migrarMonitoreo(string $conexion, string $archivo = '2026_09_24_000001_create_sysmon_tables.php'): void
    {
        /** @var Migration $migracion */
        $migracion = require database_path('migrations/'.$archivo);
        (fn () => $this->connection = $conexion)->call($migracion);
        $migracion->up();
    }

    protected function crearUsuario(array $atributos = []): Usuario
    {
        return Usuario::create(array_merge([
            'nombre' => 'Operador Monitoreo',
            'contrasenia' => 'secret',
            'numero_empleado' => '7701',
            'area' => 'Tejido',
        ], $atributos));
    }

    /** Ruta autenticada mínima (sin layout) para ejercitar los middlewares del grupo web. */
    protected function registrarRutaDePrueba(): void
    {
        Route::middleware(['web', 'auth'])->get('/_prueba/monitoreo', fn () => 'ok')->name('prueba.monitoreo');
        Route::getRoutes()->refreshNameLookups();
    }

    /** En PHP-FPM cada request es un proceso: olvidar guard y sesión entre llamadas. */
    protected function nuevoProceso(): void
    {
        Auth::forgetGuards();
        Facade::clearResolvedInstance('auth');
        Facade::clearResolvedInstance('session');
        $this->app->forgetInstance('auth');
        $this->app->forgetInstance('session');
        $this->app->forgetInstance('session.store');
        $this->app->forgetInstance(StartSession::class);
        $this->defaultCookies = [];
        $this->unencryptedCookies = [];
    }

    protected function cookieRecordarme(Usuario $usuario): array
    {
        $guard = Auth::guard();

        return [$guard->getRecallerName() => $usuario->idusuario.'|'.$usuario->remember_token.'|'
            .$guard->hashPasswordForCookie($usuario->getAuthPassword())];
    }

    protected function mon(string $tabla)
    {
        return DB::connection('sqlsrv')->table($tabla);
    }
}
