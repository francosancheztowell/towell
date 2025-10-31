<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\ReqProgramaTejido;
use App\Observers\ReqProgramaTejidoObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Incluir helpers de permisos manualmente
        require_once app_path('Helpers/permission-helpers.php');

        // Registrar el Observer para ReqProgramaTejido
        // Cuando se cree o actualice un ReqProgramaTejido,
        // automáticamente se llenarán las líneas en ReqProgramaTejidoLine
        ReqProgramaTejido::observe(ReqProgramaTejidoObserver::class);
    }
}
