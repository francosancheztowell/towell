<?php

use App\Http\Controllers\Engomado\BPMEngomado\EngBpmController;
use App\Http\Controllers\Engomado\BPMEngomado\EngBpmLineController;
use App\Http\Controllers\Engomado\CapturaFormulas\EngProduccionFormulacionController;
use App\Http\Controllers\Engomado\Configuracion\ActividadesBPMEngomado\EngActividadesBpmController;
use App\Http\Controllers\Engomado\Produccion\ModuloProduccionEngomadoController;
use App\Http\Controllers\Engomado\ProgramaEngomado\ProgramarEngomadoController;
use App\Http\Controllers\UrdEngomado\UrdEngNucleosController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::get('/engomado/{moduloPrincipal?}', [UsuarioController::class, 'showSubModulos'])
    ->defaults('moduloPrincipal', 'engomado')
    ->where('moduloPrincipal', 'engomado')
    ->name('engomado.index');

Route::prefix('engomado')->name('engomado.')->group(function () {
    Route::get('/configuracion/{moduloPadre?}', [UsuarioController::class, 'showSubModulosNivel3'])
        ->defaults('moduloPadre', '404')
        ->where('moduloPadre', '404')
        ->name('configuracion');

    Route::get('/configuracion/actividadesbpmengomado', [EngActividadesBpmController::class, 'index'])
        ->name('configuracion.actividades-bpm');
    Route::get('/configuracion/actividades-bpm', [EngActividadesBpmController::class, 'index'])
        ->name('configuracion.actividades-bpm.legacy');
    Route::get('/configuracion/catalogodenucleos', [UrdEngNucleosController::class, 'index'])
        ->name('configuracion.catalogos-nucleos');
    Route::get('/configuracion/catalogos-nucleos', [UrdEngNucleosController::class, 'index'])
        ->name('configuracion.catalogos-nucleos.legacy');
    
    // Catálogo de Julios para Engomado
    Route::get('/configuracion/catalogojulioseng', [\App\Http\Controllers\Urdido\Configuracion\CatalogosJulios\CatalogosUrdidoController::class, 'catalogosJulios'])
        ->name('configuracion.catalogos.julios');
    Route::post('/configuracion/catalogojulioseng', [\App\Http\Controllers\Urdido\Configuracion\CatalogosJulios\CatalogosUrdidoController::class, 'storeJulio'])
        ->name('configuracion.catalogos.julios.store');
    Route::put('/configuracion/catalogojulioseng/{id}', [\App\Http\Controllers\Urdido\Configuracion\CatalogosJulios\CatalogosUrdidoController::class, 'updateJulio'])
        ->name('configuracion.catalogos.julios.update');
    Route::delete('/configuracion/catalogojulioseng/{id}', [\App\Http\Controllers\Urdido\Configuracion\CatalogosJulios\CatalogosUrdidoController::class, 'destroyJulio'])
        ->name('configuracion.catalogos.julios.destroy');

    Route::get('/programaengomado', [ProgramarEngomadoController::class, 'index'])->name('programa.engomado');
    Route::redirect('/programaengomado/produccionengomado', '/engomado/modulo-produccion-engomado', 301);
    Route::redirect('/bpmbuenaspracticasmanufacturaeng', '/eng-bpm', 301);
    Route::redirect('/bpm', '/eng-bpm', 301);
    Route::get('/capturadeformula', [EngProduccionFormulacionController::class, 'index'])->name('captura-formula');

    Route::get('/programar-engomado', [ProgramarEngomadoController::class, 'index'])->name('programar.engomado');
    Route::get('/reimpresion-engomado', [ProgramarEngomadoController::class, 'reimpresionFinalizadas'])->name('reimpresion.finalizadas');
    Route::get('/programar-engomado/ordenes', [ProgramarEngomadoController::class, 'getOrdenes'])->name('programar.engomado.ordenes');
    Route::get('/programar-engomado/verificar-en-proceso', [ProgramarEngomadoController::class, 'verificarOrdenEnProceso'])->name('programar.engomado.verificar.en.proceso');
    Route::post('/programar-engomado/intercambiar-prioridad', [ProgramarEngomadoController::class, 'intercambiarPrioridad'])->name('programar.engomado.intercambiar.prioridad');
    Route::post('/programar-engomado/guardar-observaciones', [ProgramarEngomadoController::class, 'guardarObservaciones'])->name('programar.engomado.guardar.observaciones');
    Route::get('/programar-engomado/todas-ordenes', [ProgramarEngomadoController::class, 'getTodasOrdenes'])->name('programar.engomado.todas.ordenes');
    Route::post('/programar-engomado/actualizar-prioridades', [ProgramarEngomadoController::class, 'actualizarPrioridades'])->name('programar.engomado.actualizar.prioridades');

    Route::get('/modulo-produccion-engomado', [ModuloProduccionEngomadoController::class, 'index'])->name('modulo.produccion.engomado');
    Route::get('/modulo-produccion-engomado/catalogos-julios', [ModuloProduccionEngomadoController::class, 'getCatalogosJulios'])->name('modulo.produccion.engomado.catalogos.julios');
    Route::get('/modulo-produccion-engomado/usuarios-engomado', [ModuloProduccionEngomadoController::class, 'getUsuariosEngomado'])->name('modulo.produccion.engomado.usuarios.engomado');
    Route::post('/modulo-produccion-engomado/guardar-oficial', [ModuloProduccionEngomadoController::class, 'guardarOficial'])->name('modulo.produccion.engomado.guardar.oficial');
    Route::post('/modulo-produccion-engomado/actualizar-turno-oficial', [ModuloProduccionEngomadoController::class, 'actualizarTurnoOficial'])->name('modulo.produccion.engomado.actualizar.turno.oficial');
    Route::post('/modulo-produccion-engomado/actualizar-fecha', [ModuloProduccionEngomadoController::class, 'actualizarFecha'])->name('modulo.produccion.engomado.actualizar.fecha');
    Route::post('/modulo-produccion-engomado/actualizar-julio-tara', [ModuloProduccionEngomadoController::class, 'actualizarJulioTara'])->name('modulo.produccion.engomado.actualizar.julio.tara');
    Route::post('/modulo-produccion-engomado/actualizar-kg-bruto', [ModuloProduccionEngomadoController::class, 'actualizarKgBruto'])->name('modulo.produccion.engomado.actualizar.kg.bruto');
    Route::post('/modulo-produccion-engomado/actualizar-campos-produccion', [ModuloProduccionEngomadoController::class, 'actualizarCamposProduccion'])->name('modulo.produccion.engomado.actualizar.campos.produccion');
    Route::post('/modulo-produccion-engomado/actualizar-horas', [ModuloProduccionEngomadoController::class, 'actualizarHoras'])->name('modulo.produccion.engomado.actualizar.horas');
    Route::post('/modulo-produccion-engomado/finalizar', [ModuloProduccionEngomadoController::class, 'finalizar'])->name('modulo.produccion.engomado.finalizar');
    Route::get('/modulo-produccion-engomado/pdf', [\App\Http\Controllers\PDFController::class, 'generarPDFUrdidoEngomado'])->name('modulo.produccion.engomado.pdf');

    Route::get('/captura-formula', [EngProduccionFormulacionController::class, 'index'])->name('captura-formula.legacy');
});

Route::resource('eng-actividades-bpm', EngActividadesBpmController::class)
    ->parameters(['eng-actividades-bpm' => 'engActividadesBpm'])
    ->names('eng-actividades-bpm');

Route::resource('eng-bpm', EngBpmController::class)
    ->parameters(['eng-bpm' => 'id'])
    ->names('eng-bpm');

Route::get('eng-bpm-line/{folio}', [EngBpmLineController::class, 'index'])->name('eng-bpm-line.index');
Route::post('eng-bpm-line/{folio}/toggle', [EngBpmLineController::class, 'toggleActividad'])->name('eng-bpm-line.toggle');
Route::patch('eng-bpm-line/{folio}/terminar', [EngBpmLineController::class, 'terminar'])->name('eng-bpm-line.terminar');
Route::patch('eng-bpm-line/{folio}/autorizar', [EngBpmLineController::class, 'autorizar'])->name('eng-bpm-line.autorizar');
Route::patch('eng-bpm-line/{folio}/rechazar', [EngBpmLineController::class, 'rechazar'])->name('eng-bpm-line.rechazar');

Route::resource('eng-formulacion', EngProduccionFormulacionController::class)
    ->parameters(['eng-formulacion' => 'folio'])
    ->names('eng-formulacion');
Route::get('eng-formulacion/validar-folio', [EngProduccionFormulacionController::class, 'validarFolio'])->name('eng-formulacion.validar-folio');
Route::get('eng-formulacion/componentes/formula', [EngProduccionFormulacionController::class, 'getComponentesFormula'])->name('eng-formulacion.componentes');

Route::resource('urd-eng-nucleos', UrdEngNucleosController::class)
    ->parameters(['urd-eng-nucleos' => 'urdEngNucleo'])
    ->names('urd-eng-nucleos');
