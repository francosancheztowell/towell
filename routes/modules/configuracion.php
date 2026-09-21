<?php

use App\Http\Controllers\Configuracion\BaseDeDatosController;
use App\Http\Controllers\Configuracion\ConfiguracionController;
use App\Http\Controllers\Configuracion\DepartamentosController;
use App\Http\Controllers\Configuracion\MensajesController;
use App\Http\Controllers\Configuracion\SecuenciaFoliosController;
use App\Http\Controllers\ModulosController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

// Los modulos se referencian por SYSRoles.idrol, no por nombre: userPermissions() indexa
// con keyBy(strtolower(modulo)) y hay 5 nombres repetidos en SYSRoles, asi que por nombre
// gana una fila arbitraria (la query no lleva ORDER BY). userCan() acepta el idrol directo
// via is_numeric(). Para ver a que modulo corresponde cada numero: scratchpad/verificar_final.php
// imprime idrol + nombre + cuantos usuarios tienen el permiso.
$config = 58;           // Configuración (x3 en SYSRoles: 58 / 155 Urdido / 159 Engomado)
$usuarios = 59;         // Usuarios
$utileria = 67;         // Utilería (x2: 67 Configuración / 188 Planeación)
$modulos = 101;         // Modulos
$cargarCatalogos = 68;  // Cargar Catálogos
$cargarPlaneacion = 69;  // Cargar Planeación
$departamentos = 179;   // Departamentos
$folios = 180;          // Secuencia de Folios
$mensajes = 181;        // Mensajes

Route::get('/configuracion', [UsuarioController::class, 'showConfiguracion'])
    ->middleware("module.permission:acceso,{$config}")
    ->name('configuracion.index');
Route::redirect('/modulo-configuracion', '/configuracion', 301);

Route::prefix('configuracion')->name('configuracion.')->group(function () use (
    $config, $usuarios, $utileria, $modulos, $cargarCatalogos, $cargarPlaneacion,
    $departamentos, $folios, $mensajes
) {
    Route::prefix('usuarios')->name('usuarios.')->group(function () use ($usuarios) {
        Route::get('/', [UsuarioController::class, 'select'])
            ->middleware("module.permission:acceso,{$usuarios}")->name('index');
        Route::get('/select', [UsuarioController::class, 'select'])
            ->middleware("module.permission:acceso,{$usuarios}")->name('select');
        Route::get('/create', [UsuarioController::class, 'create'])
            ->middleware("module.permission:crear,{$usuarios}")->name('create');
        Route::post('/store', [UsuarioController::class, 'store'])
            ->middleware("module.permission:crear,{$usuarios}")->name('store');
        Route::get('/{id}/qr', [UsuarioController::class, 'showQR'])
            ->middleware("module.permission:acceso,{$usuarios}")->name('qr');
        Route::get('/{id}/edit', [UsuarioController::class, 'edit'])
            ->middleware("module.permission:modificar,{$usuarios}")->name('edit');
        Route::put('/{id}', [UsuarioController::class, 'update'])
            ->middleware("module.permission:modificar,{$usuarios}")->name('update');
        Route::delete('/{id}', [UsuarioController::class, 'destroy'])
            ->middleware("module.permission:eliminar,{$usuarios}")->name('destroy');
        // Si esta queda abierta, cualquier usuario autenticado se asigna permisos de cualquier modulo.
        Route::post('/{id}/permisos', [UsuarioController::class, 'updatePermiso'])
            ->middleware("module.permission:modificar,{$usuarios}")->name('permisos.update');
    });

    Route::prefix('utileria')->name('utileria.')->group(function () use (
        $utileria, $modulos, $cargarCatalogos, $cargarPlaneacion
    ) {
        Route::get('/', [UsuarioController::class, 'showSubModulosNivel3'])
            ->defaults('moduloPadre', '909')
            ->where('moduloPadre', '909')
            ->middleware("module.permission:acceso,{$utileria}")
            ->name('index');

        Route::prefix('modulos')->name('modulos.')->controller(ModulosController::class)
            ->group(function () use ($modulos) {
                Route::get('/', 'index')
                    ->middleware("module.permission:acceso,{$modulos}")->name('index');
                Route::post('/', 'store')
                    ->middleware("module.permission:crear,{$modulos}")->name('store');
                Route::put('/{id}', 'update')->whereNumber('id')
                    ->middleware("module.permission:modificar,{$modulos}")->name('update');
                Route::delete('/{id}', 'destroy')->whereNumber('id')
                    ->middleware("module.permission:eliminar,{$modulos}")->name('destroy');
                Route::post('/{id}/toggle-acceso', 'toggleAcceso')->whereNumber('id')
                    ->middleware("module.permission:modificar,{$modulos}")->name('toggle.acceso');
                Route::post('/{id}/toggle-permiso', 'togglePermiso')->whereNumber('id')
                    ->middleware("module.permission:modificar,{$modulos}")->name('toggle.permiso');
                Route::post('/{id}/sincronizar-permisos', 'sincronizarPermisos')->whereNumber('id')
                    ->middleware("module.permission:modificar,{$modulos}")->name('sincronizar.permisos');
                Route::get('/{modulo}/duplicar', 'duplicar')->whereNumber('modulo')
                    ->middleware("module.permission:crear,{$modulos}")->name('duplicar');
            });

        Route::middleware("module.permission:acceso,{$modulos}")->group(function () {
            Route::get('/api/modulos/nivel/{nivel}', [ModulosController::class, 'getModulosPorNivel'])
                ->whereNumber('nivel')->name('api.modulos.nivel');
            Route::get('/api/modulos/submodulos/{dependencia}', [ModulosController::class, 'getSubmodulos'])
                ->whereNumber('dependencia')->name('api.modulos.submodulos');
        });

        Route::view('/cargarcatalogos', 'modulos/cargar-catalogos')
            ->middleware("module.permission:acceso,{$cargarCatalogos}")->name('cargar-catalogos');
        Route::get('/cargarplaneacion', [ConfiguracionController::class, 'cargarPlaneacion'])
            ->middleware("module.permission:acceso,{$cargarPlaneacion}")->name('cargar-planeacion');
        Route::post('/cargarplaneacion/upload', [ConfiguracionController::class, 'procesarExcel'])
            ->middleware("module.permission:crear,{$cargarPlaneacion}")->name('cargar-planeacion.upload');
        Route::post('/cargarplaneacion/upload-update', [ConfiguracionController::class, 'procesarExcelUpdate'])
            ->middleware("module.permission:modificar,{$cargarPlaneacion}")->name('cargar-planeacion.upload-update');
    });

    Route::get('/cargar-planeacion', [ConfiguracionController::class, 'cargarPlaneacion'])
        ->middleware("module.permission:acceso,{$cargarPlaneacion}")->name('cargar.planeacion');
    Route::post('/cargar-planeacion/upload', [ConfiguracionController::class, 'procesarExcel'])
        ->middleware("module.permission:crear,{$cargarPlaneacion}")->name('cargar.planeacion.upload');

    Route::get('/modulos', [ModulosController::class, 'index'])
        ->middleware("module.permission:acceso,{$modulos}")->name('modulos.index');

    // No tiene modulo propio en SYSRoles; updateProductivo cambia la BD productiva.
    Route::get('/basededatos', [BaseDeDatosController::class, 'index'])
        ->middleware("module.permission:acceso,{$config}")->name('basededatos');
    Route::post('/basededatos/update-productivo', [BaseDeDatosController::class, 'updateProductivo'])
        ->middleware("module.permission:modificar,{$config}")->name('basededatos.update-productivo');

    Route::get('/departamentos', [DepartamentosController::class, 'index'])
        ->middleware("module.permission:acceso,{$departamentos}")->name('departamentos');
    Route::post('/departamentos', [DepartamentosController::class, 'store'])
        ->middleware("module.permission:crear,{$departamentos}")->name('departamentos.store');
    Route::put('/departamentos/{id}', [DepartamentosController::class, 'update'])->whereNumber('id')
        ->middleware("module.permission:modificar,{$departamentos}")->name('departamentos.update');
    Route::delete('/departamentos/{id}', [DepartamentosController::class, 'destroy'])->whereNumber('id')
        ->middleware("module.permission:eliminar,{$departamentos}")->name('departamentos.destroy');

    Route::get('/secuencia-de-folios', [SecuenciaFoliosController::class, 'index'])
        ->middleware("module.permission:acceso,{$folios}")->name('secuencia-folios');
    Route::post('/secuencia-de-folios', [SecuenciaFoliosController::class, 'store'])
        ->middleware("module.permission:crear,{$folios}")->name('secuencia-folios.store');
    Route::put('/secuencia-de-folios/{id}', [SecuenciaFoliosController::class, 'update'])->whereNumber('id')
        ->middleware("module.permission:modificar,{$folios}")->name('secuencia-folios.update');
    Route::delete('/secuencia-de-folios/{id}', [SecuenciaFoliosController::class, 'destroy'])->whereNumber('id')
        ->middleware("module.permission:eliminar,{$folios}")->name('secuencia-folios.destroy');

    Route::get('/mensajes', [MensajesController::class, 'index'])
        ->middleware("module.permission:acceso,{$mensajes}")->name('mensajes');
    Route::post('/mensajes', [MensajesController::class, 'store'])
        ->middleware("module.permission:crear,{$mensajes}")->name('mensajes.store');
    Route::put('/mensajes/{id}', [MensajesController::class, 'update'])->whereNumber('id')
        ->middleware("module.permission:modificar,{$mensajes}")->name('mensajes.update');
    Route::delete('/mensajes/{id}', [MensajesController::class, 'destroy'])->whereNumber('id')
        ->middleware("module.permission:eliminar,{$mensajes}")->name('mensajes.destroy');
    Route::get('/mensajes/{id}/obtener-chat-ids', [MensajesController::class, 'obtenerChatIds'])->whereNumber('id')
        ->middleware("module.permission:acceso,{$mensajes}")->name('mensajes.obtener-chat-ids');
    Route::put('/mensajes/{id}/chat-id', [MensajesController::class, 'actualizarChatId'])->whereNumber('id')
        ->middleware("module.permission:modificar,{$mensajes}")->name('mensajes.actualizar-chat-id');
});
