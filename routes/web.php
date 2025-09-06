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


//Rutas de login, con logout, no protegidas por middleware

Route::get('/', function () {
    return view('login');
});
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::post('/login', [AuthController::class, 'login']);
// Ruta para obtener el nombre y la foto del empleado
Route::get('/obtener-nombre/{noEmpleado}', function ($noEmpleado) {
    $empleado = App\Models\Usuario::where('numero_empleado', $noEmpleado)->first();

    if ($empleado) {
        return response()->json([
            'nombre' => $empleado->nombre,
            'foto' => $empleado->foto // Asumiendo que 'foto' es el campo en la base de datos
        ]);
    }
    return response()->json([], 404);
});
// Ruta para obtener los empleados de un área específica
Route::get('/obtener-empleados/{area}', function ($area) {
    return App\Models\Usuario::where('area', $area)->get();
});
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/login-qr', [AuthController::class, 'loginQR']);


Route::middleware('auth')->group(function () {
    Route::get('/produccionProceso', [UsuarioController::class, 'index'])->name('produccion.index');

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
    Route::post('/guardar-requerimiento', [RequerimientoController::class, 'store']);
    Route::get('/ultimos-requerimientos', [RequerimientoController::class, 'obtenerRequerimientosActivos']);
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
    Route::post('/inventario/seleccion', [RequerimientoController::class, 'step3Store'])->name('inventario.step3.store'); // guardado de step 3 prog-requerimientos



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
    Route::get('/modulo-configuracion', function () {
        return view('modulos/configuracion');
    });

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
    // CRUD REST (edit/update/destroy)
    Route::resource('usuarios', UsuarioController::class)->only(['edit', 'update', 'destroy']);

    //Route::get('/usuarios/{numero_empleado}/edit', [UsuarioController::class, 'edit'])->name('usuarios.edit'); // (puedes apuntarlo a tu formulario existente)
    //Route::delete('/usuarios/{numero_empleado}', [UsuarioController::class, 'destroy'])->name('usuarios.destroy');

    //RUTAS DEL MODULO planeacion
    // Ruta de RECURSOS para Planeacion
    Route::resource('planeacion', PlaneacionController::class);
    //RUTAS de CATALAGOS (3 catalagos), se usaron rutas de recursos para manejar las operaciones CRUD ¡IMPORTANTE!
    Route::resource('telares', CatalagoTelarController::class);
    Route::resource('eficiencia', CatalagoEficienciaController::class);
    Route::resource('velocidad', CatalagoVelocidadController::class);

    Route::get('/traspasoDataRedireccion', [TejidoSchedullingController::class, 'envioDeDataPlaneacion']);
    Route::get('/Tejido-Scheduling/ultimo-por-telar', [TejidoSchedullingController::class, 'buscarUltimoPorTelar']);
    Route::get('/Tejido-Scheduling/fechaFin', [TejidoSchedullingController::class, 'calcularFechaFin']);
    Route::get('/Tejido-Scheduling/editar', [TejidoSchedullingController::class, 'editarRegistro']); // formulario de edición para Registros de Planeación
    Route::post('/Tejido-Scheduling/actualizar', [TejidoSchedullingController::class, 'actualizarRegistro'])->name('actualizarRegistro.add');
    Route::post('/tejido-scheduling/mover', [TejidoSchedullingController::class, 'moverRegistro'])->name('tejido.mover'); //ruta para subir



    // ✅ NUEVAS RUTAS de PLANEACION  PLANEACION  PLANEACION  PLANEACION  PLANEACION  PLANEACION  PLANEACION  PLANEACION  PLANEACION  PLANEACION  PLANEACION  PLANEACION
    Route::get('/catalagos/calendarios', [CalendarioController::class, 'CalendarioT1'])->name('calendariot1.index');
    Route::get('/aplicaciones', [PlaneacionController::class, 'aplicaciones'])->name('planeacion.aplicaciones');
    Route::post('/calendarios/update-inline', [CalendarioController::class, 'updateInline'])->name('calendarios.update.inline');
    Route::get('/planeacion/tipo-movimientos/{id}', [PlaneacionController::class, 'obtenerPorTejNum']);
    Route::put('/tejido-en-proceso/{num_registro}', [PlaneacionController::class, 'update'])->name('tejido_scheduling.update');
    Route::get('/buscar-modelos', [PlaneacionController::class, 'buscarModelos'])->name('modelos.buscar'); //<- Rutas para SELECTS en Planeacion 
    Route::get('/modelos-por-clave', [PlaneacionController::class, 'obtenerModelosPorClave'])->name('modelos.porClave');
    Route::get('/modelo/detalle', [PlaneacionController::class, 'buscarDetalleModelo'])->name('modelos.detalle'); // ruta pra obtener DETALLES del registro del modelo, de acuerdo con la CLAVE_AX y el NOMBRE_MODELO
    Route::get('/telares/datos', [PlaneacionController::class, 'obtenerDatosTelar'])->name('telares.datos');


    // Rutas de catálogos
    Route::resource('telares', CatalagoTelarController::class);
    Route::resource('eficiencia', CatalagoEficienciaController::class);
    Route::resource('velocidad', CatalagoVelocidadController::class);

    //RUTAS para REPORTES en TEJIDO_SCHEDULING - REPORTES REPORTES REPORTES REPORTES REPORTES REPORTES REPORTES REPORTES REPORTES REPORTES REPORTES REPORTES REPORTES REPORTES REPORTES
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
    Route::resource('modelos', ModelosController::class);
    /* 
Verbo HTTP | URI | Acción del Controller | Nombre de ruta
GET | /modelos | index() | modelos.index
GET | /modelos/create | create() | modelos.create
POST | /modelos | store() | modelos.store
GET | /modelos/{id} | show() | modelos.show
GET | /modelos/{id}/edit | edit() | modelos.edit
PUT/PATCH | /modelos/{id} | update() | modelos.update
DELETE | /modelos/{id} | destroy() | modelos.destroy
*/
    Route::put('/modelos/{clave_ax}/{tamanio_ax}', [ModelosController::class, 'update'])->name('modelos.update');

    Route::get('/modelos/{clave_ax}/{tamanio_ax}/edit', [ModelosController::class, 'edit'])->name('modelos.edit');
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

    //RUTAS TEMPORALES
    Route::post('/reportes-temporales', [ReporteTemporalController::class, 'store'])->name('reportes-temporales.store');
    Route::get('/reportes-temporales', [\App\Http\Controllers\ReporteTemporalController::class, 'index'])->name('reportes-temporales.index');
});
