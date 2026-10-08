<?php

use App\Http\Controllers\PDFController;
use App\Http\Controllers\Urdido\Configuracion\CatalogosJulios\CatalogosUrdidoController;
use App\Http\Controllers\Urdido\Configuracion\ModuloProduccionUrdidoController;
use App\Http\Controllers\Urdido\ListaMaterialesController;
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
    // Lista de materiales (Urdbom): listado + CRUD en el componente Livewire Urdido\ListaMateriales
    Route::get('/lmaturdido', [ListaMaterialesController::class, 'index'])
        ->middleware('module.permission:acceso,205')->name('lista-materiales');

    // Reportes Urdido: selector + reportes individuales
    Route::get('/reportesurdido', [ReportesUrdidoController::class, 'index'])->name('reportes.urdido');
    Route::get('/reportesurdido/03-oee-urd-eng', [ReportesUrdidoController::class, 'reporte03Oee'])->name('reportes.urdido.03-oee');
    Route::get('/reportesurdido/kaizen', [ReportesUrdidoController::class, 'reporteKaizen'])->name('reportes.urdido.kaizen');
    Route::get('/reportesurdido/kaizen/excel', [ReportesUrdidoController::class, 'exportarKaizenExcel'])->name('reportes.urdido.kaizen.excel');
    Route::get('/reportesurdido/roturas-millon', [ReportesUrdidoController::class, 'reporteRoturas'])->name('reportes.urdido.roturas');
    Route::get('/reportesurdido/roturas-millon/excel', [ReportesUrdidoController::class, 'exportarRoturasExcel'])->name('reportes.urdido.roturas.excel');
    Route::get('/reportesurdido/bpm-urdido', [ReportesUrdidoController::class, 'reporteBpm'])->name('reportes.urdido.bpm');
    Route::get('/reportesurdido/bpm-urdido/excel', [ReportesUrdidoController::class, 'exportarBpmExcel'])->name('reportes.urdido.bpm.excel');
    Route::get('/reportesurdido/resumen', [ReportesUrdidoController::class, 'reporteResumen'])->name('reportes.urdido.resumen');
    Route::get('/reportesurdido/resumen/excel', [ReportesUrdidoController::class, 'exportarResumenExcel'])->name('reportes.urdido.resumen.excel');
    Route::get('/reportesurdido/panel-control', [ReportesUrdidoController::class, 'reportePanelControl'])->name('reportes.urdido.panel-control');
    Route::get('/reportesurdido/panel-control/excel', [ReportesUrdidoController::class, 'exportarPanelControlExcel'])->name('reportes.urdido.panel-control.excel');
    Route::get('/reportesurdido/exportar-excel', [ReportesUrdidoController::class, 'exportarExcel'])->name('reportes.urdido.excel');

    Route::get('/configuracion/{moduloPadre?}', [UsuarioController::class, 'showSubModulosNivel3'])
        ->defaults('moduloPadre', '304')
        ->where('moduloPadre', '304')
        ->name('configuracion');

    Route::get('/programaurdido', [ProgramarUrdidoController::class, 'index'])->name('programa.urdido');
    Route::redirect('/programaurdido/produccionurdido', '/urdido/modulo-produccion-urdido', 301);

    Route::redirect('/bpmbuenaspracticasmanufacturaurd', '/urd-bpm', 301);
    Route::redirect('/bpm', '/urd-bpm', 301);

    Route::get('/configuracion/catalogosjulios', [CatalogosUrdidoController::class, 'catalogosJulios'])
        ->name('configuracion.catalogos-julios');

    Route::get('/configuracion/catalogosmaquinas', [CatalogosUrdidoController::class, 'catalogoMaquinas'])
        ->name('configuracion.catalogos-maquinas');

    Route::get('/programar-urdido', [ProgramarUrdidoController::class, 'index'])->name('programar.urdido');
    Route::redirect('/programar-urdido/legacy', '/urdido/programar-urdido', 301)->name('programar.urdido.legacy');
    Route::redirect('/programar-urdido/livewire', '/urdido/programar-urdido', 301)->name('programar.urdido.livewire');
    Route::get('/programar-urdido/ordenes', [ProgramarUrdidoController::class, 'getOrdenes'])->name('programar.urdido.ordenes');
    Route::get('/programar-urdido/todas-ordenes', [ProgramarUrdidoController::class, 'getTodasOrdenes'])->name('programar.urdido.todas.ordenes');
    Route::post('/programar-urdido/intercambiar-prioridad', [ProgramarUrdidoController::class, 'intercambiarPrioridad'])->name('programar.urdido.intercambiar.prioridad');
    Route::post('/programar-urdido/actualizar-prioridades', [ProgramarUrdidoController::class, 'actualizarPrioridades'])->name('programar.urdido.actualizar.prioridades');
    Route::post('/programar-urdido/guardar-observaciones', [ProgramarUrdidoController::class, 'guardarObservaciones'])->name('programar.urdido.guardar.observaciones');
    Route::post('/programar-urdido/marcar-incorrecto', [ProgramarUrdidoController::class, 'marcarIncorrecto'])->name('programar.urdido.marcar.incorrecto');
    Route::post('/programar-urdido/actualizar-calidad', [ProgramarUrdidoController::class, 'actualizarCalidad'])->name('programar.urdido.actualizar.calidad');
    Route::post('/programar-urdido/actualizar-status', [ProgramarUrdidoController::class, 'actualizarStatus'])->name('programar.urdido.actualizar.status');
    Route::get('/reimpresion-urdido', [ProgramarUrdidoController::class, 'reimpresionFinalizadas'])->name('reimpresion.finalizadas');
    Route::get('/reimpresion-urdido/ventana-imprimir', [ProgramarUrdidoController::class, 'reimpresionVentanaImprimir'])->name('reimpresion.urdido.ventana.imprimir');

    Route::get('/editar-ordenes-programadas', [EditarOrdenesProgramadasController::class, 'index'])->name('editar.ordenes.programadas');

    Route::get('/catalogos-julios', [CatalogosUrdidoController::class, 'catalogosJulios'])->name('catalogos.julios');
    // Alta/edición/borrado de julios y máquinas: en los componentes Livewire Urdido\CatalogoJulios y CatalogoMaquinas.
    Route::get('/catalogo-maquinas', [CatalogosUrdidoController::class, 'catalogoMaquinas'])->name('catalogo.maquinas');

    Route::get('/modulo-produccion-urdido', [ModuloProduccionUrdidoController::class, 'index'])->name('modulo.produccion.urdido');
    Route::get('/modulo-produccion-urdido/catalogos-julios', [ModuloProduccionUrdidoController::class, 'getCatalogosJulios'])->name('modulo.produccion.urdido.catalogos.julios');
    Route::get('/modulo-produccion-urdido/usuarios-urdido', [ModuloProduccionUrdidoController::class, 'getUsuariosUrdido'])->name('modulo.produccion.urdido.usuarios.urdido');
    Route::post('/modulo-produccion-urdido/guardar-oficial', [ModuloProduccionUrdidoController::class, 'guardarOficial'])->middleware('module.permission:modificar,154,auditar')->name('modulo.produccion.urdido.guardar.oficial'); // Producción Urdido
    Route::post('/modulo-produccion-urdido/eliminar-oficial', [ModuloProduccionUrdidoController::class, 'eliminarOficial'])
        ->middleware('module.permission:eliminar,154')->name('modulo.produccion.urdido.eliminar.oficial'); // Producción Urdido
    Route::post('/modulo-produccion-urdido/actualizar-turno-oficial', [ModuloProduccionUrdidoController::class, 'actualizarTurnoOficial'])->middleware('module.permission:modificar,154,auditar')->name('modulo.produccion.urdido.actualizar.turno.oficial'); // Producción Urdido
    Route::post('/modulo-produccion-urdido/actualizar-fecha', [ModuloProduccionUrdidoController::class, 'actualizarFecha'])->middleware('module.permission:modificar,154,auditar')->name('modulo.produccion.urdido.actualizar.fecha'); // Producción Urdido
    Route::post('/modulo-produccion-urdido/actualizar-julio-tara', [ModuloProduccionUrdidoController::class, 'actualizarJulioTara'])->middleware('module.permission:modificar,154,auditar')->name('modulo.produccion.urdido.actualizar.julio.tara'); // Producción Urdido
    Route::post('/modulo-produccion-urdido/actualizar-kg-bruto', [ModuloProduccionUrdidoController::class, 'actualizarKgBruto'])->middleware('module.permission:modificar,154,auditar')->name('modulo.produccion.urdido.actualizar.kg.bruto'); // Producción Urdido
    Route::post('/modulo-produccion-urdido/actualizar-campos-produccion', [ModuloProduccionUrdidoController::class, 'actualizarCamposProduccion'])->middleware('module.permission:modificar,154,auditar')->name('modulo.produccion.urdido.actualizar.campos.produccion'); // Producción Urdido
    Route::post('/modulo-produccion-urdido/actualizar-horas', [ModuloProduccionUrdidoController::class, 'actualizarHoras'])->middleware('module.permission:modificar,154,auditar')->name('modulo.produccion.urdido.actualizar.horas'); // Producción Urdido
    Route::post('/modulo-produccion-urdido/finalizar', [ModuloProduccionUrdidoController::class, 'finalizar'])
        ->middleware('module.permission:modificar,154')->name('modulo.produccion.urdido.finalizar'); // Producción Urdido
    Route::post('/modulo-produccion-urdido/marcar-listo', [ModuloProduccionUrdidoController::class, 'marcarListo'])->middleware('module.permission:modificar,154,auditar')->name('modulo.produccion.urdido.marcar.listo'); // Producción Urdido
    Route::get('/modulo-produccion-urdido/pdf', [PDFController::class, 'generarPDFUrdidoEngomado'])->name('modulo.produccion.urdido.pdf');
});
