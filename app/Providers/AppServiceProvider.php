<?php

namespace App\Providers;

use App\Contracts\Crudo\CrudoDashboardProvider;
use App\Contracts\Crudo\CrudoFlogProvider;
use App\Contracts\Crudo\CrudoReadRepository;
use App\Database\SqlServerScopeIdentityProcessor;
use App\Models\Atadores\AtaMontadoTelasModel;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Models\Sistema\SYSRoles;
use App\Observers\AtaMontadoTelasObserver;
use App\Observers\ReqProgramaTejidoObserver;
use App\Repositories\Crudo\SqlServerCrudoReadRepository;
use App\Services\Crudo\CachedCrudoDashboardProvider;
use App\Services\Crudo\CrudoFlogService;
use App\Services\Tejedores\Desarrolladores\NotificacionTelegramDesarrolladorService;
use App\Services\Tejedores\Desarrolladores\ProcesarMuestrasDesarrolladorService;
use App\Services\Trazabilidad\TrazabilidadProgramaLookupService;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use WeakMap;

class AppServiceProvider extends ServiceProvider
{
    /** @var WeakMap<Connection, true>|null */
    private ?WeakMap $conexionesConUmbral = null;

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CrudoReadRepository::class, SqlServerCrudoReadRepository::class);
        $this->app->bind(CrudoDashboardProvider::class, CachedCrudoDashboardProvider::class);
        $this->app->bind(CrudoFlogProvider::class, CrudoFlogService::class);

        // El resumen y la tabla de avance comparten este catálogo durante la
        // petición Livewire, sin conservar datos entre peticiones.
        $this->app->scoped(TrazabilidadProgramaLookupService::class);

        // Las dos capturas de desarrollador mandan el mismo mensaje a Telegram con tres
        // cadenas distintas. Un solo servicio, configurado aquí, en vez de dos clases
        // que se corrigen por separado (así fue como muestras se quedó sin escapar el
        // Markdown y sin timeout).
        $this->app->when(ProcesarMuestrasDesarrolladorService::class)
            ->needs(NotificacionTelegramDesarrolladorService::class)
            ->give(fn (): NotificacionTelegramDesarrolladorService => new NotificacionTelegramDesarrolladorService(
                modulo: 'DesarrolladoresPrue',
                titulo: 'PROCESO MUESTRA - DESARROLLADOR COMPLETADO',
                estado: 'Muestra procesada y eliminada del programa',
            ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // pdo_sqlsrv resuelve lastInsertId() con @@IDENTITY, que devuelve el Id que
        // genera el trigger de auditoría en SYSAuditoria en vez del de la tabla real.
        Event::listen(ConnectionEstablished::class, function (ConnectionEstablished $event) {
            if ($event->connection->getDriverName() === 'sqlsrv') {
                $event->connection->setPostProcessor(new SqlServerScopeIdentityProcessor);
            }

            $this->avisarConsultasLentas($event->connection);
        });

        $this->avisarLazyLoading();

        // PERF-04: moduleNameForRoute() cachea por ruta; una alta/cambio/baja de módulo la invalida.
        SYSRoles::saved(static fn () => olvidarModulosPorRuta());
        SYSRoles::deleted(static fn () => olvidarModulosPorRuta());

        // Livewire 4 deriva el prefijo /livewire-<hash8> del APP_KEY
        // (EndpointResolver::prefix). Publicar /livewire/update fija un endpoint
        // estable para todas las páginas nuevas. El hash del APP_KEY vigente se
        // conserva como alias para las pestañas abiertas durante el despliegue.
        //
        // OJO: el alias NO rescata una pestaña renderizada con OTRO APP_KEY.
        // Ese hash ya no existe y, aunque existiera, el checksum del snapshot va
        // firmado con el APP_KEY, así que la única salida es recargar la página.
        // Por eso el front deja de hacer poll ante un 404 en vez de reintentar.
        $legacyUpdateRoute = collect(Route::getRoutes()->getRoutes())
            ->first(static fn (RoutingRoute $route): bool => $route->getName() === 'default-livewire.update');

        if ($legacyUpdateRoute instanceof RoutingRoute) {
            $legacyAction = $legacyUpdateRoute->getAction();
            $legacyAction['as'] = 'legacy-livewire-endpoint';
            $legacyUpdateRoute->setAction($legacyAction);
        }

        Livewire::setUpdateRoute(
            static fn (array $handle): RoutingRoute => Route::post('/livewire/update', $handle),
        );

        ReqProgramaTejido::observe(ReqProgramaTejidoObserver::class);
        AtaMontadoTelasModel::observe(AtaMontadoTelasObserver::class);
    }

    /**
     * PERF-05: fuera de producción, cada relación cargada en lazy (el N+1 típico) deja una
     * línea en el log, una sola vez por modelo y relación en la request. Nunca lanza: una
     * pantalla no se cae en local por algo que en producción funciona.
     */
    private function avisarLazyLoading(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        Model::handleLazyLoadingViolationUsing(function (Model $modelo, string $relacion): void {
            $clave = $modelo::class.'::'.$relacion;
            $vistos = $this->app->bound('rendimiento.lazy') ? $this->app->make('rendimiento.lazy') : [];
            if (isset($vistos[$clave])) {
                return;
            }
            $this->app->instance('rendimiento.lazy', $vistos + [$clave => true]);

            Log::warning('Rendimiento: relación cargada en lazy (posible N+1).', [
                'relacion' => $clave,
                'ruta' => $this->rutaActual(),
            ]);
        });
    }

    /**
     * PERF-06: una request cuyas consultas suman más de 500 ms en una conexión deja una línea
     * en el log (una vez por conexión y request; en colas Laravel lo rearma por job). Es el
     * acumulado: la consulta individual lenta ya la registra Pulse (SlowQueries), no se duplica.
     */
    private function avisarConsultasLentas(Connection $conexion): void
    {
        // DB::reconnect() vuelve a disparar ConnectionEstablished con el mismo objeto: un solo aviso por conexión.
        $this->conexionesConUmbral ??= new WeakMap;
        if (isset($this->conexionesConUmbral[$conexion])) {
            return;
        }
        $this->conexionesConUmbral[$conexion] = true;

        $conexion->whenQueryingForLongerThan(500, function (Connection $conexion, QueryExecuted $consulta): void {
            // Laravel escucha QueryExecuted de todas las conexiones: la que cruzó el umbral
            // puede venir de otra, y entonces su SQL no es de esta.
            Log::warning('Rendimiento: la request lleva más de 500 ms en consultas.', [
                'conexion' => $conexion->getName(),
                'ms' => round($conexion->totalQueryDuration(), 1),
                'ruta' => $this->rutaActual(),
                'ultima_consulta' => $consulta->connectionName === $conexion->getName()
                    ? mb_substr($consulta->sql, 0, 300)
                    : null,
            ]);
        });
    }

    private function rutaActual(): ?string
    {
        $ruta = $this->app->bound('request') ? request()->route() : null;

        return is_object($ruta) ? ($ruta->getName() ?? $ruta->uri()) : null;
    }
}
