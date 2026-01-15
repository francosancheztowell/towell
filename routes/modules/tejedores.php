<?php

use App\Http\Controllers\Tejedores\BPMTejedores\TelBpmController;
use App\Http\Controllers\Tejedores\BPMTejedores\TelBpmLineController;
use App\Http\Controllers\Tejedores\Configuracion\CatDesarrolladores\catDesarrolladoresController;
use App\Http\Controllers\Tejedores\Configuracion\TelaresOperador\TelTelaresOperadorController;
use App\Http\Controllers\Tejedores\Desarrolladores\TelDesarrolladoresController;
use App\Http\Controllers\Tejedores\InventarioTelaresController;
use App\Http\Controllers\Tejedores\NotificarMontadoJulios\NotificarMontadoJulioController;
use App\Http\Controllers\Tejedores\NotificarMontadoRollo\NotificarMontRollosController;
use App\Http\Controllers\Tejedores\TelActividadesBPMController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::get('/tejedores/{moduloPrincipal?}', [UsuarioController::class, 'showSubModulos'])
    ->defaults('moduloPrincipal', 'tejedores')
    ->where('moduloPrincipal', 'tejedores')
    ->name('tejedores.index');

Route::prefix('tejedores')->name('tejedores.')->group(function () {
    Route::get('/configurar', [UsuarioController::class, 'showTejedoresConfiguracion'])->name('configurar');

    Route::get('/configurar/telaresxoperador', [TelTelaresOperadorController::class, 'index'])->name('configurar.telares-operador');
    Route::get('/configurar/catalogodesarrolladores', [catDesarrolladoresController::class, 'index'])->name('configurar.catalogo-desarrolladores');
    Route::get('/configurar/actividadestejedores', [TelActividadesBPMController::class, 'index'])->name('configurar.actividades');

    Route::get('/bpmtejedores', [TelBpmController::class, 'index'])->name('bpm');
    Route::redirect('/bpm', '/tejedores/bpmtejedores', 301);

    Route::get('/desarrolladores', [TelDesarrolladoresController::class, 'index'])->name('desarrolladores');
});

Route::prefix('tejedores')->group(function () {
    Route::get('/notificarmontadodejulio', [NotificarMontadoJulioController::class, 'index'])->name('notificar.montado.julios');
    Route::get('/notificarmontadodejulio/telares', [NotificarMontadoJulioController::class, 'telares'])->name('notificar.montado.julios.telares');
    Route::get('/notificarmontadodejulio/detalle', [NotificarMontadoJulioController::class, 'detalle'])->name('notificar.montado.julios.detalle');
    Route::post('/notificarmontadodejulio/notificar', [NotificarMontadoJulioController::class, 'notificar'])->name('notificar.montado.julios.notificar');

    Route::get('/notificarcortadoderollo', [NotificarMontRollosController::class, 'index'])->name('notificar.mont.rollos');
    Route::get('/notificarcortadoderollo/telares', [NotificarMontRollosController::class, 'telares'])->name('notificar.mont.rollos.telares');
    Route::get('/notificarcortadoderollo/detalle', [NotificarMontRollosController::class, 'detalle'])->name('notificar.mont.rollos.detalle');
    Route::post('/notificarcortadoderollo/notificar', [NotificarMontRollosController::class, 'notificar'])->name('notificar.mont.rollos.notificar');
    Route::get('/notificarcortadoderollo/orden-produccion', [NotificarMontRollosController::class, 'getOrdenProduccion'])->name('notificar.mont.rollos.orden.produccion');
    Route::get('/notificarcortadoderollo/datos-produccion', [NotificarMontRollosController::class, 'getDatosProduccion'])->name('notificar.mont.rollos.datos.produccion');
    Route::post('/notificarcortadoderollo/insertar', [NotificarMontRollosController::class, 'insertarMarbetes'])->name('notificar.mont.rollos.insertar');

    Route::redirect('/notificar-montado-julios', '/tejedores/notificarmontadodejulio', 301);
    Route::redirect('/notificar-montado-julios/telares', '/tejedores/notificarmontadodejulio/telares', 301);
    Route::redirect('/notificar-montado-julios/detalle', '/tejedores/notificarmontadodejulio/detalle', 301);
    Route::redirect('/notificar-montado-julios/notificar', '/tejedores/notificarmontadodejulio/notificar', 301);
    Route::redirect('/notificar-mont-rollos', '/tejedores/notificarcortadoderollo', 301);
    Route::redirect('/notificar-mont-rollos/notificar', '/tejedores/notificarcortadoderollo/notificar', 301);
    Route::redirect('/notificar-mont-rollos/orden-produccion', '/tejedores/notificarcortadoderollo/orden-produccion', 301);
    Route::redirect('/notificar-mont-rollos/datos-produccion', '/tejedores/notificarcortadoderollo/datos-produccion', 301);
    Route::redirect('/notificar-mont-rollos/insertar', '/tejedores/notificarcortadoderollo/insertar', 301);
});

Route::resource('tel-actividades-bpm', TelActividadesBPMController::class)
    ->parameters(['tel-actividades-bpm' => 'telActividadesBPM'])
    ->names('tel-actividades-bpm');

Route::resource('tel-telares-operador', TelTelaresOperadorController::class)
    ->parameters(['tel-telares-operador' => 'telTelaresOperador'])
    ->names('tel-telares-operador');

Route::get('/telaresPorOperador', [TelTelaresOperadorController::class, 'index'])->name('telaresPorOperador');
Route::get('/ActividadesBPM', [TelActividadesBPMController::class, 'index'])->name('ActividadesBPM');

Route::resource('tel-bpm', TelBpmController::class)
    ->parameters(['tel-bpm' => 'folio'])
    ->names('tel-bpm');

Route::patch('tel-bpm/{folio}/terminar', [TelBpmLineController::class, 'finish'])->name('tel-bpm.finish');
Route::patch('tel-bpm/{folio}/autorizar', [TelBpmLineController::class, 'authorizeDoc'])->name('tel-bpm.authorize');
Route::patch('tel-bpm/{folio}/rechazar', [TelBpmLineController::class, 'reject'])->name('tel-bpm.reject');

Route::get('tel-bpm/{folio}/lineas', [TelBpmLineController::class, 'index'])->name('tel-bpm-line.index');
Route::post('tel-bpm/{folio}/lineas/toggle', [TelBpmLineController::class, 'toggle'])->name('tel-bpm-line.toggle');
Route::post('tel-bpm/{folio}/lineas/bulk-save', [TelBpmLineController::class, 'bulkSave'])->name('tel-bpm-line.bulk');
Route::post('tel-bpm/{folio}/lineas/comentarios', [TelBpmLineController::class, 'updateComentarios'])->name('tel-bpm-line.comentarios');

Route::controller(InventarioTelaresController::class)
    ->prefix('inventario-telares')->name('inventario.telares.modulo.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/guardar', 'store')->name('store');
        Route::delete('/eliminar', 'destroy')->name('destroy');
    });

Route::get('/desarrolladores', [TelDesarrolladoresController::class, 'index'])->name('desarrolladores');
Route::get('/desarrolladores/telar/{telarId}/producciones', [TelDesarrolladoresController::class, 'obtenerProducciones'])->name('desarrolladores.obtener-producciones');
Route::get('/desarrolladores/telar/{telarId}/produccion/{noProduccion}', [TelDesarrolladoresController::class, 'formularioDesarrollador'])->name('desarrolladores.formulario');
Route::get('/desarrolladores/orden/{noProduccion}/detalles', [TelDesarrolladoresController::class, 'obtenerDetallesOrden'])->name('desarrolladores.obtener-detalles-orden');
Route::get('/desarrolladores/modelo-codificado/{salonTejidoId}/{tamanoClave}', [TelDesarrolladoresController::class, 'obtenerCodigoDibujo'])->name('desarrolladores.obtener-codigo-dibujo');
Route::post('/desarrolladores', [TelDesarrolladoresController::class, 'store'])->name('desarrolladores.store');

Route::get('catalogo-desarrolladores', [catDesarrolladoresController::class, 'index'])->name('desarrolladores.catalogo-desarrolladores');
Route::post('catalogo-desarrolladores', [catDesarrolladoresController::class, 'store'])->name('cat-desarrolladores.store');
Route::put('catalogo-desarrolladores/{cat_desarrolladore}', [catDesarrolladoresController::class, 'update'])->name('cat-desarrolladores.update');
Route::delete('catalogo-desarrolladores/{cat_desarrolladore}', [catDesarrolladoresController::class, 'destroy'])->name('cat-desarrolladores.destroy');
