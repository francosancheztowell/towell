<?php
use App\Http\Controllers\Planeacion\CatCodificados\CatCodificacionController;
use App\Http\Controllers\Planeacion\ProgramaTejido\OrdenDeCambio\Felpa\OrdenDeCambioFelpaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Planeacion\CatalogoPlaneacion\CatCalendarios\CalendarioController;
use App\Http\Controllers\Planeacion\CatalogoPlaneacion\CatEficiencias\CatalagoEficienciaController;
use App\Http\Controllers\Planeacion\CatalogoPlaneacion\CatTelares\CatalagoTelarController;
use App\Http\Controllers\Planeacion\CatalogoPlaneacion\CatVelocidades\CatalagoVelocidadController;
use App\Http\Controllers\Planeacion\CatalogoPlaneacion\CatPesosRollos\PesosRollosController;
use App\Http\Controllers\Tejido\CortesEficiencia\CortesEficienciaController;
use App\Http\Controllers\Planeacion\ProgramaTejido\ProgramaTejidoController;
use App\Http\Controllers\Planeacion\ProgramaTejido\ColumnasProgramaTejidoController;
use App\Http\Controllers\ProgramaUrdEng\ReservarProgramar\RequerimientoController;
use App\Http\Controllers\Tejido\Configuracion\SecuenciaInvTelas\SecuenciaInvTelasController;
use App\Http\Controllers\Tejido\Configuracion\SecuenciaInvTrama\SecuenciaInvTramaController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Tejido\InventarioTelas\TelaresController;
use App\Http\Controllers\ModulosController;
use App\Http\Controllers\Planeacion\CatalogoPlaneacion\CatAplicaciones\AplicacionesController;
use App\Http\Controllers\Atadores\ProgramaAtadores\AtadoresController;
use App\Http\Controllers\Atadores\Catalogos\Actividades\AtaActividadesController;
use App\Http\Controllers\Atadores\Catalogos\Comentarios\AtaComentariosController;
use App\Http\Controllers\Atadores\Catalogos\Maquinas\AtaMaquinasController;
use App\Http\Controllers\Tejido\InventarioTrama\NuevoRequerimientoController;
use App\Http\Controllers\Tejido\ProduccionReenconado\ProduccionReenconadoCabezuelaController;
use App\Http\Controllers\Tejido\InventarioTrama\ConsultarRequerimientoController;
use App\Http\Controllers\Planeacion\CatalogoPlaneacion\ModelosCodificados\CodificacionController;
use App\Http\Controllers\Tejedores\InventarioTelaresController;
use App\Http\Controllers\ProgramaUrdEng\ReservarProgramar\InvTelasReservadasController;
use App\Http\Controllers\ProgramaUrdEng\ReservarProgramar\ReservarProgramarController;
use App\Http\Controllers\ProgramaUrdEng\ReservarProgramar\ProgramarUrdEngController;
use App\Http\Controllers\Urdido\ProgramaUrdido\ProgramarUrdidoController;
use App\Http\Controllers\Urdido\ProgramaUrdido\EditarOrdenesProgramadasController;
use App\Http\Controllers\Engomado\ProgramaEngomado\ProgramarEngomadoController;
use App\Http\Controllers\Engomado\Produccion\ModuloProduccionEngomadoController;
use App\Http\Controllers\Urdido\Configuracion\ModuloProduccionUrdidoController;
use App\Http\Controllers\Urdido\Configuracion\CatalogosJulios\CatalogosUrdidoController;
use App\Http\Controllers\Tejedores\Configuracion\CatDesarrolladores\catDesarrolladoresController;
use App\Http\Controllers\PDFController;
use App\Http\Controllers\Tejedores\TelActividadesBPMController;
use App\Http\Controllers\Tejedores\BPMTejedores\TelBpmController;
use App\Http\Controllers\Tejedores\BPMTejedores\TelBpmLineController;
use App\Http\Controllers\Tejedores\Configuracion\TelaresOperador\TelTelaresOperadorController;
use App\Http\Controllers\Urdido\Configuracion\ActividadesBPMUrdido\UrdActividadesBpmController;
use App\Http\Controllers\Urdido\BPMUrdido\UrdBpmController;
use App\Http\Controllers\Urdido\BPMUrdido\UrdBpmLineController;
use App\Http\Controllers\Mantenimiento\MantenimientoParosController;
use App\Http\Controllers\Simulaciones\SimulacionProgramaTejidoController;
use App\Http\Controllers\Tejido\MarcasFinales\MarcasController;
use App\Http\Controllers\Tejedores\NotificarMontadoJulios\NotificarMontadoJulioController;
use App\Http\Controllers\Tejedores\NotificarMontadoRollo\NotificarMontRollosController;
use App\Http\Controllers\Tejedores\Desarrolladores\TelDesarrolladoresController;
use App\Models\SYSRoles;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\Planeacion\ProgramaTejido\funciones\DividirTejido;
use App\Http\Controllers\Planeacion\ProgramaTejido\ReqProgramaTejidoLineController;
use App\Http\Controllers\Configuracion\ConfiguracionController;
use App\Http\Controllers\Telegram\TelegramController;
use Illuminate\Http\Request;
use App\Http\Controllers\Engomado\BPMEngomado\EngBpmController;
use App\Http\Controllers\Engomado\CapturaFormulas\EngProduccionFormulacionController;
use App\Http\Controllers\Planeacion\CatalogoPlaneacion\CatMatrizHilos\MatrizHilosController;
use App\Http\Controllers\Engomado\Configuracion\ActividadesBPMEngomado\EngActividadesBpmController;
use App\Http\Controllers\Engomado\BPMEngomado\EngBpmLineController;
use App\Http\Controllers\UrdEngomado\UrdEngNucleosController;
use App\Http\Controllers\ComprasEspecialesController;
use App\Http\Controllers\PronosticosController;
use App\Http\Controllers\Planeacion\ProgramaTejido\LiberarOrdenesController;
use App\Http\Controllers\Planeacion\ProgramaTejido\ReimprimirOrdenesController;
use App\Http\Controllers\Planeacion\ProgramaTejido\DescargarProgramaController;
use App\Http\Controllers\Simulaciones\SimulacionComprasEspecialesController;
use App\Http\Controllers\Simulaciones\SimulacionProgramaTejidoLineController;
use App\Http\Controllers\Planeacion\ProgramaTejido\funciones\BalancearTejido;
use App\Models\Usuario;
//Rutas de login, con logout, no protegidas por middleware

// Rutas de autenticación
Route::get('/', [AuthController::class, 'showLoginForm'])->name('home');
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/login-qr', [AuthController::class, 'loginQR']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');


Route::get('/obtener-empleados/{area}', function ($area) {
    try {
        return Usuario::where('area', $area)->get();
    } catch (\Throwable $e) {
        return [];
    }
});

// Ruta de prueba para 404 personalizado
Route::get('/test-404', function () {
    abort(404);
});

// Ruta offline para PWA
Route::view('/offline', 'offline')->name('offline');

// ============================================
// RUTAS PARA MÓDULOS SIN AUTENTICACIÓN
// ============================================
Route::prefix('modulos-sin-auth')->name('modulos.sin.auth.')->group(function () {
    // Listar módulos
    Route::get('/', function() {
        try {
            $modulos = SYSRoles::orderBy('orden')->get();
            return view('modulos.gestion-modulos.index', compact('modulos'));
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()]);
        }
    })->name('index');

    // Crear módulo
    Route::get('/create', function() {
        $modulosPrincipales = SYSRoles::where('Nivel', 1)
            ->whereNull('Dependencia')
            ->orderBy('orden')
            ->get();
        return view('modulos.gestion-modulos.create', compact('modulosPrincipales'));
    })->name('create');

    // Guardar módulo - Usa el controlador ModulosController
    Route::post('/', [ModulosController::class, 'store'])->name('store');

    // Editar módulo
    Route::get('/{id}/edit', function($id) {
        try {
            $modulo = SYSRoles::findOrFail($id);
            $modulosPrincipales = SYSRoles::where('Nivel', 1)
                ->whereNull('Dependencia')
                ->where('idrol', '!=', $modulo->idrol)
                ->orderBy('orden')
                ->get();
            return view('modulos.gestion-modulos.edit', compact('modulo', 'modulosPrincipales'));
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()]);
        }
    })->name('edit');

    // Actualizar módulo
    Route::put('/{id}', function($id, Request $request) {
        try {
            $modulo = SYSRoles::findOrFail($id);

            $data = $request->validate([
                'orden' => 'required|string|max:255',
                'modulo' => 'required|string|max:255',
                'Nivel' => 'required|string',
                'Dependencia' => 'nullable|string',
                'acceso' => 'boolean',
                'crear' => 'boolean',
                'modificar' => 'boolean',
                'eliminar' => 'boolean',
                'reigstrar' => 'boolean',
                'imagen_archivo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
            ]);

            // Convertir checkboxes a boolean
            $data['acceso'] = $request->has('acceso');
            $data['crear'] = $request->has('crear');
            $data['modificar'] = $request->has('modificar');
            $data['eliminar'] = $request->has('eliminar');
            $data['reigstrar'] = $request->has('reigstrar');

            // Manejar imagen
            $imagenActualizada = false;
            if ($request->hasFile('imagen_archivo')) {
                $file = $request->file('imagen_archivo');
                $filename = time() . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('images/fotos_modulos/'), $filename);
                $data['imagen'] = $filename;
                $imagenActualizada = true;
            }

            $modulo->update($data);

            // Limpiar caché automáticamente si se actualizó la imagen
            if ($imagenActualizada) {
                Artisan::call('cache:clear');
                Artisan::call('view:clear');

                // Limpiar caché específico de módulos
                $usuarios = Usuario::all();
                foreach($usuarios as $usuario) {
                    $cacheKeys = [
                        "modulos_principales_user_{$usuario->idusuario}",
                        "submodulos_planeacion_user_{$usuario->idusuario}",
                        "submodulos_tejido_user_{$usuario->idusuario}",
                        "submodulos_urdido_user_{$usuario->idusuario}",
                        "submodulos_engomado_user_{$usuario->idusuario}",
                        "submodulos_atadores_user_{$usuario->idusuario}",
                        "submodulos_tejedores_user_{$usuario->idusuario}",
                        "submodulos_mantenimiento_user_{$usuario->idusuario}",
                        "submodulos_configuracion_user_{$usuario->idusuario}",
                    ];

                    foreach($cacheKeys as $cacheKey) {
                        cache()->forget($cacheKey);
                    }
                }
            }

            return redirect()->route('modulos.sin.auth.index')
                ->with('success', 'Módulo actualizado exitosamente')
                ->with('show_sweetalert', true);

        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Error al actualizar módulo: ' . $e->getMessage());
        }
    })->name('update');

    // Eliminar módulo
    Route::delete('/{id}', function($id) {
        try {
            $modulo = SYSRoles::findOrFail($id);
            $modulo->delete();

            return response()->json([
                'success' => true,
                'message' => 'Módulo eliminado exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar módulo: ' . $e->getMessage()
            ], 500);
        }
    })->name('destroy');
});


// Rutas protegidas por autenticación
Route::middleware(['auth'])->group(function () {
    // RUTA PRINCIPAL
    Route::get('/produccionProceso', [UsuarioController::class, 'index'])->name('produccion.index');

    // ============================================
    // PRODUCCIÓN » REENCONADO CABEZUELA
    // ============================================
    Route::get('/produccion/reenconado-cabezuela', [ProduccionReenconadoCabezuelaController::class, 'index'])
        ->name('produccion.reenconado_cabezuela');
    Route::post('/produccion/reenconado-cabezuela', [ProduccionReenconadoCabezuelaController::class, 'store'])
        ->name('produccion.reenconado_cabezuela.store');

    // RUTAS PARA SUB-MÓDULOS
    Route::get('/submodulos/{modulo}', [UsuarioController::class, 'showSubModulos'])->name('submodulos.show');

    // Redirects específicos ANTES de la ruta genérica (importante: orden de evaluación)
    Route::redirect('/submodulos-nivel3/202', '/tejido/marcas-finales', 301);
    Route::redirect('/submodulos-nivel3/203', '/tejido/inventario', 301);
    Route::redirect('/submodulos-nivel3/206', '/tejido/cortes-eficiencia', 301);
    Route::redirect('/submodulos-nivel3/909', '/configuracion/utileria', 301);
    // Redirects para submódulos de Atadores
    Route::redirect('/submodulos-nivel3/502', '/atadores/configuracion', 301);
    Route::redirect('/submodulos-nivel3/503', '/atadores/catalogos', 301);
    // Redirects para submódulos de Engomado
    Route::redirect('/submodulos-nivel3/404', '/engomado/configuracion', 301);

    // Redirects para módulo producción urdido (compatibilidad con URLs antiguas)
    Route::get('/modulo-produccion-urdido', function () {
        return redirect('/urdido/modulo-produccion-urdido', 301);
    });
    Route::get('/modulo-producción-urdido', function () {
        return redirect('/urdido/modulo-produccion-urdido', 301);
    });

    // Rutas específicas con nombres descriptivos (reemplazan submodulos-nivel3/{id})
    // Estas rutas llaman directamente al método del controlador con el ID específico
    Route::get('/tejido/marcas-finales', fn() => app(UsuarioController::class)->showSubModulosNivel3('202'))
        ->name('tejido.marcas.finales');

    Route::get('/tejido/inventario', fn() => app(UsuarioController::class)->showSubModulosNivel3('203'))
        ->name('tejido.inventario');

    Route::get('/tejido/cortes-eficiencia', fn() => app(UsuarioController::class)->showSubModulosNivel3('206'))
        ->name('tejido.cortes.eficiencia');

    Route::get('/configuracion/utileria', fn() => app(UsuarioController::class)->showSubModulosNivel3('909'))
        ->name('configuracion.utileria');

    // Rutas específicas para submódulos de Atadores
    Route::get('/atadores/configuracion', fn() => app(UsuarioController::class)->showSubModulosNivel3('502'))
        ->name('atadores.configuracion');
    Route::get('/atadores/catalogos', fn() => app(UsuarioController::class)->showSubModulosNivel3('503'))
        ->name('atadores.catalogos');

    // Configuración de Urdido
    Route::get('/urdido/configuracion', fn() => app(UsuarioController::class)->showSubModulosNivel3('304'))
        ->name('urdido.configuracion');

    // Configuración de Engomado
    Route::get('/engomado/configuracion', fn() => app(UsuarioController::class)->showSubModulosNivel3('404'))
        ->name('engomado.configuracion');

    // BPM Urdido - Redirigir al controlador
    Route::get('/urdido/bpm', [UrdBpmController::class, 'index'])->name('urdido.bpm');

    // BPM Engomado - Redirigir al controlador
    Route::get('/engomado/bpm', [EngBpmController::class, 'index'])->name('engomado.bpm');

    // Captura de Fórmulas - Redirigir al controlador
    Route::get('/engomado/captura-formula', [EngProduccionFormulacionController::class, 'index'])->name('engomado.captura-formula');

    // Redirección alternativa para captura de fórmulas
    Route::get('/modulo-captura-de-formula', function() {
        return redirect('/engomado/captura-formula', 301);
    });

    // Redirección alternativa para módulo de codificación
    Route::get('/modulo-codificación', function() {
        return redirect('/planeacion/codificacion', 301);
    });

    // Actividades BPM Urdido - Redirigir al CRUD
    Route::get('/urdido/configuracion/actividades-bpm', function() {
        return redirect()->route('urd-actividades-bpm.index');
    })->name('urdido.configuracion.actividades-bpm');

    // Actividades BPM Engomado - Redirigir al CRUD
    Route::get('/engomado/configuracion/actividades-bpm', function() {
        return redirect()->route('eng-actividades-bpm.index');
    })->name('engomado.configuracion.actividades-bpm');

    // Catálogo de Núcleos Engomado - Redirigir al CRUD
    Route::get('/engomado/configuracion/catalogos-nucleos', function() {
        return redirect()->route('urd-eng-nucleos.index');
    })->name('engomado.configuracion.catalogos-nucleos');

    // Ruta genérica para compatibilidad (solo para otros IDs no especificados arriba)
    Route::get('/submodulos-nivel3/{moduloPadre}', [UsuarioController::class, 'showSubModulosNivel3'])->name('submodulos.nivel3');

    // API para precarga de submódulos (AJAX)
    Route::get('/api/submodulos/{moduloPrincipal}', [UsuarioController::class, 'getSubModulosAPI'])->name('api.submodulos');

    // ============================================
    // MÓDULO PLANEACIÓN (100)
    // ============================================
    Route::prefix('planeacion')->name('planeacion.')->group(function () {
        // Catálogos con estructura jerárquica
        Route::prefix('catalogos')->name('catalogos.')->group(function () {
            Route::get('/', fn() => app(UsuarioController::class)->showSubModulosNivel3('104'))->name('index');
            // Route::get('/req-programa-tejido', [ExcelImportacionesController::class, 'showReqProgramaTejido'])->name('req-programa-tejido');
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
            // Rutas para Codificación de Modelos (orden específico primero)
            Route::get('/codificacion-modelos', [CodificacionController::class, 'index'])->name('codificacion-modelos');
            Route::get('/codificacion-modelos/create', [CodificacionController::class, 'create'])->name('codificacion.create');
            Route::get('/codificacion-modelos/get-all', [CodificacionController::class, 'getAll'])->name('codificacion.get-all');
            Route::get('/codificacion-modelos/api/all-fast', [CodificacionController::class, 'getAllFast'])->name('codificacion.all-fast');
            Route::get('/codificacion-modelos/estadisticas', [CodificacionController::class, 'estadisticas'])->name('codificacion.estadisticas');
            Route::get('/codificacion-modelos/salones-telares', [CodificacionController::class, 'getSalonesYTelares'])->name('codificacion.salones-telares');
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

        // Ruta directa para catálogo de codificaciónddf
        Route::get('/codificacion', [CatCodificacionController::class, 'index'])->name('codificacion.index');
        Route::get('/codificacion/api/all-fast', [CatCodificacionController::class, 'getAllFast'])->name('codificacion.all-fast');
        Route::post('/codificacion/excel', [CatCodificacionController::class, 'procesarExcel'])->name('codificacion.excel');
        Route::get('/codificacion/excel-progress/{id}', [CatCodificacionController::class, 'importProgress'])->name('codificacion.excel.progress');
        Route::get('/codificacion/orden-cambio-pdf', [OrdenDeCambioFelpaController::class, 'generarPDF'])->name('codificacion.orden-cambio-pdf');
        Route::get('/codificacion/orden-cambio-excel', [OrdenDeCambioFelpaController::class, 'generarExcel'])->name('codificacion.orden-cambio-excel');

        // Rutas directas para compatibilidad
        Route::get('/telares', [CatalagoTelarController::class, 'index'])->name('telares.index');
        Route::get('/telares/falla', [CatalagoTelarController::class, 'falla'])->name('telares.falla');
        Route::get('/eficiencia', [CatalagoEficienciaController::class, 'index'])->name('eficiencia.index');
        Route::get('/velocidad', [CatalagoVelocidadController::class, 'index'])->name('velocidad.index');
        Route::get('/calendarios', [CalendarioController::class, 'index'])->name('calendarios.index');
        Route::get('/aplicaciones', [AplicacionesController::class, 'index'])->name('aplicaciones.index');

        // Rutas CRUD para telares
        Route::post('/telares', [CatalagoTelarController::class, 'store'])->name('telares.store');
        Route::put('/telares/{telar}', [CatalagoTelarController::class, 'update'])->name('telares.update');
        Route::delete('/telares/{telar}', [CatalagoTelarController::class, 'destroy'])->name('telares.destroy');

        // Rutas para procesar Excel de catálogos
        Route::post('/telares/excel', [CatalagoTelarController::class, 'procesarExcel'])->name('telares.excel.upload');
        Route::post('/eficiencia/excel', [CatalagoEficienciaController::class, 'procesarExcel'])->name('eficiencia.excel.upload');
        Route::post('/velocidad/excel', [CatalagoVelocidadController::class, 'procesarExcel'])->name('velocidad.excel.upload');
        Route::post('/calendarios/excel', [CalendarioController::class, 'procesarExcel'])->name('calendarios.excel.upload');

        // Rutas CRUD para calendarios
        Route::get('/calendarios/json', [CalendarioController::class, 'getCalendariosJson'])->name('calendarios.json');
        Route::get('/calendarios/{calendario}/detalle', [CalendarioController::class, 'getCalendarioDetalle'])->name('calendarios.detalle');
        Route::post('/calendarios', [CalendarioController::class, 'store'])->name('calendarios.store');
        Route::put('/calendarios/{calendario}', [CalendarioController::class, 'update'])->name('calendarios.update');
        Route::put('/calendarios/{calendario}/masivo', [CalendarioController::class, 'updateMasivo'])->name('calendarios.update.masivo');
        Route::delete('/calendarios/{calendario}', [CalendarioController::class, 'destroy'])->name('calendarios.destroy');

        // Rutas CRUD para líneas de calendario
        Route::post('/calendarios/lineas', [CalendarioController::class, 'storeLine'])->name('calendarios.lineas.store');
        Route::put('/calendarios/lineas/{linea}', [CalendarioController::class, 'updateLine'])->name('calendarios.lineas.update');
        Route::delete('/calendarios/lineas/{linea}', [CalendarioController::class, 'destroyLine'])->name('calendarios.lineas.destroy');
        Route::delete('/calendarios/{calendario}/lineas/rango', [CalendarioController::class, 'destroyLineasPorRango'])->name('calendarios.lineas.destroy.rango');

        // Ruta para recalcular programas por calendario
        Route::post('/calendarios/{calendario}/recalcular-programas', [CalendarioController::class, 'recalcularProgramas'])->name('calendarios.recalcular.programas');

        Route::post('/aplicaciones/excel', [AplicacionesController::class, 'procesarExcel'])->name('aplicaciones.excel.upload');

        // Rutas CRUD para eficiencia
        Route::post('/eficiencia', [CatalagoEficienciaController::class, 'store'])->name('eficiencia.store');
        Route::put('/eficiencia/{eficiencia}', [CatalagoEficienciaController::class, 'update'])->name('eficiencia.update');
        Route::delete('/eficiencia/{eficiencia}', [CatalagoEficienciaController::class, 'destroy'])->name('eficiencia.destroy');

        // Rutas CRUD para velocidad
        Route::post('/velocidad', [CatalagoVelocidadController::class, 'store'])->name('velocidad.store');
        Route::put('/velocidad/{velocidad}', [CatalagoVelocidadController::class, 'update'])->name('velocidad.update');
        Route::delete('/velocidad/{velocidad}', [CatalagoVelocidadController::class, 'destroy'])->name('velocidad.destroy');

        // Rutas CRUD para aplicaciones
        Route::post('/aplicaciones', [AplicacionesController::class, 'store'])->name('aplicaciones.store');
        Route::put('/aplicaciones/{aplicacion}', [AplicacionesController::class, 'update'])->name('aplicaciones.update');
        Route::delete('/aplicaciones/{aplicacion}', [AplicacionesController::class, 'destroy'])->name('aplicaciones.destroy');

        // Rutas CRUD para matriz de hilos
        Route::get('/catalogos/matriz-hilos/list', [MatrizHilosController::class, 'list'])->name('matriz-hilos.list');
        Route::post('/catalogos/matriz-hilos', [MatrizHilosController::class, 'store'])->name('matriz-hilos.store');
        Route::get('/catalogos/matriz-hilos/{id}', [MatrizHilosController::class, 'show'])->name('matriz-hilos.show');
        Route::put('/catalogos/matriz-hilos/{id}', [MatrizHilosController::class, 'update'])->name('matriz-hilos.update');
        Route::delete('/catalogos/matriz-hilos/{id}', [MatrizHilosController::class, 'destroy'])->name('matriz-hilos.destroy');
    });

    // ============================================
    // MÓDULO TEJIDO (200)
    // ============================================
    Route::prefix('tejido')->name('tejido.')->group(function () {
        // Configurar - Muestra submódulos nieto (nivel 3) con Dependencia = 205
        Route::get('/configurar', function() {
            return app(UsuarioController::class)->showSubModulosConfiguracion('205');
        })->name('configurar');

        // Producción » Reenconado Cabezuela (alias dentro de /tejido)
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

        // Secuencia Inv Telas
        Route::get('/secuencia-inv-telas', [SecuenciaInvTelasController::class, 'index'])->name('secuencia-inv-telas.index');
        Route::post('/secuencia-inv-telas', [SecuenciaInvTelasController::class, 'store'])->name('secuencia-inv-telas.store');
        Route::put('/secuencia-inv-telas/{id}', [SecuenciaInvTelasController::class, 'update'])->name('secuencia-inv-telas.update');
        Route::delete('/secuencia-inv-telas/{id}', [SecuenciaInvTelasController::class, 'destroy'])->name('secuencia-inv-telas.destroy');

        // Secuencia Inv Trama
        Route::get('/secuencia-inv-trama', [SecuenciaInvTramaController::class, 'index'])->name('secuencia-inv-trama.index');
        Route::post('/secuencia-inv-trama', [SecuenciaInvTramaController::class, 'store'])->name('secuencia-inv-trama.store');
        Route::put('/secuencia-inv-trama/{id}', [SecuenciaInvTramaController::class, 'update'])->name('secuencia-inv-trama.update');
        Route::delete('/secuencia-inv-trama/{id}', [SecuenciaInvTramaController::class, 'destroy'])->name('secuencia-inv-trama.destroy');

        // Inventario de Telas
        Route::get('/inventario-telas', function () {
        return view('modulos/tejido/inventario-telas');
        })->name('inventario.telas');

        // Inventario específico por tipo de telar
        Route::get('/inventario-telas/jacquard', [TelaresController::class, 'inventarioJacquard'])->name('inventario.jacquard');
        Route::get('/inventario-telas/itema', [TelaresController::class, 'inventarioItema'])->name('inventario.itema');

        // Trama - Nuevo y Consultar Requerimientos
        Route::get('/inventario/trama/nuevo-requerimiento', [NuevoRequerimientoController::class, 'index'])->name('inventario.trama.nuevo.requerimiento');
        Route::post('/inventario/trama/nuevo-requerimiento', [NuevoRequerimientoController::class, 'guardarRequerimientos'])->name('inventario.trama.nuevo.requerimiento.store');
        Route::get('/inventario/trama/nuevo-requerimiento/turno-info', [NuevoRequerimientoController::class, 'getTurnoInfo'])->name('inventario.trama.nuevo.requerimiento.turno.info');
        Route::get('/inventario/trama/consultar-requerimiento', [ConsultarRequerimientoController::class, 'index'])->name('inventario.trama.consultar.requerimiento');
        Route::get('/inventario/trama/consultar-requerimiento/{folio}/resumen', [ConsultarRequerimientoController::class, 'resumen'])->name('inventario.trama.consultar.requerimiento.resumen');
        Route::get('/inventario/trama/nuevo-requerimiento/en-proceso', [NuevoRequerimientoController::class, 'enProcesoInfo'])->name('inventario.trama.nuevo.requerimiento.enproceso');
        Route::post('/inventario/trama/nuevo-requerimiento/actualizar-cantidad', [NuevoRequerimientoController::class, 'actualizarCantidad'])->name('inventario.trama.nuevo.requerimiento.actualizar.cantidad');
    });

    // Actividades BPM
    Route::resource('tel-actividades-bpm', TelActividadesBPMController::class)
        ->parameters(['tel-actividades-bpm' => 'telActividadesBPM'])
        ->names('tel-actividades-bpm');

    // Actividades BPM Urdido
    Route::resource('urd-actividades-bpm', UrdActividadesBpmController::class)
        ->parameters(['urd-actividades-bpm' => 'urdActividadesBpm'])
        ->names('urd-actividades-bpm');

    // Actividades BPM Engomado
    Route::resource('eng-actividades-bpm', EngActividadesBpmController::class)
        ->parameters(['eng-actividades-bpm' => 'engActividadesBpm'])
        ->names('eng-actividades-bpm');

    // Catálogo de Núcleos Urd/Eng
    Route::resource('urd-eng-nucleos', UrdEngNucleosController::class)
        ->parameters(['urd-eng-nucleos' => 'urdEngNucleo'])
        ->names('urd-eng-nucleos');

    // BPM Urdido
    Route::resource('urd-bpm', UrdBpmController::class)
        ->parameters(['urd-bpm' => 'id'])
        ->names('urd-bpm');

    // BPM Urdido - Líneas (checklist)
    Route::get('urd-bpm-line/{folio}', [UrdBpmLineController::class, 'index'])->name('urd-bpm-line.index');
    Route::post('urd-bpm-line/{folio}/toggle', [UrdBpmLineController::class, 'toggleActividad'])->name('urd-bpm-line.toggle');
    Route::patch('urd-bpm-line/{folio}/terminar', [UrdBpmLineController::class, 'terminar'])->name('urd-bpm-line.terminar');
    Route::patch('urd-bpm-line/{folio}/autorizar', [UrdBpmLineController::class, 'autorizar'])->name('urd-bpm-line.autorizar');
    Route::patch('urd-bpm-line/{folio}/rechazar', [UrdBpmLineController::class, 'rechazar'])->name('urd-bpm-line.rechazar');

    // BPM Engomado
    Route::resource('eng-bpm', EngBpmController::class)
        ->parameters(['eng-bpm' => 'id'])
        ->names('eng-bpm');

    // BPM Engomado - Líneas (checklist)
    Route::get('eng-bpm-line/{folio}', [EngBpmLineController::class, 'index'])->name('eng-bpm-line.index');
    Route::post('eng-bpm-line/{folio}/toggle', [EngBpmLineController::class, 'toggleActividad'])->name('eng-bpm-line.toggle');
    Route::patch('eng-bpm-line/{folio}/terminar', [EngBpmLineController::class, 'terminar'])->name('eng-bpm-line.terminar');
    Route::patch('eng-bpm-line/{folio}/autorizar', [EngBpmLineController::class, 'autorizar'])->name('eng-bpm-line.autorizar');
    Route::patch('eng-bpm-line/{folio}/rechazar', [EngBpmLineController::class, 'rechazar'])->name('eng-bpm-line.rechazar');

    // Captura de Fórmulas Engomado
    Route::resource('eng-formulacion', EngProduccionFormulacionController::class)
        ->parameters(['eng-formulacion' => 'folio'])
        ->names('eng-formulacion');
    Route::get('eng-formulacion/validar-folio', [EngProduccionFormulacionController::class, 'validarFolio'])->name('eng-formulacion.validar-folio');
    Route::get('eng-formulacion/componentes/formula', [EngProduccionFormulacionController::class, 'getComponentesFormula'])->name('eng-formulacion.componentes');

        Route::resource('tel-bpm', TelBpmController::class)
    ->parameters(['tel-bpm' => 'folio'])   // PK string
    ->names('tel-bpm');                    // tel-bpm.index, tel-bpm.store, etc.

Route::patch('tel-bpm/{folio}/terminar',  [TelBpmLineController::class, 'finish'])->name('tel-bpm.finish');
Route::patch('tel-bpm/{folio}/autorizar', [TelBpmLineController::class, 'authorizeDoc'])->name('tel-bpm.authorize');
Route::patch('tel-bpm/{folio}/rechazar',  [TelBpmLineController::class, 'reject'])->name('tel-bpm.reject');

Route::get ('tel-bpm/{folio}/lineas',           [TelBpmLineController::class, 'index'])->name('tel-bpm-line.index');
Route::post('tel-bpm/{folio}/lineas/toggle',    [TelBpmLineController::class, 'toggle'])->name('tel-bpm-line.toggle');
Route::post('tel-bpm/{folio}/lineas/bulk-save', [TelBpmLineController::class, 'bulkSave'])->name('tel-bpm-line.bulk');
Route::post('tel-bpm/{folio}/lineas/comentarios', [TelBpmLineController::class, 'updateComentarios'])->name('tel-bpm-line.comentarios');

    // Telares por Operador
    Route::resource('tel-telares-operador', TelTelaresOperadorController::class)
        ->parameters(['tel-telares-operador' => 'telTelaresOperador'])
        ->names('tel-telares-operador');

    // Alias solicitado para acceso directo
    Route::get('/telaresPorOperador', [TelTelaresOperadorController::class, 'index'])->name('telaresPorOperador');
    Route::get('/ActividadesBPM', [TelActividadesBPMController::class, 'index'])->name('ActividadesBPM');

    // ============================================
    // MÓDULO PRODUCCIÓN URD ENGOMADO
    // ============================================
    Route::prefix('programa-urd-eng')->name('programa.urd.eng.')->group(function () {
        Route::get('/reservar-programar', [ReservarProgramarController::class, 'index'])->name('reservar.programar');
        Route::get('/programacion-requerimientos', [ReservarProgramarController::class, 'programacionRequerimientos'])->name('programacion.requerimientos');
        Route::get('/creacion-ordenes', [ReservarProgramarController::class, 'creacionOrdenes'])->name('creacion.ordenes');
        Route::post('/programacion-requerimientos/resumen-semanas', [ReservarProgramarController::class, 'getResumenSemanas'])->name('programacion.resumen.semanas');
        Route::get('/inventario-telares', [ReservarProgramarController::class, 'getInventarioTelares'])->name('inventario.telares');
        Route::get('/inventario-disponible', [InvTelasReservadasController::class, 'disponible'])->name('inventario.disponible.get');
        Route::post('/inventario-disponible', [InvTelasReservadasController::class, 'disponible'])->name('inventario.disponible');
        Route::post('/programar-telar', [ReservarProgramarController::class, 'programarTelar'])->name('programar.telar');
        Route::post('/actualizar-telar', [ReservarProgramarController::class, 'actualizarTelar'])->name('actualizar.telar');
        Route::post('/reservar-inventario', [InvTelasReservadasController::class, 'reservar'])->name('reservar.inventario');
        Route::post('/liberar-telar', [ReservarProgramarController::class, 'liberarTelar'])->name('liberar.telar');
        Route::get('/column-options', [ReservarProgramarController::class, 'getColumnOptions'])->name('column.options');
        Route::get('/reservas/{noTelar}', [InvTelasReservadasController::class, 'porTelar'])->name('reservas.porTelar');
        Route::post('/reservas/cancelar', [InvTelasReservadasController::class, 'cancelar'])->name('reservas.cancelar');
        Route::get('/reservas/diagnostico', [InvTelasReservadasController::class, 'diagnosticarReservas'])->name('reservas.diagnostico');
        Route::get('/buscar-bom-urdido', [ReservarProgramarController::class, 'buscarBomUrdido'])->name('buscar.bom.urdido');
        Route::get('/buscar-bom-engomado', [ReservarProgramarController::class, 'buscarBomEngomado'])->name('buscar.bom.engomado');
        Route::get('/materiales-urdido', [ReservarProgramarController::class, 'getMaterialesUrdido'])->name('materiales.urdido');
        Route::get('/materiales-engomado', [ReservarProgramarController::class, 'getMaterialesEngomado'])->name('materiales.engomado');
        Route::get('/anchos-balona', [ReservarProgramarController::class, 'getAnchosBalona'])->name('anchos.balona');
        Route::get('/maquinas-engomado', [ReservarProgramarController::class, 'getMaquinasEngomado'])->name('maquinas.engomado');
        Route::post('/crear-ordenes', [ProgramarUrdEngController::class, 'crearOrdenes'])->name('crear.ordenes');
    });

    // ============================================
    // MÓDULO CONFIGURACIÓN
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
            Route::post('/{id}/permisos', [UsuarioController::class, 'updatePermiso'])->name('permisos.update');
        });

        // Utilería
        Route::prefix('utileria')->name('utileria.')->group(function () {
            Route::get('/', function() {
                return app(UsuarioController::class)->showSubModulosNivel3('909');
            })->name('index');

            Route::prefix('modulos')->name('modulos.')->controller(ModulosController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/create', 'create')->name('create');
                Route::post('/', 'store')->name('store');
                Route::get('/{id}/edit', 'edit')->whereNumber('id')->name('edit');
                Route::put('/{id}', 'update')->whereNumber('id')->name('update');
                Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy');
                Route::post('/{id}/toggle-acceso', 'toggleAcceso')->whereNumber('id')->name('toggle.acceso');
                Route::post('/{id}/toggle-permiso', 'togglePermiso')->whereNumber('id')->name('toggle.permiso');
                Route::post('/{id}/sincronizar-permisos', 'sincronizarPermisos')->whereNumber('id')->name('sincronizar.permisos');
                Route::get('/{modulo}/duplicar', 'duplicar')->whereNumber('modulo')->name('duplicar');
            });

            Route::get('/api/modulos/nivel/{nivel}', [ModulosController::class, 'getModulosPorNivel'])
                ->whereNumber('nivel')->name('api.modulos.nivel');
            Route::get('/api/modulos/submodulos/{dependencia}', [ModulosController::class, 'getSubmodulos'])
                ->whereNumber('dependencia')->name('api.modulos.submodulos');
        });

        Route::get('/cargar-planeacion', [ConfiguracionController::class, 'cargarPlaneacion'])->name('cargar.planeacion');
        Route::post('/cargar-planeacion/upload', [ConfiguracionController::class, 'procesarExcel'])->name('cargar.planeacion.upload');
    });

    // ============================================
    // MÓDULO TEJEDORES (600)
    // ============================================
    Route::prefix('tejedores')->name('tejedores.')->group(function () {
        // Configurar - Muestra submódulos nieto (nivel 3) con Dependencia = 605 (o el orden que corresponda)
        Route::get('/configurar', function() {
            // Buscar dinámicamente el orden del módulo "Configurar" que depende de Tejedores (600)
            $configurarModulo = SYSRoles::where('modulo', 'Configurar')
                ->where('Dependencia', 600)
                ->where('Nivel', 2)
                ->first();

            if ($configurarModulo) {
                return app(UsuarioController::class)->showSubModulosConfiguracion($configurarModulo->orden);
            }

            // Fallback: intentar con 605 si no se encuentra
            return app(UsuarioController::class)->showSubModulosConfiguracion('605');
        })->name('configurar');

        // BPM Tejedores · redirige al listado principal del recurso
        Route::get('/bpm', function () {
            return redirect()->route('tel-bpm.index');
        })->name('bpm');

        // Desarrolladores
        Route::get('/desarrolladores', [TelDesarrolladoresController::class, 'index'])->name('desarrolladores');
    });

    // Notificar Montado de Julios (fuera del grupo para acceso desde módulos)
    Route::get('/tejedores/notificar-montado-julios', [NotificarMontadoJulioController::class, 'index'])->name('notificar.montado.julios');
    Route::get('/tejedores/notificar-montado-julios/telares', [NotificarMontadoJulioController::class, 'telares'])->name('notificar.montado.julios.telares');
    Route::get('/tejedores/notificar-montado-julios/detalle', [NotificarMontadoJulioController::class, 'detalle'])->name('notificar.montado.julios.detalle');
    Route::post('/tejedores/notificar-montado-julios/notificar', [NotificarMontadoJulioController::class, 'notificar'])->name('notificar.montado.julios.notificar');

    // Notificar Montado de Rollos
    Route::get('/tejedores/notificar-mont-rollos', [NotificarMontRollosController::class, 'index'])->name('notificar.mont.rollos');
    Route::post('/tejedores/notificar-mont-rollos/notificar', [NotificarMontRollosController::class, 'notificar'])->name('notificar.mont.rollos.notificar');
    Route::get('/tejedores/notificar-mont-rollos/orden-produccion', [NotificarMontRollosController::class, 'getOrdenProduccion'])->name('notificar.mont.rollos.orden.produccion');
    Route::get('/tejedores/notificar-mont-rollos/datos-produccion', [NotificarMontRollosController::class, 'getDatosProduccion'])->name('notificar.mont.rollos.datos.produccion');
    Route::post('/tejedores/notificar-mont-rollos/insertar', [NotificarMontRollosController::class, 'insertarMarbetes'])->name('notificar.mont.rollos.insertar');

    // ============================================
    // RUTAS DIRECTAS (COMPATIBILIDAD)
    // ============================================

    // Rutas directas de catálogos
    Route::get('/planeacion/programa-tejido', [ProgramaTejidoController::class, 'index'])->name('catalogos.req-programa-tejido');

    Route::get('/planeacion/programa-tejido/altas-especiales', [ComprasEspecialesController::class, 'index'])->name('programa-tejido.altas-especiales');
    // Altas especiales

    // Alta de pronósticos
    Route::get('/planeacion/programa-tejido/alta-pronosticos', [PronosticosController::class, 'index'])->name('programa-tejido.alta-pronosticos');
    Route::post('/pronosticos/sincronizar', [PronosticosController::class, 'sincronizar'])->name('pronosticos.sincronizar');
    Route::get('/pronosticos', [PronosticosController::class, 'get'])->name('pronosticos.get');

// Liberar órdenes
Route::get('/planeacion/programa-tejido/liberar-ordenes', [LiberarOrdenesController::class, 'index'])->name('programa-tejido.liberar-ordenes');
Route::post('/planeacion/programa-tejido/liberar-ordenes/procesar', [LiberarOrdenesController::class, 'liberar'])->name('programa-tejido.liberar-ordenes.procesar');
Route::get('/planeacion/programa-tejido/liberar-ordenes/bom-sugerencias', [LiberarOrdenesController::class, 'obtenerBomYNombre'])->name('programa-tejido.liberar-ordenes.bom');
Route::get('/planeacion/programa-tejido/liberar-ordenes/tipo-hilo', [LiberarOrdenesController::class, 'obtenerTipoHilo'])->name('programa-tejido.liberar-ordenes.tipo-hilo');
Route::get('/planeacion/programa-tejido/liberar-ordenes/codigo-dibujo', [LiberarOrdenesController::class, 'obtenerCodigoDibujo'])->name('programa-tejido.liberar-ordenes.codigo-dibujo');
Route::get('/planeacion/programa-tejido/liberar-ordenes/opciones-hilos', [LiberarOrdenesController::class, 'obtenerOpcionesHilos'])->name('programa-tejido.liberar-ordenes.opciones-hilos');
Route::post('/planeacion/programa-tejido/liberar-ordenes/guardar-campos', [LiberarOrdenesController::class, 'guardarCamposEditables'])->name('programa-tejido.liberar-ordenes.guardar-campos');

// Ruta para reimpresión de órdenes (recibe ID de CatCodificados)
Route::get('/planeacion/programa-tejido/reimprimir-ordenes/{id}', [ReimprimirOrdenesController::class, 'reimprimir'])->name('planeacion.programa-tejido.reimprimir-ordenes');

// Descargar programa
Route::post('/planeacion/programa-tejido/descargar-programa', [DescargarProgramaController::class, 'descargar'])->name('programa-tejido.descargar-programa');

        // Nueva ruta para crear/editar programa de tejido
        Route::get('/planeacion/programa-tejido/nuevo', function() {
        return view('modulos.programa-tejido.programatejidoform.create');
    })->name('programa-tejido.nuevo');
        Route::get('/planeacion/programa-tejido/altas-especiales/nuevo', [ComprasEspecialesController::class, 'nuevo'])->name('programa-tejido.altas-especiales.nuevo');
        Route::get('/planeacion/programa-tejido/pronosticos/nuevo', [PronosticosController::class, 'nuevo'])->name('programa-tejido.pronosticos.nuevo');
        Route::get('/planeacion/buscar-detalle-modelo', [ComprasEspecialesController::class, 'buscarDetalleModelo'])->name('planeacion.buscar-detalle-modelo');
        Route::get('/planeacion/buscar-modelos-sugerencias', [ComprasEspecialesController::class, 'buscarModelosSugerencias'])->name('planeacion.buscar-modelos-sugerencias');
        Route::post('/planeacion/programa-tejido', [ProgramaTejidoController::class, 'store'])->name('programa-tejido.store');
        Route::get('/planeacion/programa-tejido/{id}/editar', [ProgramaTejidoController::class, 'edit'])->name('programa-tejido.edit');
    Route::put('/planeacion/programa-tejido/{id}', [ProgramaTejidoController::class, 'update'])->name('programa-tejido.update');
    Route::post('/planeacion/programa-tejido/{id}/prioridad/mover', [ProgramaTejidoController::class, 'moveToPosition'])->name('programa-tejido.prioridad.mover');
    Route::post('/planeacion/programa-tejido/{id}/verificar-cambio-telar', [ProgramaTejidoController::class, 'verificarCambioTelar'])->name('programa-tejido.verificar-cambio-telar');
    Route::post('/planeacion/programa-tejido/{id}/cambiar-telar', [ProgramaTejidoController::class, 'cambiarTelar'])->name('programa-tejido.cambiar-telar');
    Route::post('/planeacion/programa-tejido/duplicar-telar', [ProgramaTejidoController::class, 'duplicarTelar'])->name('programa-tejido.duplicar-telar');
    Route::post('/planeacion/programa-tejido/dividir-telar', [ProgramaTejidoController::class, 'dividirTelar'])->name('programa-tejido.dividir-telar');
    Route::post('/planeacion/programa-tejido/dividir-saldo', [ProgramaTejidoController::class, 'dividirSaldo'])->name('programa-tejido.dividir-saldo');
    Route::post('/planeacion/programa-tejido/vincular-telar', [ProgramaTejidoController::class, 'vincularTelar'])->name('programa-tejido.vincular-telar');
    Route::post('/planeacion/programa-tejido/vincular-registros-existentes', [ProgramaTejidoController::class, 'vincularRegistrosExistentes'])->name('programa-tejido.vincular-registros-existentes');
    Route::get('/planeacion/programa-tejido/registros-ord-compartida/{ordCompartida}', [ProgramaTejidoController::class, 'getRegistrosPorOrdCompartida'])->name('programa-tejido.registros-ord-compartida');
    Route::get('/planeacion/programa-tejido/balancear', [ProgramaTejidoController::class, 'balancear'])->name('programa-tejido.balancear');
    Route::get('/planeacion/programa-tejido/{id}/detalles-balanceo', [ProgramaTejidoController::class, 'detallesBalanceo'])->name('programa-tejido.detalles-balanceo');
    Route::post('/planeacion/programa-tejido/preview-fechas-balanceo', [ProgramaTejidoController::class, 'previewFechasBalanceo'])->name('programa-tejido.preview-fechas-balanceo');
    Route::post('/planeacion/programa-tejido/actualizar-pedidos-balanceo', [ProgramaTejidoController::class, 'actualizarPedidosBalanceo'])->name('programa-tejido.actualizar-pedidos-balanceo');
    Route::post('/planeacion/programa-tejido/balancear-automatico', [ProgramaTejidoController::class, 'balancearAutomatico'])->name('programa-tejido.balancear-automatico');
    Route::get('/planeacion/programa-tejido/ver-detalles-grupo-balanceo/{ordCompartida}', [BalancearTejido::class, 'verDetallesGrupoBalanceo'])->name('verdetallesgrupobalanceo');
    Route::delete('/planeacion/programa-tejido/{id}', [ProgramaTejidoController::class, 'destroy'])->name('programa-tejido.destroy');
    Route::get('/planeacion/programa-tejido/all-registros-json', [ProgramaTejidoController::class, 'getAllRegistrosJson'])->name('programa-tejido.all-registros-json');
    Route::post('/planeacion/programa-tejido/actualizar-calendarios-masivo', [ProgramaTejidoController::class, 'actualizarCalendariosMasivo'])->name('programa-tejido.actualizar-calendarios-masivo');
    Route::post('/planeacion/programa-tejido/{id}/reprogramar', [ProgramaTejidoController::class, 'actualizarReprogramar'])->name('programa-tejido.reprogramar');
        // JSON: ReqProgramaTejidoLine dentro de planeación
        Route::get('/planeacion/req-programa-tejido-line', [ReqProgramaTejidoLineController::class, 'index']);

    // Rutas API para los selects del programa de tejido
    Route::get('/programa-tejido/salon-options', [ProgramaTejidoController::class, 'getSalonTejidoOptions']);
    Route::get('/programa-tejido/salon-tejido-options', [ProgramaTejidoController::class, 'getSalonTejidoOptions'])->name('programa-tejido.salon-tejido-options');
    Route::get('/programa-tejido/tamano-clave-by-salon', [ProgramaTejidoController::class, 'getTamanoClaveBySalon']);
    Route::get('/programa-tejido/flogs-id-options', [ProgramaTejidoController::class, 'getFlogsIdOptions']);
    Route::get('/programa-tejido/flogs-id-from-twflogs', [ProgramaTejidoController::class, 'getFlogsIdFromTwFlogsTable']);
    Route::get('/programa-tejido/descripcion-by-idflog/{idflog}', [ProgramaTejidoController::class, 'getDescripcionByIdFlog']);
    Route::get('/programa-tejido/calendario-id-options', [ProgramaTejidoController::class, 'getCalendarioIdOptions']);
    Route::get('/programa-tejido/calendario-lineas/{calendarioId}', [ProgramaTejidoController::class, 'getCalendarioLineas'])->name('programa-tejido.calendario-lineas');
    Route::get('/programa-tejido/aplicacion-id-options', [ProgramaTejidoController::class, 'getAplicacionIdOptions']);
    Route::match(['get','post'],'/programa-tejido/datos-relacionados', [ProgramaTejidoController::class, 'getDatosRelacionados']);
Route::get('/programa-tejido/telares-by-salon', [ProgramaTejidoController::class, 'getTelaresBySalon']);
Route::get('/programa-tejido/ultima-fecha-final-telar', [ProgramaTejidoController::class, 'getUltimaFechaFinalTelar']);
Route::get('/programa-tejido/hilos-options', [ProgramaTejidoController::class, 'getHilosOptions']);
Route::get('/programa-tejido/eficiencia-std', [ProgramaTejidoController::class, 'getEficienciaStd']);
Route::get('/programa-tejido/velocidad-std', [ProgramaTejidoController::class, 'getVelocidadStd']);
Route::post('/programa-tejido/calcular-totales-dividir', function (Request $request) {
    return DividirTejido::calcularTotalesDividir($request);
});
// Estado de columnas (ocultar/mostrar) por usuario
Route::get('/programa-tejido/columnas', [ColumnasProgramaTejidoController::class, 'index']);
Route::post('/programa-tejido/columnas', [ColumnasProgramaTejidoController::class, 'store']);

// ============================================
// RUTAS SIMULACIÓN
// ============================================
Route::prefix('simulacion')->name('simulacion.')->group(function () {
    // Vista principal de simulación (req-programa-tejido)
    Route::get('/', [SimulacionProgramaTejidoController::class, 'index'])->name('index');

    // Alta de pronósticos de simulación
    Route::get('/alta-pronosticos', function() {
        $mesActual = now()->format('Y-m');
        $meses = request()->has('meses') ? request()->get('meses') : [$mesActual];
        return view('modulos.simulacion.alta-pronosticos', compact('mesActual', 'meses'));
    })->name('alta-pronosticos');

    // Altas especiales de simulación
    Route::get('/altas-especiales', [SimulacionComprasEspecialesController::class, 'index'])->name('altas-especiales');

    // Rutas para crear nuevo
    Route::get('/nuevo', function() {
        return view('modulos.simulacion.simulacionform.create');
    })->name('nuevo');

    Route::get('/pronosticos/nuevo', function() {
        return view('modulos.simulacion.simulacionform.pronosticos');
    })->name('pronosticos.nuevo');

    Route::get('/altas-especiales/nuevo', [SimulacionComprasEspecialesController::class, 'nuevo'])->name('altas-especiales.nuevo');

    // Rutas para catálogos y helpers (deben ir ANTES de las rutas con {id})
    Route::get('/salon-tejido-options', [SimulacionProgramaTejidoController::class, 'getSalonTejidoOptions'])->name('salon-tejido-options');
    Route::get('/tamano-clave-options', [SimulacionProgramaTejidoController::class, 'getTamanoClaveOptions'])->name('tamano-clave-options');
    Route::get('/tamano-clave-by-salon', [SimulacionProgramaTejidoController::class, 'getTamanoClaveBySalon'])->name('tamano-clave-by-salon');
    Route::get('/flogs-id-options', [SimulacionProgramaTejidoController::class, 'getFlogsIdOptions'])->name('flogs-id-options');
    Route::get('/flogs-id-from-twflogs-table', [SimulacionProgramaTejidoController::class, 'getFlogsIdFromTwFlogsTable'])->name('flogs-id-from-twflogs-table');
    Route::get('/descripcion-by-idflog/{idflog}', [SimulacionProgramaTejidoController::class, 'getDescripcionByIdFlog'])->name('descripcion-by-idflog');
    Route::get('/calendario-id-options', [SimulacionProgramaTejidoController::class, 'getCalendarioIdOptions'])->name('calendario-id-options');
    Route::get('/aplicacion-id-options', [SimulacionProgramaTejidoController::class, 'getAplicacionIdOptions'])->name('aplicacion-id-options');
    Route::post('/datos-relacionados', [SimulacionProgramaTejidoController::class, 'getDatosRelacionados'])->name('datos-relacionados');
    Route::get('/telares-by-salon', [SimulacionProgramaTejidoController::class, 'getTelaresBySalon'])->name('telares-by-salon');
    Route::get('/ultima-fecha-final-telar', [SimulacionProgramaTejidoController::class, 'getUltimaFechaFinalTelar'])->name('ultima-fecha-final-telar');
    Route::get('/ultimo-registro-salon', [SimulacionProgramaTejidoController::class, 'getUltimoRegistroSalon'])->name('ultimo-registro-salon');
    Route::get('/hilos-options', [SimulacionProgramaTejidoController::class, 'getHilosOptions'])->name('hilos-options');
    Route::post('/calcular-fecha-fin', [SimulacionProgramaTejidoController::class, 'calcularFechaFin'])->name('calcular-fecha-fin');
    Route::get('/eficiencia-std', [SimulacionProgramaTejidoController::class, 'getEficienciaStd'])->name('eficiencia-std');
    Route::get('/velocidad-std', [SimulacionProgramaTejidoController::class, 'getVelocidadStd'])->name('velocidad-std');

    // JSON: SimulacionProgramaTejidoLine dentro de simulación
    Route::get('/req-programa-tejido-line', [SimulacionProgramaTejidoLineController::class, 'index'])->name('req-programa-tejido-line');

    // Duplicar datos de Programa de Tejido a Simulación
    Route::post('/duplicar-datos', [SimulacionProgramaTejidoController::class, 'duplicarDatos'])->name('duplicar-datos');

    // Rutas para el CRUD de SimulacionProgramaTejido (con {id} al final)
    Route::get('/{id}/json', [SimulacionProgramaTejidoController::class, 'showJson'])->name('show-json');
    Route::get('/{id}/editar', [SimulacionProgramaTejidoController::class, 'edit'])->name('edit');
    Route::post('/', [SimulacionProgramaTejidoController::class, 'store'])->name('store');
    Route::put('/{id}', [SimulacionProgramaTejidoController::class, 'update'])->name('update');
    Route::delete('/{id}', [SimulacionProgramaTejidoController::class, 'destroy'])->name('destroy');

    // Rutas para prioridad
    Route::post('/{id}/prioridad/subir', [SimulacionProgramaTejidoController::class, 'moveUp'])->name('move-up');
    Route::post('/{id}/prioridad/bajar', [SimulacionProgramaTejidoController::class, 'moveDown'])->name('move-down');
});

// Rutas para configuración - movidas dentro del grupo de middleware
    Route::get('/planeacion/eficiencia', [CatalagoEficienciaController::class, 'index'])->name('eficiencia.index');
    Route::get('/planeacion/velocidad', [CatalagoVelocidadController::class, 'index'])->name('velocidad.index');
    Route::get('/planeacion/calendarios', [CalendarioController::class, 'index'])->name('calendarios.index');
    Route::get('/planeacion/aplicaciones', [AplicacionesController::class, 'index'])->name('aplicaciones.index');

     // Rutas para Marcas (Nuevas Marcas Finales y Consultar Marcas Finales)
    Route::get('/modulo-marcas', [MarcasController::class, 'index'])->name('marcas.nuevo');
    Route::get('/modulo-marcas/consultar', [MarcasController::class, 'consultar'])->name('marcas.consultar');
    Route::post('/modulo-marcas/generar-folio', [MarcasController::class, 'generarFolio'])->name('marcas.generar.folio');
    Route::get('/modulo-marcas/obtener-datos-std', [MarcasController::class, 'obtenerDatosSTD'])->name('marcas.datos.std');
    Route::post('/modulo-marcas/store', [MarcasController::class, 'store'])->name('marcas.store');
    Route::get('/modulo-marcas/visualizar/{folio}', [MarcasController::class, 'visualizarFolio'])->name('marcas.visualizar');
    Route::get('/modulo-marcas/{folio}', [MarcasController::class, 'show'])->name('marcas.show');
    Route::put('/modulo-marcas/{folio}', [MarcasController::class, 'update'])->name('marcas.update');
    Route::post('/modulo-marcas/{folio}/finalizar', [MarcasController::class, 'finalizar'])->name('marcas.finalizar');

    // Ruta estática de reporte DEBE ir antes de la dinámica {folio}
    Route::get('/modulo-marcas/reporte', [MarcasController::class, 'reporte'])->name('marcas.reporte');
    Route::post('/modulo-marcas/reporte/exportar-excel', [MarcasController::class, 'exportarExcel'])->name('marcas.reporte.excel');
    Route::post('/modulo-marcas/reporte/descargar-pdf', [MarcasController::class, 'descargarPDF'])->name('marcas.reporte.pdf');

    // Evitar capturar rutas como 'reporte' en la dinámica {folio}
    Route::get('/modulo-marcas/{folio}', [MarcasController::class, 'show'])
        ->where('folio', '^(?!reporte$).+')
        ->name('marcas.show');
    Route::put('/modulo-marcas/{folio}', [MarcasController::class, 'update'])
        ->where('folio', '^(?!reporte$).+')
        ->name('marcas.update');
    Route::post('/modulo-marcas/{folio}/finalizar', [MarcasController::class, 'finalizar'])
        ->where('folio', '^(?!reporte$).+')
        ->name('marcas.finalizar');


    // Rutas para Cortes de Eficiencia
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
    // NOTA: Las rutas de usuarios están definidas en el grupo 'configuracion' (líneas 582-590)
    // No duplicar aquí para evitar conflictos. Usar: configuracion.usuarios.select, etc.
    Route::get('/modulo-cargar-catálogos', function () {
        return view('modulos/cargar-catalogos');
    })->name('catalogos.index');

    // API para obtener datos de telares
    Route::prefix('api/telares')->controller(TelaresController::class)->group(function () {
        Route::get('/proceso-actual/{telarId}', 'procesoActual')->whereNumber('telarId');
        Route::get('/siguiente-orden/{telarId}', 'siguienteOrden')->whereNumber('telarId');
    });

    // ============================================
    // RUTAS PARA SERVIR IMÁGENES
    // ============================================

    // Ruta para servir imágenes de usuarios
    Route::get('/storage/usuarios/{filename}', function ($filename) {
        $path = storage_path('app/public/usuarios/' . $filename);

        if (!file_exists($path)) {
            abort(404);
        }

        return response()->file($path);
    })->name('storage.usuarios');

    // ============================================
    // RUTAS LEGACY (MANTENER POR COMPATIBILIDAD)
    // ============================================

    // Módulo Urdido
    Route::prefix('urdido')->name('urdido.')->group(function () {
        Route::get('/programar-urdido', [ProgramarUrdidoController::class, 'index'])->name('programar.urdido');
        Route::get('/programar-urdido/ordenes', [ProgramarUrdidoController::class, 'getOrdenes'])->name('programar.urdido.ordenes');
        Route::get('/programar-urdido/todas-ordenes', [ProgramarUrdidoController::class, 'getTodasOrdenes'])->name('programar.urdido.todas.ordenes');
        Route::get('/programar-urdido/verificar-en-proceso', [ProgramarUrdidoController::class, 'verificarOrdenEnProceso'])->name('programar.urdido.verificar.en.proceso');
        Route::post('/programar-urdido/intercambiar-prioridad', [ProgramarUrdidoController::class, 'intercambiarPrioridad'])->name('programar.urdido.intercambiar.prioridad');
        Route::post('/programar-urdido/actualizar-prioridades', [ProgramarUrdidoController::class, 'actualizarPrioridades'])->name('programar.urdido.actualizar.prioridades');
        Route::post('/programar-urdido/guardar-observaciones', [ProgramarUrdidoController::class, 'guardarObservaciones'])->name('programar.urdido.guardar.observaciones');
        Route::post('/programar-urdido/actualizar-status', [ProgramarUrdidoController::class, 'actualizarStatus'])->name('programar.urdido.actualizar.status');
        Route::get('/reimpresion-urdido', [ProgramarUrdidoController::class, 'reimpresionFinalizadas'])->name('reimpresion.finalizadas');

        // Editar Órdenes Programadas
        Route::get('/editar-ordenes-programadas', [EditarOrdenesProgramadasController::class, 'index'])->name('editar.ordenes.programadas');
        Route::post('/editar-ordenes-programadas/actualizar', [EditarOrdenesProgramadasController::class, 'actualizar'])->name('editar.ordenes.programadas.actualizar');
        Route::get('/editar-ordenes-programadas/obtener-orden', [EditarOrdenesProgramadasController::class, 'obtenerOrden'])->name('editar.ordenes.programadas.obtener.orden');
        Route::post('/editar-ordenes-programadas/actualizar-julios', [EditarOrdenesProgramadasController::class, 'actualizarJulios'])->name('editar.ordenes.programadas.actualizar.julios');

        // Catálogos de Urdido
        Route::get('/catalogos-julios', [CatalogosUrdidoController::class, 'catalogosJulios'])->name('catalogos.julios');
        Route::post('/catalogos-julios', [CatalogosUrdidoController::class, 'storeJulio'])->name('catalogos.julios.store');
        Route::put('/catalogos-julios/{id}', [CatalogosUrdidoController::class, 'updateJulio'])->name('catalogos.julios.update');
        Route::delete('/catalogos-julios/{id}', [CatalogosUrdidoController::class, 'destroyJulio'])->name('catalogos.julios.destroy');
        Route::get('/catalogo-maquinas', [CatalogosUrdidoController::class, 'catalogoMaquinas'])->name('catalogo.maquinas');
        Route::post('/catalogo-maquinas', [CatalogosUrdidoController::class, 'storeMaquina'])->name('catalogo.maquinas.store');
        Route::put('/catalogo-maquinas/{maquinaId}', [CatalogosUrdidoController::class, 'updateMaquina'])->name('catalogo.maquinas.update');
        Route::delete('/catalogo-maquinas/{maquinaId}', [CatalogosUrdidoController::class, 'destroyMaquina'])->name('catalogo.maquinas.destroy');
        Route::get('/modulo-produccion-urdido', [ModuloProduccionUrdidoController::class, 'index'])->name('modulo.produccion.urdido');
        Route::get('/modulo-produccion-urdido/catalogos-julios', [ModuloProduccionUrdidoController::class, 'getCatalogosJulios'])->name('modulo.produccion.urdido.catalogos.julios');
        Route::get('/modulo-produccion-urdido/hilos-by-julio', [ModuloProduccionUrdidoController::class, 'getHilosByJulio'])->name('modulo.produccion.urdido.hilos.by.julio');
        Route::get('/modulo-produccion-urdido/usuarios-urdido', [ModuloProduccionUrdidoController::class, 'getUsuariosUrdido'])->name('modulo.produccion.urdido.usuarios.urdido');
        Route::post('/modulo-produccion-urdido/guardar-oficial', [ModuloProduccionUrdidoController::class, 'guardarOficial'])->name('modulo.produccion.urdido.guardar.oficial');
        Route::post('/modulo-produccion-urdido/actualizar-turno-oficial', [ModuloProduccionUrdidoController::class, 'actualizarTurnoOficial'])->name('modulo.produccion.urdido.actualizar.turno.oficial');
        Route::post('/modulo-produccion-urdido/actualizar-fecha', [ModuloProduccionUrdidoController::class, 'actualizarFecha'])->name('modulo.produccion.urdido.actualizar.fecha');
        Route::post('/modulo-produccion-urdido/actualizar-julio-tara', [ModuloProduccionUrdidoController::class, 'actualizarJulioTara'])->name('modulo.produccion.urdido.actualizar.julio.tara');
        Route::post('/modulo-produccion-urdido/actualizar-kg-bruto', [ModuloProduccionUrdidoController::class, 'actualizarKgBruto'])->name('modulo.produccion.urdido.actualizar.kg.bruto');
        Route::post('/modulo-produccion-urdido/actualizar-campos-produccion', [ModuloProduccionUrdidoController::class, 'actualizarCamposProduccion'])->name('modulo.produccion.urdido.actualizar.campos.produccion');
        Route::post('/modulo-produccion-urdido/actualizar-horas', [ModuloProduccionUrdidoController::class, 'actualizarHoras'])->name('modulo.produccion.urdido.actualizar.horas');
        Route::post('/modulo-produccion-urdido/finalizar', [ModuloProduccionUrdidoController::class, 'finalizar'])->name('modulo.produccion.urdido.finalizar');
        Route::get('/modulo-produccion-urdido/pdf', [PDFController::class, 'generarPDFUrdidoEngomado'])->name('modulo.produccion.urdido.pdf');
    });

    // Módulo Engomado
    Route::prefix('engomado')->name('engomado.')->group(function () {
        Route::get('/programar-engomado', [ProgramarEngomadoController::class, 'index'])->name('programar.engomado');
        Route::get('/reimpresion-engomado', [ProgramarEngomadoController::class, 'reimpresionFinalizadas'])->name('reimpresion.finalizadas');
        Route::get('/programar-engomado/ordenes', [ProgramarEngomadoController::class, 'getOrdenes'])->name('programar.engomado.ordenes');
        Route::get('/programar-engomado/verificar-en-proceso', [ProgramarEngomadoController::class, 'verificarOrdenEnProceso'])->name('programar.engomado.verificar.en.proceso');
        Route::post('/programar-engomado/intercambiar-prioridad', [ProgramarEngomadoController::class, 'intercambiarPrioridad'])->name('programar.engomado.intercambiar.prioridad');
        Route::post('/programar-engomado/guardar-observaciones', [ProgramarEngomadoController::class, 'guardarObservaciones'])->name('programar.engomado.guardar.observaciones');
        Route::get('/programar-engomado/todas-ordenes', [ProgramarEngomadoController::class, 'getTodasOrdenes'])->name('programar.engomado.todas.ordenes');
        Route::post('/programar-engomado/actualizar-prioridades', [ProgramarEngomadoController::class, 'actualizarPrioridades'])->name('programar.engomado.actualizar.prioridades');

        // Módulo Producción Engomado
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
        Route::get('/modulo-produccion-engomado/pdf', [PDFController::class, 'generarPDFUrdidoEngomado'])->name('modulo.produccion.engomado.pdf');
    });

    Route::get('/modulo-engomado', function () {
        return view('modulos/engomado');
    });
    Route::get('/folio-pantalla/{folio}', function ($folio) {
        return view('modulos.programar_requerimientos.FolioEnPantalla')->with('folio', $folio);
    })->name('folio.pantalla');

    // Módulo Atadores

    // Ruta principal desde produccionProceso
    Route::get('produccionProceso/atadores', [AtadoresController::class, 'index'])
        ->name('atadores.index');

    // Ruta alternativa (mantener por compatibilidad)
    Route::get('modulo-atadores', [AtadoresController::class, 'index'])
        ->name('atadores.modulo');
    Route::get('/modulo-atadores', function () {
        return view('modulos/atadores');
    });
    Route::get('/atadores/programar-requerimientos', function () {
        return view('modulos/atadores/programar-requerimientos');
    });
    Route::get('/atadores/programa', [AtadoresController::class, 'index'])->name('atadores.programa');
    Route::get('/atadores/iniciar', [AtadoresController::class, 'iniciarAtado'])->name('atadores.iniciar');
    Route::get('/atadores/calificar', [AtadoresController::class, 'calificarAtadores'])->name('atadores.calificar');
    Route::get('/atadores-juliosAtados', [AtadoresController::class, 'cargarDatosUrdEngAtador'])->name('datosAtadores.Atador');
    Route::post('/atadores/save', [AtadoresController::class, 'save'])->name('atadores.save');
    Route::get('/atadores/show', [AtadoresController::class, 'show'])->name('atadores.show');
    Route::post('/tejedores/validar', [AtadoresController::class, 'validarTejedor'])->name('tejedor.validar');

    // Catálogos de Atadores//

    // Atadores - Actividades
    Route::get('/atadores/catalogos/actividades', [AtaActividadesController::class, 'index'])->name('atadores.catalogos.actividades');
    Route::post('/atadores/catalogos/actividades', [AtaActividadesController::class, 'store'])->name('atadores.catalogos.actividades.store');
    Route::get('/atadores/catalogos/actividades/{id}', [AtaActividadesController::class, 'show'])->name('atadores.catalogos.actividades.show');
    Route::put('/atadores/catalogos/actividades/{id}', [AtaActividadesController::class, 'update'])->name('atadores.catalogos.actividades.update');
    Route::delete('/atadores/catalogos/actividades/{id}', [AtaActividadesController::class, 'destroy'])->name('atadores.catalogos.actividades.destroy');

    // Atadores Comentarios
    Route::get('/atadores/catalogos/comentarios', [AtaComentariosController::class, 'index'])->name('atadores.catalogos.comentarios');
    Route::post('/atadores/catalogos/comentarios', [AtaComentariosController::class, 'store'])->name('atadores.catalogos.comentarios.store');
    Route::get('/atadores/catalogos/comentarios/{nota1}', [AtaComentariosController::class, 'show'])->name('atadores.catalogos.comentarios.show');
    Route::put('/atadores/catalogos/comentarios/{nota1}', [AtaComentariosController::class, 'update'])->name('atadores.catalogos.comentarios.update');
    Route::delete('/atadores/catalogos/comentarios/{nota1}', [AtaComentariosController::class, 'destroy'])->name('atadores.catalogos.comentarios.destroy');

    // Atadores Maquinas
    Route::get('/atadores/catalogos/maquinas', [AtaMaquinasController::class, 'index'])->name('atadores.catalogos.maquinas');
    Route::post('/atadores/catalogos/maquinas', [AtaMaquinasController::class, 'store'])->name('atadores.catalogos.maquinas.store');
    Route::get('/atadores/catalogos/maquinas/{maquinaId}', [AtaMaquinasController::class, 'show'])->name('atadores.catalogos.maquinas.show');
    Route::put('/atadores/catalogos/maquinas/{maquinaId}', [AtaMaquinasController::class, 'update'])->name('atadores.catalogos.maquinas.update');
    Route::delete('/atadores/catalogos/maquinas/{maquinaId}', [AtaMaquinasController::class, 'destroy'])->name('atadores.catalogos.maquinas.destroy');

    // Módulo Mantenimiento
    Route::get('/modulo-mantenimiento', function () {
        return view('modulos/mantenimiento');
    });

    // Reportar Paro de Maquina
    Route::get('/mantenimiento/nuevo-paro', function () {
        return view('modulos.mantenimiento.nuevo-paro.index');
    })->name('mantenimiento.nuevo-paro');

    // Finalizar Paro de Maquina
    Route::get('/mantenimiento/finalizar-paro', function () {
        return view('modulos.mantenimiento.finalizar-paro.index');
    })->name('mantenimiento.finalizar-paro');

    // API Mantenimiento (movido a controlador para no tener lógica en web.php)
    Route::get('/api/mantenimiento/departamentos', [MantenimientoParosController::class, 'departamentos'])
        ->name('api.mantenimiento.departamentos');

    Route::get('/api/mantenimiento/maquinas/{departamento}', [MantenimientoParosController::class, 'maquinas'])
        ->name('api.mantenimiento.maquinas');

    Route::get('/api/mantenimiento/tipos-falla', [MantenimientoParosController::class, 'tiposFalla'])
        ->name('api.mantenimiento.tipos-falla');

    Route::get('/api/mantenimiento/fallas/{departamento}', [MantenimientoParosController::class, 'fallas'])
        ->name('api.mantenimiento.fallas');

    Route::get('/api/mantenimiento/orden-trabajo/{departamento}/{maquina}', [MantenimientoParosController::class, 'ordenTrabajo'])
        ->name('api.mantenimiento.orden-trabajo');

    Route::get('/api/mantenimiento/operadores', [MantenimientoParosController::class, 'operadores'])
        ->name('api.mantenimiento.operadores');

    // Guardar paro/falla
    Route::post('/api/mantenimiento/paros', [MantenimientoParosController::class, 'store'])
        ->name('api.mantenimiento.paros.store');

    // Obtener lista de paros/fallas activos
    Route::get('/api/mantenimiento/paros', [MantenimientoParosController::class, 'index'])
        ->name('api.mantenimiento.paros.index');

    // Obtener un paro específico
    Route::get('/api/mantenimiento/paros/{id}', [MantenimientoParosController::class, 'show'])
        ->name('api.mantenimiento.paros.show');

    // Finalizar un paro
    Route::put('/api/mantenimiento/paros/{id}/finalizar', [MantenimientoParosController::class, 'finalizar'])
        ->name('api.mantenimiento.paros.finalizar');

    // Reporte de Fallos y Paros
    Route::get('/mantenimientos/reporte-fallos-paros', function () {
        return view('modulos.mantenimiento.reporte-fallos-paros.index');
    })->name('mantenimiento.reporte-fallos-paros');

    // ============================================
    // MÓDULO TELEGRAM
    // ============================================
    Route::prefix('telegram')->name('telegram.')->group(function () {
        Route::post('/send', [TelegramController::class, 'sendMessage'])->name('send');
        Route::get('/bot-info', [TelegramController::class, 'getBotInfo'])->name('bot-info');
        Route::get('/get-chat-id', [TelegramController::class, 'getChatId'])->name('get-chat-id');
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


    // Módulo Configuración
    Route::get('/modulo-configuracion', [UsuarioController::class, 'showConfiguracion'])->name('configuracion.index');
    //Route::get('/configuracion/parametros', function () { return view('modulos/configuracion/parametros'); });
    //Route::get('/configuracion/base-datos', function () { return view('modulos/configuracion/base-datos'); });
    //Route::get('/configuracion/bd-pro-productivo', function () { return view('modulos/configuracion/bd-pro-productivo'); });
    //Route::get('/configuracion/bd-pro-pruebas', function () { return view('modulos/configuracion/bd-pro-pruebas'); });
    //Route::get('/configuracion/bd-tow-productivo', function () { return view('modulos/configuracion/bd-tow-productivo'); });
    //Route::get('/configuracion/bd-tow-pruebas', function () { return view('modulos/configuracion/bd-tow-pruebas'); });
    //Route::get('/configuracion/ambiente', function () { return view('modulos/configuracion/ambiente'); });
    Route::get('/configuracion/cargar-orden-produccion', function () { return view('modulos/configuracion/cargar-orden-produccion'); });

    // Información individual de telares
    Route::get('/tejido/jacquard-sulzer/{telar}', [TelaresController::class, 'mostrarTelarSulzer'])->name('tejido.mostrarTelarSulzer');
    Route::get('/ordenes-programadas-dinamica/{telar}', [TelaresController::class, 'obtenerOrdenesProgramadas'])->name('ordenes.programadas');

    // Rutas adicionales de módulos
    Route::get('/modulos/{modulo}/duplicar', [ModulosController::class, 'duplicar'])->name('modulos.duplicar');
    Route::post('/modulos/{modulo}/toggle-acceso', [ModulosController::class, 'toggleAcceso'])->name('modulos.toggle.acceso');
    Route::post('/modulos/{modulo}/toggle-permiso', [ModulosController::class, 'togglePermiso'])->name('modulos.toggle.permiso');
    Route::get('/api/modulos/nivel/{nivel}', [ModulosController::class, 'getModulosPorNivel'])->name('api.modulos.nivel');
    Route::get('/api/modulos/submodulos/{dependencia}', [ModulosController::class, 'getSubmodulos'])->name('api.modulos.submodulos');

    // Rutas para inventario de telares (módulo separado)
    // NOTA: Esta ruta es diferente de 'programa-urd-eng/inventario-telares' (línea 558)
    Route::controller(InventarioTelaresController::class)
        ->prefix('inventario-telares')->name('inventario.telares.modulo.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/guardar', 'store')->name('store');
            Route::delete('/eliminar', 'destroy')->name('destroy');
        });

    // ============================================
    // MÓDULO DESARROLLADORES
    // ============================================
    Route::get('/desarrolladores', [TelDesarrolladoresController::class, 'index'])->name('desarrolladores');
    Route::get('/desarrolladores/telar/{telarId}/producciones', [TelDesarrolladoresController::class, 'obtenerProducciones'])->name('desarrolladores.obtener-producciones');
    Route::get('/desarrolladores/telar/{telarId}/produccion/{noProduccion}', [TelDesarrolladoresController::class, 'formularioDesarrollador'])->name('desarrolladores.formulario');
    Route::get('/desarrolladores/orden/{noProduccion}/detalles', [TelDesarrolladoresController::class, 'obtenerDetallesOrden'])->name('desarrolladores.obtener-detalles-orden');
    Route::get('/desarrolladores/modelo-codificado/{salonTejidoId}/{tamanoClave}', [TelDesarrolladoresController::class, 'obtenerCodigoDibujo'])->name('desarrolladores.obtener-codigo-dibujo');
    // Route::get('/desarrolladores/catcodificados/{telarId}/{noProduccion}', [TelDesarrolladoresController::class, 'obtenerRegistroCatCodificado'])->name('desarrolladores.obtener-catcodificado');
    Route::post('/desarrolladores', [TelDesarrolladoresController::class, 'store'])->name('desarrolladores.store');

    // ============================================
    // MÓDULO Catalogo De Desarrolladores
    // ============================================
    Route::get('catalogo-desarrolladores', [catDesarrolladoresController::class, 'index'])->name('desarrolladores.catalogo-desarrolladores');
    Route::post('catalogo-desarrolladores', [catDesarrolladoresController::class, 'store'])->name('cat-desarrolladores.store');
    Route::put('catalogo-desarrolladores/{cat_desarrolladore}', [catDesarrolladoresController::class, 'update'])->name('cat-desarrolladores.update');
    Route::delete('catalogo-desarrolladores/{cat_desarrolladore}', [catDesarrolladoresController::class, 'destroy'])->name('cat-desarrolladores.destroy');
});
