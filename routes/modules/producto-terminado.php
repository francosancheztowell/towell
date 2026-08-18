<?php

declare(strict_types=1);

use App\Http\Controllers\ProductoTerminado\TiemposPtController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Producto Terminado — rutas alineadas con SYSRoles
|--------------------------------------------------------------------------
| Nivel 1: 1300 Producto Terminado → /producto-terminado
| Nivel 2: 1301 Tiempos PT         → /producto-terminado/tiempos-pt
*/

// Tiempos PT (SYSRoles 1301) — registrar antes del grid del módulo principal.
Route::prefix('producto-terminado/tiempos-pt')
    ->as('producto-terminado.tiempos-pt.')
    ->group(function (): void {
        Route::get('/', [TiemposPtController::class, 'index'])->name('index');
    });

// Módulo principal (SYSRoles 1300 → Ruta=/producto-terminado)
Route::get('/producto-terminado/{moduloPrincipal?}', [UsuarioController::class, 'showSubModulos'])
    ->defaults('moduloPrincipal', 'producto-terminado')
    ->where('moduloPrincipal', 'producto-terminado')
    ->name('producto-terminado.index');

// Compatibilidad con rutas antiguas /submodulos/1300
Route::redirect('/submodulos/1300', '/producto-terminado', 301);
