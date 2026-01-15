<?php

use App\Http\Controllers\StorageController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::get('/produccionProceso', [UsuarioController::class, 'index'])->name('produccion.index');

Route::get('/submodulos/{modulo}', [UsuarioController::class, 'showSubModulos'])->name('submodulos.show');
Route::get('/submodulos-nivel3/{moduloPadre}', [UsuarioController::class, 'showSubModulosNivel3'])
    ->name('submodulos.nivel3');
Route::get('/api/submodulos/{moduloPrincipal}', [UsuarioController::class, 'getSubModulosAPI'])->name('api.submodulos');
Route::get('/api/modulo-padre', [UsuarioController::class, 'getModuloPadre'])->name('api.modulo.padre');

Route::get('/storage/usuarios/{filename}', [StorageController::class, 'usuarioFoto'])->name('storage.usuarios');
