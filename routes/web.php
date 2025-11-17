<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CalendarioController;
use App\Http\Controllers\CatalagoEficienciaController;
use App\Http\Controllers\CatalagoTelarController;
use App\Http\Controllers\CatalagoVelocidadController;
use App\Http\Controllers\CortesEficienciaController;
use App\Http\Controllers\ProgramaTejidoController;
use App\Http\Controllers\RequerimientoController;
use App\Http\Controllers\SecuenciaInvTelasController;
use App\Http\Controllers\SecuenciaInvTramaController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TelaresController;
use App\Http\Controllers\ModulosController;
use App\Http\Controllers\AplicacionesController;
use App\Http\Controllers\AtadoresController;
use App\Http\Controllers\AtaActividadesController;
use App\Http\Controllers\AtaComentariosController;
use App\Http\Controllers\AtaMaquinasController;
use App\Http\Controllers\NuevoRequerimientoController;
use App\Http\Controllers\ProduccionReenconadoCabezuelaController;
use App\Http\Controllers\ConsultarRequerimientoController;
use App\Http\Controllers\CodificacionController;
use App\Http\Controllers\InventarioTelaresController;
use App\Http\Controllers\InvTelasReservadasController;
use App\Http\Controllers\ReservarProgramarController;
use App\Http\Controllers\ProgramarUrdEngController;
use App\Http\Controllers\ProgramarUrdidoController;
use App\Http\Controllers\ProgramarEngomadoController;
use App\Http\Controllers\ModuloProduccionEngomadoController;
use App\Http\Controllers\ModuloProduccionUrdidoController;
use App\Http\Controllers\CatalogosUrdidoController;
use App\Http\Controllers\PDFController;
use App\Http\Controllers\TelActividadesBPMController;
use App\Http\Controllers\TelBpmController;
use App\Http\Controllers\TelBpmLineController;
use App\Http\Controllers\TelTelaresOperadorController;
use App\Http\Controllers\MantenimientoParosController;
use App\Http\Controllers\Simulaciones\SimulacionProgramaTejidoController;
use App\Http\Controllers\MarcasFinalesController;
use App\Http\Controllers\MarcasController;
use App\Models\SYSRoles;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;


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

    // Guardar módulo
    Route::post('/', function(\Illuminate\Http\Request $request) {
        try {
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

            $nuevoModulo = SYSRoles::create($data);

            // Asignar permisos del nuevo módulo a todos los usuarios existentes usando SQL robusto
            $acceso = $data['acceso'] ? 1 : 0;
            $crear = $data['crear'] ? 1 : 0;
            $modificar = $data['modificar'] ? 1 : 0;
            $eliminar = $data['eliminar'] ? 1 : 0;
            $registrar = $data['reigstrar'] ? 1 : 0;
            $fechaActual = now()->format('Y-m-d H:i:s');



            // SQL robusto para insertar y forzar permisos a todos los usuarios existentes
            $sql = "
                SET XACT_ABORT ON;
                BEGIN TRAN;

                DECLARE @idrol INT = {$nuevoModulo->idrol};

                -- 1) Insertar asignaciones faltantes a TODOS los usuarios con permisos del formulario
                INSERT INTO SYSUsuariosRoles
                    (idusuario, idrol, acceso, crear, modificar, eliminar, registrar, assigned_at)
                SELECT
                    u.idusuario,
                    @idrol,
                    {$acceso}, {$crear}, {$modificar}, {$eliminar}, {$registrar},
                    '{$fechaActual}'
                FROM SYSUsuario u
                WHERE NOT EXISTS (
                    SELECT 1
                    FROM SYSUsuariosRoles ur
                    WHERE ur.idusuario = u.idusuario
                      AND ur.idrol = @idrol
                );

                -- 2) Forzar permisos del formulario para cualquier asignación existente de ese rol
                UPDATE ur
                SET acceso = {$acceso}, crear = {$crear}, modificar = {$modificar}, eliminar = {$eliminar}, registrar = {$registrar}
                FROM SYSUsuariosRoles ur
                WHERE ur.idrol = @idrol;

                COMMIT;

                -- 3) Resumen de verificación
                DECLARE @total_usuarios INT = (SELECT COUNT(*) FROM SYSUsuario);
                DECLARE @asignados INT = (SELECT COUNT(*) FROM SYSUsuariosRoles WHERE idrol = @idrol);
                DECLARE @con_permisos_correctos INT = (
                    SELECT COUNT(*)
                    FROM SYSUsuariosRoles
                    WHERE idrol = @idrol
                      AND acceso={$acceso} AND crear={$crear} AND modificar={$modificar} AND eliminar={$eliminar} AND registrar={$registrar}
                );

                SELECT
                    @idrol AS idrol_creado_o_usado,
                    @total_usuarios AS total_usuarios,
                    @asignados AS total_asignados_en_pivote,
                    @con_permisos_correctos AS total_con_permisos_correctos;
            ";

            \Illuminate\Support\Facades\Log::info('SQL robusto a ejecutar', ['sql' => $sql]);

            $resultado = \Illuminate\Support\Facades\DB::statement($sql);

            \Illuminate\Support\Facades\Log::info('Resultado de la asignación de permisos', ['resultado' => $resultado]);

            // Verificar cuántos registros se insertaron
            $permisosAsignados = \Illuminate\Support\Facades\DB::table('SYSUsuariosRoles')
                ->where('idrol', $nuevoModulo->idrol)
                ->count();

            \Illuminate\Support\Facades\Log::info('Permisos asignados después de la inserción', ['count' => $permisosAsignados]);

            // Limpiar caché automáticamente si se subió una imagen
            if ($imagenActualizada) {
                Artisan::call('cache:clear');
                Artisan::call('view:clear');

                // Limpiar caché específico de módulos
                $usuarios = \App\Models\SYSUsuario::all();
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
                ->with('success', "Módulo creado exitosamente y permisos asignados a todos los usuarios. Permisos asignados: {$permisosAsignados}")
                ->with('show_sweetalert', true);

        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Error al crear módulo: ' . $e->getMessage());
        }
    })->name('store');

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
    Route::put('/{id}', function($id, \Illuminate\Http\Request $request) {
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
                $usuarios = \App\Models\SYSUsuario::all();
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

    // Ruta genérica para compatibilidad (solo para otros IDs no especificados arriba)
    Route::get('/submodulos-nivel3/{moduloPadre}', [UsuarioController::class, 'showSubModulosNivel3'])->name('submodulos.nivel3');

    // API para precarga de submódulos (AJAX)
    Route::get('/api/submodulos/{moduloPrincipal}', [UsuarioController::class, 'getSubModulosAPI'])->name('api.submodulos');

    // ============================================
    // MÓDULO PLANEACIÓN (100)
    // ============================================
    Route::prefix('planeacion')->name('planeacion.')->group(function () {
        // Submódulos de Planeación
        Route::get('/programa-tejido', [ProgramaTejidoController::class, 'index'])->name('programa-tejido.index');

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
            Route::get('/matriz-hilos', [App\Http\Controllers\MatrizHilosController::class, 'index'])->name('matriz-hilos');
            // Rutas para Codificación de Modelos (orden específico primero)
            Route::get('/codificacion-modelos', [CodificacionController::class, 'index'])->name('codificacion-modelos');
            Route::get('/codificacion-modelos/create', [CodificacionController::class, 'create'])->name('codificacion.create');
            Route::get('/codificacion-modelos/get-all', [CodificacionController::class, 'getAll'])->name('codificacion.get-all');
            Route::get('/codificacion-modelos/estadisticas', [CodificacionController::class, 'estadisticas'])->name('codificacion.estadisticas');
            Route::get('/codificacion-modelos/{id}/edit', [CodificacionController::class, 'edit'])->name('codificacion.edit');
            Route::get('/codificacion-modelos/{id}', [CodificacionController::class, 'show'])->name('codificacion.show');
            Route::post('/codificacion-modelos', [CodificacionController::class, 'store'])->name('codificacion.store');
            Route::put('/codificacion-modelos/{id}', [CodificacionController::class, 'update'])->name('codificacion.update');
            Route::delete('/codificacion-modelos/{id}', [CodificacionController::class, 'destroy'])->name('codificacion.destroy');
            Route::post('/codificacion-modelos/excel', [CodificacionController::class, 'procesarExcel'])->name('codificacion.excel');
            Route::get('/codificacion-modelos/excel-progress/{id}', [CodificacionController::class, 'importProgress'])->name('codificacion.excel.progress');
            Route::post('/codificacion-modelos/buscar', [CodificacionController::class, 'buscar'])->name('codificacion.buscar');
        });

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
        Route::post('/calendarios', [CalendarioController::class, 'store'])->name('calendarios.store');
        Route::put('/calendarios/{calendario}', [CalendarioController::class, 'update'])->name('calendarios.update');
        Route::delete('/calendarios/{calendario}', [CalendarioController::class, 'destroy'])->name('calendarios.destroy');

        // Rutas CRUD para líneas de calendario
        Route::post('/calendarios/lineas', [CalendarioController::class, 'storeLine'])->name('calendarios.lineas.store');
        Route::put('/calendarios/lineas/{linea}', [CalendarioController::class, 'updateLine'])->name('calendarios.lineas.update');
        Route::delete('/calendarios/lineas/{linea}', [CalendarioController::class, 'destroyLine'])->name('calendarios.lineas.destroy');
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
        Route::post('/catalogos/matriz-hilos', [App\Http\Controllers\MatrizHilosController::class, 'store'])->name('matriz-hilos.store');
        Route::get('/catalogos/matriz-hilos/{id}', [App\Http\Controllers\MatrizHilosController::class, 'show'])->name('matriz-hilos.show');
        Route::put('/catalogos/matriz-hilos/{id}', [App\Http\Controllers\MatrizHilosController::class, 'update'])->name('matriz-hilos.update');
        Route::delete('/catalogos/matriz-hilos/{id}', [App\Http\Controllers\MatrizHilosController::class, 'destroy'])->name('matriz-hilos.destroy');
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
        Route::put('/produccion-reenconado/{folio}', [ProduccionReenconadoCabezuelaController::class, 'update'])
            ->name('produccion.reenconado.update');
        Route::delete('/produccion-reenconado/{folio}', [ProduccionReenconadoCabezuelaController::class, 'destroy'])
            ->name('produccion.reenconado.destroy');

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

        // Marcas Finales
        Route::get('/inventario/marcas-finales', [App\Http\Controllers\MarcasFinalesController::class, 'index'])->name('inventario.marcas.finales');
        Route::post('/inventario/marcas-finales', [App\Http\Controllers\MarcasFinalesController::class, 'store'])->name('inventario.marcas.finales.store');
        Route::get('/inventario/marcas-finales/{folio}', [App\Http\Controllers\MarcasFinalesController::class, 'show'])->name('inventario.marcas.finales.show');
        Route::put('/inventario/marcas-finales/{folio}', [App\Http\Controllers\MarcasFinalesController::class, 'update'])->name('inventario.marcas.finales.update');
        Route::post('/inventario/marcas-finales/{folio}/finalizar', [App\Http\Controllers\MarcasFinalesController::class, 'finalizar'])->name('inventario.marcas.finales.finalizar');

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

        Route::resource('tel-bpm', TelBpmController::class)
    ->parameters(['tel-bpm' => 'folio'])   // PK string
    ->names('tel-bpm');                    // tel-bpm.index, tel-bpm.store, etc.

Route::patch('tel-bpm/{folio}/terminar',  [TelBpmController::class, 'finish'])->name('tel-bpm.finish');
Route::patch('tel-bpm/{folio}/autorizar', [TelBpmController::class, 'authorizeDoc'])->name('tel-bpm.authorize');
Route::patch('tel-bpm/{folio}/rechazar',  [TelBpmController::class, 'reject'])->name('tel-bpm.reject');

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
                Route::get('/{modulo}/duplicar', 'duplicar')->whereNumber('modulo')->name('duplicar');
            });

            Route::get('/api/modulos/nivel/{nivel}', [ModulosController::class, 'getModulosPorNivel'])
                ->whereNumber('nivel')->name('api.modulos.nivel');
            Route::get('/api/modulos/submodulos/{dependencia}', [ModulosController::class, 'getSubmodulos'])
                ->whereNumber('dependencia')->name('api.modulos.submodulos');
        });

        Route::get('/cargar-planeacion', [App\Http\Controllers\ConfiguracionController::class, 'cargarPlaneacion'])->name('cargar.planeacion');
        Route::post('/cargar-planeacion/upload', [App\Http\Controllers\ConfiguracionController::class, 'procesarExcel'])->name('cargar.planeacion.upload');
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

        // Notificar Montado de Julios
        Route::get('/notificar-montado-julios', function () {
            return view('modulos.notificar-montado-julios.index');
        })->name('notificar.montado.julios');
    });

    // ============================================
    // RUTAS DIRECTAS (COMPATIBILIDAD)
    // ============================================

    // Rutas directas de catálogos
    Route::get('/planeacion/programa-tejido', [ProgramaTejidoController::class, 'index'])->name('catalogos.req-programa-tejido');

        // Altas especiales
        Route::get('/planeacion/programa-tejido/altas-especiales', [\App\Http\Controllers\ComprasEspecialesController::class, 'index'])->name('programa-tejido.altas-especiales');

        // Alta de pronósticos
        Route::get('/planeacion/programa-tejido/alta-pronosticos', [\App\Http\Controllers\PronosticosController::class, 'index'])->name('programa-tejido.alta-pronosticos');
        Route::post('/pronosticos/sincronizar', [\App\Http\Controllers\PronosticosController::class, 'sincronizar'])->name('pronosticos.sincronizar');
        Route::get('/pronosticos', [\App\Http\Controllers\PronosticosController::class, 'get'])->name('pronosticos.get');

        // Nueva ruta para crear/editar programa de tejido
        Route::get('/planeacion/programa-tejido/nuevo', function() {
        return view('modulos.programa-tejido.programatejidoform.create');
    })->name('programa-tejido.nuevo');
        Route::get('/planeacion/programa-tejido/altas-especiales/nuevo', [\App\Http\Controllers\ComprasEspecialesController::class, 'nuevo'])->name('programa-tejido.altas-especiales.nuevo');
        Route::get('/planeacion/programa-tejido/pronosticos/nuevo', [\App\Http\Controllers\PronosticosController::class, 'nuevo'])->name('programa-tejido.pronosticos.nuevo');
        Route::get('/planeacion/buscar-detalle-modelo', [\App\Http\Controllers\ComprasEspecialesController::class, 'buscarDetalleModelo'])->name('planeacion.buscar-detalle-modelo');
        Route::get('/planeacion/buscar-modelos-sugerencias', [\App\Http\Controllers\ComprasEspecialesController::class, 'buscarModelosSugerencias'])->name('planeacion.buscar-modelos-sugerencias');
        Route::post('/planeacion/programa-tejido', [ProgramaTejidoController::class, 'store'])->name('programa-tejido.store');
        Route::get('/planeacion/programa-tejido/{id}/editar', [ProgramaTejidoController::class, 'edit'])->name('programa-tejido.edit');
    Route::put('/planeacion/programa-tejido/{id}', [ProgramaTejidoController::class, 'update'])->name('programa-tejido.update');
    Route::post('/planeacion/programa-tejido/{id}/prioridad/subir', [ProgramaTejidoController::class, 'moveUp'])->name('programa-tejido.prioridad.subir');
    Route::post('/planeacion/programa-tejido/{id}/prioridad/bajar', [ProgramaTejidoController::class, 'moveDown'])->name('programa-tejido.prioridad.bajar');
    Route::delete('/planeacion/programa-tejido/{id}', [ProgramaTejidoController::class, 'destroy'])->name('programa-tejido.destroy');
        // JSON: ReqProgramaTejidoLine dentro de planeación
        Route::get('/planeacion/req-programa-tejido-line', [\App\Http\Controllers\ReqProgramaTejidoLineController::class, 'index']);

    // Rutas API para los selects del programa de tejido
    Route::get('/programa-tejido/salon-options', [ProgramaTejidoController::class, 'getSalonTejidoOptions']);
    Route::get('/programa-tejido/tamano-clave-by-salon', [ProgramaTejidoController::class, 'getTamanoClaveBySalon']);
    Route::get('/programa-tejido/flogs-id-options', [ProgramaTejidoController::class, 'getFlogsIdOptions']);
    Route::get('/programa-tejido/flogs-id-from-twflogs', [ProgramaTejidoController::class, 'getFlogsIdFromTwFlogsTable']);
    Route::get('/programa-tejido/descripcion-by-idflog/{idflog}', [ProgramaTejidoController::class, 'getDescripcionByIdFlog']);
    Route::get('/programa-tejido/calendario-id-options', [ProgramaTejidoController::class, 'getCalendarioIdOptions']);
    Route::get('/programa-tejido/aplicacion-id-options', [ProgramaTejidoController::class, 'getAplicacionIdOptions']);
    Route::post('/programa-tejido/datos-relacionados', [ProgramaTejidoController::class, 'getDatosRelacionados']);
Route::get('/programa-tejido/telares-by-salon', [ProgramaTejidoController::class, 'getTelaresBySalon']);
Route::get('/programa-tejido/ultima-fecha-final-telar', [ProgramaTejidoController::class, 'getUltimaFechaFinalTelar']);
Route::get('/programa-tejido/ultimo-registro-salon', [ProgramaTejidoController::class, 'getUltimoRegistroSalon']);
Route::get('/programa-tejido/hilos-options', [ProgramaTejidoController::class, 'getHilosOptions']);
Route::post('/programa-tejido/calcular-fecha-fin', [ProgramaTejidoController::class, 'calcularFechaFin']);
Route::get('/programa-tejido/eficiencia-std', [ProgramaTejidoController::class, 'getEficienciaStd']);
Route::get('/programa-tejido/velocidad-std', [ProgramaTejidoController::class, 'getVelocidadStd']);

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
    Route::get('/altas-especiales', function() {
        return view('modulos.simulacion.altas-especiales', ['registros' => []]);
    })->name('altas-especiales');

    // Rutas para crear nuevo
    Route::get('/nuevo', function() {
        return view('modulos.simulacion.simulacionform.create');
    })->name('nuevo');

    Route::get('/pronosticos/nuevo', function() {
        return view('modulos.simulacion.simulacionform.pronosticos');
    })->name('pronosticos.nuevo');

    Route::get('/altas-especiales/nuevo', function() {
        return view('modulos.simulacion.simulacionform.altas');
    })->name('altas-especiales.nuevo');

    // Rutas para catálogos y helpers (deben ir ANTES de las rutas con {id})
    Route::get('/salon-tejido-options', [SimulacionProgramaTejidoController::class, 'getSalonTejidoOptions'])->name('salon-tejido-options');
    Route::get('/tamano-clave-options', [SimulacionProgramaTejidoController::class, 'getTamanoClaveOptions'])->name('tamano-clave-options');
    Route::get('/tamano-clave-by-salon', [SimulacionProgramaTejidoController::class, 'getTamanoClaveBySalon'])->name('tamano-clave-by-salon');
    Route::get('/flogs-id-options', [SimulacionProgramaTejidoController::class, 'getFlogsIdOptions'])->name('flogs-id-options');
    Route::get('/flogs-id-from-twflogs-table', [SimulacionProgramaTejidoController::class, 'getFlogsIdFromTwFlogsTable'])->name('flogs-id-from-twflogs-table');
    Route::get('/descripcion-by-idflog/{idflog}', [SimulacionProgramaTejidoController::class, 'getDescripcionByIdFlog'])->name('descripcion-by-idflog');
    Route::get('/calendario-id-options', [SimulacionProgramaTejidoController::class, 'getCalendarioIdOptions'])->name('calendario-id-options');
    Route::get('/aplicacion-id-options', [SimulacionProgramaTejidoController::class, 'getAplicacionIdOptions'])->name('aplicacion-id-options');
    Route::get('/datos-relacionados', [SimulacionProgramaTejidoController::class, 'getDatosRelacionados'])->name('datos-relacionados');
    Route::get('/telares-by-salon', [SimulacionProgramaTejidoController::class, 'getTelaresBySalon'])->name('telares-by-salon');
    Route::get('/ultima-fecha-final-telar', [SimulacionProgramaTejidoController::class, 'getUltimaFechaFinalTelar'])->name('ultima-fecha-final-telar');
    Route::get('/ultimo-registro-salon', [SimulacionProgramaTejidoController::class, 'getUltimoRegistroSalon'])->name('ultimo-registro-salon');
    Route::get('/hilos-options', [SimulacionProgramaTejidoController::class, 'getHilosOptions'])->name('hilos-options');
    Route::post('/calcular-fecha-fin', [SimulacionProgramaTejidoController::class, 'calcularFechaFin'])->name('calcular-fecha-fin');
    Route::get('/eficiencia-std', [SimulacionProgramaTejidoController::class, 'getEficienciaStd'])->name('eficiencia-std');
    Route::get('/velocidad-std', [SimulacionProgramaTejidoController::class, 'getVelocidadStd'])->name('velocidad-std');

    // JSON: SimulacionProgramaTejidoLine dentro de simulación
    Route::get('/req-programa-tejido-line', [App\Http\Controllers\Simulaciones\SimulacionProgramaTejidoLineController::class, 'index'])->name('req-programa-tejido-line');

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

    // Rutas legacy de tejido (mantener por compatibilidad, pero ya están dentro del grupo tejido arriba)
    // Estas rutas están duplicadas - las de arriba dentro del grupo tejido tienen prioridad
    Route::get('/modulo-marcas-finales', [MarcasFinalesController::class, 'index'])->name('modulo.marcas.finales');
    Route::post('/modulo-marcas-finales', [MarcasFinalesController::class, 'store'])->name('modulo.marcas.finales.store');
    Route::get('/modulo-marcas-finales/{folio}', [MarcasFinalesController::class, 'show'])->name('modulo.marcas.finales.show');
    Route::put('/modulo-marcas-finales/{folio}', [MarcasFinalesController::class, 'update'])->name('modulo.marcas.finales.update');
    Route::post('/modulo-marcas-finales/{folio}/finalizar', [MarcasFinalesController::class, 'finalizar'])->name('modulo.marcas.finales.finalizar');

    // Rutas para Marcas (Nuevas Marcas Finales y Consultar Marcas Finales)
    Route::get('/modulo-marcas', [MarcasController::class, 'index'])->name('marcas.nuevo');
    Route::get('/modulo-marcas/consultar', [MarcasController::class, 'consultar'])->name('marcas.consultar');
    Route::post('/modulo-marcas/generar-folio', [MarcasController::class, 'generarFolio'])->name('marcas.generar.folio');
    Route::get('/modulo-marcas/obtener-datos-std', [MarcasController::class, 'obtenerDatosSTD'])->name('marcas.datos.std');
    Route::post('/modulo-marcas/store', [MarcasController::class, 'store'])->name('marcas.store');
    Route::get('/modulo-marcas/{folio}', [MarcasController::class, 'show'])->name('marcas.show');
    Route::put('/modulo-marcas/{folio}', [MarcasController::class, 'update'])->name('marcas.update');
    Route::post('/modulo-marcas/{folio}/finalizar', [MarcasController::class, 'finalizar'])->name('marcas.finalizar');

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
        Route::post('/programar-urdido/subir-prioridad', [ProgramarUrdidoController::class, 'subirPrioridad'])->name('programar.urdido.subir.prioridad');
        Route::post('/programar-urdido/bajar-prioridad', [ProgramarUrdidoController::class, 'bajarPrioridad'])->name('programar.urdido.bajar.prioridad');

        // Catálogos de Urdido
        Route::get('/catalogos-julios', [CatalogosUrdidoController::class, 'catalogosJulios'])->name('catalogos.julios');
        Route::get('/catalogo-maquinas', [CatalogosUrdidoController::class, 'catalogoMaquinas'])->name('catalogo.maquinas');
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
        Route::get('/programar-engomado/ordenes', [ProgramarEngomadoController::class, 'getOrdenes'])->name('programar.engomado.ordenes');
        Route::post('/programar-engomado/subir-prioridad', [ProgramarEngomadoController::class, 'subirPrioridad'])->name('programar.engomado.subir.prioridad');
        Route::post('/programar-engomado/bajar-prioridad', [ProgramarEngomadoController::class, 'bajarPrioridad'])->name('programar.engomado.bajar.prioridad');

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
        Route::post('/send', [App\Http\Controllers\Telegram\TelegramController::class, 'sendMessage'])->name('send');
        Route::get('/bot-info', [App\Http\Controllers\Telegram\TelegramController::class, 'getBotInfo'])->name('bot-info');
        Route::get('/get-chat-id', [App\Http\Controllers\Telegram\TelegramController::class, 'getChatId'])->name('get-chat-id');
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
        });

    // RUTAS DE MÓDULOS (MOVIDAS A MÓDULOS ORGANIZADOS)

});
