<?php

declare(strict_types=1);

use App\Services\Programas\ProgramBoardActionService;
use App\Services\Programas\ProgramBoardReadService;
use App\Support\Programas\ProgramaModulo;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\Unit\Programas\FakeProgramBoardActionService;
use Tests\Unit\Programas\FakeProgramBoardReadService;
use Tests\Unit\Programas\TestableProgramBoard;

// Fakes de los servicios y el tablero sin permisos: los mismos del test de Livewire (sin SQL Server).
require_once __DIR__.'/../Unit/Programas/ProgramBoardLivewireTest.php';

/**
 * Programa Urdido (tablero Livewire) en Chromium: clic en una orden la selecciona en el
 * servidor y el wire:poll.visible vuelve a pintar el tablero sin perder la selección.
 */
beforeEach(function () {
    config(['program-board.poll_seconds' => 1]);
    app()->instance(ProgramBoardReadService::class, new FakeProgramBoardReadService);
    app()->instance(ProgramBoardActionService::class, new FakeProgramBoardActionService);
    Livewire::component('program-board-probe', TestableProgramBoard::class);

    Route::middleware('web')->get('/__browser/programa-urdido', fn () => Blade::render(<<<'BLADE'
        <!doctype html>
        <html lang="es"><head>
            <meta charset="utf-8"><meta name="csrf-token" content="{{ csrf_token() }}">
            @vite('resources/css/urd-eng/program-board.css')
            <x-layout-scripts :simple="true" />
        </head><body>
            <div id="program-board-navbar-controls"></div>
            <livewire:program-board-probe module="urdido" />
            @vite('resources/js/urd-eng/program-board.ts')
        </body></html>
        BLADE));
});

it('selecciona una orden con clic y el poll refresca el tablero', function () {
    $pagina = visit('/__browser/programa-urdido');

    $pagina->assertSee('URD-100')
        ->assertSee('URD-101')
        ->click('URD-100')
        ->assertQueryStringHas('orden', '100')
        ->assertSourceHas('Orden seleccionada: URD-100');

    // La siguiente lectura del servidor trae otro folio: solo aparece si el poll corre.
    app()->instance(ProgramBoardReadService::class, new class extends FakeProgramBoardReadService
    {
        public function order(ProgramaModulo $module, int $orderId): ?array
        {
            $orden = parent::order($module, $orderId);

            return $orden !== null && $orderId === 101 ? ['folio' => 'URD-101-POLL'] + $orden : $orden;
        }
    });

    $pagina->assertSee('URD-101-POLL')
        ->assertSourceHas('Orden seleccionada: URD-100')
        ->assertNoJavaScriptErrors();
});
