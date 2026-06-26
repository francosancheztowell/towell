<?php

use App\Http\Controllers\Trazabilidad\TrazabilidadController;
use Illuminate\Support\Facades\Route;

Route::get('/trazabilidad', [TrazabilidadController::class, 'index'])->name('trazabilidad.index');
