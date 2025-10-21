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
use App\Models\SYSRoles;
use Illuminate\Support\Facades\Artisan;


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

            // Verificar usuarios existentes
            $usuariosCount = \Illuminate\Support\Facades\DB::table('SYSUsuario')->count();
            \Illuminate\Support\Facades\Log::info('Usuarios existentes en SYSUsuario', ['count' => $usuariosCount]);

            // Asignar permisos del nuevo módulo a todos los usuarios existentes usando SQL robusto
            $acceso = $data['acceso'] ? 1 : 0;
            $crear = $data['crear'] ? 1 : 0;
            $modificar = $data['modificar'] ? 1 : 0;
            $eliminar = $data['eliminar'] ? 1 : 0;
            $registrar = $data['reigstrar'] ? 1 : 0;
            $fechaActual = now()->format('Y-m-d H:i:s');

            \Illuminate\Support\Facades\Log::info('Datos originales del request', [
                'acceso_request' => $request->has('acceso'),
                'crear_request' => $request->has('crear'),
                'modificar_request' => $request->has('modificar'),
                'eliminar_request' => $request->has('eliminar'),
                'reigstrar_request' => $request->has('reigstrar'),
            ]);

            \Illuminate\Support\Facades\Log::info('Datos para asignar permisos', [
                'idrol' => $nuevoModulo->idrol,
                'acceso' => $acceso,
                'crear' => $crear,
                'modificar' => $modificar,
                'eliminar' => $eliminar,
                'registrar' => $registrar,
                'fecha' => $fechaActual
            ]);

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
        Route::get('/programa-tejido', function() {
            $registros = \App\Models\ReqProgramaTejido::orderBy('NoTelarId')->get();
            return view('modulos.req-programa-tejido', compact('registros'));
        })->name('catalogos.req-programa-tejido');

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
            // Obtener datos de inventario de telares para la vista
            $inventarioTelares = \App\Models\TejInventarioTelares::where('status', 'Activo')
                ->orderBy('no_telar')
                ->orderBy('tipo')
                ->get();

            return view('modulos.programa_urd_eng.reservar-programar', compact('inventarioTelares'));
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
        });
    });

    // ============================================
    // RUTAS DIRECTAS (COMPATIBILIDAD)
    // ============================================

    // Rutas directas de catálogos
    Route::get('/planeacion/programa-tejido', function() {
        $registros = \App\Models\ReqProgramaTejido::orderBy('NoTelarId')->get();
        return view('modulos.req-programa-tejido', compact('registros'));
    })->name('catalogos.req-programa-tejido');
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
    Route::get('/modulo-cortes-de-eficiencia/consultar', [CortesEficienciaController::class, 'consultar'])->name('cortes.eficiencia.consultar');
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

    // Módulo Urdido (mantener por compatibilidad)
    Route::get('/modulo-urdido', function () {
        return view('modulos/urdido');
    });
    //Route::get('/urdido/programar-requerimientos', function () {
    //    return view('modulos/urdido/programar-requerimientos');
    //});
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
    //Route::get('/engomado/programar-requerimientos', function () {
    //    return view('modulos/engomado/programar-requerimientos');
    //});
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
    //Route::get('/modulo-edicion-urdido-engomado', function () {
    //    return view('/modulos/edicion_urdido_engomado/edicion-urdido-engomado-folio');
    //})->name('ingresarFolioEdicion');
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
