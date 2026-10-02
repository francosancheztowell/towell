<?php

declare(strict_types=1);

use App\Contracts\Crudo\CrudoDashboardProvider;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\Unit\Crudo\CrudoLivewireTest;
use Tests\Unit\Crudo\FakeCrudoDashboardProvider;
use Tests\Unit\Crudo\TestableCrudoDashboard;

// Proveedor falso, tablero sin permisos y datos de ejemplo: los del test de Livewire (sin SQL Server).
require_once __DIR__.'/../Unit/Crudo/CrudoLivewireTest.php';

/** @return array<string, mixed> */
function datosCrudo(): array
{
    return (new ReflectionMethod(CrudoLivewireTest::class, 'dashboardData'))
        ->invoke(new CrudoLivewireTest('datos'));
}

/**
 * Dashboard de Crudo (andón) en Chromium: pinta los telares y el wire:poll.visible
 * vuelve a leer el proveedor sin errores de JS.
 */
beforeEach(function () {
    config(['crudo.poll_seconds' => 1]);
    app()->instance(CrudoDashboardProvider::class, new FakeCrudoDashboardProvider(datosCrudo()));
    Livewire::component('crudo-dashboard-probe', TestableCrudoDashboard::class);

    Route::middleware('web')->get('/__browser/crudo', fn () => Blade::render(<<<'BLADE'
        <!doctype html>
        <html lang="es"><head>
            <meta charset="utf-8"><meta name="csrf-token" content="{{ csrf_token() }}">
            @vite('resources/css/crudo/dashboard.css')
            <x-layout-scripts :simple="true" />
        </head><body>
            <div id="crudo-navbar-controls"></div>
            <div class="crudo-page" data-crudo-root>
                <aside class="crudo-livewire-error" data-crudo-livewire-error role="alert" hidden>
                    <span data-crudo-livewire-error-message>No fue posible actualizar el tablero.</span>
                    <button type="button" data-crudo-livewire-error-reload>Recargar</button>
                </aside>
                <livewire:crudo-dashboard-probe />
            </div>
            @vite('resources/js/crudo/dashboard.ts')
        </body></html>
        BLADE));
});

it('pinta el tablero y el poll lo refresca', function () {
    $pagina = visit('/__browser/crudo');

    $pagina->assertSee('1 telares')
        ->assertSee('Alertas por salón');

    // La siguiente lectura trae otro resumen: solo se ve si el poll vuelve al servidor.
    $datos = datosCrudo();
    $datos['summary']['total'] = 2;
    app()->instance(CrudoDashboardProvider::class, new FakeCrudoDashboardProvider($datos));

    $pagina->assertSee('2 telares')
        ->assertDontSee('1 telares')
        ->assertDontSee('No fue posible actualizar el tablero')
        ->assertNoJavaScriptErrors();
});
