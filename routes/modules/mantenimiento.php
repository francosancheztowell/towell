<?php

use App\Http\Controllers\Mantenimiento\MantenimientoParosController;
use App\Http\Controllers\Mantenimiento\CatalogosFallasController;
use App\Http\Controllers\Mantenimiento\ManOperadoresMantenimientoController;
use App\Http\Controllers\Mantenimiento\ReportesMantenimientoController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::get('/mantenimiento/reportes', [ReportesMantenimientoController::class, 'index'])->name('mantenimiento.reportes');
Route::get('/mantenimiento/reportes/fallas-paros', [ReportesMantenimientoController::class, 'reporteFallasParos'])->name('mantenimiento.reportes.fallas-paros');
Route::get('/mantenimiento/reportes/fallas-paros/excel', [ReportesMantenimientoController::class, 'exportarExcel'])->name('mantenimiento.reportes.fallas-paros.excel');

Route::get('/mantenimiento/{moduloPrincipal?}', [UsuarioController::class, 'showSubModulos'])
    ->defaults('moduloPrincipal', 'mantenimiento')
    ->where('moduloPrincipal', 'mantenimiento')
    ->name('mantenimiento.index');

Route::view('/mantenimiento/solicitudes', 'modulos.mantenimiento.reporte-fallos-paros.index')->name('mantenimiento.solicitudes');

Route::get('/mantenimiento/nuevo-paro', [MantenimientoParosController::class, 'nuevoParo'])->name('mantenimiento.nuevo-paro');
Route::view('/mantenimiento/finalizar-paro', 'modulos.mantenimiento.finalizar-paro.index')->name('mantenimiento.finalizar-paro');
Route::view('/mantenimiento/reporte-fallos-paros', 'modulos.mantenimiento.reporte-fallos-paros.index')->name('mantenimiento.reporte-fallos-paros');

// CRUD Catálogo de Fallas
Route::get('/mantenimiento/catalogodefallas', [CatalogosFallasController::class, 'index'])->name('mantenimiento.catalogos-fallas.index');
Route::post('/mantenimiento/catalogodefallas', [CatalogosFallasController::class, 'store'])->name('mantenimiento.catalogos-fallas.store');
Route::put('/mantenimiento/catalogodefallas/{catalogosFalla}', [CatalogosFallasController::class, 'update'])->name('mantenimiento.catalogos-fallas.update');
Route::delete('/mantenimiento/catalogodefallas/{catalogosFalla}', [CatalogosFallasController::class, 'destroy'])->name('mantenimiento.catalogos-fallas.destroy');

// CRUD Operadores de Mantenimiento
Route::get('/mantenimiento/operadores-mantenimiento', [ManOperadoresMantenimientoController::class, 'index'])->name('mantenimiento.operadores-mantenimiento.index');
Route::post('/mantenimiento/operadores-mantenimiento', [ManOperadoresMantenimientoController::class, 'store'])->name('mantenimiento.operadores-mantenimiento.store');
Route::put('/mantenimiento/operadores-mantenimiento/{operador}', [ManOperadoresMantenimientoController::class, 'update'])->name('mantenimiento.operadores-mantenimiento.update');
Route::delete('/mantenimiento/operadores-mantenimiento/{operador}', [ManOperadoresMantenimientoController::class, 'destroy'])->name('mantenimiento.operadores-mantenimiento.destroy');

Route::get('/api/mantenimiento/departamentos', [MantenimientoParosController::class, 'departamentos'])
    ->name('api.mantenimiento.departamentos');
Route::get('/api/mantenimiento/maquinas/{departamento}', [MantenimientoParosController::class, 'maquinas'])
    ->name('api.mantenimiento.maquinas');
Route::get('/api/mantenimiento/tipos-falla', [MantenimientoParosController::class, 'tiposFalla'])
    ->name('api.mantenimiento.tipos-falla');
Route::get('/api/mantenimiento/fallas/{departamento}/{tipoFallaId?}', [MantenimientoParosController::class, 'fallas'])
    ->name('api.mantenimiento.fallas');
Route::get('/api/mantenimiento/orden-trabajo/{departamento}/{maquina}', [MantenimientoParosController::class, 'ordenTrabajo'])
    ->name('api.mantenimiento.orden-trabajo');
Route::get('/api/mantenimiento/operadores', [MantenimientoParosController::class, 'operadores'])
    ->name('api.mantenimiento.operadores');
Route::post('/api/mantenimiento/paros', [MantenimientoParosController::class, 'store'])
    ->name('api.mantenimiento.paros.store');
Route::get('/api/mantenimiento/paros', [MantenimientoParosController::class, 'index'])
    ->name('api.mantenimiento.paros.index');
Route::get('/api/mantenimiento/paros/{id}', [MantenimientoParosController::class, 'show'])
    ->name('api.mantenimiento.paros.show');
Route::put('/api/mantenimiento/paros/{id}/finalizar', [MantenimientoParosController::class, 'finalizar'])
    ->name('api.mantenimiento.paros.finalizar');
