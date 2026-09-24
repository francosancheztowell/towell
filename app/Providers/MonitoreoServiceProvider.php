<?php

namespace App\Providers;

use App\Services\Monitoreo\AccesoAdmin;
use App\Services\Monitoreo\DispositivoService;
use App\Services\Monitoreo\EstadoRequest;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

/**
 * Monitoreo (fase 11, contrato 11-CONTRACT.md).
 *
 * Los listeners de app/Listeners/Monitoreo los registra el descubrimiento de
 * eventos de Laravel (activo por defecto); registrarlos aquí también con
 * Event::listen haría que cada login se grabara dos veces.
 *
 * La conexión `sqlsrv_monitoreo` se registra de forma perezosa en
 * Monitoreo::conexionErrores() (ver ahí por qué no en boot()).
 */
class MonitoreoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(EstadoRequest::class);
    }

    public function boot(): void
    {
        Gate::define('admin', fn ($usuario): bool => AccesoAdmin::permite($usuario));

        // Lo aplica AuthController antes de validar la contraseña (y registra el bloqueo).
        RateLimiter::for('login', fn (Request $request): array => [
            Limit::perMinute(10)->by('emp|'.Str::lower(trim((string) $request->input('numero_empleado'))).'|'.$request->ip()),
            Limit::perMinute(30)->by('ip|'.$request->ip()),
        ]);

        RateLimiter::for('telemetria', fn (Request $request): Limit => Limit::perMinute(120)
            ->by('disp|'.(DispositivoService::uuid($request) ?? $request->ip())));
    }
}
