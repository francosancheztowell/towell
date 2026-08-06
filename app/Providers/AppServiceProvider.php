<?php

namespace App\Providers;

use App\Contracts\Crudo\CrudoDashboardProvider;
use App\Contracts\Crudo\CrudoFlogProvider;
use App\Contracts\Crudo\CrudoReadRepository;
use App\Models\Atadores\AtaMontadoTelasModel;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Observers\AtaMontadoTelasObserver;
use App\Observers\ReqProgramaTejidoObserver;
use App\Repositories\Crudo\SqlServerCrudoReadRepository;
use App\Services\Crudo\CachedCrudoDashboardProvider;
use App\Services\Crudo\CrudoFlogService;
use App\Services\Trazabilidad\TrazabilidadProgramaLookupService;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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

        require_once app_path('Helpers/permission-helpers.php');

        ReqProgramaTejido::observe(ReqProgramaTejidoObserver::class);
        AtaMontadoTelasModel::observe(AtaMontadoTelasObserver::class);
    }
}
