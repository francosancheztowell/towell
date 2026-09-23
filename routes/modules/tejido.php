<?php

use App\Http\Controllers\Tejido\Configuracion\SecuenciaCorteEficiencia\SecuenciaCorteEficienciaController;
use App\Http\Controllers\Tejido\Configuracion\SecuenciaInvTelas\SecuenciaInvTelasController;
use App\Http\Controllers\Tejido\Configuracion\SecuenciaInvTrama\SecuenciaInvTramaController;
use App\Http\Controllers\Tejido\Configuracion\SecuenciaMarcasFinales\SecuenciaMarcasFinalesController;
use App\Http\Controllers\Tejido\CortesEficiencia\CortesEficienciaController;
use App\Http\Controllers\Tejido\InventarioTelas\TelaresController;
use App\Http\Controllers\Tejido\InventarioTrama\ConsultarRequerimientoController;
use App\Http\Controllers\Tejido\InventarioTrama\NuevoRequerimientoController;
use App\Http\Controllers\Tejido\MarcasFinales\MarcasController;
use App\Http\Controllers\Tejido\ProduccionReenconado\ProduccionReenconadoCabezuelaController;
use App\Http\Controllers\Tejido\Reportes\PromedioParosEficienciaController;
use App\Http\Controllers\Tejido\Reportes\ReporteInvTelasController;
use App\Http\Controllers\Tejido\Reportes\ReporteMarcasFinalesController;
use App\Http\Controllers\Tejido\Reportes\ReporteRpmSemanalController;
use App\Http\Controllers\Tejido\Reportes\SaldosController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::get('/tejido/{moduloPrincipal?}', [UsuarioController::class, 'showSubModulos'])
    ->defaults('moduloPrincipal', 'tejido')
    ->where('moduloPrincipal', 'tejido')
    ->name('tejido.index');

Route::prefix('tejido')->name('tejido.')->group(function () {
    Route::get('/reportes', function () {
        $reportes = [
            [
                'nombre' => 'Reporte inv telas',
                'accion' => 'Pedir Rango de Fechas',
                'url' => route('tejido.reportes.inv-telas'),
                'disponible' => true,
            ],
            [
                'nombre' => 'Promedio Paros y Eficiencia',
                'accion' => 'Pedir Rango de Fechas',
                'url' => route('tejido.reportes.promedio-paros-eficiencia'),
                'disponible' => true,
            ],
            [
                'nombre' => 'Marcas Finales',
                'accion' => 'Pedir Rango de Fechas',
                'url' => route('tejido.reportes.marcas-finales'),
                'disponible' => true,
            ],
            [
                'nombre' => 'Saldos 2026',
                'accion' => 'Órdenes de producción activas',
                'url' => route('tejido.reportes.saldos-2026'),
                'disponible' => true,
            ],
            [
                'nombre' => 'Reporte RPM Sem',
                'accion' => 'Elegir semana (lunes a domingo)',
                'url' => route('tejido.reportes.inv-trama'),
                'disponible' => true,
            ],
        ];

        return view('modulos.tejido.reportes.index', ['reportes' => $reportes]);
    })->name('reportes.index');

    Route::get('/reportes/inv-telas', [ReporteInvTelasController::class, 'index'])->name('reportes.inv-telas');
    Route::get('/reportes/inv-telas/excel', [ReporteInvTelasController::class, 'exportarExcel'])->name('reportes.inv-telas.excel');
    Route::get('/reportes/inv-telas/pdf', [ReporteInvTelasController::class, 'exportarPdf'])->name('reportes.inv-telas.pdf');
    Route::get('/reportes/promedio-paros-eficiencia', [PromedioParosEficienciaController::class, 'index'])->name('reportes.promedio-paros-eficiencia');
    Route::get('/reportes/promedio-paros-eficiencia/excel', [PromedioParosEficienciaController::class, 'exportarExcel'])->name('reportes.promedio-paros-eficiencia.excel');
    Route::get('/reportes/marcas-finales', [ReporteMarcasFinalesController::class, 'index'])->name('reportes.marcas-finales');
    Route::get('/reportes/marcas-finales/excel', [ReporteMarcasFinalesController::class, 'exportarExcel'])->name('reportes.marcas-finales.export');

    Route::get('/reportes/saldos-2026', [SaldosController::class, 'index'])->name('reportes.saldos-2026');
    Route::get('/reportes/saldos-2026/excel', [SaldosController::class, 'exportarExcel'])->name('reportes.saldos-2026.excel');

    Route::get('/reportes/rpm-semanal', [ReporteRpmSemanalController::class, 'index'])->name('reportes.inv-trama');
    Route::get('/reportes/rpm-semanal/excel', [ReporteRpmSemanalController::class, 'exportarExcel'])->name('reportes.inv-trama.excel');

    Route::get('/configurar/{moduloPadre?}', [UsuarioController::class, 'showSubModulosNivel3'])
        ->defaults('moduloPadre', '205')
        ->where('moduloPadre', '205')
        ->name('configurar');

    Route::get('/marcasfinales/{moduloPadre?}', function () {
        return redirect('/modulo-marcas/consultar');
    })
        ->where('moduloPadre', '202')
        ->name('marcas.finales');

    Route::get('/invtrama', [ConsultarRequerimientoController::class, 'index'])
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

    Route::get('/configurar/secuenciamarcasfinales', [SecuenciaMarcasFinalesController::class, 'index'])->name('secuencia-marcas-finales.index');
    Route::post('/configurar/secuenciamarcasfinales', [SecuenciaMarcasFinalesController::class, 'store'])->middleware('module.permission:crear,32')->name('secuencia-marcas-finales.store'); // Secuencia Marcas Finales
    Route::post('/configurar/secuenciamarcasfinales/orden', [SecuenciaMarcasFinalesController::class, 'updateOrden'])->middleware('module.permission:modificar,32')->name('secuencia-marcas-finales.orden'); // Secuencia Marcas Finales
    Route::put('/configurar/secuenciamarcasfinales/{id}', [SecuenciaMarcasFinalesController::class, 'update'])->middleware('module.permission:modificar,32')->name('secuencia-marcas-finales.update'); // Secuencia Marcas Finales
    Route::delete('/configurar/secuenciamarcasfinales/{id}', [SecuenciaMarcasFinalesController::class, 'destroy'])
        ->middleware('module.permission:eliminar,32')->name('secuencia-marcas-finales.destroy'); // Secuencia Marcas Finales

    Route::get('/configurar/secuenciacortedeeficiencia', [SecuenciaCorteEficienciaController::class, 'index'])->name('secuencia-corte-eficiencia.index');
    Route::post('/configurar/secuenciacortedeeficiencia', [SecuenciaCorteEficienciaController::class, 'store'])->middleware('module.permission:crear,30')->name('secuencia-corte-eficiencia.store'); // Secuencia Corte de Eficiencia
    Route::post('/configurar/secuenciacortedeeficiencia/orden', [SecuenciaCorteEficienciaController::class, 'updateOrden'])->middleware('module.permission:modificar,30')->name('secuencia-corte-eficiencia.orden'); // Secuencia Corte de Eficiencia
    Route::put('/configurar/secuenciacortedeeficiencia/{id}', [SecuenciaCorteEficienciaController::class, 'update'])->middleware('module.permission:modificar,30')->name('secuencia-corte-eficiencia.update'); // Secuencia Corte de Eficiencia
    Route::delete('/configurar/secuenciacortedeeficiencia/{id}', [SecuenciaCorteEficienciaController::class, 'destroy'])
        ->middleware('module.permission:eliminar,30')->name('secuencia-corte-eficiencia.destroy'); // Secuencia Corte de Eficiencia

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
        ->middleware('module.permission:eliminar,27') // Producción Reenconado Cabezuela
        ->name('produccion.reenconado.destroy');
    Route::patch('/produccion-reenconado/{folio}/cambiar-status', [ProduccionReenconadoCabezuelaController::class, 'cambiarStatus'])
        ->name('produccion.reenconado.cambiar-status');

    Route::get('/secuencia-inv-telas', [SecuenciaInvTelasController::class, 'index'])->name('secuencia-inv-telas.index');
    Route::post('/secuencia-inv-telas', [SecuenciaInvTelasController::class, 'store'])->middleware('module.permission:crear,29')->name('secuencia-inv-telas.store'); // Secuencia Inv Telas
    Route::post('/secuencia-inv-telas/orden', [SecuenciaInvTelasController::class, 'updateOrden'])->middleware('module.permission:modificar,29')->name('secuencia-inv-telas.orden'); // Secuencia Inv Telas
    Route::put('/secuencia-inv-telas/{id}', [SecuenciaInvTelasController::class, 'update'])->middleware('module.permission:modificar,29')->name('secuencia-inv-telas.update'); // Secuencia Inv Telas
    Route::delete('/secuencia-inv-telas/{id}', [SecuenciaInvTelasController::class, 'destroy'])
        ->middleware('module.permission:eliminar,29')->name('secuencia-inv-telas.destroy'); // Secuencia Inv Telas

    Route::get('/secuencia-inv-trama', [SecuenciaInvTramaController::class, 'index'])->name('secuencia-inv-trama.index');
    Route::post('/secuencia-inv-trama', [SecuenciaInvTramaController::class, 'store'])->middleware('module.permission:crear,31')->name('secuencia-inv-trama.store'); // Secuencia Inv Trama
    Route::post('/secuencia-inv-trama/orden', [SecuenciaInvTramaController::class, 'updateOrden'])->middleware('module.permission:modificar,31')->name('secuencia-inv-trama.orden'); // Secuencia Inv Trama
    Route::put('/secuencia-inv-trama/{id}', [SecuenciaInvTramaController::class, 'update'])->middleware('module.permission:modificar,31')->name('secuencia-inv-trama.update'); // Secuencia Inv Trama
    Route::delete('/secuencia-inv-trama/{id}', [SecuenciaInvTramaController::class, 'destroy'])
        ->middleware('module.permission:eliminar,31')->name('secuencia-inv-trama.destroy'); // Secuencia Inv Trama

    Route::view('/inventario-telas', 'modulos/tejido/inventario-telas')->name('inventario.telas');
    Route::get('/inventario-telas/jacquard', [TelaresController::class, 'inventarioJacquard'])->name('inventario.jacquard');
    Route::get('/inventario-telas/itema', [TelaresController::class, 'inventarioItema'])->name('inventario.itema');
    Route::get('/inventario-telas/karl-mayer', [TelaresController::class, 'inventarioKarlMayer'])->name('inventario.karl_mayer');

    Route::get('/inventario/trama/nuevo-requerimiento', [NuevoRequerimientoController::class, 'index'])->name('inventario.trama.nuevo.requerimiento');
    Route::get('/inventario/trama/consultar-requerimiento', [ConsultarRequerimientoController::class, 'index'])->name('inventario.trama.consultar.requerimiento');
    Route::get('/inventario/trama/consultar-requerimiento/{folio}/resumen', [ConsultarRequerimientoController::class, 'resumen'])->name('inventario.trama.consultar.requerimiento.resumen');
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
Route::post('/modulo-marcas/reporte/notificar-telegram', [MarcasController::class, 'notificarTelegram'])->name('marcas.reporte.telegram');
Route::get('/modulo-marcas/{folio}', [MarcasController::class, 'show'])
    ->where('folio', '^(?!reporte$).+')
    ->name('marcas.show');
Route::put('/modulo-marcas/{folio}/actualizar-registro', [MarcasController::class, 'actualizarRegistro'])
    ->where('folio', '^(?!reporte$).+')
    ->name('marcas.actualizar.registro');
Route::put('/modulo-marcas/{folio}', [MarcasController::class, 'update'])
    ->where('folio', '^(?!reporte$).+')
    ->name('marcas.update');
Route::post('/modulo-marcas/{folio}/finalizar', [MarcasController::class, 'finalizar'])
    ->where('folio', '^(?!reporte$).+')
    ->middleware('module.permission:modificar,177') // Marcas Finales
    ->name('marcas.finalizar');
Route::post('/modulo-marcas/{folio}/reabrir', [MarcasController::class, 'reabrirFolio'])
    ->where('folio', '^(?!reporte$).+')
    ->middleware('module.permission:modificar,177') // Marcas Finales
    ->name('marcas.reabrir');

Route::get('/modulo-cortes-de-eficiencia', [CortesEficienciaController::class, 'index'])->name('cortes.eficiencia');
Route::get('/modulo-cortes-de-eficiencia/consultar', [CortesEficienciaController::class, 'consultar'])->name('cortes.eficiencia.consultar');
Route::get('/modulo-cortes-de-eficiencia/turno-info', [CortesEficienciaController::class, 'getTurnoInfo'])->name('cortes.eficiencia.turno.info');
Route::get('/modulo-cortes-de-eficiencia/datos-programa-tejido', [CortesEficienciaController::class, 'getDatosProgramaTejido'])->name('cortes.eficiencia.datos.programa.tejido');
Route::get('/modulo-cortes-de-eficiencia/datos-telares', [CortesEficienciaController::class, 'getDatosTelares'])->name('cortes.eficiencia.datos.telares');
Route::get('/modulo-cortes-de-eficiencia/fallas', [CortesEficienciaController::class, 'getFallasCe'])->name('cortes.eficiencia.fallas');
Route::get('/modulo-cortes-de-eficiencia/generar-folio', [CortesEficienciaController::class, 'generarFolio'])->name('cortes.eficiencia.generar.folio');
Route::post('/modulo-cortes-de-eficiencia/guardar-hora', [CortesEficienciaController::class, 'guardarHora'])->name('cortes.eficiencia.guardar.hora');
Route::post('/modulo-cortes-de-eficiencia/guardar-tabla', [CortesEficienciaController::class, 'guardarTabla'])->name('cortes.eficiencia.guardar.tabla');
Route::post('/modulo-cortes-de-eficiencia', [CortesEficienciaController::class, 'store'])->name('cortes.eficiencia.store');
Route::get('/modulo-cortes-de-eficiencia/{id}/pdf', [CortesEficienciaController::class, 'pdf'])->name('cortes.eficiencia.pdf');
Route::put('/modulo-cortes-de-eficiencia/{id}/actualizar-registro', [CortesEficienciaController::class, 'actualizarRegistro'])->name('cortes.eficiencia.actualizar.registro');
Route::get('/modulo-cortes-de-eficiencia/{id}', [CortesEficienciaController::class, 'show'])->name('cortes.eficiencia.show');
Route::put('/modulo-cortes-de-eficiencia/{id}', [CortesEficienciaController::class, 'update'])->name('cortes.eficiencia.update');
Route::post('/modulo-cortes-de-eficiencia/{id}/finalizar', [CortesEficienciaController::class, 'finalizar'])
    ->middleware('module.permission:modificar,105')->name('cortes.eficiencia.finalizar'); // Cortes de Eficiencia
Route::get('/modulo-cortes-de-eficiencia/visualizar/{folio}', [CortesEficienciaController::class, 'visualizar'])->name('cortes.eficiencia.visualizar');
Route::get('/modulo-cortes-de-eficiencia/visualizar-folio/{folio}', [CortesEficienciaController::class, 'visualizarFolio'])->name('cortes.eficiencia.visualizar.folio');
Route::post('/modulo-cortes-de-eficiencia/visualizar/exportar-excel', [CortesEficienciaController::class, 'exportarVisualizacionExcel'])->name('cortes.eficiencia.visualizar.excel');
Route::post('/modulo-cortes-de-eficiencia/visualizar/descargar-pdf', [CortesEficienciaController::class, 'descargarVisualizacionPDF'])->name('cortes.eficiencia.visualizar.pdf');
Route::post('/modulo-cortes-de-eficiencia/visualizar/notificar-telegram', [CortesEficienciaController::class, 'notificarTelegram'])->name('cortes.eficiencia.visualizar.telegram');
Route::post('/modulo-cortes-de-eficiencia/visualizar/notificar-telegram-imagen', [CortesEficienciaController::class, 'notificarTelegramImagen'])->name('cortes.eficiencia.visualizar.telegram.imagen');

Route::get('/modulo-nuevo-requerimiento', [NuevoRequerimientoController::class, 'index'])->name('modulo.nuevo.requerimiento');

Route::get('/modulo-consultar-requerimiento', [ConsultarRequerimientoController::class, 'index'])->name('modulo.consultar.requerimiento');
Route::get('/modulo-consultar-requerimiento/{folio}/resumen', [ConsultarRequerimientoController::class, 'resumen'])->name('modulo.consultar.requerimiento.resumen');

Route::prefix('api/telares')->controller(TelaresController::class)->group(function () {
    Route::get('/proceso-actual/{telarId}', 'procesoActual')->whereNumber('telarId');
    Route::get('/siguiente-orden/{telarId}', 'siguienteOrden')->whereNumber('telarId');
});
