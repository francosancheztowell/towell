<?php

use App\Http\Controllers\Atadores\Catalogos\Actividades\AtaActividadesController;
use App\Http\Controllers\Atadores\Catalogos\Comentarios\AtaComentariosController;
use App\Http\Controllers\Atadores\Catalogos\Maquinas\AtaMaquinasController;
use App\Http\Controllers\Atadores\ProgramaAtadores\AtaDevolucionesController;
use App\Http\Controllers\Atadores\ProgramaAtadores\AtadoresController;
use App\Http\Controllers\Atadores\Reportes\ReportesAtadoresController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::get('/atadores/{moduloPrincipal?}', [UsuarioController::class, 'showSubModulos'])
    ->defaults('moduloPrincipal', 'atadores')
    ->where('moduloPrincipal', 'atadores')
    ->name('atadores.index');

Route::prefix('atadores')->name('atadores.')->group(function () {
    Route::get('/catalogos/{moduloPadre?}', [UsuarioController::class, 'showSubModulosNivel3'])
        ->defaults('moduloPadre', '503')
        ->where('moduloPadre', '503')
        ->name('catalogos');

    Route::get('/programaatadores', [AtadoresController::class, 'index'])->name('programa');
    Route::get('/programaatadores/estatus', [AtadoresController::class, 'estatus'])->name('programa.estatus');
    Route::redirect('/programa', '/atadores/programaatadores', 301);

    Route::get('/iniciar', [AtadoresController::class, 'iniciarAtado'])
        ->middleware('module.permission:crear,45')->name('iniciar'); // Programa Atadores
    Route::get('/calificar/montado', [AtadoresController::class, 'procesoKm'])->defaults('proceso', 'montado')->name('calificar.montado');
    Route::get('/calificar/enhebrado', [AtadoresController::class, 'procesoKm'])->defaults('proceso', 'enhebrado')->name('calificar.enhebrado');
    Route::get('/calificar', [AtadoresController::class, 'calificarAtadores'])->name('calificar');
    Route::post('/save', [AtadoresController::class, 'save'])->middleware('module.permission:modificar,45,auditar')->name('save'); // Programa Atadores

    // Las dos mitades del mismo checkbox (toggleDevolucion/eliminarDevolucion en
    // calificar-atadores/index.blade.php): mismo permiso o se puede marcar y no desmarcar.
    Route::post('/devoluciones', [AtaDevolucionesController::class, 'store'])
        ->middleware('module.permission:modificar,45')->name('devoluciones.store'); // Programa Atadores
    Route::delete('/devoluciones', [AtaDevolucionesController::class, 'destroy'])
        ->middleware('module.permission:modificar,45')->name('devoluciones.destroy'); // Programa Atadores
    Route::get('/devoluciones/ubicaciones', [AtaDevolucionesController::class, 'ubicaciones'])->name('devoluciones.ubicaciones');
    Route::get('/devoluciones/julios', [AtaDevolucionesController::class, 'julios'])->name('devoluciones.julios');
    Route::get('/devoluciones/disponibilidad', [AtaDevolucionesController::class, 'disponibilidad'])->name('devoluciones.disponibilidad');
});

Route::get('/produccionProceso/atadores', [AtadoresController::class, 'index'])->name('atadores.produccion');

Route::get('/atadores/catalogos/actividades', [AtaActividadesController::class, 'index'])->name('atadores.catalogos.actividades');
Route::post('/atadores/catalogos/actividades', [AtaActividadesController::class, 'store'])
    ->middleware('module.permission:crear,150')->name('atadores.catalogos.actividades.store'); // Actividades
Route::get('/atadores/catalogos/actividades/{id}', [AtaActividadesController::class, 'show'])->name('atadores.catalogos.actividades.show');
Route::put('/atadores/catalogos/actividades/{id}', [AtaActividadesController::class, 'update'])
    ->middleware('module.permission:modificar,150')->name('atadores.catalogos.actividades.update'); // Actividades
Route::delete('/atadores/catalogos/actividades/{id}', [AtaActividadesController::class, 'destroy'])
    ->middleware('module.permission:eliminar,150')->name('atadores.catalogos.actividades.destroy'); // Actividades

Route::get('/atadores/catalogos/comentarios', [AtaComentariosController::class, 'index'])->name('atadores.catalogos.comentarios');
Route::post('/atadores/catalogos/comentarios', [AtaComentariosController::class, 'store'])
    ->middleware('module.permission:crear,151')->name('atadores.catalogos.comentarios.store'); // Comentarios
Route::get('/atadores/catalogos/comentarios/{nota1}', [AtaComentariosController::class, 'show'])->name('atadores.catalogos.comentarios.show');
Route::put('/atadores/catalogos/comentarios/{nota1}', [AtaComentariosController::class, 'update'])
    ->middleware('module.permission:modificar,151')->name('atadores.catalogos.comentarios.update'); // Comentarios
Route::delete('/atadores/catalogos/comentarios/{nota1}', [AtaComentariosController::class, 'destroy'])
    ->middleware('module.permission:eliminar,151')->name('atadores.catalogos.comentarios.destroy'); // Comentarios

Route::get('/atadores/catalogos/maquinas', [AtaMaquinasController::class, 'index'])->name('atadores.catalogos.maquinas');
Route::post('/atadores/catalogos/maquinas', [AtaMaquinasController::class, 'store'])
    ->middleware('module.permission:crear,152')->name('atadores.catalogos.maquinas.store'); // Maquinas
Route::get('/atadores/catalogos/maquinas/{maquinaId}', [AtaMaquinasController::class, 'show'])->name('atadores.catalogos.maquinas.show');
Route::put('/atadores/catalogos/maquinas/{maquinaId}', [AtaMaquinasController::class, 'update'])
    ->middleware('module.permission:modificar,152')->name('atadores.catalogos.maquinas.update'); // Maquinas
Route::delete('/atadores/catalogos/maquinas/{maquinaId}', [AtaMaquinasController::class, 'destroy'])
    ->middleware('module.permission:eliminar,152')->name('atadores.catalogos.maquinas.destroy'); // Maquinas

// Rutas para modulo de reportes
Route::prefix('atadores/reportes-atadores')->name('atadores.reportes.')->group(function () {
    Route::get('/', [ReportesAtadoresController::class, 'index'])->name('index');
    Route::get('/programa', [ReportesAtadoresController::class, 'reportePrograma'])->name('programa');
    Route::get('/programa/excel', [ReportesAtadoresController::class, 'exportarExcel'])->name('programa.excel');
    Route::get('/atadores', [ReportesAtadoresController::class, 'reporteAtadores'])->name('atadores');
    Route::get('/km', [ReportesAtadoresController::class, 'reporteKm'])->name('km');
    Route::get('/atadores/descargar', [ReportesAtadoresController::class, 'descargarExcelRango'])->name('atadores.descargar');
    Route::get('/oee/verificar', [ReportesAtadoresController::class, 'verificarOeeAtadores'])->name('oee.verificar');
    Route::post('/oee/despachar', [ReportesAtadoresController::class, 'despacharOeeAtadores'])->name('oee.despachar');
    Route::get('/oee/estado/{token}', [ReportesAtadoresController::class, 'estadoOeeAtadores'])->name('oee.estado');
});
