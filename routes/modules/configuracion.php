<?php

use App\Http\Controllers\ambienteController;
use App\Http\Controllers\Configuracion\ConfiguracionController;
use App\Http\Controllers\ModulosController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::get('/configuracion', [UsuarioController::class, 'showConfiguracion'])->name('configuracion.index');
Route::redirect('/modulo-configuracion', '/configuracion', 301);

Route::prefix('configuracion')->name('configuracion.')->group(function () {
    Route::prefix('usuarios')->name('usuarios.')->group(function () {
        Route::get('/', [UsuarioController::class, 'select'])->name('index');
        Route::get('/select', [UsuarioController::class, 'select'])->name('select');
        Route::get('/create', [UsuarioController::class, 'create'])->name('create');
        Route::post('/store', [UsuarioController::class, 'store'])->name('store');
        Route::get('/{id}/qr', [UsuarioController::class, 'showQR'])->name('qr');
        Route::get('/{id}/edit', [UsuarioController::class, 'edit'])->name('edit');
        Route::put('/{id}', [UsuarioController::class, 'update'])->name('update');
        Route::delete('/{id}', [UsuarioController::class, 'destroy'])->name('destroy');
        Route::post('/{id}/permisos', [UsuarioController::class, 'updatePermiso'])->name('permisos.update');
    });

    Route::prefix('utileria')->name('utileria.')->group(function () {
        Route::get('/', [UsuarioController::class, 'showSubModulosNivel3'])
            ->defaults('moduloPadre', '909')
            ->where('moduloPadre', '909')
            ->name('index');

        Route::prefix('modulos')->name('modulos.')->controller(ModulosController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('/{id}/edit', 'edit')->whereNumber('id')->name('edit');
            Route::put('/{id}', 'update')->whereNumber('id')->name('update');
            Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy');
            Route::post('/{id}/toggle-acceso', 'toggleAcceso')->whereNumber('id')->name('toggle.acceso');
            Route::post('/{id}/toggle-permiso', 'togglePermiso')->whereNumber('id')->name('toggle.permiso');
            Route::post('/{id}/sincronizar-permisos', 'sincronizarPermisos')->whereNumber('id')->name('sincronizar.permisos');
            Route::get('/{modulo}/duplicar', 'duplicar')->whereNumber('modulo')->name('duplicar');
        });

        Route::get('/api/modulos/nivel/{nivel}', [ModulosController::class, 'getModulosPorNivel'])
            ->whereNumber('nivel')->name('api.modulos.nivel');
        Route::get('/api/modulos/submodulos/{dependencia}', [ModulosController::class, 'getSubmodulos'])
            ->whereNumber('dependencia')->name('api.modulos.submodulos');

        Route::view('/cargarcatalogos', 'modulos/cargar-catalogos')->name('cargar-catalogos');
        Route::get('/cargarplaneacion', [ConfiguracionController::class, 'cargarPlaneacion'])->name('cargar-planeacion');
        Route::post('/cargarplaneacion/upload', [ConfiguracionController::class, 'procesarExcel'])->name('cargar-planeacion.upload');
    });

    Route::get('/cargar-planeacion', [ConfiguracionController::class, 'cargarPlaneacion'])->name('cargar.planeacion');
    Route::post('/cargar-planeacion/upload', [ConfiguracionController::class, 'procesarExcel'])->name('cargar.planeacion.upload');

    Route::get('/modulos', [ModulosController::class, 'index'])->name('modulos.index');

    Route::view('/basededatos', 'modulos.configuracion.basededatos')->name('basededatos');

    Route::get('/ambiente', [ambienteController::class, 'index']) -> name('configuracion.ambiente');
});

Route::get('/modulos/{modulo}/duplicar', [ModulosController::class, 'duplicar'])->name('modulos.duplicar');
Route::post('/modulos/{modulo}/toggle-acceso', [ModulosController::class, 'toggleAcceso'])->name('modulos.toggle.acceso');
Route::post('/modulos/{modulo}/toggle-permiso', [ModulosController::class, 'togglePermiso'])->name('modulos.toggle.permiso');
Route::get('/api/modulos/nivel/{nivel}', [ModulosController::class, 'getModulosPorNivel'])->name('api.modulos.nivel');
Route::get('/api/modulos/submodulos/{dependencia}', [ModulosController::class, 'getSubmodulos'])->name('api.modulos.submodulos');
