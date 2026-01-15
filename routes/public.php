<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ModulosController;
use App\Http\Controllers\SystemController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AuthController::class, 'showLoginForm'])->name('home');
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/login-qr', [AuthController::class, 'loginQR']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::get('/obtener-empleados/{area}', [UsuarioController::class, 'obtenerEmpleados'])
    ->name('usuarios.obtener-empleados');

Route::get('/test-404', [SystemController::class, 'test404'])->name('test-404');
Route::view('/offline', 'offline')->name('offline');

// Muchos navegadores piden /favicon.ico aunque exista <link rel="icon">.
// Servimos el PNG oficial para evitar el ícono "fantasma" cuando falta/corrompe el .ico.
Route::get('/favicon.ico', function () {
    $path = public_path('images/fotosTowell/TOWELLIN.png');
    if (file_exists($path)) {
        return response()->file($path, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
    abort(404);
})->name('favicon.ico');

Route::prefix('modulos-sin-auth')->name('modulos.sin.auth.')->group(function () {
    Route::get('/', [ModulosController::class, 'index'])->name('index');
    Route::get('/create', [ModulosController::class, 'create'])->name('create');
    Route::post('/', [ModulosController::class, 'store'])->name('store');
    Route::get('/{id}/edit', [ModulosController::class, 'edit'])->whereNumber('id')->name('edit');
    Route::put('/{id}', [ModulosController::class, 'update'])->whereNumber('id')->name('update');
    Route::delete('/{id}', [ModulosController::class, 'destroy'])->whereNumber('id')->name('destroy');
});
