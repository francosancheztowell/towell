<?php

use App\Http\Controllers\Integraciones\RedboothController;
use Illuminate\Support\Facades\Route;

Route::prefix('integraciones/redbooth')->name('redbooth.')->group(function (): void {
    Route::get('/', [RedboothController::class, 'status'])->name('status');
    Route::get('/conectar', [RedboothController::class, 'connect'])->name('connect');
    Route::get('/callback', [RedboothController::class, 'callback'])->name('callback');
    Route::get('/me', [RedboothController::class, 'me'])->name('me');
    Route::get('/actividades', [RedboothController::class, 'activities'])->name('activities');
    Route::get('/tareas', [RedboothController::class, 'tasks'])->name('tasks');
    Route::get('/comentarios', [RedboothController::class, 'comments'])->name('comments');
    Route::get('/archivos', [RedboothController::class, 'files'])->name('files');
    Route::get('/imagenes', [RedboothController::class, 'images'])->name('images');
    Route::get('/archivos/{fileId}/descargar', [RedboothController::class, 'download'])
        ->whereNumber('fileId')
        ->name('files.download');
    Route::delete('/conexion', [RedboothController::class, 'disconnect'])->name('disconnect');
});
