<?php

namespace App\Providers;

use App\Models\Atadores\AtaMontadoTelasModel;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Observers\AtaMontadoTelasObserver;
use App\Observers\ReqProgramaTejidoObserver;
use App\Services\Trazabilidad\TrazabilidadProgramaLookupService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // El resumen y la tabla de avance comparten este catálogo durante la
        // petición Livewire, sin conservar datos entre peticiones.
        $this->app->scoped(TrazabilidadProgramaLookupService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        require_once app_path('Helpers/permission-helpers.php');

        ReqProgramaTejido::observe(ReqProgramaTejidoObserver::class);
        AtaMontadoTelasModel::observe(AtaMontadoTelasObserver::class);
    }
}
