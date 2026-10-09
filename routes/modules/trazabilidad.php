<?php

use App\Http\Controllers\Trazabilidad\TrazabilidadController;
use App\Http\Controllers\Trazabilidad\TrazabilidadDetailController;
use Illuminate\Support\Facades\Route;

// idrol 190 = Trazabilidad en SYSRoles. Por id y no por nombre: userCan('acceso', 'Trazabilidad')
// resuelve a una fila arbitraria si alguna vez aparece un módulo homónimo.
Route::prefix('trazabilidad')->name('trazabilidad.')->middleware('module.permission:acceso,190')->group(function (): void {
    Route::get('/', [TrazabilidadController::class, 'index'])->name('index');
    Route::get('/detalles/matriz', [TrazabilidadDetailController::class, 'matrix'])->name('details.matrix');
    Route::get('/detalles/produccion', [TrazabilidadDetailController::class, 'production'])->name('details.production');
    Route::get('/detalles/flog', [TrazabilidadDetailController::class, 'flog'])->name('details.flog');
    Route::get('/opciones/flog', [TrazabilidadController::class, 'opcionesFlog'])->name('opciones.flog');
    Route::get('/redbooth', [TrazabilidadController::class, 'redbooth'])->name('redbooth');
    Route::get('/flog-archivo', [TrazabilidadController::class, 'flogArchivo'])->name('flog-archivo');
});
