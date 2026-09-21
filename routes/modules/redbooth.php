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
    // Redbooth no tiene modulo propio en SYSRoles y ninguna vista llama a esta ruta:
    // desconectar el OAuth es administracion, por eso va bajo Configuración (idrol 58,
    // por id porque el nombre esta repetido 3 veces en SYSRoles).
    Route::delete('/conexion', [RedboothController::class, 'disconnect'])
        ->middleware('module.permission:modificar,58')->name('disconnect');
});
