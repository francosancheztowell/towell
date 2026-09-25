<?php

use App\Http\Controllers\Planeacion\Alineacion\AlineacionController;
use App\Http\Controllers\Planeacion\Auditoria\AuditoriaProgramaTejidoController;
use App\Http\Controllers\Planeacion\CatalogoPlaneacion\CatAplicaciones\AplicacionesController;
use App\Http\Controllers\Planeacion\CatalogoPlaneacion\CatCalendarios\CalendarioController;
use App\Http\Controllers\Planeacion\CatalogoPlaneacion\CatEficiencias\CatalagoEficienciaController;
use App\Http\Controllers\Planeacion\CatalogoPlaneacion\CatMatrizCalibres\MatrizCalibresController;
use App\Http\Controllers\Planeacion\CatalogoPlaneacion\CatMatrizHilos\MatrizHilosController;
use App\Http\Controllers\Planeacion\CatalogoPlaneacion\CatPesosRollos\PesosRollosController;
use App\Http\Controllers\Planeacion\CatalogoPlaneacion\CatTelares\CatalagoTelarController;
use App\Http\Controllers\Planeacion\CatalogoPlaneacion\CatVelocidades\CatalagoVelocidadController;
use App\Http\Controllers\Planeacion\CatalogoPlaneacion\ModelosCodificados\CodificacionController;
use App\Http\Controllers\Planeacion\CatCodificados\CatCodificacionController;
use App\Http\Controllers\Planeacion\CatLMat\CatLMatController;
use App\Http\Controllers\Planeacion\ProgramaTejido\ColumnasProgramaTejidoController;
use App\Http\Controllers\Planeacion\ProgramaTejido\DescargarProgramaController;
use App\Http\Controllers\Planeacion\ProgramaTejido\LiberarOrdenesController;
use App\Http\Controllers\Planeacion\ProgramaTejido\OrdenDeCambio\Felpa\OrdenDeCambioFelpaController;
use App\Http\Controllers\Planeacion\ProgramaTejido\ProgramaTejidoBalanceoController;
use App\Http\Controllers\Planeacion\ProgramaTejido\ProgramaTejidoCalendariosController;
use App\Http\Controllers\Planeacion\ProgramaTejido\ProgramaTejidoCatalogosController;
use App\Http\Controllers\Planeacion\ProgramaTejido\ProgramaTejidoController;
use App\Http\Controllers\Planeacion\ProgramaTejido\ProgramaTejidoOperacionesController;
use App\Http\Controllers\Planeacion\ProgramaTejido\ProgramaTejidoReadController;
use App\Http\Controllers\Planeacion\ProgramaTejido\RedboothProgramaTejidoController;
use App\Http\Controllers\Planeacion\ProgramaTejido\ReimprimirOrdenesController;
use App\Http\Controllers\Planeacion\ProgramaTejido\RepasoController;
use App\Http\Controllers\Planeacion\ProgramaTejido\ReqProgramaTejidoLineController;
use App\Http\Controllers\Planeacion\Utilerias\FinalizarOrdenesController;
use App\Http\Controllers\Planeacion\Utilerias\MoverOrdenesController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::get('/planeacion', fn () => app(UsuarioController::class)->showSubModulos('planeacion'))
    ->name('planeacion.index');

Route::redirect('/planeacion/programatejido', '/planeacion/programa-tejido', 301);
Route::redirect('/planeacion/simulaciones', '/simulacion', 301);

Route::prefix('planeacion')->name('planeacion.')->group(function () {
    Route::prefix('catalogos')->name('catalogos.')->group(function () {
        Route::get('/', [UsuarioController::class, 'showSubModulosNivel3'])->defaults('moduloPadre', '104')->name('index');
        Route::redirect('/eficienciasstd', '/planeacion/catalogos/eficiencia', 301);
        Route::redirect('/velocidadstd', '/planeacion/catalogos/velocidad', 301);
        Route::redirect('/aplicacionescat', '/planeacion/catalogos/aplicaciones', 301);
        Route::redirect('/matrizhilos', '/planeacion/catalogos/matriz-hilos', 301);
        Route::redirect('/pesosporrollos', '/planeacion/catalogos/pesos-rollos', 301);
        Route::redirect('/codificacionmodelos', '/planeacion/catalogos/codificacion-modelos', 301);

        Route::get('/lista-de-materiales', [CatLMatController::class, 'listaMateriales'])->name('lmat.lista');
        Route::get('/telares', [CatalagoTelarController::class, 'index'])->name('telares');
        Route::get('/eficiencia', [CatalagoEficienciaController::class, 'index'])->name('eficiencia');
        Route::get('/velocidad', [CatalagoVelocidadController::class, 'index'])->name('velocidad');
        Route::get('/calendarios', [CalendarioController::class, 'index'])->name('calendarios');
        Route::get('/aplicaciones', [AplicacionesController::class, 'index'])->name('aplicaciones');
        Route::get('/matriz-hilos', [MatrizHilosController::class, 'index'])->name('matriz-hilos');
        Route::get('/matrizcalibres', [MatrizCalibresController::class, 'index'])->name('matrizcalibres');
        Route::post('/matrizcalibres', [MatrizCalibresController::class, 'store'])
            ->middleware('module.permission:crear,14')->name('matrizcalibres.store'); // Matriz Calibres
        Route::get('/matrizcalibres/{id}', [MatrizCalibresController::class, 'show'])->name('matrizcalibres.show');
        Route::put('/matrizcalibres/{id}', [MatrizCalibresController::class, 'update'])
            ->middleware('module.permission:modificar,14')->name('matrizcalibres.update'); // Matriz Calibres
        Route::delete('/matrizcalibres/{id}', [MatrizCalibresController::class, 'destroy'])
            ->middleware('module.permission:eliminar,14')->name('matrizcalibres.destroy'); // Matriz Calibres
        Route::get('/pesos-rollos', [PesosRollosController::class, 'index'])->name('pesos-rollos');
        Route::post('/pesos-rollos', [PesosRollosController::class, 'store'])
            ->middleware('module.permission:crear,172')->name('pesos-rollos.store'); // Pesos por Rollos
        Route::put('/pesos-rollos/{id}', [PesosRollosController::class, 'update'])
            ->middleware('module.permission:modificar,172')->name('pesos-rollos.update'); // Pesos por Rollos
        Route::delete('/pesos-rollos/{id}', [PesosRollosController::class, 'destroy'])
            ->middleware('module.permission:eliminar,172')->name('pesos-rollos.destroy'); // Pesos por Rollos

        Route::get('/codificacion-modelos', [CodificacionController::class, 'index'])->name('codificacion-modelos');
        Route::get('/codificacion-modelos/create', [CodificacionController::class, 'create'])->name('codificacion.create');
        Route::get('/codificacion-modelos/get-all', [CodificacionController::class, 'getAll'])->name('codificacion.get-all');
        Route::get('/codificacion-modelos/api/all-fast', [CodificacionController::class, 'getAllFast'])->name('codificacion.all-fast');
        Route::get('/codificacion-modelos/estadisticas', [CodificacionController::class, 'estadisticas'])->name('codificacion.estadisticas');
        Route::get('/codificacion-modelos/salones-telares', [CodificacionController::class, 'getSalonesYTelares'])->name('codificacion.salones-telares');
        Route::get('/codificacion-modelos/flogs-data', [CodificacionController::class, 'getFlogsData'])->name('codificacion.flogs-data');
        Route::get('/codificacion-modelos/catcodificados-orden', [CodificacionController::class, 'getCatCodificadosByOrden'])->name('codificacion.catcodificados-orden');
        Route::get('/codificacion-modelos/modelo-similar', [CodificacionController::class, 'modeloSimilar'])->name('codificacion.modelo-similar');
        Route::post('/codificacion-modelos/duplicar-importar', [CodificacionController::class, 'duplicarImportar'])
            ->middleware('module.permission:crear,16')->name('codificacion.duplicar-importar'); // Codificación Modelos
        Route::get('/codificacion-modelos/{id}/edit', [CodificacionController::class, 'edit'])->name('codificacion.edit');
        Route::post('/codificacion-modelos/{id}/duplicate', [CodificacionController::class, 'duplicate'])
            ->middleware('module.permission:crear,16')->name('codificacion.duplicate'); // Codificación Modelos
        Route::get('/codificacion-modelos/{id}', [CodificacionController::class, 'show'])->name('codificacion.show');
        Route::post('/codificacion-modelos', [CodificacionController::class, 'store'])
            ->middleware('module.permission:crear,16')->name('codificacion.store'); // Codificación Modelos
        Route::put('/codificacion-modelos/{id}', [CodificacionController::class, 'update'])
            ->middleware('module.permission:modificar,16')->name('codificacion.update'); // Codificación Modelos
        Route::delete('/codificacion-modelos/{id}', [CodificacionController::class, 'destroy'])
            ->middleware('module.permission:eliminar,16')->name('codificacion.destroy'); // Codificación Modelos
        Route::post('/codificacion-modelos/excel', [CodificacionController::class, 'procesarExcel'])
            ->middleware('module.permission:crear,16')->name('codificacion.excel'); // Codificación Modelos
        Route::get('/codificacion-modelos/excel-progress/{id}', [CodificacionController::class, 'importProgress'])->name('codificacion.excel.progress');
        Route::post('/codificacion-modelos/buscar', [CodificacionController::class, 'buscar'])->name('codificacion.buscar');
    });

    Route::get('/codificacion', [CatCodificacionController::class, 'index'])->name('codificacion.index');
    Route::get('/codificacion/api/all-fast', [CatCodificacionController::class, 'getAllFast'])->name('codificacion.all-fast');
    Route::get('/codificacion/api/ordenes-en-proceso', [CatCodificacionController::class, 'ordenesEnProceso'])->name('codificacion.ordenes-en-proceso');
    Route::post('/codificacion/api/revivir-programa', [CatCodificacionController::class, 'revivirProgramaDesdeCat'])
        ->middleware('module.permission:modificar,169')->name('codificacion.revivir-programa'); // Codificación
    Route::post('/codificacion/api/recalcular-marbetes', [CatCodificacionController::class, 'recalcularMarbete'])
        ->middleware('module.permission:modificar,169')->name('codificacion.recalcular-marbetes'); // Codificación
    Route::get('/codificacion/api/catcodificados-por-orden/{ordenTejido}', [CatCodificacionController::class, 'getCatCodificadosPorOrden'])->name('codificacion.catcodificados-por-orden');
    Route::post('/codificacion/api/actualizar-peso-muestra-lmat', [CatCodificacionController::class, 'actualizarPesoMuestraLmat'])
        ->middleware('module.permission:modificar,169')->name('codificacion.actualizar-peso-muestra-lmat'); // Codificación
    Route::get('/codificacion/api/registros-ord-compartida/{ordCompartida}', [CatCodificacionController::class, 'registrosOrdCompartida'])->name('codificacion.registros-ord-compartida');
    Route::post('/codificacion/excel', [CatCodificacionController::class, 'procesarExcel'])
        ->middleware('module.permission:crear,169')->name('codificacion.excel'); // Codificación
    Route::get('/codificacion/excel-progress/{id}', [CatCodificacionController::class, 'importProgress'])->name('codificacion.excel.progress');
    // Mismo permiso que lanzar la importacion: si no, se puede iniciar y no cancelar.
    Route::post('/codificacion/excel-cancel/{id}', [CatCodificacionController::class, 'cancelImport'])
        ->middleware('module.permission:crear,169')->name('codificacion.excel.cancel'); // Codificación
    Route::get('/codificacion/orden-cambio-excel', [OrdenDeCambioFelpaController::class, 'generarExcel'])->name('codificacion.orden-cambio-excel');

    Route::get('/lmat/api/calibres', [CatLMatController::class, 'getCalibres'])->name('lmat.calibres');
    Route::get('/lmat/api/matriz-calibre', [MatrizCalibresController::class, 'lookup'])->name('lmat.matriz-calibre');
    Route::post('/lmat/api/matriz-calibre/lote', [MatrizCalibresController::class, 'lookupBatch'])->name('lmat.matriz-calibre.lote');
    Route::get('/lmat/api/existe', [CatLMatController::class, 'existeLmat'])->name('lmat.existe');
    Route::get('/lmat/api/ultima', [CatLMatController::class, 'getUltimaLmat'])->name('lmat.ultima');
    Route::get('/lmat/api/configs', [CatLMatController::class, 'getConfigs'])->name('lmat.configs');
    Route::get('/lmat/api/tamanos', [CatLMatController::class, 'getTamanos'])->name('lmat.tamanos');
    Route::get('/lmat/api/colores', [CatLMatController::class, 'getColores'])->name('lmat.colores');
    Route::get('/lmat/api/catalogos-materiales', [CatLMatController::class, 'getCatalogosMateriales'])->name('lmat.catalogos-materiales');
    Route::get('/lmat/api/por-orden/{orden}', [CatLMatController::class, 'getLmatPorOrden'])->name('lmat.por-orden');
    Route::get('/lmat/api/catcodificados-por-orden/{orden}', [CatLMatController::class, 'getRegistroCatCodificadosPorOrden'])->name('lmat.catcodificados-por-orden');
    Route::post('/lmat/api/guardar', [CatLMatController::class, 'guardarLmat'])
        ->middleware('module.permission:modificar,169') // Codificación
        ->name('lmat.guardar');

    Route::get('/alineacion', [AlineacionController::class, 'index'])->name('alineacion.index');
    Route::get('/alineacion/api/data', [AlineacionController::class, 'apiData'])->name('alineacion.api.data');
    Route::get('/alineacion/export/excel', [AlineacionController::class, 'exportarExcel'])->name('alineacion.export.excel');
    Route::get('/alineacion/export/pdf', [AlineacionController::class, 'exportarPdf'])->name('alineacion.export.pdf');

    // ====== RUTAS DE UTILERÍA ======
    Route::prefix('utileria')->name('utileria.')->group(function () {
        Route::get('/', fn () => view('planeacion.utileria.index'))->name('index');

        // * Finalizar Órdenes
        Route::get('/finalizar/telares', [FinalizarOrdenesController::class, 'getTelares'])->name('finalizar.telares');
        Route::get('/finalizar/ordenes', [FinalizarOrdenesController::class, 'getOrdenesByTelar'])->name('finalizar.ordenes');
        Route::post('/finalizar/procesar', [FinalizarOrdenesController::class, 'finalizarOrdenes'])
            ->middleware('module.permission:modificar,188') // Utilería de Planeación; por nombre choca con la 67 de Configuración
            ->name('finalizar.procesar');

        // * Mover Órdenes
        Route::get('/mover/telares', [MoverOrdenesController::class, 'getTelares'])->name('mover.telares');
        Route::get('/mover/registros', [MoverOrdenesController::class, 'getRegistrosByTelar'])->name('mover.registros');
        Route::post('/mover/procesar', [MoverOrdenesController::class, 'moverOrdenes'])
            ->middleware('module.permission:modificar,188') // Utilería de Planeación; por nombre choca con la 67 de Configuración
            ->name('mover.procesar');
    });

    Route::get('/telares', [CatalagoTelarController::class, 'index'])->name('telares.index');
    Route::get('/eficiencia', [CatalagoEficienciaController::class, 'index'])->name('eficiencia.index');
    Route::get('/velocidad', [CatalagoVelocidadController::class, 'index'])->name('velocidad.index');
    Route::get('/calendarios', [CalendarioController::class, 'index'])->name('calendarios.index');
    Route::get('/aplicaciones', [AplicacionesController::class, 'index'])->name('aplicaciones.index');

    Route::post('/telares', [CatalagoTelarController::class, 'store'])
        ->middleware('module.permission:crear,8')->name('telares.store'); // Telares
    Route::put('/telares/{telar}', [CatalagoTelarController::class, 'update'])
        ->middleware('module.permission:modificar,8')->name('telares.update'); // Telares
    Route::delete('/telares/{telar}', [CatalagoTelarController::class, 'destroy'])
        ->middleware('module.permission:eliminar,8')->name('telares.destroy'); // Telares

    Route::post('/telares/excel', [CatalagoTelarController::class, 'procesarExcel'])
        ->middleware('module.permission:crear,8')->name('telares.excel.upload'); // Telares
    Route::post('/eficiencia/excel', [CatalagoEficienciaController::class, 'procesarExcel'])
        ->middleware('module.permission:crear,9')->name('eficiencia.excel.upload'); // Eficiencias STD
    Route::post('/velocidad/excel', [CatalagoVelocidadController::class, 'procesarExcel'])
        ->middleware('module.permission:crear,10')->name('velocidad.excel.upload'); // Velocidad STD
    Route::post('/calendarios/excel', [CalendarioController::class, 'procesarExcel'])
        ->middleware('module.permission:crear,11')->name('calendarios.excel.upload'); // Calendarios
    Route::post('/aplicaciones/excel', [AplicacionesController::class, 'procesarExcel'])
        ->middleware('module.permission:crear,12')->name('aplicaciones.excel.upload'); // Aplicaciones (Cat.)

    Route::get('/calendarios/json', [CalendarioController::class, 'getCalendariosJson'])->name('calendarios.json');
    Route::get('/calendarios/{calendario}/detalle', [CalendarioController::class, 'getCalendarioDetalle'])->name('calendarios.detalle');
    Route::post('/calendarios', [CalendarioController::class, 'store'])
        ->middleware('module.permission:crear,11')->name('calendarios.store'); // Calendarios
    Route::put('/calendarios/{calendario}', [CalendarioController::class, 'update'])
        ->middleware('module.permission:modificar,11')->name('calendarios.update'); // Calendarios
    Route::put('/calendarios/{calendario}/masivo', [CalendarioController::class, 'updateMasivo'])
        ->middleware('module.permission:modificar,11')->name('calendarios.update.masivo'); // Calendarios
    Route::delete('/calendarios/{calendario}', [CalendarioController::class, 'destroy'])
        ->middleware('module.permission:eliminar,11')->name('calendarios.destroy'); // Calendarios

    Route::post('/calendarios/lineas', [CalendarioController::class, 'storeLine'])
        ->middleware('module.permission:crear,11')->name('calendarios.lineas.store'); // Calendarios
    Route::put('/calendarios/lineas/{linea}', [CalendarioController::class, 'updateLine'])
        ->middleware('module.permission:modificar,11')->name('calendarios.lineas.update'); // Calendarios
    Route::delete('/calendarios/lineas/{linea}', [CalendarioController::class, 'destroyLine'])
        ->middleware('module.permission:eliminar,11')->name('calendarios.lineas.destroy'); // Calendarios
    Route::delete('/calendarios/{calendario}/lineas/rango', [CalendarioController::class, 'destroyLineasPorRango'])
        ->middleware('module.permission:eliminar,11')->name('calendarios.lineas.destroy.rango'); // Calendarios

    Route::post('/calendarios/{calendario}/recalcular-programas', [CalendarioController::class, 'recalcularProgramas'])
        ->middleware('module.permission:modificar,11')->name('calendarios.recalcular.programas'); // Calendarios

    Route::post('/eficiencia', [CatalagoEficienciaController::class, 'store'])
        ->middleware('module.permission:crear,9')->name('eficiencia.store'); // Eficiencias STD
    Route::put('/eficiencia/{eficiencia}', [CatalagoEficienciaController::class, 'update'])
        ->middleware('module.permission:modificar,9')->name('eficiencia.update'); // Eficiencias STD
    Route::delete('/eficiencia/{eficiencia}', [CatalagoEficienciaController::class, 'destroy'])
        ->middleware('module.permission:eliminar,9')->name('eficiencia.destroy'); // Eficiencias STD

    Route::post('/velocidad', [CatalagoVelocidadController::class, 'store'])
        ->middleware('module.permission:crear,10')->name('velocidad.store'); // Velocidad STD
    Route::put('/velocidad/{velocidad}', [CatalagoVelocidadController::class, 'update'])
        ->middleware('module.permission:modificar,10')->name('velocidad.update'); // Velocidad STD
    Route::delete('/velocidad/{velocidad}', [CatalagoVelocidadController::class, 'destroy'])
        ->middleware('module.permission:eliminar,10')->name('velocidad.destroy'); // Velocidad STD

    Route::post('/aplicaciones', [AplicacionesController::class, 'store'])
        ->middleware('module.permission:crear,12')->name('aplicaciones.store'); // Aplicaciones (Cat.)
    Route::put('/aplicaciones/{aplicacion}', [AplicacionesController::class, 'update'])
        ->middleware('module.permission:modificar,12')->name('aplicaciones.update'); // Aplicaciones (Cat.)
    Route::delete('/aplicaciones/{aplicacion}', [AplicacionesController::class, 'destroy'])
        ->middleware('module.permission:eliminar,12')->name('aplicaciones.destroy'); // Aplicaciones (Cat.)

    Route::get('/catalogos/matriz-hilos/list', [MatrizHilosController::class, 'list'])->name('matriz-hilos.list');
    Route::post('/catalogos/matriz-hilos', [MatrizHilosController::class, 'store'])
        ->middleware('module.permission:crear,15')->name('matriz-hilos.store'); // Matriz Hilos
    Route::get('/catalogos/matriz-hilos/{id}', [MatrizHilosController::class, 'show'])->name('matriz-hilos.show');
    Route::put('/catalogos/matriz-hilos/{id}', [MatrizHilosController::class, 'update'])
        ->middleware('module.permission:modificar,15')->name('matriz-hilos.update'); // Matriz Hilos
    Route::delete('/catalogos/matriz-hilos/{id}', [MatrizHilosController::class, 'destroy'])
        ->middleware('module.permission:eliminar,15')->name('matriz-hilos.destroy'); // Matriz Hilos
});

Route::get('/modulo-codificación', [CatCodificacionController::class, 'index'])->name('modulo.codificacion');

// ====== RUTAS DE PROGRAMA TEJIDO ======
// IMPORTANTE: Las rutas específicas deben ir ANTES de las rutas con parámetros dinámicos
// para evitar conflictos de precedencia en el enrutador de Laravel

// Ruta GET para el index de programa-tejido (debe ir ANTES de las rutas con {id})
Route::get('/planeacion/programa-tejido', [ProgramaTejidoController::class, 'index'])->name('catalogos.req-programa-tejido');
// Lectura v2 (PT-02): apagada por flag (planeacion.read_v2.*), responde 404 hasta el canary.
Route::get('/planeacion/programa-tejido/v2/registros', [ProgramaTejidoReadController::class, 'registros'])
    ->middleware('module.permission:acceso,2')->name('programa-tejido.v2.registros'); // Programa Tejido
Route::get('/planeacion/programa-tejido/redbooth/proyectos', [RedboothProgramaTejidoController::class, 'projectOptions'])
    ->name('programa-tejido.redbooth.proyectos');
Route::post('/planeacion/programa-tejido/redbooth', [RedboothProgramaTejidoController::class, 'store'])
    ->middleware('module.permission:modificar,2') // Programa Tejido
    ->name('programa-tejido.redbooth.store');
Route::get('/planeacion/programa-tejido/redbooth/{programa}', [RedboothProgramaTejidoController::class, 'show'])
    ->whereNumber('programa')
    ->name('programa-tejido.redbooth.show');
Route::delete('/planeacion/programa-tejido/redbooth/{programa}', [RedboothProgramaTejidoController::class, 'destroy'])
    ->whereNumber('programa')
    // Desvincula Redbooth: pone IdRedbooth/NombreRedbooth en NULL, no borra el programa.
    ->middleware('module.permission:modificar,2') // Programa Tejido
    ->name('programa-tejido.redbooth.destroy');

// Rutas específicas de programa-tejido (sin parámetros dinámicos)
Route::get('/planeacion/programa-tejido/auditoria', [AuditoriaProgramaTejidoController::class, 'index'])->name('programa-tejido.auditoria');
Route::get('/planeacion/programa-tejido/liberar-ordenes', [LiberarOrdenesController::class, 'index'])->name('programa-tejido.liberar-ordenes');
Route::post('/planeacion/programa-tejido/liberar-ordenes/procesar', [LiberarOrdenesController::class, 'liberar'])
    ->middleware('module.permission:crear,2') // Programa Tejido
    ->name('programa-tejido.liberar-ordenes.procesar');
Route::get('/planeacion/programa-tejido/liberar-ordenes/bom-sugerencias', [LiberarOrdenesController::class, 'obtenerBomYNombre'])->name('programa-tejido.liberar-ordenes.bom');
Route::get('/planeacion/programa-tejido/liberar-ordenes/tipo-hilo', [LiberarOrdenesController::class, 'obtenerTipoHilo'])->name('programa-tejido.liberar-ordenes.tipo-hilo');
Route::get('/planeacion/programa-tejido/liberar-ordenes/codigo-dibujo', [LiberarOrdenesController::class, 'obtenerCodigoDibujo'])->name('programa-tejido.liberar-ordenes.codigo-dibujo');
Route::get('/planeacion/programa-tejido/liberar-ordenes/opciones-hilos', [LiberarOrdenesController::class, 'obtenerOpcionesHilos'])->name('programa-tejido.liberar-ordenes.opciones-hilos');
Route::get('/planeacion/programa-tejido/liberar-ordenes/flog-sugerido', [LiberarOrdenesController::class, 'obtenerFlogSugerido'])->name('programa-tejido.liberar-ordenes.flog');
Route::post('/planeacion/programa-tejido/liberar-ordenes/guardar-campos', [LiberarOrdenesController::class, 'guardarCamposEditables'])
    ->middleware('module.permission:modificar,2')->name('programa-tejido.liberar-ordenes.guardar-campos'); // Programa Tejido

// Editar marbetes desde el menú contextual de Programa Tejido
Route::get('/planeacion/programa-tejido/marbetes', [LiberarOrdenesController::class, 'marbetes'])->name('programa-tejido.marbetes');
Route::post('/planeacion/programa-tejido/marbetes', [LiberarOrdenesController::class, 'guardarMarbetes'])
    ->middleware('module.permission:modificar,2')->name('programa-tejido.marbetes.guardar'); // Programa Tejido

Route::get('/planeacion/programa-tejido/reimprimir-ordenes/{id}', [ReimprimirOrdenesController::class, 'reimprimir'])->name('planeacion.programa-tejido.reimprimir-ordenes');

// Escribe el TXT UNC de planta: mismo permiso con el que la UI muestra el botón (x-navbar.button-report).
Route::post('/planeacion/programa-tejido/descargar-programa', [DescargarProgramaController::class, 'descargar'])
    ->middleware('module.permission:registrar,2')->name('programa-tejido.descargar-programa'); // Programa Tejido
Route::post('/planeacion/programa-tejido/{id}/prioridad/mover', [ProgramaTejidoOperacionesController::class, 'moveToPosition'])
    ->middleware('module.permission:modificar,2')->name('programa-tejido.prioridad.mover'); // Programa Tejido
Route::post('/planeacion/programa-tejido/{id}/verificar-cambio-telar', [ProgramaTejidoOperacionesController::class, 'verificarCambioTelar'])->name('programa-tejido.verificar-cambio-telar');
Route::post('/planeacion/programa-tejido/{id}/cambiar-telar', [ProgramaTejidoOperacionesController::class, 'cambiarTelar'])
    ->middleware('module.permission:modificar,2')->name('programa-tejido.cambiar-telar'); // Programa Tejido
Route::post('/planeacion/programa-tejido/duplicar-telar', [ProgramaTejidoOperacionesController::class, 'duplicarTelar'])
    ->middleware('module.permission:crear,2')->name('programa-tejido.duplicar-telar'); // Programa Tejido
Route::post('/planeacion/programa-tejido/dividir-telar', [ProgramaTejidoOperacionesController::class, 'dividirTelar'])
    ->middleware('module.permission:crear,2')->name('programa-tejido.dividir-telar'); // Programa Tejido
Route::post('/planeacion/programa-tejido/dividir-saldo', [ProgramaTejidoOperacionesController::class, 'dividirSaldo'])
    ->middleware('module.permission:crear,2')->name('programa-tejido.dividir-saldo'); // Programa Tejido
Route::post('/planeacion/programa-tejido/vincular-registros-existentes', [ProgramaTejidoOperacionesController::class, 'vincularRegistrosExistentes'])
    ->middleware('module.permission:modificar,2')->name('programa-tejido.vincular-registros-existentes'); // Programa Tejido
Route::post('/planeacion/programa-tejido/{id}/desvincular', [ProgramaTejidoOperacionesController::class, 'desvincularRegistro'])
    ->middleware('module.permission:modificar,2')->name('programa-tejido.desvincular'); // Programa Tejido
Route::get('/planeacion/programa-tejido/registros-ord-compartida/{ordCompartida}', [ProgramaTejidoOperacionesController::class, 'getRegistrosPorOrdCompartida'])->name('programa-tejido.registros-ord-compartida');
Route::get('/planeacion/programa-tejido/balancear', [ProgramaTejidoBalanceoController::class, 'balancear'])->name('programa-tejido.balancear');
Route::get('/planeacion/programa-tejido/{id}/detalles-balanceo', [ProgramaTejidoBalanceoController::class, 'detallesBalanceo'])->name('programa-tejido.detalles-balanceo');
Route::post('/planeacion/programa-tejido/preview-fechas-balanceo', [ProgramaTejidoBalanceoController::class, 'previewFechasBalanceo'])->name('programa-tejido.preview-fechas-balanceo');
Route::post('/planeacion/programa-tejido/actualizar-pedidos-balanceo', [ProgramaTejidoBalanceoController::class, 'actualizarPedidosBalanceo'])
    ->middleware('module.permission:modificar,2')->name('programa-tejido.actualizar-pedidos-balanceo'); // Programa Tejido
Route::post('/planeacion/programa-tejido/balancear-automatico', [ProgramaTejidoBalanceoController::class, 'balancearAutomatico'])
    ->middleware('module.permission:modificar,2')->name('programa-tejido.balancear-automatico'); // Programa Tejido
Route::get('/planeacion/programa-tejido/ver-detalles-grupo-balanceo/{ordCompartida}', [ProgramaTejidoBalanceoController::class, 'verDetallesGrupoBalanceo'])->name('verdetallesgrupobalanceo');
Route::put('/planeacion/programa-tejido/{id}', [ProgramaTejidoController::class, 'update'])
    ->middleware('module.permission:modificar,2')->name('programa-tejido.update'); // Programa Tejido
Route::delete('/planeacion/programa-tejido/{id}', [ProgramaTejidoController::class, 'destroy'])
    ->middleware('module.permission:eliminar,2')->name('programa-tejido.destroy'); // Programa Tejido
Route::delete('/planeacion/programa-tejido/{id}/en-proceso', [ProgramaTejidoController::class, 'destroyEnProceso'])
    ->middleware('module.permission:eliminar,2')->name('programa-tejido.destroy-en-proceso'); // Programa Tejido
Route::get('/planeacion/programa-tejido/all-registros-json', [ProgramaTejidoCalendariosController::class, 'getAllRegistrosJson'])->name('programa-tejido.all-registros-json');
Route::post('/planeacion/programa-tejido/actualizar-calendarios-masivo', [ProgramaTejidoCalendariosController::class, 'actualizarCalendariosMasivo'])
    ->middleware('module.permission:modificar,2')->name('programa-tejido.actualizar-calendarios-masivo'); // Programa Tejido
Route::post('/planeacion/programa-tejido/{id}/reprogramar', [ProgramaTejidoCalendariosController::class, 'actualizarReprogramar'])
    ->middleware('module.permission:modificar,2')->name('programa-tejido.reprogramar'); // Programa Tejido
Route::post('/planeacion/programa-tejido/crear-repaso', [RepasoController::class, 'createrepaso'])
    ->middleware('module.permission:crear,2')->name('programa-tejido.crear-repaso'); // Programa Tejido
Route::post('/planeacion/programa-tejido/recalcular-fechas', [ProgramaTejidoCalendariosController::class, 'recalcularFechas'])
    ->middleware('module.permission:modificar,2')->name('programa-tejido.recalcular-fechas'); // Programa Tejido
Route::post('/planeacion/muestras/recalcular-fechas', [ProgramaTejidoCalendariosController::class, 'recalcularFechas'])
    ->middleware('module.permission:modificar,5')->name('muestras.recalcular-fechas'); // Muestras
Route::get('/planeacion/req-programa-tejido-line', [ReqProgramaTejidoLineController::class, 'index'])->name('planeacion.req-programa-tejido-line');

Route::get('/programa-tejido/salon-options', [ProgramaTejidoCatalogosController::class, 'getSalonTejidoOptions']);
Route::get('/programa-tejido/salon-tejido-options', [ProgramaTejidoCatalogosController::class, 'getSalonTejidoOptions'])->name('programa-tejido.salon-tejido-options');
Route::get('/programa-tejido/tamano-clave-by-salon', [ProgramaTejidoCatalogosController::class, 'getTamanoClaveBySalon']);
Route::get('/programa-tejido/flogs-id-options', [ProgramaTejidoCatalogosController::class, 'getFlogsIdOptions']);
Route::get('/programa-tejido/flogs-id-from-twflogs', [ProgramaTejidoCatalogosController::class, 'getFlogsIdFromTwFlogsTable']);
Route::get('/programa-tejido/descripcion-by-idflog/{idflog}', [ProgramaTejidoCatalogosController::class, 'getDescripcionByIdFlog']);
Route::get('/programa-tejido/flog-by-item', [ProgramaTejidoCatalogosController::class, 'getFlogByItem']);
Route::get('/programa-tejido/flogs-by-tamano-clave', [ProgramaTejidoCatalogosController::class, 'getFlogsByTamanoClave']);
Route::get('/programa-tejido/calendario-id-options', [ProgramaTejidoCatalogosController::class, 'getCalendarioIdOptions']);
Route::get('/programa-tejido/calendario-lineas/{calendarioId}', [ProgramaTejidoCatalogosController::class, 'getCalendarioLineas'])->name('programa-tejido.calendario-lineas');
Route::get('/programa-tejido/aplicacion-id-options', [ProgramaTejidoCatalogosController::class, 'getAplicacionIdOptions']);
Route::match(['get', 'post'], '/programa-tejido/datos-relacionados', [ProgramaTejidoCatalogosController::class, 'getDatosRelacionados']);
Route::get('/programa-tejido/telares-by-salon', [ProgramaTejidoCatalogosController::class, 'getTelaresBySalon']);
Route::get('/programa-tejido/telares-all', [ProgramaTejidoCatalogosController::class, 'getTelaresAll']);
Route::get('/programa-tejido/ultima-fecha-final-telar', [ProgramaTejidoCatalogosController::class, 'getUltimaFechaFinalTelar']);
Route::get('/programa-tejido/hilos-options', [ProgramaTejidoCatalogosController::class, 'getHilosOptions']);
Route::get('/programa-tejido/eficiencia-std', [ProgramaTejidoCatalogosController::class, 'getEficienciaStd']);
Route::get('/programa-tejido/velocidad-std', [ProgramaTejidoCatalogosController::class, 'getVelocidadStd']);
Route::get('/programa-tejido/eficiencia-velocidad-std', [ProgramaTejidoCatalogosController::class, 'getEficienciaVelocidadStd']);
Route::get('/programa-tejido/columnas', [ColumnasProgramaTejidoController::class, 'index']);
Route::get('/programa-tejido/columnas/visibles', [ColumnasProgramaTejidoController::class, 'getColumnasVisibles']);
Route::post('/programa-tejido/columnas', [ColumnasProgramaTejidoController::class, 'store']);

// ====== RUTAS DE MUESTRAS (reusa ProgramaTejido) ======
Route::get('/planeacion/muestras', [ProgramaTejidoController::class, 'index'])->name('muestras.index');
Route::get('/planeacion/muestras/v2/registros', [ProgramaTejidoReadController::class, 'registros'])
    ->middleware('module.permission:acceso,5')->name('muestras.v2.registros'); // Muestras

Route::get('/planeacion/muestras/liberar-ordenes', [LiberarOrdenesController::class, 'index'])->name('muestras.liberar-ordenes');
Route::post('/planeacion/muestras/liberar-ordenes/procesar', [LiberarOrdenesController::class, 'liberar'])
    ->middleware('module.permission:crear,5') // Muestras (decisión del owner PT-01.3)
    ->name('muestras.liberar-ordenes.procesar');
Route::get('/planeacion/muestras/liberar-ordenes/bom-sugerencias', [LiberarOrdenesController::class, 'obtenerBomYNombre'])->name('muestras.liberar-ordenes.bom');
Route::get('/planeacion/muestras/liberar-ordenes/tipo-hilo', [LiberarOrdenesController::class, 'obtenerTipoHilo'])->name('muestras.liberar-ordenes.tipo-hilo');
Route::get('/planeacion/muestras/liberar-ordenes/codigo-dibujo', [LiberarOrdenesController::class, 'obtenerCodigoDibujo'])->name('muestras.liberar-ordenes.codigo-dibujo');
Route::get('/planeacion/muestras/liberar-ordenes/opciones-hilos', [LiberarOrdenesController::class, 'obtenerOpcionesHilos'])->name('muestras.liberar-ordenes.opciones-hilos');
Route::get('/planeacion/muestras/liberar-ordenes/flog-sugerido', [LiberarOrdenesController::class, 'obtenerFlogSugerido'])->name('muestras.liberar-ordenes.flog');
Route::post('/planeacion/muestras/liberar-ordenes/guardar-campos', [LiberarOrdenesController::class, 'guardarCamposEditables'])
    ->middleware('module.permission:modificar,5')->name('muestras.liberar-ordenes.guardar-campos'); // Muestras

Route::get('/planeacion/muestras/reimprimir-ordenes/{id}', [ReimprimirOrdenesController::class, 'reimprimir'])->name('planeacion.muestras.reimprimir-ordenes');

// Descarga es exclusiva de Programa (decisión 01.3 B): el controller responde 422 en Muestras.
Route::post('/planeacion/muestras/descargar-programa', [DescargarProgramaController::class, 'descargar'])
    ->middleware('module.permission:registrar,5')->name('muestras.descargar-programa'); // Muestras
Route::post('/planeacion/muestras/{id}/prioridad/mover', [ProgramaTejidoOperacionesController::class, 'moveToPosition'])
    ->middleware('module.permission:modificar,5')->name('muestras.prioridad.mover'); // Muestras
Route::post('/planeacion/muestras/{id}/verificar-cambio-telar', [ProgramaTejidoOperacionesController::class, 'verificarCambioTelar'])->name('muestras.verificar-cambio-telar');
Route::post('/planeacion/muestras/{id}/cambiar-telar', [ProgramaTejidoOperacionesController::class, 'cambiarTelar'])
    ->middleware('module.permission:modificar,5')->name('muestras.cambiar-telar'); // Muestras
Route::post('/planeacion/muestras/duplicar-telar', [ProgramaTejidoOperacionesController::class, 'duplicarTelar'])
    ->middleware('module.permission:crear,5')->name('muestras.duplicar-telar'); // Muestras
Route::post('/planeacion/muestras/dividir-telar', [ProgramaTejidoOperacionesController::class, 'dividirTelar'])
    ->middleware('module.permission:crear,5')->name('muestras.dividir-telar'); // Muestras
Route::post('/planeacion/muestras/dividir-saldo', [ProgramaTejidoOperacionesController::class, 'dividirSaldo'])
    ->middleware('module.permission:crear,5')->name('muestras.dividir-saldo'); // Muestras
Route::post('/planeacion/muestras/vincular-registros-existentes', [ProgramaTejidoOperacionesController::class, 'vincularRegistrosExistentes'])
    ->middleware('module.permission:modificar,5')->name('muestras.vincular-registros-existentes'); // Muestras
Route::post('/planeacion/muestras/{id}/desvincular', [ProgramaTejidoOperacionesController::class, 'desvincularRegistro'])
    ->middleware('module.permission:modificar,5')->name('muestras.desvincular'); // Muestras
Route::get('/planeacion/muestras/registros-ord-compartida/{ordCompartida}', [ProgramaTejidoOperacionesController::class, 'getRegistrosPorOrdCompartida'])->name('muestras.registros-ord-compartida');
Route::get('/planeacion/muestras/balancear', [ProgramaTejidoBalanceoController::class, 'balancear'])->name('muestras.balancear');
Route::get('/planeacion/muestras/{id}/detalles-balanceo', [ProgramaTejidoBalanceoController::class, 'detallesBalanceo'])->name('muestras.detalles-balanceo');
Route::post('/planeacion/muestras/preview-fechas-balanceo', [ProgramaTejidoBalanceoController::class, 'previewFechasBalanceo'])->name('muestras.preview-fechas-balanceo');
Route::post('/planeacion/muestras/actualizar-pedidos-balanceo', [ProgramaTejidoBalanceoController::class, 'actualizarPedidosBalanceo'])
    ->middleware('module.permission:modificar,5')->name('muestras.actualizar-pedidos-balanceo'); // Muestras
Route::post('/planeacion/muestras/balancear-automatico', [ProgramaTejidoBalanceoController::class, 'balancearAutomatico'])
    ->middleware('module.permission:modificar,5')->name('muestras.balancear-automatico'); // Muestras
Route::get('/planeacion/muestras/ver-detalles-grupo-balanceo/{ordCompartida}', [ProgramaTejidoBalanceoController::class, 'verDetallesGrupoBalanceo'])->name('muestras.verdetallesgrupobalanceo');
Route::put('/planeacion/muestras/{id}', [ProgramaTejidoController::class, 'update'])
    ->middleware('module.permission:modificar,5')->name('muestras.update'); // Muestras
Route::delete('/planeacion/muestras/{id}', [ProgramaTejidoController::class, 'destroy'])
    ->middleware('module.permission:eliminar,5')->name('muestras.destroy'); // Muestras
Route::delete('/planeacion/muestras/{id}/en-proceso', [ProgramaTejidoController::class, 'destroyEnProceso'])
    ->middleware('module.permission:eliminar,5')->name('muestras.destroy-en-proceso'); // Muestras
Route::get('/planeacion/muestras/all-registros-json', [ProgramaTejidoCalendariosController::class, 'getAllRegistrosJson'])->name('muestras.all-registros-json');
Route::post('/planeacion/muestras/actualizar-calendarios-masivo', [ProgramaTejidoCalendariosController::class, 'actualizarCalendariosMasivo'])
    ->middleware('module.permission:modificar,5')->name('muestras.actualizar-calendarios-masivo'); // Muestras
Route::post('/planeacion/muestras/{id}/reprogramar', [ProgramaTejidoCalendariosController::class, 'actualizarReprogramar'])
    ->middleware('module.permission:modificar,5')->name('muestras.reprogramar'); // Muestras
Route::post('/planeacion/muestras/crear-repaso', [RepasoController::class, 'createrepaso'])
    ->middleware('module.permission:crear,5')->name('muestras.crear-repaso'); // Muestras
Route::get('/planeacion/muestras-line', [ReqProgramaTejidoLineController::class, 'index'])->name('planeacion.muestras-line');

Route::get('/muestras/salon-options', [ProgramaTejidoCatalogosController::class, 'getSalonTejidoOptions']);
Route::get('/muestras/salon-tejido-options', [ProgramaTejidoCatalogosController::class, 'getSalonTejidoOptions'])->name('muestras.salon-tejido-options');
Route::get('/muestras/tamano-clave-by-salon', [ProgramaTejidoCatalogosController::class, 'getTamanoClaveBySalon']);
Route::get('/muestras/flogs-id-options', [ProgramaTejidoCatalogosController::class, 'getFlogsIdOptions']);
Route::get('/muestras/flogs-id-from-twflogs', [ProgramaTejidoCatalogosController::class, 'getFlogsIdFromTwFlogsTable']);
Route::get('/muestras/descripcion-by-idflog/{idflog}', [ProgramaTejidoCatalogosController::class, 'getDescripcionByIdFlog']);
Route::get('/muestras/flog-by-item', [ProgramaTejidoCatalogosController::class, 'getFlogByItem']);
Route::get('/muestras/flogs-by-tamano-clave', [ProgramaTejidoCatalogosController::class, 'getFlogsByTamanoClave']);
Route::get('/muestras/calendario-id-options', [ProgramaTejidoCatalogosController::class, 'getCalendarioIdOptions']);
Route::get('/muestras/calendario-lineas/{calendarioId}', [ProgramaTejidoCatalogosController::class, 'getCalendarioLineas'])->name('muestras.calendario-lineas');
Route::get('/muestras/aplicacion-id-options', [ProgramaTejidoCatalogosController::class, 'getAplicacionIdOptions']);
Route::match(['get', 'post'], '/muestras/datos-relacionados', [ProgramaTejidoCatalogosController::class, 'getDatosRelacionados']);
Route::get('/muestras/telares-by-salon', [ProgramaTejidoCatalogosController::class, 'getTelaresBySalon']);
Route::get('/muestras/telares-all', [ProgramaTejidoCatalogosController::class, 'getTelaresAll']);
Route::get('/muestras/ultima-fecha-final-telar', [ProgramaTejidoCatalogosController::class, 'getUltimaFechaFinalTelar']);
Route::get('/muestras/hilos-options', [ProgramaTejidoCatalogosController::class, 'getHilosOptions']);
Route::get('/muestras/eficiencia-std', [ProgramaTejidoCatalogosController::class, 'getEficienciaStd']);
Route::get('/muestras/velocidad-std', [ProgramaTejidoCatalogosController::class, 'getVelocidadStd']);
Route::get('/muestras/eficiencia-velocidad-std', [ProgramaTejidoCatalogosController::class, 'getEficienciaVelocidadStd']);
Route::get('/muestras/columnas', [ColumnasProgramaTejidoController::class, 'index']);
Route::get('/muestras/columnas/visibles', [ColumnasProgramaTejidoController::class, 'getColumnasVisibles']);
Route::post('/muestras/columnas', [ColumnasProgramaTejidoController::class, 'store']);
