<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ModulosController;
use App\Http\Controllers\SystemController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/', [AuthController::class, 'showLoginForm'])->name('home');
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::get('/test-404', [SystemController::class, 'test404'])->name('test-404');
Route::view('/offline', 'offline')->name('offline');

/*
 * Superficies que antes eran públicas (BUG-001 / BUG-002). Siguen en las
 * mismas URIs para no romper bookmarks o clientes logueados; ahora exigen sesión.
 * No hay middleware de permiso por módulo en el router (userCan se usa en
 * controllers/vistas, no como alias de ruta).
 */
Route::middleware('auth')->group(function () {
    Route::get('/obtener-empleados/{area}', [UsuarioController::class, 'obtenerEmpleados'])
        ->name('usuarios.obtener-empleados');

    Route::prefix('modulos-sin-auth')->name('modulos.gestion.')->group(function () {
        Route::get('/', [ModulosController::class, 'index'])->name('index');
        Route::post('/', [ModulosController::class, 'store'])
            ->middleware('module.permission:crear,101')->name('store'); // Modulos
        Route::put('/{id}', [ModulosController::class, 'update'])->whereNumber('id')
            ->middleware('module.permission:modificar,101')->name('update'); // Modulos
        Route::delete('/{id}', [ModulosController::class, 'destroy'])->whereNumber('id')
            ->middleware('module.permission:eliminar,101')->name('destroy'); // Modulos
    });
});
