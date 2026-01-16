<?php

use App\Http\Controllers\Tejido\Configuracion\SecuenciaInvTelas\SecuenciaInvTelasController;
use App\Http\Controllers\Tejido\Configuracion\SecuenciaInvTrama\SecuenciaInvTramaController;
use App\Http\Controllers\Tejido\CortesEficiencia\CortesEficienciaController;
use App\Http\Controllers\Tejido\InventarioTelas\TelaresController;
use App\Http\Controllers\Tejido\InventarioTrama\ConsultarRequerimientoController;
use App\Http\Controllers\Tejido\InventarioTrama\NuevoRequerimientoController;
use App\Http\Controllers\Tejido\MarcasFinales\MarcasController;
use App\Http\Controllers\Tejido\ProduccionReenconado\ProduccionReenconadoCabezuelaController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::get('/tejido/{moduloPrincipal?}', [UsuarioController::class, 'showSubModulos'])
    ->defaults('moduloPrincipal', 'tejido')
    ->where('moduloPrincipal', 'tejido')
    ->name('tejido.index');

Route::prefix('tejido')->name('tejido.')->group(function () {
    Route::get('/configurar/{serie?}', [UsuarioController::class, 'showSubModulosConfiguracion'])
        ->defaults('serie', '205')
        ->where('serie', '205')
        ->name('configurar');

    Route::get('/marcasfinales/{moduloPadre?}', function () {
        return redirect('/modulo-marcas/consultar');
    })
        ->where('moduloPadre', '202')
        ->name('marcas.finales');

    Route::get('/invtrama/{moduloPadre?}', [UsuarioController::class, 'showSubModulosNivel3'])
        ->defaults('moduloPadre', '203')
        ->where('moduloPadre', '203')
        ->name('inventario');

    Route::redirect('/inventario', '/tejido/invtrama', 301);

    Route::get('/cortesdeeficiencia/{moduloPadre?}', function () {
        return redirect('/modulo-cortes-de-eficiencia/consultar');
    })
        ->where('moduloPadre', '206')
        ->name('cortes.eficiencia');

    Route::redirect('/marcasfinales/nuevasmarcasfinales', '/modulo-marcas', 301);
    Route::redirect('/marcasfinales/consultarmarcasfinales', '/modulo-marcas/consultar', 301);
    Route::redirect('/cortesdeeficiencia/nuevoscortesdeeficiencia', '/modulo-cortes-de-eficiencia', 301);
    Route::redirect('/cortesdeeficiencia/consultareficiencia', '/modulo-cortes-de-eficiencia/consultar', 301);

    Route::redirect('/invtelas', '/tejido/inventario-telas', 301);
    Route::redirect('/invtelas/jacquard', '/tejido/inventario-telas/jacquard', 301);
    Route::redirect('/invtelas/itema', '/tejido/inventario-telas/itema', 301);
    Route::redirect('/invtelas/karlmayer', '/tejido/inventario-telas/karl-mayer', 301);

    Route::redirect('/invtrama/nuevorequerimiento', '/tejido/inventario/trama/nuevo-requerimiento', 301);
    Route::redirect('/invtrama/consultarrequerimiento', '/tejido/inventario/trama/consultar-requerimiento', 301);

    Route::redirect('/produccionreenconadocabezuela', '/tejido/produccion-reenconado', 301);
    Route::redirect('/configurar/secuenciainvtelas', '/tejido/secuencia-inv-telas', 301);
    Route::redirect('/configurar/secuenciainvtrama', '/tejido/secuencia-inv-trama', 301);

    Route::get('/produccion-reenconado', [ProduccionReenconadoCabezuelaController::class, 'index'])
        ->name('produccion.reenconado');
    Route::post('/produccion-reenconado', [ProduccionReenconadoCabezuelaController::class, 'store'])
        ->name('produccion.reenconado.store');
    Route::post('/produccion-reenconado/generar-folio', [ProduccionReenconadoCabezuelaController::class, 'generarFolio'])
        ->name('produccion.reenconado.generar-folio');
    Route::get('/produccion-reenconado/calibres', [ProduccionReenconadoCabezuelaController::class, 'getCalibres'])
        ->name('produccion.reenconado.calibres');
    Route::get('/produccion-reenconado/fibras', [ProduccionReenconadoCabezuelaController::class, 'getFibras'])
        ->name('produccion.reenconado.fibras');
    Route::get('/produccion-reenconado/colores', [ProduccionReenconadoCabezuelaController::class, 'getColores'])
        ->name('produccion.reenconado.colores');
    Route::put('/produccion-reenconado/{folio}', [ProduccionReenconadoCabezuelaController::class, 'update'])
        ->name('produccion.reenconado.update');
    Route::delete('/produccion-reenconado/{folio}', [ProduccionReenconadoCabezuelaController::class, 'destroy'])
        ->name('produccion.reenconado.destroy');
    Route::patch('/produccion-reenconado/{folio}/cambiar-status', [ProduccionReenconadoCabezuelaController::class, 'cambiarStatus'])
        ->name('produccion.reenconado.cambiar-status');

    Route::get('/secuencia-inv-telas', [SecuenciaInvTelasController::class, 'index'])->name('secuencia-inv-telas.index');
    Route::post('/secuencia-inv-telas', [SecuenciaInvTelasController::class, 'store'])->name('secuencia-inv-telas.store');
    Route::put('/secuencia-inv-telas/{id}', [SecuenciaInvTelasController::class, 'update'])->name('secuencia-inv-telas.update');
    Route::delete('/secuencia-inv-telas/{id}', [SecuenciaInvTelasController::class, 'destroy'])->name('secuencia-inv-telas.destroy');

    Route::get('/secuencia-inv-trama', [SecuenciaInvTramaController::class, 'index'])->name('secuencia-inv-trama.index');
    Route::post('/secuencia-inv-trama', [SecuenciaInvTramaController::class, 'store'])->name('secuencia-inv-trama.store');
    Route::put('/secuencia-inv-trama/{id}', [SecuenciaInvTramaController::class, 'update'])->name('secuencia-inv-trama.update');
    Route::delete('/secuencia-inv-trama/{id}', [SecuenciaInvTramaController::class, 'destroy'])->name('secuencia-inv-trama.destroy');

    Route::view('/inventario-telas', 'modulos/tejido/inventario-telas')->name('inventario.telas');
    Route::get('/inventario-telas/jacquard', [TelaresController::class, 'inventarioJacquard'])->name('inventario.jacquard');
    Route::get('/inventario-telas/itema', [TelaresController::class, 'inventarioItema'])->name('inventario.itema');
    Route::get('/inventario-telas/karl-mayer', [TelaresController::class, 'inventarioKarlMayer'])->name('inventario.karl_mayer');

    Route::get('/inventario/trama/nuevo-requerimiento', [NuevoRequerimientoController::class, 'index'])->name('inventario.trama.nuevo.requerimiento');
    Route::post('/inventario/trama/nuevo-requerimiento', [NuevoRequerimientoController::class, 'guardarRequerimientos'])->name('inventario.trama.nuevo.requerimiento.store');
    Route::get('/inventario/trama/nuevo-requerimiento/turno-info', [NuevoRequerimientoController::class, 'getTurnoInfo'])->name('inventario.trama.nuevo.requerimiento.turno.info');
    Route::get('/inventario/trama/consultar-requerimiento', [ConsultarRequerimientoController::class, 'index'])->name('inventario.trama.consultar.requerimiento');
    Route::get('/inventario/trama/consultar-requerimiento/{folio}/resumen', [ConsultarRequerimientoController::class, 'resumen'])->name('inventario.trama.consultar.requerimiento.resumen');
    Route::get('/inventario/trama/nuevo-requerimiento/en-proceso', [NuevoRequerimientoController::class, 'enProcesoInfo'])->name('inventario.trama.nuevo.requerimiento.enproceso');
    Route::post('/inventario/trama/nuevo-requerimiento/actualizar-cantidad', [NuevoRequerimientoController::class, 'actualizarCantidad'])->name('inventario.trama.nuevo.requerimiento.actualizar.cantidad');
});

Route::get('/produccion/reenconado-cabezuela', [ProduccionReenconadoCabezuelaController::class, 'index'])
    ->name('produccion.reenconado_cabezuela');
Route::post('/produccion/reenconado-cabezuela', [ProduccionReenconadoCabezuelaController::class, 'store'])
    ->name('produccion.reenconado_cabezuela.store');

// Rutas para módulo de marcas finales
Route::get('/modulo-marcas', [MarcasController::class, 'index'])->name('marcas.nuevo');
Route::get('/modulo-marcas/consultar', [MarcasController::class, 'consultar'])->name('marcas.consultar');
Route::post('/modulo-marcas/generar-folio', [MarcasController::class, 'generarFolio'])->name('marcas.generar.folio');
Route::get('/modulo-marcas/obtener-datos-std', [MarcasController::class, 'obtenerDatosSTD'])->name('marcas.datos.std');
Route::post('/modulo-marcas/store', [MarcasController::class, 'store'])->name('marcas.store');
Route::get('/modulo-marcas/visualizar/{folio}', [MarcasController::class, 'visualizarFolio'])->name('marcas.visualizar');
Route::get('/modulo-marcas/reporte', [MarcasController::class, 'reporte'])->name('marcas.reporte');
Route::post('/modulo-marcas/reporte/exportar-excel', [MarcasController::class, 'exportarExcel'])->name('marcas.reporte.excel');
Route::post('/modulo-marcas/reporte/descargar-pdf', [MarcasController::class, 'descargarPDF'])->name('marcas.reporte.pdf');
Route::get('/modulo-marcas/{folio}', [MarcasController::class, 'show'])
    ->where('folio', '^(?!reporte$).+')
    ->name('marcas.show');
Route::put('/modulo-marcas/{folio}', [MarcasController::class, 'update'])
    ->where('folio', '^(?!reporte$).+')
    ->name('marcas.update');
Route::post('/modulo-marcas/{folio}/finalizar', [MarcasController::class, 'finalizar'])
    ->where('folio', '^(?!reporte$).+')
    ->name('marcas.finalizar');

Route::get('/modulo-cortes-de-eficiencia', [CortesEficienciaController::class, 'index'])->name('cortes.eficiencia');
Route::get('/modulo-cortes-de-eficiencia/consultar', [CortesEficienciaController::class, 'consultar'])->name('cortes.eficiencia.consultar');
Route::get('/modulo-cortes-de-eficiencia/turno-info', [CortesEficienciaController::class, 'getTurnoInfo'])->name('cortes.eficiencia.turno.info');
Route::get('/modulo-cortes-de-eficiencia/datos-programa-tejido', [CortesEficienciaController::class, 'getDatosProgramaTejido'])->name('cortes.eficiencia.datos.programa.tejido');
Route::get('/modulo-cortes-de-eficiencia/datos-telares', [CortesEficienciaController::class, 'getDatosTelares'])->name('cortes.eficiencia.datos.telares');
Route::get('/modulo-cortes-de-eficiencia/generar-folio', [CortesEficienciaController::class, 'generarFolio'])->name('cortes.eficiencia.generar.folio');
Route::post('/modulo-cortes-de-eficiencia/guardar-hora', [CortesEficienciaController::class, 'guardarHora'])->name('cortes.eficiencia.guardar.hora');
Route::post('/modulo-cortes-de-eficiencia/guardar-tabla', [CortesEficienciaController::class, 'guardarTabla'])->name('cortes.eficiencia.guardar.tabla');
Route::post('/modulo-cortes-de-eficiencia', [CortesEficienciaController::class, 'store'])->name('cortes.eficiencia.store');
Route::get('/modulo-cortes-de-eficiencia/{id}/pdf', [CortesEficienciaController::class, 'pdf'])->name('cortes.eficiencia.pdf');
Route::get('/modulo-cortes-de-eficiencia/{id}', [CortesEficienciaController::class, 'show'])->name('cortes.eficiencia.show');
Route::put('/modulo-cortes-de-eficiencia/{id}', [CortesEficienciaController::class, 'update'])->name('cortes.eficiencia.update');
Route::post('/modulo-cortes-de-eficiencia/{id}/finalizar', [CortesEficienciaController::class, 'finalizar'])->name('cortes.eficiencia.finalizar');
Route::get('/modulo-cortes-de-eficiencia/visualizar/{folio}', [CortesEficienciaController::class, 'visualizar'])->name('cortes.eficiencia.visualizar');
Route::get('/modulo-cortes-de-eficiencia/visualizar-folio/{folio}', [CortesEficienciaController::class, 'visualizarFolio'])->name('cortes.eficiencia.visualizar.folio');
Route::post('/modulo-cortes-de-eficiencia/visualizar/exportar-excel', [CortesEficienciaController::class, 'exportarVisualizacionExcel'])->name('cortes.eficiencia.visualizar.excel');
Route::post('/modulo-cortes-de-eficiencia/visualizar/descargar-pdf', [CortesEficienciaController::class, 'descargarVisualizacionPDF'])->name('cortes.eficiencia.visualizar.pdf');

Route::get('/modulo-nuevo-requerimiento', [NuevoRequerimientoController::class, 'index'])->name('modulo.nuevo.requerimiento');
Route::post('/modulo-nuevo-requerimiento/guardar', [NuevoRequerimientoController::class, 'guardarRequerimientos'])->name('modulo.nuevo.requerimiento.store');
Route::get('/modulo-nuevo-requerimiento/turno-info', [NuevoRequerimientoController::class, 'getTurnoInfo'])->name('modulo.nuevo.requerimiento.turno.info');
Route::get('/modulo-nuevo-requerimiento/en-proceso', [NuevoRequerimientoController::class, 'enProcesoInfo'])->name('modulo.nuevo.requerimiento.enproceso');
Route::post('/modulo-nuevo-requerimiento/actualizar-cantidad', [NuevoRequerimientoController::class, 'actualizarCantidad'])->name('modulo.nuevo.requerimiento.actualizar.cantidad');

Route::get('/modulo-consultar-requerimiento', [ConsultarRequerimientoController::class, 'index'])->name('modulo.consultar.requerimiento');
Route::get('/modulo-consultar-requerimiento/{folio}', [ConsultarRequerimientoController::class, 'show'])->name('modulo.consultar.requerimiento.show');
Route::post('/modulo-consultar-requerimiento/{folio}/status', [ConsultarRequerimientoController::class, 'updateStatus'])->name('modulo.consultar.requerimiento.status');
Route::get('/modulo-consultar-requerimiento/{folio}/resumen', [ConsultarRequerimientoController::class, 'resumen'])->name('modulo.consultar.requerimiento.resumen');

Route::get('/tejido/jacquard-sulzer/{telar}', [TelaresController::class, 'mostrarTelarSulzer'])->name('tejido.mostrarTelarSulzer');
Route::get('/ordenes-programadas-dinamica/{telar}', [TelaresController::class, 'obtenerOrdenesProgramadas'])->name('ordenes.programadas');

Route::prefix('api/telares')->controller(TelaresController::class)->group(function () {
    Route::get('/proceso-actual/{telarId}', 'procesoActual')->whereNumber('telarId');
    Route::get('/siguiente-orden/{telarId}', 'siguienteOrden')->whereNumber('telarId');
});
