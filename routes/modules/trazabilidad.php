<?php

use App\Http\Controllers\Trazabilidad\TrazabilidadController;
use App\Http\Controllers\Trazabilidad\TrazabilidadDetailController;
use Illuminate\Support\Facades\Route;

Route::prefix('trazabilidad')->name('trazabilidad.')->group(function (): void {
    Route::get('/', [TrazabilidadController::class, 'index'])->name('index');
    Route::get('/detalles/matriz', [TrazabilidadDetailController::class, 'matrix'])->name('details.matrix');
    Route::get('/detalles/produccion', [TrazabilidadDetailController::class, 'production'])->name('details.production');
    Route::get('/detalles/flog', [TrazabilidadDetailController::class, 'flog'])->name('details.flog');
    Route::get('/redbooth', [TrazabilidadController::class, 'redbooth'])->name('redbooth');
    Route::get('/exportar', [TrazabilidadController::class, 'exportar'])->name('exportar');
    Route::get('/flog-archivo', [TrazabilidadController::class, 'flogArchivo'])->name('flog-archivo');
});
