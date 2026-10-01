<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Livewire\Component;
use Livewire\Livewire;

/**
 * Error #6 del monitoreo: Apache contesta 503 a /livewire/update y Livewire pintaba esa
 * página en un modal que bloqueaba la tablet. Con el Livewire real y el bundle real
 * (app.js → utils/sesion.ts), el 503 debe dar un toast y ningún modal.
 */
class GuardarProbe extends Component
{
    public int $guardados = 0;

    public function guardar(): void
    {
        $this->guardados++;
    }

    public function render(): string
    {
        return '<div><button dusk="guardar" wire:click="guardar">Guardar</button> <span>Guardados: {{ $guardados }}</span></div>';
    }
}

beforeEach(function () {
    Livewire::component('guardar-probe', GuardarProbe::class);

    Route::middleware('web')->get('/__browser/guardar', fn () => Blade::render(<<<'BLADE'
        <!doctype html>
        <html lang="es"><head>
            <meta charset="utf-8"><meta name="csrf-token" content="{{ csrf_token() }}">
            <x-layout-scripts :simple="true" />
        </head><body>
            <livewire:guardar-probe />
        </body></html>
        BLADE));
});

it('un 503 de Livewire muestra el aviso y no el modal de error', function () {
    $pagina = visit('/__browser/guardar');
    $pagina->click('Guardar')->assertSee('Guardados: 1');

    // A partir de aquí Apache "no tiene workers": toda petición de Livewire responde 503.
    app('router')->pushMiddlewareToGroup('web', ResponderServidorOcupado::class);
    ResponderServidorOcupado::$activo = true;

    $pagina->click('Guardar')
        ->assertSee('El servidor no respondió')
        ->assertMissing('#livewire-error')
        ->assertSee('Guardados: 1')
        ->assertNoJavaScriptErrors();
});

class ResponderServidorOcupado
{
    public static bool $activo = false;

    public function handle($request, Closure $next)
    {
        return self::$activo && $request->is('livewire*/update')
            ? response('<h1>Service Unavailable</h1>', 503)
            : $next($request);
    }
}
