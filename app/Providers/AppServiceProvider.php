<?php

namespace App\Providers;

use App\Models\ReqProgramaTejido;
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
    }
}
