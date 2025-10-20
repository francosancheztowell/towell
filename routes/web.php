<?php

use App\Http\Controllers\AtadorController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CalendarioController;
use App\Http\Controllers\CatalagoEficienciaController;
use App\Http\Controllers\CatalagoTelarController;
use App\Http\Controllers\CatalagoVelocidadController;
use App\Http\Controllers\CortesEficienciaController;
use App\Http\Controllers\RequerimientoController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TelaresController;
use App\Http\Controllers\UrdidoController;
use App\Http\Controllers\ModulosController;
use App\Http\Controllers\AplicacionesController;
use App\Http\Controllers\NuevoRequerimientoController;
use App\Http\Controllers\ConsultarRequerimientoController;


//Rutas de login, con logout, no protegidas por middleware

// Rutas de autenticación
Route::get('/', [AuthController::class, 'showLoginForm'])->name('home');
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/login-qr', [AuthController::class, 'loginQR']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');


Route::get('/obtener-empleados/{area}', function ($area) {
    try {
        return App\Models\Usuario::where('area', $area)->get();
    } catch (\Throwable $e) {
        return [];
    }
});


// Rutas protegidas por autenticación
Route::middleware(['auth'])->group(function () {
    // RUTA PRINCIPAL
    Route::get('/produccionProceso', [UsuarioController::class, 'index'])->name('produccion.index');

    // RUTAS PARA SUB-MÓDULOS
    Route::get('/submodulos/{modulo}', [UsuarioController::class, 'showSubModulos'])->name('submodulos.show');
    Route::get('/submodulos-nivel3/{moduloPadre}', [UsuarioController::class, 'showSubModulosNivel3'])->name('submodulos.nivel3');

    // API para precarga de submódulos (AJAX)
    Route::get('/api/submodulos/{moduloPrincipal}', [UsuarioController::class, 'getSubModulosAPI'])->name('api.submodulos');

    // ============================================
    // MÓDULO PLANEACIÓN (100)
    // ============================================
    Route::prefix('planeacion')->name('planeacion.')->group(function () {
        // Submódulos de Planeación
        // Route::get('/programa-tejido', [ExcelImportacionesController::class, 'showReqProgramaTejido'])->name('catalogos.req-programa-tejido');

        // Catálogos con estructura jerárquica
        Route::prefix('catalogos')->name('catalogos.')->group(function () {
            Route::get('/', [UsuarioController::class, 'showSubModulosNivel3'])->name('index');
            // Route::get('/req-programa-tejido', [ExcelImportacionesController::class, 'showReqProgramaTejido'])->name('req-programa-tejido');
            Route::get('/telares', [CatalagoTelarController::class, 'index'])->name('telares');
            Route::get('/telares/falla', [CatalagoTelarController::class, 'falla'])->name('telares.falla');
            Route::get('/eficiencia', [CatalagoEficienciaController::class, 'index'])->name('eficiencia');
            Route::get('/velocidad', [CatalagoVelocidadController::class, 'index'])->name('velocidad');
            Route::get('/calendarios', [CalendarioController::class, 'index'])->name('calendarios');
            Route::get('/aplicaciones', [AplicacionesController::class, 'index'])->name('aplicaciones');
        });

        // Rutas directas para compatibilidad
        Route::get('/telares', [CatalagoTelarController::class, 'index'])->name('telares.index');
        Route::get('/telares/falla', [CatalagoTelarController::class, 'falla'])->name('telares.falla');
        Route::get('/eficiencia', [CatalagoEficienciaController::class, 'index'])->name('eficiencia.index');
        Route::get('/velocidad', [CatalagoVelocidadController::class, 'index'])->name('velocidad.index');
        Route::get('/calendarios', [CalendarioController::class, 'index'])->name('calendarios.index');
        Route::get('/aplicaciones', [AplicacionesController::class, 'index'])->name('aplicaciones.index');

        // Rutas para procesar Excel de catálogos
        Route::post('/telares/excel', [CatalagoTelarController::class, 'procesarExcel'])->name('telares.excel.upload');
        Route::post('/eficiencia/excel', [CatalagoEficienciaController::class, 'procesarExcel'])->name('eficiencia.excel.upload');
        Route::post('/velocidad/excel', [CatalagoVelocidadController::class, 'procesarExcel'])->name('velocidad.excel.upload');
        Route::post('/aplicaciones/excel', [AplicacionesController::class, 'procesarExcel'])->name('aplicaciones.excel.upload');


    });

    // ============================================
    // MÓDULO TEJIDO (200)
    // ============================================
    Route::prefix('tejido')->name('tejido.')->group(function () {
        // Inventario de Telas
        Route::get('/inventario-telas', function () {
        return view('modulos/tejido/inventario-telas');
        })->name('inventario.telas');

        // Inventario específico por tipo de telar
        Route::get('/inventario-telas/jacquard', [TelaresController::class, 'inventarioJacquard'])->name('inventario.jacquard');
        Route::get('/inventario-telas/itema', [TelaresController::class, 'inventarioItema'])->name('inventario.itema');

        // Marcas Finales
        Route::get('/inventario/marcas-finales', [App\Http\Controllers\MarcasFinalesController::class, 'index'])->name('inventario.marcas.finales');
        Route::post('/inventario/marcas-finales', [App\Http\Controllers\MarcasFinalesController::class, 'store'])->name('inventario.marcas.finales.store');
        Route::get('/inventario/marcas-finales/{folio}', [App\Http\Controllers\MarcasFinalesController::class, 'show'])->name('inventario.marcas.finales.show');
        Route::put('/inventario/marcas-finales/{folio}', [App\Http\Controllers\MarcasFinalesController::class, 'update'])->name('inventario.marcas.finales.update');
        Route::post('/inventario/marcas-finales/{folio}/finalizar', [App\Http\Controllers\MarcasFinalesController::class, 'finalizar'])->name('inventario.marcas.finales.finalizar');

        // Trama - Nuevo y Consultar Requerimientos
        Route::get('/inventario/trama/nuevo-requerimiento', [NuevoRequerimientoController::class, 'index'])->name('inventario.trama.nuevo.requerimiento');
        Route::post('/inventario/trama/nuevo-requerimiento', [NuevoRequerimientoController::class, 'guardarRequerimientos'])->name('inventario.trama.nuevo.requerimiento.store');
        Route::get('/inventario/trama/consultar-requerimiento', [ConsultarRequerimientoController::class, 'index'])->name('inventario.trama.consultar.requerimiento');
        Route::get('/inventario/trama/consultar-requerimiento/{folio}/resumen', [ConsultarRequerimientoController::class, 'resumen'])->name('inventario.trama.consultar.requerimiento.resumen');
        Route::get('/inventario/trama/nuevo-requerimiento/en-proceso', [NuevoRequerimientoController::class, 'enProcesoInfo'])->name('inventario.trama.nuevo.requerimiento.enproceso');
        Route::post('/inventario/trama/nuevo-requerimiento/actualizar-cantidad', [NuevoRequerimientoController::class, 'actualizarCantidad'])->name('inventario.trama.nuevo.requerimiento.actualizar.cantidad');
    });

    // ============================================
    // MÓDULO PRODUCCIÓN URD ENGOMADO
    // ============================================
    Route::prefix('programa-urd-eng')->name('programa.urd.eng.')->group(function () {
        Route::get('/reservar-programar', function () {
            return view('modulos/programa-urd-eng/reservar-programar');
        })->name('reservar.programar');
    });

    // ============================================
    // MÓDULO CONFIGURACIÓN (900)
    // ============================================
    Route::prefix('configuracion')->name('configuracion.')->group(function () {
        // Usuarios
        Route::prefix('usuarios')->name('usuarios.')->group(function () {
            Route::get('/select', [UsuarioController::class, 'select'])->name('select');
            Route::get('/create', [UsuarioController::class, 'create'])->name('create');
            Route::post('/store', [UsuarioController::class, 'store'])->name('store');
            Route::get('/{id}/qr', [UsuarioController::class, 'showQR'])->name('qr');
            Route::get('/{id}/edit', [UsuarioController::class, 'edit'])->name('edit');
            Route::put('/{id}', [UsuarioController::class, 'update'])->name('update');
            Route::delete('/{id}', [UsuarioController::class, 'destroy'])->name('destroy');
        });

        // Utilería
        Route::prefix('utileria')->name('utileria.')->group(function () {
            Route::get('/modulos', [ModulosController::class, 'index'])->name('modulos');
            Route::get('/modulos/create', [ModulosController::class, 'create'])->name('modulos.create');
            Route::post('/modulos', [ModulosController::class, 'store'])->name('modulos.store');
            Route::get('/modulos/{id}/edit', [ModulosController::class, 'edit'])->name('modulos.edit');
            Route::put('/modulos/{id}', [ModulosController::class, 'update'])->name('modulos.update');
            Route::delete('/modulos/{id}', [ModulosController::class, 'destroy'])->name('modulos.destroy');
            Route::post('/modulos/{id}/toggle-acceso', [ModulosController::class, 'toggleAcceso'])->name('modulos.toggle.acceso');
            Route::post('/modulos/{id}/toggle-permiso', [ModulosController::class, 'togglePermiso'])->name('modulos.toggle.permiso');



            // Route::get('/cargar-catalogos', [ExcelImportacionesController::class, 'showForm'])->name('cargar.catalogos');
            // Route::post('/cargar-catalogos', [ExcelImportacionesController::class, 'uploadCatalogos'])->name('cargar.catalogos.upload');
        });
    });

    // ============================================
    // RUTAS DIRECTAS (COMPATIBILIDAD)
    // ============================================

    // Rutas directas de catálogos
    // Route::get('/planeacion/programa-tejido', [ExcelImportacionesController::class, 'showReqProgramaTejido'])->name('catalogos.req-programa-tejido');
    Route::get('/planeacion/telares', [CatalagoTelarController::class, 'index'])->name('telares.index');
    Route::get('/planeacion/eficiencia', [CatalagoEficienciaController::class, 'index'])->name('eficiencia.index');
    Route::get('/planeacion/velocidad', [CatalagoVelocidadController::class, 'index'])->name('velocidad.index');
    Route::get('/planeacion/calendarios', [CalendarioController::class, 'index'])->name('calendarios.index');
    Route::get('/planeacion/aplicaciones', [AplicacionesController::class, 'index'])->name('aplicaciones.index');

    // Rutas directas de tejido
    Route::get('/tejido/inventario-telas', function () {
        return view('modulos/tejido/inventario-telas');
    })->name('tejido.inventario.telas');
    Route::get('/tejido/inventario-telas/jacquard', [TelaresController::class, 'inventarioJacquard'])->name('tejido.inventario.jacquard');
    Route::get('/tejido/inventario-telas/itema', [TelaresController::class, 'inventarioItema'])->name('tejido.inventario.itema');
    Route::get('/modulo-marcas-finales', [App\Http\Controllers\MarcasFinalesController::class, 'index'])->name('modulo.marcas.finales');
    Route::post('/modulo-marcas-finales', [App\Http\Controllers\MarcasFinalesController::class, 'store'])->name('modulo.marcas.finales.store');
    Route::get('/modulo-marcas-finales/{folio}', [App\Http\Controllers\MarcasFinalesController::class, 'show'])->name('modulo.marcas.finales.show');
    Route::put('/modulo-marcas-finales/{folio}', [App\Http\Controllers\MarcasFinalesController::class, 'update'])->name('modulo.marcas.finales.update');
    Route::post('/modulo-marcas-finales/{folio}/finalizar', [App\Http\Controllers\MarcasFinalesController::class, 'finalizar'])->name('modulo.marcas.finales.finalizar');

    // Rutas para Cortes de Eficiencia
    Route::get('/modulo-cortes-de-eficiencia', [CortesEficienciaController::class, 'index'])->name('cortes.eficiencia');
    Route::get('/modulo-cortes-de-eficiencia/turno-info', [CortesEficienciaController::class, 'getTurnoInfo'])->name('cortes.eficiencia.turno.info');
Route::get('/modulo-cortes-de-eficiencia/datos-telares', [CortesEficienciaController::class, 'getDatosTelares'])->name('cortes.eficiencia.datos.telares');
    Route::get('/modulo-cortes-de-eficiencia/generar-folio', [CortesEficienciaController::class, 'generarFolio'])->name('cortes.eficiencia.generar.folio');
    Route::post('/modulo-cortes-de-eficiencia', [CortesEficienciaController::class, 'store'])->name('cortes.eficiencia.store');
    Route::get('/modulo-cortes-de-eficiencia/{id}', [CortesEficienciaController::class, 'show'])->name('cortes.eficiencia.show');
    Route::put('/modulo-cortes-de-eficiencia/{id}', [CortesEficienciaController::class, 'update'])->name('cortes.eficiencia.update');
    Route::post('/modulo-cortes-de-eficiencia/{id}/finalizar', [CortesEficienciaController::class, 'finalizar'])->name('cortes.eficiencia.finalizar');
    Route::get('/modulo-nuevo-requerimiento', [NuevoRequerimientoController::class, 'index'])->name('modulo.nuevo.requerimiento');
    Route::post('/modulo-nuevo-requerimiento/guardar', [NuevoRequerimientoController::class, 'guardarRequerimientos'])->name('modulo.nuevo.requerimiento.store');
    Route::get('/modulo-nuevo-requerimiento/turno-info', [NuevoRequerimientoController::class, 'getTurnoInfo'])->name('modulo.nuevo.requerimiento.turno.info');
    Route::get('/modulo-nuevo-requerimiento/en-proceso', [NuevoRequerimientoController::class, 'enProcesoInfo'])->name('modulo.nuevo.requerimiento.enproceso');
    Route::post('/modulo-nuevo-requerimiento/actualizar-cantidad', [NuevoRequerimientoController::class, 'actualizarCantidad'])->name('modulo.nuevo.requerimiento.actualizar.cantidad');
    Route::get('/modulo-consultar-requerimiento', [ConsultarRequerimientoController::class, 'index'])->name('modulo.consultar.requerimiento');
    Route::get('/modulo-consultar-requerimiento/{folio}', [ConsultarRequerimientoController::class, 'show'])->name('modulo.consultar.requerimiento.show');
    Route::post('/modulo-consultar-requerimiento/{folio}/status', [ConsultarRequerimientoController::class, 'updateStatus'])->name('modulo.consultar.requerimiento.status');
    Route::get('/modulo-consultar-requerimiento/{folio}/resumen', [ConsultarRequerimientoController::class, 'resumen'])->name('modulo.consultar.requerimiento.resumen');

    // Rutas directas de configuración
    Route::get('/usuarios/select', [UsuarioController::class, 'select'])->name('usuarios.select');
    Route::get('/usuarios/create', [UsuarioController::class, 'create'])->name('usuarios.create');
    Route::post('/usuarios', [UsuarioController::class, 'store'])->name('usuarios.store');
    Route::get('/usuarios/{id}/qr', [UsuarioController::class, 'showQR'])->name('usuarios.qr');
    Route::get('/usuarios/{id}/edit', [UsuarioController::class, 'edit'])->name('usuarios.edit');
    Route::put('/usuarios/{id}', [UsuarioController::class, 'update'])->name('usuarios.update');
    Route::delete('/usuarios/{id}', [UsuarioController::class, 'destroy'])->name('usuarios.destroy');
    Route::get('/modulo-cargar-catálogos', function () {
        return view('modulos/cargar-catalogos');
    })->name('catalogos.index');
    // Route::post('/catalogos/upload', [ExcelImportacionesController::class, 'uploadCatalogos'])->name('catalogos.upload');

    // API para obtener datos del proceso actual
    Route::get('/api/telares/proceso-actual/{telarId}', function ($telarId) {
        // Determinar el tipo de salón según el telar
        $tipoSalon = null;

        // Verificar si es telar Jacquard (200-215)
        if ($telarId >= 200 && $telarId <= 215) {
            $tipoSalon = 'JACQUARD';
        }
        // Verificar si es telar Itema (299-320)
        elseif ($telarId >= 299 && $telarId <= 320) {
            $tipoSalon = 'ITEMA';
        }

        if (!$tipoSalon) {
            return response()->json(['error' => 'Tipo de telar no reconocido'], 400);
        }

        $procesoActual = \Illuminate\Support\Facades\DB::table('ReqProgramaTejido')
            ->where('SalonTejidoId', $tipoSalon)
            ->where('NoTelarId', $telarId)
            ->where('EnProceso', 1)
            ->select([
                'CuentaRizo as Cuenta',
                'CalibreRizo as Calibre_Rizo',
                'FibraRizo as Fibra_Rizo',
                'CuentaPie as Cuenta_Pie',
                'CalibrePie as Calibre_Pie',
                'FibraPie as Fibra_Pie'
            ])
            ->first();

        return response()->json($procesoActual ?: null);
    });

    // API para obtener datos de la siguiente orden
    Route::get('/api/telares/siguiente-orden/{telarId}', function ($telarId) {
        // Determinar el tipo de salón según el telar
        $tipoSalon = null;

        // Verificar si es telar Jacquard (200-215)
        if ($telarId >= 200 && $telarId <= 215) {
            $tipoSalon = 'JACQUARD';
        }
        // Verificar si es telar Itema (299-320)
        elseif ($telarId >= 299 && $telarId <= 320) {
            $tipoSalon = 'ITEMA';
        }

        if (!$tipoSalon) {
            return response()->json(['error' => 'Tipo de telar no reconocido'], 400);
        }

        // Obtener datos del telar en proceso para obtener FechaInicio
        $telarEnProceso = \Illuminate\Support\Facades\DB::table('ReqProgramaTejido')
            ->where('SalonTejidoId', $tipoSalon)
            ->where('NoTelarId', $telarId)
            ->where('EnProceso', 1)
            ->select('FechaInicio')
            ->first();

        if (!$telarEnProceso) {
            return response()->json(['error' => 'Telar no encontrado en proceso'], 404);
        }

        // Obtener siguiente orden
        $siguienteOrden = \Illuminate\Support\Facades\DB::table('ReqProgramaTejido')
            ->where('SalonTejidoId', $tipoSalon)
            ->where('NoTelarId', $telarId)
            ->where('EnProceso', 0)
            ->where('FechaInicio', '>', $telarEnProceso->FechaInicio)
            ->select([
                'CuentaRizo as Cuenta',
                'CalibreRizo as Calibre_Rizo',
                'FibraRizo as Fibra_Rizo',
                'CuentaPie as Cuenta_Pie',
                'CalibrePie as Calibre_Pie',
                'FibraPie as Fibra_Pie'
            ])
            ->orderBy('FechaInicio')
            ->first();

        return response()->json($siguienteOrden ?: null);
    });

    // ============================================
    // RUTAS LEGACY (MANTENER POR COMPATIBILIDAD)
    // ============================================

    // Módulo Urdido (mantener por compatibilidad)
    Route::get('/modulo-urdido', function () {
        return view('modulos/urdido');
    });
    Route::get('/urdido/programar-requerimientos', function () {
        return view('modulos/urdido/programar-requerimientos');
    });
    Route::get('ingresar-folio', [UrdidoController::class, 'cargarOrdenesPendientesUrd'])->name('ingresarFolio');
    Route::post('/urdido/prioridad/mover', [UrdidoController::class, 'mover'])->name('urdido.prioridad.mover');
    // Route::post('/engomado/prioridad/mover', [EngomadoController::class, 'mover'])->name('engomado.prioridad.mover');
    Route::post('orden-trabajo', [UrdidoController::class, 'cargarDatosUrdido'])->name('produccion.ordenTrabajo');
    Route::post('/urdido/autoguardar', [UrdidoController::class, 'autoguardar'])->name('urdido.autoguardar');
    Route::post('/urdido/finalizar', [UrdidoController::class, 'finalizarUrdido'])->name('urdido.finalizar');
    // Route::post('/engomado/autoguardar', [EngomadoController::class, 'autoguardar'])->name('engomado.autoguardar');
    // Route::post('/engomado/finalizar', [EngomadoController::class, 'finalizarEngomado'])->name('engomado.finalizar');
    Route::get('/imprimir-orden-llena-urd/{folio}', [UrdidoController::class, 'imprimirOrdenUrdido'])->name('imprimir.orden.urdido');
    Route::get('/imprimir-papeletas-pequenias/{folio}', [UrdidoController::class, 'imprimirPapeletas'])->name('imprimir.orden.papeletas');

    // Módulo Engomado
    Route::get('/modulo-engomado', function () {
        return view('modulos/engomado');
    });
    Route::get('/engomado/programar-requerimientos', function () {
        return view('modulos/engomado/programar-requerimientos');
    });
    // Route::get('/ingresar-folio-engomado', [EngomadoController::class, 'cargarOrdenesPendientesEng'])->name('ingresarFolioEngomado');
    // Route::post('/orden-trabajo-engomado', [EngomadoController::class, 'cargarDatosEngomado'])->name('produccion.ordenTrabajoEngomado');
    // Route::post('/guardar-y-finalizar-engomado', [EngomadoController::class, 'guardarYFinalizar'])->name('ordenEngomado.guardarYFinalizar');
    // Route::get('/imprimir-orden/{folio}', [EngomadoController::class, 'imprimirOrdenUE'])->name('imprimir.orden');
    // Route::get('/imprimir-papeletas-llenas/{folio}', [EngomadoController::class, 'imprimirPapeletasEngomado'])->name('imprimir.papeletas.engomado');
    Route::get('/folio-pantalla/{folio}', function ($folio) {
        return view('modulos.programar_requerimientos.FolioEnPantalla')->with('folio', $folio);
    })->name('folio.pantalla');

    // Módulo Atadores
    Route::get('/modulo-atadores', function () {
        return view('modulos/atadores');
    });
    Route::get('/atadores/programar-requerimientos', function () {
        return view('modulos/atadores/programar-requerimientos');
    });
    Route::get('/atadores-juliosAtados', [AtadorController::class, 'cargarDatosUrdEngAtador'])->name('datosAtadores.Atador');
    Route::post('/atadores/save', [AtadorController::class, 'save'])->name('atadores.save');
    Route::get('/atadores/show', [AtadorController::class, 'show'])->name('atadores.show');
    Route::post('/tejedores/validar', [AtadorController::class, 'validarTejedor'])->name('tejedor.validar');

    // Módulo Mantenimiento
    Route::get('/modulo-mantenimiento', function () {
        return view('modulos/mantenimiento');
    });

    // ============================================
    // RUTAS DE PRODUCCIÓN URD ENGOMADO
    // ============================================
    // Route::get('/programa-urd-eng/reservar-programar', [ExcelImportacionesController::class, 'showReservarProgramar'])->name('programa.urdeng.reservar');
    Route::post('/guardar-requerimiento', [RequerimientoController::class, 'store']);
    Route::get('/ultimos-requerimientos', function() {
        return response()->json([]);
    });
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

    // Módulo Edición Urdido-Engomado
    Route::get('/modulo-edicion-urdido-engomado', function () {
        return view('/modulos/edicion_urdido_engomado/edicion-urdido-engomado-folio');
    })->name('ingresarFolioEdicion');
    Route::get('/orden-trabajo-editar', [UrdidoController::class, 'cargarDatosOrdenUrdEng'])->name('update.ordenTrabajo');
    Route::post('/tejido/actualizarUrdidoEngomado', [UrdidoController::class, 'ordenToActualizar'])->name('orden.produccion.update');
    Route::post('/reservar-inventario', [RequerimientoController::class, 'BTNreservar'])->name('reservar.inventario');

    // Módulo Configuración
    Route::get('/modulo-configuracion', [UsuarioController::class, 'showConfiguracion'])->name('configuracion.index');
    Route::get('/configuracion/parametros', function () { return view('modulos/configuracion/parametros'); });
    Route::get('/configuracion/base-datos', function () { return view('modulos/configuracion/base-datos'); });
    Route::get('/configuracion/bd-pro-productivo', function () { return view('modulos/configuracion/bd-pro-productivo'); });
    Route::get('/configuracion/bd-pro-pruebas', function () { return view('modulos/configuracion/bd-pro-pruebas'); });
    Route::get('/configuracion/bd-tow-productivo', function () { return view('modulos/configuracion/bd-tow-productivo'); });
    Route::get('/configuracion/bd-tow-pruebas', function () { return view('modulos/configuracion/bd-tow-pruebas'); });
    Route::get('/configuracion/ambiente', function () { return view('modulos/configuracion/ambiente'); });
    Route::get('/configuracion/cargar-orden-produccion', function () { return view('modulos/configuracion/cargar-orden-produccion'); });
    Route::get('/configuracion/cargar-planeacion', function () { return view('modulos/configuracion/cargar-planeacion'); });

    // Ruta temporal
    Route::get('/urdido/urdidoTemporal', function () {
        return view('modulos/urdido/urdidoTemporal');
    });

    // ============================================
    // RUTAS ADICIONALES NECESARIAS
    // ============================================

    // Información individual de telares
    Route::get('/tejido/jacquard-sulzer/{telar}', [TelaresController::class, 'mostrarTelarSulzer'])->name('tejido.mostrarTelarSulzer');
    Route::get('/ordenes-programadas-dinamica/{telar}', [TelaresController::class, 'obtenerOrdenesProgramadas'])->name('ordenes.programadas');

    // Rutas adicionales de planeación
    // Route::get('/planeacion/tipo-movimientos/{id}', [PlaneacionController::class, 'obtenerPorTejNum']);
    // Route::put('/tejido-en-proceso/{num_registro}', [PlaneacionController::class, 'update'])->name('tejido_scheduling.update');
    // Route::get('/buscar-modelos', [PlaneacionController::class, 'buscarModelos'])->name('modelos.buscar');
    // Route::get('/modelos-por-clave', [PlaneacionController::class, 'obtenerModelosPorClave'])->name('modelos.porClave');
    // Route::get('/modelo/detalle', [PlaneacionController::class, 'buscarDetalleModelo'])->name('modelos.detalle');

    // Rutas adicionales de módulos
    Route::get('/modulos/{modulo}/duplicar', [ModulosController::class, 'duplicar'])->name('modulos.duplicar');
    Route::post('/modulos/{modulo}/toggle-acceso', [ModulosController::class, 'toggleAcceso'])->name('modulos.toggle.acceso');
    Route::post('/modulos/{modulo}/toggle-permiso', [ModulosController::class, 'togglePermiso'])->name('modulos.toggle.permiso');
    Route::get('/api/modulos/nivel/{nivel}', [ModulosController::class, 'getModulosPorNivel'])->name('api.modulos.nivel');
    Route::get('/api/modulos/submodulos/{dependencia}', [ModulosController::class, 'getSubmodulos'])->name('api.modulos.submodulos');

    //Rutas para inventario de telares
    Route::get('/inventario-telares', function (\Illuminate\Http\Request $request) {
        try {
            $inventario = \App\Models\TejInventarioTelares::orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $inventario
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener inventario: ' . $e->getMessage()
            ], 500);
        }
    })->name('inventario-telares.get');

    Route::post('/inventario-telares/guardar', function (\Illuminate\Http\Request $request) {
        try {
            $validated = $request->validate([
                'no_telar' => 'required|string|max:20',
                'tipo' => 'required|string|max:20',
                'cuenta' => 'required|string|max:20',
                'calibre' => 'nullable',
                'fecha' => 'required|date',
                'turno' => 'required|integer|min:1|max:3',
                'salon' => 'required|string|max:50',
                'hilo' => 'nullable|string|max:50',
                'no_orden' => 'nullable|string|max:50',
            ]);

            // Solo una selección activa por telar+tipo (entre los 7 días de la vista): upsert por no_telar+tipo
            $existente = \App\Models\TejInventarioTelares::where('no_telar', $validated['no_telar'])
                ->where('tipo', $validated['tipo'])
                ->where('status', 'Activo')
                ->first();

            if ($existente) {
                $existente->cuenta = $validated['cuenta'];
                $existente->calibre = $validated['calibre'];
                $existente->fecha = $validated['fecha'];
                $existente->turno = $validated['turno'];
                $existente->salon = $validated['salon'];
                $existente->hilo = $validated['hilo'] ?? $existente->hilo;
                $existente->no_orden = $validated['no_orden'] ?? $existente->no_orden;
                $existente->save();
                $registro = $existente;
            } else {
                $registro = \App\Models\TejInventarioTelares::create([
                    'no_telar' => $validated['no_telar'],
                    'status' => 'Activo',
                    'tipo' => $validated['tipo'],
                    'cuenta' => $validated['cuenta'],
                    'calibre' => $validated['calibre'],
                    'fecha' => $validated['fecha'],
                    'turno' => $validated['turno'],
                    'tipo_atado' => 'Normal',
                    'salon' => $validated['salon'],
                    'hilo' => $validated['hilo'] ?? null,
                    'no_orden' => $validated['no_orden'] ?? null,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Guardado con éxito',
                'data' => $registro,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar: ' . $e->getMessage()
            ], 500);
        }
    })->name('inventario-telares.guardar');

    // RUTAS DE MÓDULOS (MOVIDAS A MÓDULOS ORGANIZADOS)
});
