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
    // Atado de Julio
    Route::get('/atadodejulio', [NotificarMontadoJulioController::class, 'index'])->name('notificar.atado.julio');
    Route::get('/atadodejulio/telares', [NotificarMontadoJulioController::class, 'telares'])->name('notificar.atado.julio.telares');
    Route::get('/atadodejulio/detalle', [NotificarMontadoJulioController::class, 'detalle'])->name('notificar.atado.julio.detalle');
    Route::post('/atadodejulio/notificar', [NotificarMontadoJulioController::class, 'notificar'])->name('notificar.atado.julio.notificar');

    // Cortado de Rollo
    Route::get('/cortadoderollo', [NotificarMontRollosController::class, 'index'])->name('notificar.cortado.rollo');
    Route::get('/cortadoderollo/telares', [NotificarMontRollosController::class, 'telares'])->name('notificar.cortado.rollo.telares');
    Route::get('/cortadoderollo/detalle', [NotificarMontRollosController::class, 'detalle'])->name('notificar.cortado.rollo.detalle');
    Route::post('/cortadoderollo/notificar', [NotificarMontRollosController::class, 'notificar'])->name('notificar.cortado.rollo.notificar');
    Route::get('/cortadoderollo/orden-produccion', [NotificarMontRollosController::class, 'getOrdenProduccion'])->name('notificar.cortado.rollo.orden.produccion');
    Route::get('/cortadoderollo/datos-produccion', [NotificarMontRollosController::class, 'getDatosProduccion'])->name('notificar.cortado.rollo.datos.produccion');
    Route::post('/cortadoderollo/insertar', [NotificarMontRollosController::class, 'insertarMarbetes'])->name('notificar.cortado.rollo.insertar');

    // Redirects legacy
    Route::redirect('/notificarmontadodejulio', '/tejedores/atadodejulio', 301);
    Route::redirect('/notificarmontadodejulio/telares', '/tejedores/atadodejulio/telares', 301);
    Route::redirect('/notificarmontadodejulio/detalle', '/tejedores/atadodejulio/detalle', 301);
    Route::redirect('/notificarmontadodejulio/notificar', '/tejedores/atadodejulio/notificar', 301);
    Route::redirect('/notificarcortadoderollo', '/tejedores/cortadoderollo', 301);
    Route::redirect('/notificarcortadoderollo/telares', '/tejedores/cortadoderollo/telares', 301);
    Route::redirect('/notificarcortadoderollo/detalle', '/tejedores/cortadoderollo/detalle', 301);
    Route::redirect('/notificarcortadoderollo/notificar', '/tejedores/cortadoderollo/notificar', 301);
    Route::redirect('/notificarcortadoderollo/orden-produccion', '/tejedores/cortadoderollo/orden-produccion', 301);
    Route::redirect('/notificarcortadoderollo/datos-produccion', '/tejedores/cortadoderollo/datos-produccion', 301);
    Route::redirect('/notificarcortadoderollo/insertar', '/tejedores/cortadoderollo/insertar', 301);

    Route::redirect('/notificar-montado-julios', '/tejedores/atadodejulio', 301);
    Route::redirect('/notificar-montado-julios/telares', '/tejedores/atadodejulio/telares', 301);
    Route::redirect('/notificar-montado-julios/detalle', '/tejedores/atadodejulio/detalle', 301);
    Route::redirect('/notificar-montado-julios/notificar', '/tejedores/atadodejulio/notificar', 301);
    Route::redirect('/notificar-mont-rollos', '/tejedores/cortadoderollo', 301);
    Route::redirect('/notificar-mont-rollos/notificar', '/tejedores/cortadoderollo/notificar', 301);
    Route::redirect('/notificar-mont-rollos/orden-produccion', '/tejedores/cortadoderollo/orden-produccion', 301);
    Route::redirect('/notificar-mont-rollos/datos-produccion', '/tejedores/cortadoderollo/datos-produccion', 301);
    Route::redirect('/notificar-mont-rollos/insertar', '/tejedores/cortadoderollo/insertar', 301);
});

// Legacy URL: mantener rutas tel-bpm.* pero no mostrar el listado por /tel-bpm
// (La ruta real de navegación es /tejedores/bpmtejedores)
Route::redirect('/tel-bpm', '/tejedores/bpmtejedores', 301);

Route::resource('tel-actividades-bpm', TelActividadesBPMController::class)
    ->parameters(['tel-actividades-bpm' => 'telActividadesBPM'])
    ->names('tel-actividades-bpm');

Route::resource('tel-telares-operador', TelTelaresOperadorController::class)
    ->parameters(['tel-telares-operador' => 'telTelaresOperador'])
    ->names('tel-telares-operador');

Route::get('/telaresPorOperador', [TelTelaresOperadorController::class, 'index'])->name('telaresPorOperador');
Route::get('/ActividadesBPM', [TelActividadesBPMController::class, 'index'])->name('ActividadesBPM');

// Ruta de depuración ANTES del resource para que no la capture tel-bpm/{folio}
Route::get('tel-bpm/log-debug', [TelBpmController::class, 'logDebug'])->name('tel-bpm.log-debug');

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
        Route::get('/verificar-estado', 'verificarEstado')->name('verificar.estado');
        Route::delete('/eliminar', 'destroy')->name('destroy');
        Route::post('/actualizar-fecha', 'updateFecha')->name('actualizar.fecha');
        Route::get('/verificar-turnos-ocupados', 'verificarTurnosOcupados')->name('verificar.turnos.ocupados');
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
