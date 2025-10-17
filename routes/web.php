<?php

use App\Http\Controllers\AtadorController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CalendarioController;
use App\Http\Controllers\CatalagoEficienciaController;
use App\Http\Controllers\CatalagoTelarController;
use App\Http\Controllers\CatalagoVelocidadController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\EngomadoController;
use App\Http\Controllers\ExcelImportacionesController;
use App\Http\Controllers\ModelosController;
use App\Http\Controllers\PlaneacionController;
use App\Http\Controllers\RequerimientoController;
use App\Http\Controllers\UsuarioController;
use App\Models\Usuario;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ReporteFallaController;
use App\Http\Controllers\ReporteTemporalController;
use App\Http\Controllers\TejedorController;
use App\Http\Controllers\TejidoSchedullingController;
use App\Http\Controllers\TelaresController;
use App\Http\Controllers\UrdidoController;
use App\Http\Controllers\WhatsAppController;
use App\Http\Controllers\ModulosController;
use App\Http\Controllers\AplicacionesController;


//Rutas de login, con logout, no protegidas por middleware

// Rutas de autenticación
Route::get('/', [AuthController::class, 'showLoginForm'])->name('home');
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/login-qr', [AuthController::class, 'loginQR']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Rutas para obtener datos de empleados
Route::get('/obtener-nombre/{noEmpleado}', function ($noEmpleado) {
    // Fallback temporal para usuarios de prueba (PRIORITARIO para evitar 500)
    $fakeUsers = [
        '1001' => ['nombre' => 'Juan Pérez',  'foto' => 'fotos_usuarios/juan_perez2.jpg'],
        '1002' => ['nombre' => 'María López', 'foto' => 'fotos_usuarios/maría_lopez.jpg'],
        '1003' => ['nombre' => 'Almacen',     'foto' => 'fotos_usuarios/carlos_ramirez.jpg'],
        '1004' => ['nombre' => 'Engomado',    'foto' => 'fotos_usuarios/ana_torres.jpg'],
        '1005' => ['nombre' => 'Tejido',      'foto' => 'fotos_usuarios/pedro_gomez.jpg'],
    ];
    $num = (string) $noEmpleado;
    if (isset($fakeUsers[$num])) {
        return response()->json($fakeUsers[$num]);
    }

    // Intento de búsqueda en BD envuelta en try/catch para evitar 500
    try {
        $empleado = App\Models\Usuario::where('numero_empleado', $noEmpleado)->first();
    } catch (\Throwable $e) {
        $empleado = null;
    }

    if ($empleado) {
        return response()->json([
            'nombre' => $empleado->nombre,
            'foto' => $empleado->foto
        ]);
    }

    return response()->json([], 404);
});

Route::get('/obtener-empleados/{area}', function ($area) {
    try {
        return App\Models\Usuario::where('area', $area)->get();
    } catch (\Throwable $e) {
        return [];
    }
});


// Rutas protegidas por autenticación
Route::middleware(['auth'])->group(function () {
    Route::get('/produccionProceso', [UsuarioController::class, 'index'])->name('produccion.index');

    // Rutas para sub-módulos
    Route::get('/submodulos/{modulo}', [UsuarioController::class, 'showSubModulos'])->name('submodulos.show');
    Route::get('/submodulos-nivel3/{moduloPadre}', [UsuarioController::class, 'showSubModulosNivel3'])->name('submodulos.nivel3');

    // API para precarga de submódulos (AJAX)
    Route::get('/api/submodulos/{moduloPrincipal}', [UsuarioController::class, 'getSubModulosAPI'])->name('api.submodulos');

    //RUTAS DEL MODULO **tejido************************************************************************************************************
    Route::get('/modulo-tejido', function () {
        return view('modulos/tejido');
    });
    Route::get('/tejido/jacquard-sulzer', function () {
        return view('modulos/tejido/jacquard-sulzer');
    });
    Route::get('/tejido/jacquard-smith', function () {
        return view('modulos/tejido/jacquard-smith');
    });
    Route::get('/tejido/smith', function () {
        return view('modulos/tejido/smith');
    });
    Route::get('/tejido/itema-viejo', function () {
        return view('modulos/tejido/itema-viejo');
    });
    Route::get('/tejido/itema-nuevo', function () {
        return view('modulos/tejido/itema-nuevo');
    });
Route::get('/tejido/karl-mayer', function () {
    return view('modulos/tejido/karl-mayer');
});
    Route::get('/tejido/inventario-telas', function () {
        return view('modulos/tejido/inventario-telas');
    });
    Route::get('/tejido/inventario-telas/jacquard', [TelaresController::class, 'inventarioJacquard'])->name('tejido.inventario.jacquard');
    Route::get('/tejido/inventario-telas/itema', [TelaresController::class, 'inventarioItema'])->name('tejido.inventario.itema');

    // Rutas para Inventario Trama
    Route::get('/modulo-nuevo-requerimiento', function () {
        return view('modulos.nuevo-requerimiento');
    });
    Route::get('/modulo-consultar-requerimiento', function () {
        return view('modulos.consultar-requerimiento');
    });

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

    //RUTAS DEL MODULO **urdido**
    Route::get('/modulo-urdido', function () {
        return view('modulos/urdido');
    });
    Route::get('/urdido/programar-requerimientos', function () {
        return view('modulos/urdido/programar-requerimientos');
    });
    //rutas para la interfaz INREGSAR FOLIO del modulo URDIDO, se agregaron mas rutas para function drag-and-drop
    Route::get('ingresar-folio', [UrdidoController::class, 'cargarOrdenesPendientesUrd'])->name('ingresarFolio');
    Route::post(
        '/urdido/prioridad/mover',
        [UrdidoController::class, 'mover']
    )->name('urdido.prioridad.mover');
    Route::post(
        '/engomado/prioridad/mover',
        [EngomadoController::class, 'mover']
    )->name('engomado.prioridad.mover');

    Route::post('orden-trabajo', [UrdidoController::class, 'cargarDatosUrdido'])->name('produccion.ordenTrabajo');
    Route::post('/urdido/autoguardar', [UrdidoController::class, 'autoguardar'])->name('urdido.autoguardar');
    Route::post('/urdido/finalizar', [UrdidoController::class, 'finalizarUrdido'])->name('urdido.finalizar');

    Route::post('/engomado/autoguardar', [EngomadoController::class, 'autoguardar'])->name('engomado.autoguardar');
    Route::post('/engomado/finalizar', [EngomadoController::class, 'finalizarEngomado'])->name('engomado.finalizar');

    Route::get('/imprimir-orden-llena-urd/{folio}', [UrdidoController::class, 'imprimirOrdenUrdido'])->name('imprimir.orden.urdido');
    Route::get('/imprimir-papeletas-pequenias/{folio}', [UrdidoController::class, 'imprimirPapeletas'])->name('imprimir.orden.papeletas');

    //RUTAS DEL MODULO **engomado**
    Route::get('/modulo-engomado', function () {
        return view('modulos/engomado');
    });
    Route::get('/engomado/programar-requerimientos', function () {
        return view('modulos/engomado/programar-requerimientos');
    });

    Route::get('/ingresar-folio-engomado', [EngomadoController::class, 'cargarOrdenesPendientesEng'])->name('ingresarFolioEngomado');
    Route::post('/orden-trabajo-engomado', [EngomadoController::class, 'cargarDatosEngomado'])->name('produccion.ordenTrabajoEngomado');
    Route::post('/guardar-y-finalizar-engomado', [EngomadoController::class, 'guardarYFinalizar'])->name('ordenEngomado.guardarYFinalizar'); //Ruta que sustituye a 2 amtiguas rutas de 2 botones que se unificaron
    Route::get('/imprimir-orden/{folio}', [EngomadoController::class, 'imprimirOrdenUE'])->name('imprimir.orden');
    Route::get('/imprimir-papeletas-llenas/{folio}', [EngomadoController::class, 'imprimirPapeletasEngomado'])->name('imprimir.papeletas.engomado');
    Route::get('/folio-pantalla/{folio}', function ($folio) {
        return view('modulos.programar_requerimientos.FolioEnPantalla')->with('folio', $folio);
    })->name('folio.pantalla');

    //RUTAS DEL MODULO **ATADORES ATADORES ATADORES ATADORES**
    Route::get('/modulo-atadores', function () {
        return view('modulos/atadores');
    });
    Route::get('/atadores/programar-requerimientos', function () {
        return view('modulos/atadores/programar-requerimientos');
    });
    Route::get('/atadores-juliosAtados',  [AtadorController::class, 'cargarDatosUrdEngAtador'])->name('datosAtadores.Atador');
    Route::post('/atadores/save', [AtadorController::class, 'save'])->name('atadores.save'); // para create/update vía AJAX
    Route::get('/atadores/show', [AtadorController::class, 'show'])->name('atadores.show'); // para mostrar registro por orden y turno vía AJAX
    Route::post('/tejedores/validar', [AtadorController::class, 'validarTejedor'])->name('tejedor.validar');


    //RUTAS DEL MODULO **tejedores** TEJEDORES TEJEDORES
    Route::get('/modulo-tejedores', function () {
        return view('modulos/tejedores');
    });
    Route::get('/tejedores/programar-requerimientos', function () {
        return view('modulos/tejedores/programar-requerimientos');
    });
    Route::get('/tejedores/formato', [TejedorController::class, 'index'])->name('tejedores.index');
    Route::post('/manufactura/guardar', [TejedorController::class, 'store'])->name('manufactura.guardar');

    Route::post('/manufactura/{id}/update', [TejedorController::class, 'update'])->name('manufactura.update');



    //RUTAS DEL MODULO **mantenimiento**
    Route::get('/modulo-mantenimiento', function () {
        return view('modulos/mantenimiento');
    });

    //RUTAS DEL MODULO **Programacion-Urdido-Engomado**
    Route::get('/programa-urd-eng/reservar-programar', [ExcelImportacionesController::class, 'showReservarProgramar'])->name('programa.urdeng.reservar');
    Route::post('/guardar-requerimiento', [RequerimientoController::class, 'store']);
    // Ruta temporal para requerimientos (evitar error 500)
    Route::get('/ultimos-requerimientos', function() {
        return response()->json([]);
    });
    Route::get('/modulo-UrdidoEngomado', [RequerimientoController::class, 'requerimientosActivos'])->name('index.requerimientosActivos');
    Route::get('/tejido/programarReq-step1', [RequerimientoController::class, 'requerimientosAProgramar'])->name('formulario.programarRequerimientos');
    Route::post('/tejido/guardarUrdidoEngomado', [RequerimientoController::class, 'requerimientosAGuardar'])->name('crear.ordenes.lanzador');

    Route::get('/prog-req/init/resolve-folio', [RequerimientoController::class, 'resolveFolio'])->name('prog.init.resolveFolio'); // Resolver/crear folio a partir de ids[] o folio existente
    Route::get('/prog-req/init/fetch-by-folio', [RequerimientoController::class, 'initAndFetchByFolio'])->name('prog.init.fetchByFolio'); // Inicializa si falta (inserta “folio” en tablas destino) y devuelve datos
    Route::post('/prog-req/init/upsert-and-fetch', [RequerimientoController::class, 'upsertAndFetchByFolio'])->name('prog.init.upsertFetch');
    Route::post('/prog-req/autosave/construccion', [RequerimientoController::class, 'autosaveConstruccion'])->name('urdido.autosave.construccion');
    Route::post('/prog-req/autosave/urdido-engomado', [RequerimientoController::class, 'autosaveUrdidoEngomado'])->name('urdido.autosave.engomado');
    Route::post('/prog/validar-folios', [RequerimientoController::class, 'validarFolios'])
        ->name('prog.validar.folios');
    Route::post('/urdido/autosave/lmaturdido', [RequerimientoController::class, 'autosaveLmaturdido'])->name('urdido.autosave.lmaturdido');
    Route::post('/inventario/seleccion', [RequerimientoController::class, 'step3Store'])->name('inventario.step3.store'); // guardado de step 3 prog-requerimientos en BD BD BD BD



    Route::post('/prog-req/step2', [RequerimientoController::class, 'step2'])->name('urdido.step2');
    Route::post('/prog-req/step3', [RequerimientoController::class, 'step3'])->name('urdido.step3');
    Route::get('/tejido/bomids', [App\Http\Controllers\Select2Controller::class, 'getBomIds'])->name('bomids.api'); //<- rutas para buscador select2 BOMIDs
    Route::get('/tejido/bomids2', [App\Http\Controllers\Select2Controller::class, 'getBomIds2'])->name('bomids.api2'); //<- rutas para buscador select2 BOMIDs

    //RUTAS DEL MODULO **EDICION-Urdido-Engomado** 22-05-2025
    Route::get('/modulo-edicion-urdido-engomado', function () {
        return view('/modulos/edicion_urdido_engomado/edicion-urdido-engomado-folio');
    })->name('ingresarFolioEdicion');
    Route::get('/orden-trabajo-editar', [UrdidoController::class, 'cargarDatosOrdenUrdEng'])->name('update.ordenTrabajo');
    Route::post('/tejido/actualizarUrdidoEngomado', [UrdidoController::class, 'ordenToActualizar'])->name('orden.produccion.update');
    Route::post('/reservar-inventario', [RequerimientoController::class, 'BTNreservar'])->name('reservar.inventario');

    //RUTAS DEL MODULO **configuracion**
    Route::get('/modulo-configuracion', [UsuarioController::class, 'showConfiguracion'])->name('configuracion.index');

    // Rutas para submódulos de configuración (usar showSubModulosConfiguracion con orden/nombre)
    Route::get('/configuracion/parametros', function () { return view('modulos/configuracion/parametros'); });
    Route::get('/configuracion/base-datos', function () { return view('modulos/configuracion/base-datos'); });
    Route::get('/configuracion/bd-pro-productivo', function () { return view('modulos/configuracion/bd-pro-productivo'); });
    Route::get('/configuracion/bd-pro-pruebas', function () { return view('modulos/configuracion/bd-pro-pruebas'); });
    Route::get('/configuracion/bd-tow-productivo', function () { return view('modulos/configuracion/bd-tow-productivo'); });
    Route::get('/configuracion/bd-tow-pruebas', function () { return view('modulos/configuracion/bd-tow-pruebas'); });
    Route::get('/configuracion/ambiente', function () { return view('modulos/configuracion/ambiente'); });
    Route::get('/configuracion/cargar-orden-produccion', function () { return view('modulos/configuracion/cargar-orden-produccion'); });
    Route::get('/configuracion/cargar-planeacion', function () { return view('modulos/configuracion/cargar-planeacion'); });

    //ruta temporal para vista de circulo - borrar despues
    Route::get('/urdido/urdidoTemporal', function () {
        return view('modulos/urdido/urdidoTemporal');
    });

    //ruta para llegar a la vista dinámica para INFORMACIÓN INDIVIDUAL DE TELARES *************************************************************
    //***********************************************************************************************************************************
    Route::get('/tejido/jacquard-sulzer/{telar}', [TelaresController::class, 'mostrarTelarSulzer'])->name('tejido.mostrarTelarSulzer');
    //el método de arriba sirve para mstrar la informacion de un telar individualmente (telar-informacion-individual)
    Route::get('/ordenes-programadas-dinamica/{telar}', [TelaresController::class, 'obtenerOrdenesProgramadas'])->name('ordenes.programadas');


    //CRUDZAZO de USUARIOS, 1er mantenimiento 19-08-2025. USUARIOS USUARIOS USUARIOS USUARIOS, en el USUARIOSCONTROLLER se manejan los contenedores visibles o no visibles
    //Route::get('/alta-usuarios', function () { return view('alta_usuarios');});//BORRAR UNA VEZ CREADO EL CONTROLLER
    Route::get('/usuarios/create', [UsuarioController::class, 'create'])->name('usuarios.create');
    Route::post('/usuarios', [UsuarioController::class, 'store'])->name('usuarios.store');
    Route::get('/usuarios/select', [UsuarioController::class, 'select'])->name('usuarios.select');
    Route::get('/usuarios/{idusuario}/qr', [UsuarioController::class, 'showQR'])->name('usuarios.qr');
    Route::get('/configuracion', [UsuarioController::class, 'showConfiguracion'])->name('configuracion.index');
    Route::get('/configuracion/submodulos/{serie}', [UsuarioController::class, 'showSubModulosConfiguracion'])->name('configuracion.submodulos');
    // CRUD REST (edit/update/destroy)
    Route::resource('usuarios', UsuarioController::class)->only(['edit', 'update', 'destroy']);

    //Route::get('/usuarios/{numero_empleado}/edit', [UsuarioController::class, 'edit'])->name('usuarios.edit'); // (puedes apuntarlo a tu formulario existente)
    //Route::delete('/usuarios/{numero_empleado}', [UsuarioController::class, 'destroy'])->name('usuarios.destroy');

    //RUTAS DEL MODULO planeacion
    // Ruta de RECURSOS para Planeacion
    Route::resource('planeacion', PlaneacionController::class);
    //RUTAS de CATALAGOS (3 catalagos), se usaron rutas de recursos para manejar las operaciones CRUD ¡IMPORTANTE!
    Route::resource('telares', CatalagoTelarController::class);
    Route::post('/telares/procesar-excel', [CatalagoTelarController::class, 'procesarExcel'])->name('telares.procesar-excel');
    Route::resource('eficiencia', CatalagoEficienciaController::class);
    Route::post('/eficiencia/procesar-excel', [CatalagoEficienciaController::class, 'procesarExcel'])->name('eficiencia.procesar-excel');
    Route::resource('velocidad', CatalagoVelocidadController::class);
    Route::post('/velocidad/procesar-excel', [CatalagoVelocidadController::class, 'procesarExcel'])->name('velocidad.procesar-excel');

    Route::get('/traspasoDataRedireccion', [TejidoSchedullingController::class, 'envioDeDataPlaneacion']);
    Route::get('/Tejido-Scheduling/ultimo-por-telar', [TejidoSchedullingController::class, 'buscarUltimoPorTelar']);
    Route::get('/Tejido-Scheduling/fechaFin', [TejidoSchedullingController::class, 'calcularFechaFin']);
    Route::get('/Tejido-Scheduling/editar', [TejidoSchedullingController::class, 'editarRegistro']); // formulario de edición para Registros de Planeación
    Route::post('/Tejido-Scheduling/actualizar', [TejidoSchedullingController::class, 'actualizarRegistro'])->name('actualizarRegistro.add');
    Route::post('/tejido-scheduling/mover', [TejidoSchedullingController::class, 'moverRegistro'])->name('tejido.mover'); //ruta para subir



    // ✅ NUEVAS RUTAS de PLANEACION  PLANEACION  PLANEACION  PLANEACION  PLANEACION  PLANEACION  PLANEACION  PLANEACION  PLANEACION  PLANEACION  PLANEACION  PLANEACION
    Route::get('/calendarios', [CalendarioController::class, 'index'])->name('calendarios.index');
    Route::post('/calendarios/procesar-excel', [CalendarioController::class, 'procesarExcel'])->name('calendario.procesar-excel');
    Route::get('/aplicaciones', [AplicacionesController::class, 'index'])->name('planeacion.aplicaciones');
    Route::resource('aplicaciones-catalogo', AplicacionesController::class);
    Route::post('/aplicaciones/procesar-excel', [AplicacionesController::class, 'procesarExcel'])->name('aplicaciones.procesar-excel');
    Route::post('/calendarios/update-inline', [CalendarioController::class, 'updateInline'])->name('calendarios.update.inline');
    Route::get('/planeacion/tipo-movimientos/{id}', [PlaneacionController::class, 'obtenerPorTejNum']);
    Route::put('/tejido-en-proceso/{num_registro}', [PlaneacionController::class, 'update'])->name('tejido_scheduling.update');
    Route::get('/buscar-modelos', [PlaneacionController::class, 'buscarModelos'])->name('modelos.buscar'); //<- Rutas para SELECTS en Planeacion
    Route::get('/modelos-por-clave', [PlaneacionController::class, 'obtenerModelosPorClave'])->name('modelos.porClave');
    Route::get('/modelo/detalle', [PlaneacionController::class, 'buscarDetalleModelo'])->name('modelos.detalle'); // ruta pra obtener DETALLES del registro del modelo, de acuerdo con la CLAVE_AX y el NOMBRE_MODELO
    Route::get('/telares/datos', [PlaneacionController::class, 'obtenerDatosTelar'])->name('telares.datos');

    //RUTAS para REPORTES en TEJIDO_SCHEDULING - REPORTES REPORTES REPORTES REPORTES REPORTES REPORTES REPORTES REPORTES REPORTES REPORTES REPORTES REPORTES REPORTES REPORTES REPORTES REPORTES
    // routes/web.php
    Route::get('/reportes/consumo', [\App\Http\Controllers\ReportesController::class, 'consumo'])->name('reportes.consumo');
    Route::get('/reportes/run', [\App\Http\Controllers\ReportesController::class, 'run'])->name('reportes.run');
    Route::get('/reportes/resumen-tejido', [\App\Http\Controllers\ReportesController::class, 'resumenTejido'])->name('reportes.resumen.tejido');
    Route::get('/reportes/aplicaciones', [\App\Http\Controllers\ReportesController::class, 'aplicaciones'])->name('reportes.aplicaciones');
    Route::get('/reportes/rasurado', [\App\Http\Controllers\ReportesController::class, 'rasurado'])->name('reportes.rasurado');
    Route::get('/reportes/perso-x-mod', [\App\Http\Controllers\ReportesController::class, 'pesoPorMod'])->name('reportes.peso.por.mod');
    Route::get('/reportes/perso-tenido', [\App\Http\Controllers\ReportesController::class, 'pesoTenido'])->name('reportes.peso.tenido');

    //twilio
    // Ruta para mostrar el formulario (GET)
    Route::get('/reportar', function () {
        return view('reportar');
    })->name('reportar.falla.form');
    Route::post('/reportar-falla', [ReporteFallaController::class, 'enviarReporte'])->name('reportar.falla');

    //WhatsApp Business
    Route::get('/whatsapp', function () {
        return view('whatsapp');
    });
    Route::post('/send-whatsapp', [WhatsAppController::class, 'sendMessage']);
    //Route::get('/whatsapp2', function () {return view('whatsapp2');});
    Route::get('/whatsapp2', [WhatsAppController::class, 'mensajeFallas'])->name('telares.falla');
    Route::post('/send-whatsapp2', [WhatsAppController::class, 'enviarMensaje']);
    Route::post('/send-failSMS', [ReporteFallaController::class, 'enviarSMS']);

    //TELEGRAM
    Route::post('/reportes-temporales/guardar', [ReporteTemporalController::class, 'guardar'])
        ->name('reportes.temporales.guardar');

    // MODELOS ***************************************************************************************************************************
    // Custom routes for modelos with composite keys
    Route::put('/modelos/{clave_ax}/{tamanio_ax}', [ModelosController::class, 'update'])->name('modelos.update');
    Route::get('/modelos/{clave_ax}/{tamanio_ax}/edit', [ModelosController::class, 'edit'])->name('modelos.edit');

    // Resource routes excluding the conflicting ones
    Route::resource('modelos', ModelosController::class)->except(['update', 'edit']);
    Route::get('/flogs/buscar', [App\Http\Controllers\TejidoSchedullingController::class, 'buscarFlogso'])->name('flog.buscar');

    //Rutas para chatbot, prueba 1 de IA integrada a Laravel
    Route::get('/chatbot', [ChatbotController::class, 'index']);
    Route::post('/chatbot/message', [ChatbotController::class, 'sendMessage']);

    //Rutas para importaciones de excel, TEJIDO_SCHEDULING y MODELOS
    Route::get('/tejido-scheduling/import', [ExcelImportacionesController::class, 'showForm'])->name('tejido.import.form');
    Route::post('/tejido-scheduling/import', [ExcelImportacionesController::class, 'import'])->name('tejido.import');
    Route::get('/tejido-scheduling/ventas', [TejidoSchedullingController::class, 'showBlade'])->name('tejido.scheduling.ventas');
    Route::get('/tejido-scheduling/pronosticos', [TejidoSchedullingController::class, 'showBladePronos'])->name('tejido.pronosticos.blade');
    // Ruta para AJAX (puede ser POST o GET)
    Route::post('/pronosticos/ajax', [TejidoSchedullingController::class, 'getPronosticosAjax'])->name('pronosticos.ajax');

    //Rutas para cargar catálogos
    Route::get('/modulo-cargar-catálogos', function () {
        return view('modulos/cargar-catalogos');
    })->name('catalogos.index');
    Route::post('/catalogos/upload', [ExcelImportacionesController::class, 'uploadCatalogos'])->name('catalogos.upload');
    Route::get('/catalogos/req-programa-tejido', [ExcelImportacionesController::class, 'showReqProgramaTejido'])->name('catalogos.req-programa-tejido');

    //RUTAS TEMPORALES
    Route::post('/reportes-temporales', [ReporteTemporalController::class, 'store'])->name('reportes-temporales.store');
    Route::get('/reportes-temporales', [\App\Http\Controllers\ReporteTemporalController::class, 'index'])->name('reportes-temporales.index');

    //Rutas para gestión de módulos
    Route::resource('modulos', ModulosController::class);
    Route::get('/modulo-modulos', [ModulosController::class, 'index'])->name('modulos.index');
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
});
