<?php

use App\Http\Controllers\Integraciones\RedboothController;
use Illuminate\Support\Facades\Route;

Route::prefix('integraciones/redbooth')->name('redbooth.')->group(function (): void {
    Route::get('/', [RedboothController::class, 'status'])->name('status');
    Route::get('/conectar', [RedboothController::class, 'connect'])->name('connect');
    Route::get('/callback', [RedboothController::class, 'callback'])->name('callback');
    Route::get('/me', [RedboothController::class, 'me'])->name('me');
    Route::get('/actividades', [RedboothController::class, 'activities'])->name('activities');
    Route::delete('/conexion', [RedboothController::class, 'disconnect'])->name('disconnect');
});
