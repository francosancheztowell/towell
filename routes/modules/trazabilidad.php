<?php

use App\Http\Controllers\Trazabilidad\TrazabilidadController;
use Illuminate\Support\Facades\Route;

Route::get('/trazabilidad', [TrazabilidadController::class, 'index'])->name('trazabilidad.index');
Route::get('/trazabilidad/redbooth', [TrazabilidadController::class, 'redbooth'])->name('trazabilidad.redbooth');
Route::get('/trazabilidad/exportar', [TrazabilidadController::class, 'exportar'])->name('trazabilidad.exportar');
Route::get('/trazabilidad/flog-archivo', [TrazabilidadController::class, 'flogArchivo'])->name('trazabilidad.flog-archivo');
