<?php

use App\Http\Controllers\Planeacion\CatCodificados\CatCodificacionController;
use App\Http\Controllers\Planeacion\CatalogoPlaneacion\CatAplicaciones\AplicacionesController;
use App\Http\Controllers\Planeacion\CatalogoPlaneacion\CatCalendarios\CalendarioController;
use App\Http\Controllers\Planeacion\CatalogoPlaneacion\CatEficiencias\CatalagoEficienciaController;
use App\Http\Controllers\Planeacion\CatalogoPlaneacion\CatMatrizHilos\MatrizHilosController;
use App\Http\Controllers\Planeacion\CatalogoPlaneacion\CatPesosRollos\PesosRollosController;
use App\Http\Controllers\Planeacion\CatalogoPlaneacion\CatTelares\CatalagoTelarController;
use App\Http\Controllers\Planeacion\CatalogoPlaneacion\CatVelocidades\CatalagoVelocidadController;
use App\Http\Controllers\Planeacion\CatalogoPlaneacion\ModelosCodificados\CodificacionController;
use App\Http\Controllers\Planeacion\ProgramaTejido\ColumnasProgramaTejidoController;
use App\Http\Controllers\Planeacion\ProgramaTejido\DescargarProgramaController;
use App\Http\Controllers\Planeacion\ProgramaTejido\LiberarOrdenesController;
use App\Http\Controllers\Planeacion\ProgramaTejido\OrdenDeCambio\Felpa\OrdenDeCambioFelpaController;
use App\Http\Controllers\Planeacion\ProgramaTejido\ProgramaTejidoController;
use App\Http\Controllers\Planeacion\ProgramaTejido\ReimprimirOrdenesController;
use App\Http\Controllers\Planeacion\ProgramaTejido\ReqProgramaTejidoLineController;
use App\Http\Controllers\Planeacion\ProgramaTejido\funciones\BalancearTejido;
use App\Http\Controllers\Planeacion\ProgramaTejido\funciones\DividirTejido;
use App\Http\Controllers\Planeacion\Alineacion\AlineacionController;

use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::get('/planeacion', fn() => app(UsuarioController::class)->showSubModulos('planeacion'))
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

        Route::get('/telares', [CatalagoTelarController::class, 'index'])->name('telares');
        Route::get('/telares/falla', [CatalagoTelarController::class, 'falla'])->name('telares.falla');
        Route::get('/eficiencia', [CatalagoEficienciaController::class, 'index'])->name('eficiencia');
        Route::get('/velocidad', [CatalagoVelocidadController::class, 'index'])->name('velocidad');
        Route::get('/calendarios', [CalendarioController::class, 'index'])->name('calendarios');
        Route::get('/aplicaciones', [AplicacionesController::class, 'index'])->name('aplicaciones');
        Route::get('/matriz-hilos', [MatrizHilosController::class, 'index'])->name('matriz-hilos');
        Route::get('/pesos-rollos', [PesosRollosController::class, 'index'])->name('pesos-rollos');
        Route::post('/pesos-rollos', [PesosRollosController::class, 'store'])->name('pesos-rollos.store');
        Route::put('/pesos-rollos/{id}', [PesosRollosController::class, 'update'])->name('pesos-rollos.update');
        Route::delete('/pesos-rollos/{id}', [PesosRollosController::class, 'destroy'])->name('pesos-rollos.destroy');

        Route::get('/codificacion-modelos', [CodificacionController::class, 'index'])->name('codificacion-modelos');
        Route::get('/codificacion-modelos/create', [CodificacionController::class, 'create'])->name('codificacion.create');
        Route::get('/codificacion-modelos/get-all', [CodificacionController::class, 'getAll'])->name('codificacion.get-all');
        Route::get('/codificacion-modelos/api/all-fast', [CodificacionController::class, 'getAllFast'])->name('codificacion.all-fast');
        Route::get('/codificacion-modelos/estadisticas', [CodificacionController::class, 'estadisticas'])->name('codificacion.estadisticas');
        Route::get('/codificacion-modelos/salones-telares', [CodificacionController::class, 'getSalonesYTelares'])->name('codificacion.salones-telares');
        Route::get('/codificacion-modelos/flogs-data', [CodificacionController::class, 'getFlogsData'])->name('codificacion.flogs-data');
        Route::get('/codificacion-modelos/catcodificados-orden', [CodificacionController::class, 'getCatCodificadosByOrden'])->name('codificacion.catcodificados-orden');
        Route::post('/codificacion-modelos/duplicar-importar', [CodificacionController::class, 'duplicarImportar'])->name('codificacion.duplicar-importar');
        Route::get('/codificacion-modelos/{id}/edit', [CodificacionController::class, 'edit'])->name('codificacion.edit');
        Route::post('/codificacion-modelos/{id}/duplicate', [CodificacionController::class, 'duplicate'])->name('codificacion.duplicate');
        Route::get('/codificacion-modelos/{id}', [CodificacionController::class, 'show'])->name('codificacion.show');
        Route::post('/codificacion-modelos', [CodificacionController::class, 'store'])->name('codificacion.store');
        Route::put('/codificacion-modelos/{id}', [CodificacionController::class, 'update'])->name('codificacion.update');
        Route::delete('/codificacion-modelos/{id}', [CodificacionController::class, 'destroy'])->name('codificacion.destroy');
        Route::post('/codificacion-modelos/excel', [CodificacionController::class, 'procesarExcel'])->name('codificacion.excel');
        Route::get('/codificacion-modelos/excel-progress/{id}', [CodificacionController::class, 'importProgress'])->name('codificacion.excel.progress');
        Route::post('/codificacion-modelos/buscar', [CodificacionController::class, 'buscar'])->name('codificacion.buscar');
    });

    Route::get('/codificacion', [CatCodificacionController::class, 'index'])->name('codificacion.index');
    Route::get('/codificacion/api/all-fast', [CatCodificacionController::class, 'getAllFast'])->name('codificacion.all-fast');
    Route::post('/codificacion/excel', [CatCodificacionController::class, 'procesarExcel'])->name('codificacion.excel');
    Route::get('/codificacion/excel-progress/{id}', [CatCodificacionController::class, 'importProgress'])->name('codificacion.excel.progress');
    Route::get('/codificacion/orden-cambio-pdf', [OrdenDeCambioFelpaController::class, 'generarPDF'])->name('codificacion.orden-cambio-pdf');
    Route::get('/codificacion/orden-cambio-excel', [OrdenDeCambioFelpaController::class, 'generarExcel'])->name('codificacion.orden-cambio-excel');

    Route::get('/alineacion', [AlineacionController::class, 'index'])->name('alineacion.index');

    Route::get('/telares', [CatalagoTelarController::class, 'index'])->name('telares.index');
    Route::get('/telares/falla', [CatalagoTelarController::class, 'falla'])->name('telares.falla');
    Route::get('/eficiencia', [CatalagoEficienciaController::class, 'index'])->name('eficiencia.index');
    Route::get('/velocidad', [CatalagoVelocidadController::class, 'index'])->name('velocidad.index');
    Route::get('/calendarios', [CalendarioController::class, 'index'])->name('calendarios.index');
    Route::get('/aplicaciones', [AplicacionesController::class, 'index'])->name('aplicaciones.index');

    Route::post('/telares', [CatalagoTelarController::class, 'store'])->name('telares.store');
    Route::put('/telares/{telar}', [CatalagoTelarController::class, 'update'])->name('telares.update');
    Route::delete('/telares/{telar}', [CatalagoTelarController::class, 'destroy'])->name('telares.destroy');

    Route::post('/telares/excel', [CatalagoTelarController::class, 'procesarExcel'])->name('telares.excel.upload');
    Route::post('/eficiencia/excel', [CatalagoEficienciaController::class, 'procesarExcel'])->name('eficiencia.excel.upload');
    Route::post('/velocidad/excel', [CatalagoVelocidadController::class, 'procesarExcel'])->name('velocidad.excel.upload');
    Route::post('/calendarios/excel', [CalendarioController::class, 'procesarExcel'])->name('calendarios.excel.upload');
    Route::post('/aplicaciones/excel', [AplicacionesController::class, 'procesarExcel'])->name('aplicaciones.excel.upload');

    Route::get('/calendarios/json', [CalendarioController::class, 'getCalendariosJson'])->name('calendarios.json');
    Route::get('/calendarios/{calendario}/detalle', [CalendarioController::class, 'getCalendarioDetalle'])->name('calendarios.detalle');
    Route::post('/calendarios', [CalendarioController::class, 'store'])->name('calendarios.store');
    Route::put('/calendarios/{calendario}', [CalendarioController::class, 'update'])->name('calendarios.update');
    Route::put('/calendarios/{calendario}/masivo', [CalendarioController::class, 'updateMasivo'])->name('calendarios.update.masivo');
    Route::delete('/calendarios/{calendario}', [CalendarioController::class, 'destroy'])->name('calendarios.destroy');

    Route::post('/calendarios/lineas', [CalendarioController::class, 'storeLine'])->name('calendarios.lineas.store');
    Route::put('/calendarios/lineas/{linea}', [CalendarioController::class, 'updateLine'])->name('calendarios.lineas.update');
    Route::delete('/calendarios/lineas/{linea}', [CalendarioController::class, 'destroyLine'])->name('calendarios.lineas.destroy');
    Route::delete('/calendarios/{calendario}/lineas/rango', [CalendarioController::class, 'destroyLineasPorRango'])->name('calendarios.lineas.destroy.rango');

    Route::post('/calendarios/{calendario}/recalcular-programas', [CalendarioController::class, 'recalcularProgramas'])->name('calendarios.recalcular.programas');

    Route::post('/eficiencia', [CatalagoEficienciaController::class, 'store'])->name('eficiencia.store');
    Route::put('/eficiencia/{eficiencia}', [CatalagoEficienciaController::class, 'update'])->name('eficiencia.update');
    Route::delete('/eficiencia/{eficiencia}', [CatalagoEficienciaController::class, 'destroy'])->name('eficiencia.destroy');

    Route::post('/velocidad', [CatalagoVelocidadController::class, 'store'])->name('velocidad.store');
    Route::put('/velocidad/{velocidad}', [CatalagoVelocidadController::class, 'update'])->name('velocidad.update');
    Route::delete('/velocidad/{velocidad}', [CatalagoVelocidadController::class, 'destroy'])->name('velocidad.destroy');

    Route::post('/aplicaciones', [AplicacionesController::class, 'store'])->name('aplicaciones.store');
    Route::put('/aplicaciones/{aplicacion}', [AplicacionesController::class, 'update'])->name('aplicaciones.update');
    Route::delete('/aplicaciones/{aplicacion}', [AplicacionesController::class, 'destroy'])->name('aplicaciones.destroy');

    Route::get('/catalogos/matriz-hilos/list', [MatrizHilosController::class, 'list'])->name('matriz-hilos.list');
    Route::post('/catalogos/matriz-hilos', [MatrizHilosController::class, 'store'])->name('matriz-hilos.store');
    Route::get('/catalogos/matriz-hilos/{id}', [MatrizHilosController::class, 'show'])->name('matriz-hilos.show');
    Route::put('/catalogos/matriz-hilos/{id}', [MatrizHilosController::class, 'update'])->name('matriz-hilos.update');
    Route::delete('/catalogos/matriz-hilos/{id}', [MatrizHilosController::class, 'destroy'])->name('matriz-hilos.destroy');
});

Route::get('/modulo-codificación', [CatCodificacionController::class, 'index'])->name('modulo.codificacion');

// ====== RUTAS DE PROGRAMA TEJIDO ======
// IMPORTANTE: Las rutas específicas deben ir ANTES de las rutas con parámetros dinámicos
// para evitar conflictos de precedencia en el enrutador de Laravel

// Ruta GET para el index de programa-tejido (debe ir ANTES de las rutas con {id})
Route::get('/planeacion/programa-tejido', [ProgramaTejidoController::class, 'index'])->name('catalogos.req-programa-tejido');

// Rutas específicas de programa-tejido (sin parámetros dinámicos)
Route::get('/planeacion/programa-tejido/liberar-ordenes', [LiberarOrdenesController::class, 'index'])->name('programa-tejido.liberar-ordenes');
Route::post('/planeacion/programa-tejido/liberar-ordenes/procesar', [LiberarOrdenesController::class, 'liberar'])->name('programa-tejido.liberar-ordenes.procesar');
Route::get('/planeacion/programa-tejido/liberar-ordenes/bom-sugerencias', [LiberarOrdenesController::class, 'obtenerBomYNombre'])->name('programa-tejido.liberar-ordenes.bom');
Route::get('/planeacion/programa-tejido/liberar-ordenes/tipo-hilo', [LiberarOrdenesController::class, 'obtenerTipoHilo'])->name('programa-tejido.liberar-ordenes.tipo-hilo');
Route::get('/planeacion/programa-tejido/liberar-ordenes/codigo-dibujo', [LiberarOrdenesController::class, 'obtenerCodigoDibujo'])->name('programa-tejido.liberar-ordenes.codigo-dibujo');
Route::get('/planeacion/programa-tejido/liberar-ordenes/opciones-hilos', [LiberarOrdenesController::class, 'obtenerOpcionesHilos'])->name('programa-tejido.liberar-ordenes.opciones-hilos');
Route::post('/planeacion/programa-tejido/liberar-ordenes/guardar-campos', [LiberarOrdenesController::class, 'guardarCamposEditables'])->name('programa-tejido.liberar-ordenes.guardar-campos');

Route::get('/planeacion/programa-tejido/reimprimir-ordenes/{id}', [ReimprimirOrdenesController::class, 'reimprimir'])->name('planeacion.programa-tejido.reimprimir-ordenes');

Route::post('/planeacion/programa-tejido/descargar-programa', [DescargarProgramaController::class, 'descargar'])->name('programa-tejido.descargar-programa');
Route::post('/planeacion/programa-tejido/{id}/prioridad/mover', [ProgramaTejidoController::class, 'moveToPosition'])->name('programa-tejido.prioridad.mover');
Route::post('/planeacion/programa-tejido/{id}/verificar-cambio-telar', [ProgramaTejidoController::class, 'verificarCambioTelar'])->name('programa-tejido.verificar-cambio-telar');
Route::post('/planeacion/programa-tejido/{id}/cambiar-telar', [ProgramaTejidoController::class, 'cambiarTelar'])->name('programa-tejido.cambiar-telar');
Route::post('/planeacion/programa-tejido/duplicar-telar', [ProgramaTejidoController::class, 'duplicarTelar'])->name('programa-tejido.duplicar-telar');
Route::post('/planeacion/programa-tejido/dividir-telar', [ProgramaTejidoController::class, 'dividirTelar'])->name('programa-tejido.dividir-telar');
Route::post('/planeacion/programa-tejido/dividir-saldo', [ProgramaTejidoController::class, 'dividirSaldo'])->name('programa-tejido.dividir-saldo');
Route::post('/planeacion/programa-tejido/vincular-telar', [ProgramaTejidoController::class, 'vincularTelar'])->name('programa-tejido.vincular-telar');
Route::post('/planeacion/programa-tejido/vincular-registros-existentes', [ProgramaTejidoController::class, 'vincularRegistrosExistentes'])->name('programa-tejido.vincular-registros-existentes');
Route::post('/planeacion/programa-tejido/{id}/desvincular', [ProgramaTejidoController::class, 'desvincularRegistro'])->name('programa-tejido.desvincular');
Route::get('/planeacion/programa-tejido/registros-ord-compartida/{ordCompartida}', [ProgramaTejidoController::class, 'getRegistrosPorOrdCompartida'])->name('programa-tejido.registros-ord-compartida');
Route::get('/planeacion/programa-tejido/balancear', [ProgramaTejidoController::class, 'balancear'])->name('programa-tejido.balancear');
Route::get('/planeacion/programa-tejido/{id}/detalles-balanceo', [ProgramaTejidoController::class, 'detallesBalanceo'])->name('programa-tejido.detalles-balanceo');
Route::post('/planeacion/programa-tejido/preview-fechas-balanceo', [ProgramaTejidoController::class, 'previewFechasBalanceo'])->name('programa-tejido.preview-fechas-balanceo');
Route::post('/planeacion/programa-tejido/actualizar-pedidos-balanceo', [ProgramaTejidoController::class, 'actualizarPedidosBalanceo'])->name('programa-tejido.actualizar-pedidos-balanceo');
Route::post('/planeacion/programa-tejido/balancear-automatico', [ProgramaTejidoController::class, 'balancearAutomatico'])->name('programa-tejido.balancear-automatico');
Route::get('/planeacion/programa-tejido/ver-detalles-grupo-balanceo/{ordCompartida}', [BalancearTejido::class, 'verDetallesGrupoBalanceo'])->name('verdetallesgrupobalanceo');
Route::put('/planeacion/programa-tejido/{id}', [ProgramaTejidoController::class, 'update'])->name('programa-tejido.update');
Route::delete('/planeacion/programa-tejido/{id}', [ProgramaTejidoController::class, 'destroy'])->name('programa-tejido.destroy');
Route::get('/planeacion/programa-tejido/all-registros-json', [ProgramaTejidoController::class, 'getAllRegistrosJson'])->name('programa-tejido.all-registros-json');
Route::post('/planeacion/programa-tejido/actualizar-calendarios-masivo', [ProgramaTejidoController::class, 'actualizarCalendariosMasivo'])->name('programa-tejido.actualizar-calendarios-masivo');
Route::post('/planeacion/programa-tejido/{id}/reprogramar', [ProgramaTejidoController::class, 'actualizarReprogramar'])->name('programa-tejido.reprogramar');
Route::get('/planeacion/req-programa-tejido-line', [ReqProgramaTejidoLineController::class, 'index'])->name('planeacion.req-programa-tejido-line');

Route::get('/programa-tejido/salon-options', [ProgramaTejidoController::class, 'getSalonTejidoOptions']);
Route::get('/programa-tejido/salon-tejido-options', [ProgramaTejidoController::class, 'getSalonTejidoOptions'])->name('programa-tejido.salon-tejido-options');
Route::get('/programa-tejido/tamano-clave-by-salon', [ProgramaTejidoController::class, 'getTamanoClaveBySalon']);
Route::get('/programa-tejido/flogs-id-options', [ProgramaTejidoController::class, 'getFlogsIdOptions']);
Route::get('/programa-tejido/flogs-id-from-twflogs', [ProgramaTejidoController::class, 'getFlogsIdFromTwFlogsTable']);
Route::get('/programa-tejido/descripcion-by-idflog/{idflog}', [ProgramaTejidoController::class, 'getDescripcionByIdFlog']);
Route::get('/programa-tejido/flog-by-item', [ProgramaTejidoController::class, 'getFlogByItem']);
Route::get('/programa-tejido/flogs-by-tamano-clave', [ProgramaTejidoController::class, 'getFlogsByTamanoClave']);
Route::get('/programa-tejido/calendario-id-options', [ProgramaTejidoController::class, 'getCalendarioIdOptions']);
Route::get('/programa-tejido/calendario-lineas/{calendarioId}', [ProgramaTejidoController::class, 'getCalendarioLineas'])->name('programa-tejido.calendario-lineas');
Route::get('/programa-tejido/aplicacion-id-options', [ProgramaTejidoController::class, 'getAplicacionIdOptions']);
Route::match(['get', 'post'], '/programa-tejido/datos-relacionados', [ProgramaTejidoController::class, 'getDatosRelacionados']);
Route::get('/programa-tejido/telares-by-salon', [ProgramaTejidoController::class, 'getTelaresBySalon']);
Route::get('/programa-tejido/ultima-fecha-final-telar', [ProgramaTejidoController::class, 'getUltimaFechaFinalTelar']);
Route::get('/programa-tejido/hilos-options', [ProgramaTejidoController::class, 'getHilosOptions']);
Route::get('/programa-tejido/eficiencia-std', [ProgramaTejidoController::class, 'getEficienciaStd']);
Route::get('/programa-tejido/velocidad-std', [ProgramaTejidoController::class, 'getVelocidadStd']);
Route::get('/programa-tejido/eficiencia-velocidad-std', [ProgramaTejidoController::class, 'getEficienciaVelocidadStd']);
Route::post('/programa-tejido/calcular-totales-dividir', [DividirTejido::class, 'calcularTotalesDividir']);
Route::get('/programa-tejido/columnas', [ColumnasProgramaTejidoController::class, 'index']);
Route::get('/programa-tejido/columnas/visibles', [ColumnasProgramaTejidoController::class, 'getColumnasVisibles']);
Route::post('/programa-tejido/columnas', [ColumnasProgramaTejidoController::class, 'store']);
