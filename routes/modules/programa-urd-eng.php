<?php

use App\Http\Controllers\ProgramaUrdEng\ReservarProgramar\BomMaterialesController;
use App\Http\Controllers\ProgramaUrdEng\ReservarProgramar\CrearOrdenKarlMayerController;
use App\Http\Controllers\ProgramaUrdEng\ReservarProgramar\InventarioTelaresController;
use App\Http\Controllers\ProgramaUrdEng\ReservarProgramar\InventarioDisponibleController;
use App\Http\Controllers\ProgramaUrdEng\ReservarProgramar\ReservaInventarioController;
use App\Http\Controllers\ProgramaUrdEng\ReservarProgramar\ProgramarUrdEngController;
use App\Http\Controllers\ProgramaUrdEng\ReservarProgramar\ReservarProgramarController;
use App\Http\Controllers\ProgramaUrdEng\ReservarProgramar\ResumenSemanasController;
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
    Route::get('/karl-mayer', [ReservarProgramarController::class, 'karlMayer'])->name('karl.mayer');

    Route::post('/programacion-requerimientos/resumen-semanas', [ResumenSemanasController::class, 'getResumenSemanas'])->name('programacion.resumen.semanas');

    Route::get('/inventario-telares', [InventarioTelaresController::class, 'getInventarioTelares'])->name('inventario.telares');
    Route::get('/inventario-disponible', [InventarioDisponibleController::class, 'disponible'])->name('inventario.disponible.get');
    Route::post('/inventario-disponible', [InventarioDisponibleController::class, 'disponible'])->name('inventario.disponible');
    Route::post('/programar-telar', [ReservarProgramarController::class, 'programarTelar'])->name('programar.telar');
    Route::post('/actualizar-telar', [ReservarProgramarController::class, 'actualizarTelar'])->name('actualizar.telar');
    Route::post('/reservar-inventario', [ReservaInventarioController::class, 'reservar'])->name('reservar.inventario');

    Route::post('/liberar-telar', [ReservarProgramarController::class, 'liberarTelar'])->name('liberar.telar');


    Route::get('/reservas/{noTelar}', [InventarioDisponibleController::class, 'porTelar'])->name('reservas.porTelar');
    Route::post('/reservas/cancelar', [ReservaInventarioController::class, 'cancelar'])->name('reservas.cancelar');
    Route::get('/reservas/diagnostico', [InventarioDisponibleController::class, 'diagnosticarReservas'])->name('reservas.diagnostico');
    Route::get('/buscar-bom-urdido', [BomMaterialesController::class, 'buscarBomUrdido'])->name('buscar.bom.urdido');
    Route::get('/buscar-bom-engomado', [BomMaterialesController::class, 'buscarBomEngomado'])->name('buscar.bom.engomado');
    Route::get('/materiales-urdido', [BomMaterialesController::class, 'getMaterialesUrdido'])->name('materiales.urdido');
    Route::get('/materiales-urdido-completo', [BomMaterialesController::class, 'getMaterialesUrdidoCompleto'])->name('materiales.urdido.completo');
    Route::get('/materiales-engomado', [BomMaterialesController::class, 'getMaterialesEngomado'])->name('materiales.engomado');
    Route::get('/anchos-balona', [BomMaterialesController::class, 'getAnchosBalona'])->name('anchos.balona');
    Route::get('/maquinas-engomado', [BomMaterialesController::class, 'getMaquinasEngomado'])->name('maquinas.engomado');
    Route::get('/nucleos', [UrdEngNucleosController::class, 'getNucleos'])->name('nucleos');
    Route::post('/crear-ordenes', [ProgramarUrdEngController::class, 'crearOrdenes'])->name('crear.ordenes');
    Route::post('/crear-orden-karl-mayer', [CrearOrdenKarlMayerController::class, 'store'])->name('crear.orden.karl.mayer');
    Route::get('/hilos', [BomMaterialesController::class, 'obtenerHilos'])->name('hilos');
    Route::get('/tamanos', [BomMaterialesController::class, 'obtenerTamanos'])->name('tamanos');
    Route::get('/bom-formula', [BomMaterialesController::class, 'getBomFormula'])->name('bom.formula');
});

