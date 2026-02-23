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

Route::get('/obtener-empleados/{area}', [UsuarioController::class, 'obtenerEmpleados'])
    ->name('usuarios.obtener-empleados');

Route::get('/test-404', [SystemController::class, 'test404'])->name('test-404');
Route::view('/offline', 'offline')->name('offline');

Route::prefix('modulos-sin-auth')->name('modulos.sin.auth.')->group(function () {
    Route::get('/', [ModulosController::class, 'index'])->name('index');
    Route::get('/create', [ModulosController::class, 'create'])->name('create');
    Route::post('/', [ModulosController::class, 'store'])->name('store');
    Route::get('/{id}/edit', [ModulosController::class, 'edit'])->whereNumber('id')->name('edit');
    Route::put('/{id}', [ModulosController::class, 'update'])->whereNumber('id')->name('update');
    Route::delete('/{id}', [ModulosController::class, 'destroy'])->whereNumber('id')->name('destroy');
});
