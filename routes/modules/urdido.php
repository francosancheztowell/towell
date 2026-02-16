<?php

use App\Http\Controllers\PDFController;
use App\Http\Controllers\Urdido\BPMUrdido\UrdBpmController;
use App\Http\Controllers\Urdido\BPMUrdido\UrdBpmLineController;
use App\Http\Controllers\Urdido\Configuracion\ActividadesBPMUrdido\UrdActividadesBpmController;
use App\Http\Controllers\Urdido\Configuracion\CatalogosJulios\CatalogosUrdidoController;
use App\Http\Controllers\Urdido\Configuracion\ModuloProduccionUrdidoController;
use App\Http\Controllers\Urdido\ProgramaUrdido\EditarOrdenesProgramadasController;
use App\Http\Controllers\Urdido\ProgramaUrdido\ProgramarUrdidoController;
use App\Http\Controllers\Urdido\ReportesUrdidoController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::get('/urdido/{moduloPrincipal?}', [UsuarioController::class, 'showSubModulos'])
    ->defaults('moduloPrincipal', 'urdido')
    ->where('moduloPrincipal', 'urdido')
    ->name('urdido.index');

Route::prefix('urdido')->name('urdido.')->group(function () {
    // Reportes Urdido: selector + reportes individuales
    Route::get('/reportesurdido', [ReportesUrdidoController::class, 'index'])->name('reportes.urdido');
    Route::get('/reportesurdido/03-oee-urd-eng', [ReportesUrdidoController::class, 'reporte03Oee'])->name('reportes.urdido.03-oee');
    Route::get('/reportesurdido/kaizen', [ReportesUrdidoController::class, 'reporteKaizen'])->name('reportes.urdido.kaizen');
    Route::get('/reportesurdido/kaizen/excel', [ReportesUrdidoController::class, 'exportarKaizenExcel'])->name('reportes.urdido.kaizen.excel');
    Route::get('/reportesurdido/roturas-millon', [ReportesUrdidoController::class, 'reporteRoturas'])->name('reportes.urdido.roturas');
    Route::get('/reportesurdido/roturas-millon/excel', [ReportesUrdidoController::class, 'exportarRoturasExcel'])->name('reportes.urdido.roturas.excel');
    Route::get('/reportesurdido/bpm-urdido', [ReportesUrdidoController::class, 'reporteBpm'])->name('reportes.urdido.bpm');
    Route::get('/reportesurdido/bpm-urdido/excel', [ReportesUrdidoController::class, 'exportarBpmExcel'])->name('reportes.urdido.bpm.excel');
    Route::get('/reportesurdido/exportar-excel', [ReportesUrdidoController::class, 'exportarExcel'])->name('reportes.urdido.excel');

    Route::get('/configuracion/{moduloPadre?}', [UsuarioController::class, 'showSubModulosNivel3'])
        ->defaults('moduloPadre', '304')
        ->where('moduloPadre', '304')
        ->name('configuracion');

    Route::get('/programaurdido', [ProgramarUrdidoController::class, 'index'])->name('programa.urdido');
    Route::redirect('/programaurdido/produccionurdido', '/urdido/modulo-produccion-urdido', 301);

    Route::redirect('/bpmbuenaspracticasmanufacturaurd', '/urd-bpm', 301);
    Route::redirect('/bpm', '/urd-bpm', 301);

    Route::get('/configuracion/actividadesbpmurdido', [UrdActividadesBpmController::class, 'index'])
        ->name('configuracion.actividades-bpm');
    Route::get('/configuracion/actividades-bpm', [UrdActividadesBpmController::class, 'index'])
        ->name('configuracion.actividades-bpm.legacy');
    Route::get('/configuracion/catalogosjulios', [CatalogosUrdidoController::class, 'catalogosJulios'])
        ->name('configuracion.catalogos-julios');

    Route::get('/configuracion/catalogosmaquinas', [CatalogosUrdidoController::class, 'catalogoMaquinas'])
        ->name('configuracion.catalogos-maquinas');

    Route::get('/programar-urdido', [ProgramarUrdidoController::class, 'index'])->name('programar.urdido');
    Route::get('/programar-urdido/ordenes', [ProgramarUrdidoController::class, 'getOrdenes'])->name('programar.urdido.ordenes');
    Route::get('/programar-urdido/todas-ordenes', [ProgramarUrdidoController::class, 'getTodasOrdenes'])->name('programar.urdido.todas.ordenes');
    Route::get('/programar-urdido/verificar-en-proceso', [ProgramarUrdidoController::class, 'verificarOrdenEnProceso'])->name('programar.urdido.verificar.en.proceso');
    Route::post('/programar-urdido/intercambiar-prioridad', [ProgramarUrdidoController::class, 'intercambiarPrioridad'])->name('programar.urdido.intercambiar.prioridad');
    Route::post('/programar-urdido/actualizar-prioridades', [ProgramarUrdidoController::class, 'actualizarPrioridades'])->name('programar.urdido.actualizar.prioridades');
    Route::post('/programar-urdido/guardar-observaciones', [ProgramarUrdidoController::class, 'guardarObservaciones'])->name('programar.urdido.guardar.observaciones');
    Route::post('/programar-urdido/actualizar-status', [ProgramarUrdidoController::class, 'actualizarStatus'])->name('programar.urdido.actualizar.status');
    Route::get('/reimpresion-urdido', [ProgramarUrdidoController::class, 'reimpresionFinalizadas'])->name('reimpresion.finalizadas');
    Route::get('/reimpresion-urdido/ventana-imprimir', [ProgramarUrdidoController::class, 'reimpresionVentanaImprimir'])->name('reimpresion.urdido.ventana.imprimir');

    Route::get('/editar-ordenes-programadas', [EditarOrdenesProgramadasController::class, 'index'])->name('editar.ordenes.programadas');
    Route::post('/editar-ordenes-programadas/actualizar', [EditarOrdenesProgramadasController::class, 'actualizar'])->name('editar.ordenes.programadas.actualizar');
    Route::get('/editar-ordenes-programadas/obtener-orden', [EditarOrdenesProgramadasController::class, 'obtenerOrden'])->name('editar.ordenes.programadas.obtener.orden');
    Route::post('/editar-ordenes-programadas/actualizar-julios', [EditarOrdenesProgramadasController::class, 'actualizarJulios'])->name('editar.ordenes.programadas.actualizar.julios');

    Route::get('/catalogos-julios', [CatalogosUrdidoController::class, 'catalogosJulios'])->name('catalogos.julios');
    Route::post('/catalogos-julios', [CatalogosUrdidoController::class, 'storeJulio'])->name('catalogos.julios.store');
    Route::put('/catalogos-julios/{id}', [CatalogosUrdidoController::class, 'updateJulio'])->name('catalogos.julios.update');
    Route::delete('/catalogos-julios/{id}', [CatalogosUrdidoController::class, 'destroyJulio'])->name('catalogos.julios.destroy');
    Route::get('/catalogo-maquinas', [CatalogosUrdidoController::class, 'catalogoMaquinas'])->name('catalogo.maquinas');
    Route::post('/catalogo-maquinas', [CatalogosUrdidoController::class, 'storeMaquina'])->name('catalogo.maquinas.store');
    Route::put('/catalogo-maquinas/{maquinaId}', [CatalogosUrdidoController::class, 'updateMaquina'])->name('catalogo.maquinas.update');
    Route::delete('/catalogo-maquinas/{maquinaId}', [CatalogosUrdidoController::class, 'destroyMaquina'])->name('catalogo.maquinas.destroy');

    Route::get('/modulo-produccion-urdido', [ModuloProduccionUrdidoController::class, 'index'])->name('modulo.produccion.urdido');
    Route::get('/modulo-produccion-urdido/catalogos-julios', [ModuloProduccionUrdidoController::class, 'getCatalogosJulios'])->name('modulo.produccion.urdido.catalogos.julios');
    Route::get('/modulo-produccion-urdido/hilos-by-julio', [ModuloProduccionUrdidoController::class, 'getHilosByJulio'])->name('modulo.produccion.urdido.hilos.by.julio');
    Route::get('/modulo-produccion-urdido/usuarios-urdido', [ModuloProduccionUrdidoController::class, 'getUsuariosUrdido'])->name('modulo.produccion.urdido.usuarios.urdido');
    Route::post('/modulo-produccion-urdido/guardar-oficial', [ModuloProduccionUrdidoController::class, 'guardarOficial'])->name('modulo.produccion.urdido.guardar.oficial');
    Route::post('/modulo-produccion-urdido/eliminar-oficial', [ModuloProduccionUrdidoController::class, 'eliminarOficial'])->name('modulo.produccion.urdido.eliminar.oficial');
    Route::post('/modulo-produccion-urdido/actualizar-turno-oficial', [ModuloProduccionUrdidoController::class, 'actualizarTurnoOficial'])->name('modulo.produccion.urdido.actualizar.turno.oficial');
    Route::post('/modulo-produccion-urdido/actualizar-fecha', [ModuloProduccionUrdidoController::class, 'actualizarFecha'])->name('modulo.produccion.urdido.actualizar.fecha');
    Route::post('/modulo-produccion-urdido/actualizar-julio-tara', [ModuloProduccionUrdidoController::class, 'actualizarJulioTara'])->name('modulo.produccion.urdido.actualizar.julio.tara');
    Route::post('/modulo-produccion-urdido/actualizar-kg-bruto', [ModuloProduccionUrdidoController::class, 'actualizarKgBruto'])->name('modulo.produccion.urdido.actualizar.kg.bruto');
    Route::post('/modulo-produccion-urdido/actualizar-campos-produccion', [ModuloProduccionUrdidoController::class, 'actualizarCamposProduccion'])->name('modulo.produccion.urdido.actualizar.campos.produccion');
    Route::post('/modulo-produccion-urdido/actualizar-horas', [ModuloProduccionUrdidoController::class, 'actualizarHoras'])->name('modulo.produccion.urdido.actualizar.horas');
    Route::post('/modulo-produccion-urdido/finalizar', [ModuloProduccionUrdidoController::class, 'finalizar'])->name('modulo.produccion.urdido.finalizar');
    Route::post('/modulo-produccion-urdido/marcar-listo', [ModuloProduccionUrdidoController::class, 'marcarListo'])->name('modulo.produccion.urdido.marcar.listo');
    Route::get('/modulo-produccion-urdido/pdf', [PDFController::class, 'generarPDFUrdidoEngomado'])->name('modulo.produccion.urdido.pdf');
});

Route::resource('urd-actividades-bpm', UrdActividadesBpmController::class)
    ->parameters(['urd-actividades-bpm' => 'urdActividadesBpm'])
    ->names('urd-actividades-bpm');

Route::resource('urd-bpm', UrdBpmController::class)
    ->parameters(['urd-bpm' => 'id'])
    ->names('urd-bpm');

Route::get('urd-bpm-line/{folio}', [UrdBpmLineController::class, 'index'])->name('urd-bpm-line.index');
Route::post('urd-bpm-line/{folio}/toggle', [UrdBpmLineController::class, 'toggleActividad'])->name('urd-bpm-line.toggle');
Route::patch('urd-bpm-line/{folio}/terminar', [UrdBpmLineController::class, 'terminar'])->name('urd-bpm-line.terminar');
Route::patch('urd-bpm-line/{folio}/autorizar', [UrdBpmLineController::class, 'autorizar'])->name('urd-bpm-line.autorizar');
Route::patch('urd-bpm-line/{folio}/rechazar', [UrdBpmLineController::class, 'rechazar'])->name('urd-bpm-line.rechazar');
