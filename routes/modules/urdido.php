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

    Route::get('/configuracion/actividadesbpmurdido', [UrdActividadesBpmController::class, 'index'])
        ->name('configuracion.actividades-bpm');
    Route::get('/configuracion/actividades-bpm', [UrdActividadesBpmController::class, 'index'])
        ->name('configuracion.actividades-bpm.legacy');
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
    Route::post('/editar-ordenes-programadas/actualizar', [EditarOrdenesProgramadasController::class, 'actualizar'])->name('editar.ordenes.programadas.actualizar');
    Route::get('/editar-ordenes-programadas/obtener-orden', [EditarOrdenesProgramadasController::class, 'obtenerOrden'])->name('editar.ordenes.programadas.obtener.orden');
    Route::post('/editar-ordenes-programadas/actualizar-julios', [EditarOrdenesProgramadasController::class, 'actualizarJulios'])->name('editar.ordenes.programadas.actualizar.julios');
    Route::post('/editar-ordenes-programadas/actualizar-hilos-produccion', [EditarOrdenesProgramadasController::class, 'actualizarHilosProduccion'])->name('editar.ordenes.programadas.actualizar.hilos.produccion');

    Route::get('/catalogos-julios', [CatalogosUrdidoController::class, 'catalogosJulios'])->name('catalogos.julios');
    Route::post('/catalogos-julios', [CatalogosUrdidoController::class, 'storeJulio'])
        ->middleware('module.permission:crear,37')->name('catalogos.julios.store'); // Catalogos Julios
    Route::put('/catalogos-julios/{id}', [CatalogosUrdidoController::class, 'updateJulio'])
        ->middleware('module.permission:modificar,37')->name('catalogos.julios.update'); // Catalogos Julios
    Route::delete('/catalogos-julios/{id}', [CatalogosUrdidoController::class, 'destroyJulio'])
        ->middleware('module.permission:eliminar,37')->name('catalogos.julios.destroy'); // Catalogos Julios
    Route::get('/catalogo-maquinas', [CatalogosUrdidoController::class, 'catalogoMaquinas'])->name('catalogo.maquinas');
    Route::post('/catalogo-maquinas', [CatalogosUrdidoController::class, 'storeMaquina'])
        ->middleware('module.permission:crear,156')->name('catalogo.maquinas.store'); // Catalogos Maquinas
    Route::put('/catalogo-maquinas/{maquinaId}', [CatalogosUrdidoController::class, 'updateMaquina'])
        ->middleware('module.permission:modificar,156')->name('catalogo.maquinas.update'); // Catalogos Maquinas
    Route::delete('/catalogo-maquinas/{maquinaId}', [CatalogosUrdidoController::class, 'destroyMaquina'])
        ->middleware('module.permission:eliminar,156')->name('catalogo.maquinas.destroy'); // Catalogos Maquinas

    Route::get('/modulo-produccion-urdido', [ModuloProduccionUrdidoController::class, 'index'])->name('modulo.produccion.urdido');
    Route::get('/modulo-produccion-urdido/catalogos-julios', [ModuloProduccionUrdidoController::class, 'getCatalogosJulios'])->name('modulo.produccion.urdido.catalogos.julios');
    Route::get('/modulo-produccion-urdido/usuarios-urdido', [ModuloProduccionUrdidoController::class, 'getUsuariosUrdido'])->name('modulo.produccion.urdido.usuarios.urdido');
    Route::post('/modulo-produccion-urdido/guardar-oficial', [ModuloProduccionUrdidoController::class, 'guardarOficial'])->name('modulo.produccion.urdido.guardar.oficial');
    Route::post('/modulo-produccion-urdido/eliminar-oficial', [ModuloProduccionUrdidoController::class, 'eliminarOficial'])
        ->middleware('module.permission:eliminar,154')->name('modulo.produccion.urdido.eliminar.oficial'); // Producción Urdido
    Route::post('/modulo-produccion-urdido/actualizar-turno-oficial', [ModuloProduccionUrdidoController::class, 'actualizarTurnoOficial'])->name('modulo.produccion.urdido.actualizar.turno.oficial');
    Route::post('/modulo-produccion-urdido/actualizar-fecha', [ModuloProduccionUrdidoController::class, 'actualizarFecha'])->name('modulo.produccion.urdido.actualizar.fecha');
    Route::post('/modulo-produccion-urdido/actualizar-julio-tara', [ModuloProduccionUrdidoController::class, 'actualizarJulioTara'])->name('modulo.produccion.urdido.actualizar.julio.tara');
    Route::post('/modulo-produccion-urdido/actualizar-kg-bruto', [ModuloProduccionUrdidoController::class, 'actualizarKgBruto'])->name('modulo.produccion.urdido.actualizar.kg.bruto');
    Route::post('/modulo-produccion-urdido/actualizar-campos-produccion', [ModuloProduccionUrdidoController::class, 'actualizarCamposProduccion'])->name('modulo.produccion.urdido.actualizar.campos.produccion');
    Route::post('/modulo-produccion-urdido/actualizar-horas', [ModuloProduccionUrdidoController::class, 'actualizarHoras'])->name('modulo.produccion.urdido.actualizar.horas');
    Route::post('/modulo-produccion-urdido/finalizar', [ModuloProduccionUrdidoController::class, 'finalizar'])
        ->middleware('module.permission:modificar,154')->name('modulo.produccion.urdido.finalizar'); // Producción Urdido
    Route::post('/modulo-produccion-urdido/marcar-listo', [ModuloProduccionUrdidoController::class, 'marcarListo'])->name('modulo.produccion.urdido.marcar.listo');
    Route::get('/modulo-produccion-urdido/pdf', [PDFController::class, 'generarPDFUrdidoEngomado'])->name('modulo.produccion.urdido.pdf');
});

Route::resource('urd-actividades-bpm', UrdActividadesBpmController::class)
    ->middlewareFor('store', 'module.permission:crear,144') // Actividades BPM Urdido
    ->middlewareFor('update', 'module.permission:modificar,144') // Actividades BPM Urdido
    ->middlewareFor('destroy', 'module.permission:eliminar,144') // Actividades BPM Urdido
    ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
    ->parameters(['urd-actividades-bpm' => 'urdActividadesBpm'])
    ->names('urd-actividades-bpm');

// Crear/editar el documento BPM es captura de turno: 12 personas tienen 'acceso' y no 'crear',
// y la vista no esconde el boton. Solo se gatea el borrado, igual que eng-bpm y tel-bpm.
Route::resource('urd-bpm', UrdBpmController::class)
    ->middlewareFor('destroy', 'module.permission:eliminar,35') // BPM (Buenas Practicas Manufactura) Urd
    ->only(['index', 'store', 'update', 'destroy'])
    ->parameters(['urd-bpm' => 'id'])
    ->names('urd-bpm');

Route::get('urd-bpm-line/{folio}', [UrdBpmLineController::class, 'index'])->name('urd-bpm-line.index');
Route::post('urd-bpm-line/{folio}/toggle', [UrdBpmLineController::class, 'toggleActividad'])->name('urd-bpm-line.toggle');
Route::patch('urd-bpm-line/{folio}/terminar', [UrdBpmLineController::class, 'terminar'])->name('urd-bpm-line.terminar');
// Visto bueno de supervision: 'registrar' es la convencion del repo para autorizar
// (ver app/Livewire/Mecanicos/VerificaMaquina/Show.php:177). UrdBpmLineController no valida nada.
Route::patch('urd-bpm-line/{folio}/autorizar', [UrdBpmLineController::class, 'autorizar'])
    ->middleware('module.permission:registrar,35')->name('urd-bpm-line.autorizar'); // BPM (Buenas Practicas Manufactura) Urd
Route::patch('urd-bpm-line/{folio}/rechazar', [UrdBpmLineController::class, 'rechazar'])
    ->middleware('module.permission:registrar,35')->name('urd-bpm-line.rechazar'); // BPM (Buenas Practicas Manufactura) Urd
