<?php

use App\Http\Controllers\mecanicos\OrdenesTrabajoMecaController;
use Illuminate\Support\Facades\Route;

// Ruta configurada para el submódulo 1100 en SYSRoles.
Route::get('/submodulos/1100/ordenes-de-trabajo', [OrdenesTrabajoMecaController::class, 'index'])
    ->name('mecanicos.ordenes-trabajo.submodulo');

Route::prefix('mecanicos/ordenes-trabajo')
    ->as('mecanicos.ordenes-trabajo.')
    ->group(function (): void {
        Route::get('/', [OrdenesTrabajoMecaController::class, 'index'])->name('index');
        Route::get('/registros', [OrdenesTrabajoMecaController::class, 'registros'])->name('registros');
        Route::get('/paros-activos', [OrdenesTrabajoMecaController::class, 'parosActivos'])->name('paros-activos');
        Route::post('/', [OrdenesTrabajoMecaController::class, 'store'])->name('store');
        Route::get('/{folio}/captura', [OrdenesTrabajoMecaController::class, 'captura'])->name('captura');
        Route::get('/{folio}', [OrdenesTrabajoMecaController::class, 'show'])->name('show');
        Route::put('/{folio}', [OrdenesTrabajoMecaController::class, 'update'])->name('update');
        Route::delete('/{folio}', [OrdenesTrabajoMecaController::class, 'destroy'])->name('destroy');
        Route::post('/{folio}/lineas', [OrdenesTrabajoMecaController::class, 'storeLinea'])->name('lineas.store');
        Route::put('/{folio}/lineas/{linea}', [OrdenesTrabajoMecaController::class, 'updateLinea'])->name('lineas.update')->whereNumber('linea');
        Route::delete('/{folio}/lineas/{linea}', [OrdenesTrabajoMecaController::class, 'destroyLinea'])->name('lineas.destroy')->whereNumber('linea');
    });
