<?php

namespace App\Providers;

//use Illuminate\Support\ServiceProvider;
use App\Models\Atadores\AtaMontadoTelasModel;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Observers\AtaMontadoTelasObserver;
use App\Observers\ReqProgramaTejidoObserver;
use Illuminate\Support\ServiceProvider;

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
        require_once app_path('Helpers/permission-helpers.php');

        ReqProgramaTejido::observe(ReqProgramaTejidoObserver::class);
        AtaMontadoTelasModel::observe(AtaMontadoTelasObserver::class);
    }
}
