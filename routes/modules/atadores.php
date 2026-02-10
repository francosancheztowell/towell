<?php

use App\Http\Controllers\Atadores\Catalogos\Actividades\AtaActividadesController;
use App\Http\Controllers\Atadores\Catalogos\Comentarios\AtaComentariosController;
use App\Http\Controllers\Atadores\Catalogos\Maquinas\AtaMaquinasController;
use App\Http\Controllers\Atadores\ProgramaAtadores\AtadoresController;
use App\Http\Controllers\Atadores\Reportes\ReportesAtadoresController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::get('/atadores/{moduloPrincipal?}', [UsuarioController::class, 'showSubModulos'])
    ->defaults('moduloPrincipal', 'atadores')
    ->where('moduloPrincipal', 'atadores')
    ->name('atadores.index');

Route::prefix('atadores')->name('atadores.')->group(function () {
    Route::get('/configuracion/{moduloPadre?}', [UsuarioController::class, 'showSubModulosNivel3'])
        ->defaults('moduloPadre', '502')
        ->where('moduloPadre', '502')
        ->name('configuracion');

    Route::get('/catalogos/{moduloPadre?}', [UsuarioController::class, 'showSubModulosNivel3'])
        ->defaults('moduloPadre', '503')
        ->where('moduloPadre', '503')
        ->name('catalogos');

    Route::get('/programaatadores', [AtadoresController::class, 'index'])->name('programa');
    Route::post('/programaatadores/exportar-excel', [AtadoresController::class, 'exportarExcel'])->name('exportar.excel');
    Route::redirect('/programa', '/atadores/programaatadores', 301);

    Route::get('/iniciar', [AtadoresController::class, 'iniciarAtado'])->name('iniciar');
    Route::get('/calificar', [AtadoresController::class, 'calificarAtadores'])->name('calificar');
    Route::get('/julios-atados', [AtadoresController::class, 'cargarDatosUrdEngAtador'])->name('datosAtadores.Atador');
    Route::post('/save', [AtadoresController::class, 'save'])->name('save');
    Route::get('/show', [AtadoresController::class, 'show'])->name('show');
});

Route::get('/produccionProceso/atadores', [AtadoresController::class, 'index'])->name('atadores.produccion');

Route::post('/tejedores/validar', [AtadoresController::class, 'validarTejedor'])->name('tejedor.validar');

Route::get('/atadores/catalogos/actividades', [AtaActividadesController::class, 'index'])->name('atadores.catalogos.actividades');
Route::post('/atadores/catalogos/actividades', [AtaActividadesController::class, 'store'])->name('atadores.catalogos.actividades.store');
Route::get('/atadores/catalogos/actividades/{id}', [AtaActividadesController::class, 'show'])->name('atadores.catalogos.actividades.show');
Route::put('/atadores/catalogos/actividades/{id}', [AtaActividadesController::class, 'update'])->name('atadores.catalogos.actividades.update');
Route::delete('/atadores/catalogos/actividades/{id}', [AtaActividadesController::class, 'destroy'])->name('atadores.catalogos.actividades.destroy');

Route::get('/atadores/catalogos/comentarios', [AtaComentariosController::class, 'index'])->name('atadores.catalogos.comentarios');
Route::post('/atadores/catalogos/comentarios', [AtaComentariosController::class, 'store'])->name('atadores.catalogos.comentarios.store');
Route::get('/atadores/catalogos/comentarios/{nota1}', [AtaComentariosController::class, 'show'])->name('atadores.catalogos.comentarios.show');
Route::put('/atadores/catalogos/comentarios/{nota1}', [AtaComentariosController::class, 'update'])->name('atadores.catalogos.comentarios.update');
Route::delete('/atadores/catalogos/comentarios/{nota1}', [AtaComentariosController::class, 'destroy'])->name('atadores.catalogos.comentarios.destroy');

Route::get('/atadores/catalogos/maquinas', [AtaMaquinasController::class, 'index'])->name('atadores.catalogos.maquinas');
Route::post('/atadores/catalogos/maquinas', [AtaMaquinasController::class, 'store'])->name('atadores.catalogos.maquinas.store');
Route::get('/atadores/catalogos/maquinas/{maquinaId}', [AtaMaquinasController::class, 'show'])->name('atadores.catalogos.maquinas.show');
Route::put('/atadores/catalogos/maquinas/{maquinaId}', [AtaMaquinasController::class, 'update'])->name('atadores.catalogos.maquinas.update');
Route::delete('/atadores/catalogos/maquinas/{maquinaId}', [AtaMaquinasController::class, 'destroy'])->name('atadores.catalogos.maquinas.destroy');

// Rutas para modulo de reportes
Route::prefix('atadores/reportes-atadores')->name('atadores.reportes.')->group(function () {
    Route::get('/', [ReportesAtadoresController::class, 'index'])->name('index');
    Route::get('/programa', [ReportesAtadoresController::class, 'reportePrograma'])->name('programa');
    Route::get('/programa/excel', [ReportesAtadoresController::class, 'exportarExcel'])->name('programa.excel');
});