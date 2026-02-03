<?php

use App\Http\Controllers\ProgramaUrdEng\ReservarProgramar\InvTelasReservadasController;
use App\Http\Controllers\ProgramaUrdEng\ReservarProgramar\ProgramarUrdEngController;
use App\Http\Controllers\ProgramaUrdEng\ReservarProgramar\RequerimientoController;
use App\Http\Controllers\ProgramaUrdEng\ReservarProgramar\ReservarProgramarController;
use App\Http\Controllers\UrdEngomado\UrdEngNucleosController;
use Illuminate\Support\Facades\Route;

// Ruta principal: muestra directamente la vista de reservar-programar
Route::get('/programaurdeng', [ReservarProgramarController::class, 'index'])
    ->name('programa.urd.eng.index');

Route::redirect('/programa-urd-eng', '/programaurdeng', 301);

Route::redirect('/programaurdeng/reservaryprogramar', '/programaurdeng', 301);

Route::prefix('programa-urd-eng')->name('programa.urd.eng.')->group(function () {
    Route::get('/reservar-programar', [ReservarProgramarController::class, 'index'])->name('reservar.programar');
    Route::get('/programacion-requerimientos', [ReservarProgramarController::class, 'programacionRequerimientos'])->name('programacion.requerimientos');
    Route::get('/creacion-ordenes', [ReservarProgramarController::class, 'creacionOrdenes'])->name('creacion.ordenes');
    Route::post('/programacion-requerimientos/resumen-semanas', [ReservarProgramarController::class, 'getResumenSemanas'])->name('programacion.resumen.semanas');
    Route::get('/inventario-telares', [ReservarProgramarController::class, 'getInventarioTelares'])->name('inventario.telares');
    Route::get('/inventario-disponible', [InvTelasReservadasController::class, 'disponible'])->name('inventario.disponible.get');
    Route::post('/inventario-disponible', [InvTelasReservadasController::class, 'disponible'])->name('inventario.disponible');
    Route::post('/programar-telar', [ReservarProgramarController::class, 'programarTelar'])->name('programar.telar');
    Route::post('/actualizar-telar', [ReservarProgramarController::class, 'actualizarTelar'])->name('actualizar.telar');
    Route::post('/reservar-inventario', [InvTelasReservadasController::class, 'reservar'])->name('reservar.inventario');
    Route::post('/liberar-telar', [ReservarProgramarController::class, 'liberarTelar'])->name('liberar.telar');
    Route::get('/column-options', [ReservarProgramarController::class, 'getColumnOptions'])->name('column.options');
    Route::get('/reservas/{noTelar}', [InvTelasReservadasController::class, 'porTelar'])->name('reservas.porTelar');
    Route::post('/reservas/cancelar', [InvTelasReservadasController::class, 'cancelar'])->name('reservas.cancelar');
    Route::get('/reservas/diagnostico', [InvTelasReservadasController::class, 'diagnosticarReservas'])->name('reservas.diagnostico');
    Route::get('/buscar-bom-urdido', [ReservarProgramarController::class, 'buscarBomUrdido'])->name('buscar.bom.urdido');
    Route::get('/buscar-bom-engomado', [ReservarProgramarController::class, 'buscarBomEngomado'])->name('buscar.bom.engomado');
    Route::get('/materiales-urdido', [ReservarProgramarController::class, 'getMaterialesUrdido'])->name('materiales.urdido');
    Route::get('/materiales-engomado', [ReservarProgramarController::class, 'getMaterialesEngomado'])->name('materiales.engomado');
    Route::get('/anchos-balona', [ReservarProgramarController::class, 'getAnchosBalona'])->name('anchos.balona');
    Route::get('/maquinas-engomado', [ReservarProgramarController::class, 'getMaquinasEngomado'])->name('maquinas.engomado');
    Route::get('/nucleos', [UrdEngNucleosController::class, 'getNucleos'])->name('nucleos');
    Route::post('/crear-ordenes', [ProgramarUrdEngController::class, 'crearOrdenes'])->name('crear.ordenes');
    Route::get('/hilos', [ReservarProgramarController::class, 'obtenerHilos'])->name('hilos');
    Route::get('/tamanos', [ReservarProgramarController::class, 'obtenerTamanos'])->name('tamanos');
});

Route::post('/guardar-requerimiento', [RequerimientoController::class, 'store']);
Route::get('/ultimos-requerimientos', [RequerimientoController::class, 'ultimosRequerimientos']);
Route::get('/modulo-UrdidoEngomado', [RequerimientoController::class, 'requerimientosActivos'])->name('index.requerimientosActivos');
Route::get('/tejido/programarReq-step1', [RequerimientoController::class, 'requerimientosAProgramar'])->name('formulario.programarRequerimientos');
Route::post('/tejido/guardarUrdidoEngomado', [RequerimientoController::class, 'requerimientosAGuardar'])->name('crear.ordenes.lanzador');
Route::get('/prog-req/init/resolve-folio', [RequerimientoController::class, 'resolveFolio'])->name('prog.init.resolveFolio');
Route::get('/prog-req/init/fetch-by-folio', [RequerimientoController::class, 'initAndFetchByFolio'])->name('prog.init.fetchByFolio');
Route::post('/prog-req/init/upsert-and-fetch', [RequerimientoController::class, 'upsertAndFetchByFolio'])->name('prog.init.upsertFetch');
Route::post('/prog-req/autosave/construccion', [RequerimientoController::class, 'autosaveConstruccion'])->name('urdido.autosave.construccion');
Route::post('/prog-req/autosave/urdido-engomado', [RequerimientoController::class, 'autosaveUrdidoEngomado'])->name('urdido.autosave.engomado');
Route::post('/prog/validar-folios', [RequerimientoController::class, 'validarFolios'])->name('prog.validar.folios');
Route::post('/inventario/seleccion', [RequerimientoController::class, 'step3Store'])->name('inventario.step3.store');
