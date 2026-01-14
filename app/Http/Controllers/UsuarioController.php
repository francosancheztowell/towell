<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUsuarioRequest;
use App\Repositories\UsuarioRepository;
use App\Services\ModuloService;
use App\Services\UsuarioService;
use App\Services\PermissionService;
use App\Models\Sistema\SysDepartamentos;
use App\Models\Sistema\SYSRoles;
use App\Models\Sistema\SYSUsuariosRoles;
use App\Models\Sistema\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
class UsuarioController extends Controller
{
    public function __construct(
        private UsuarioRepository $usuarioRepository,
        private UsuarioService $usuarioService,
        private ModuloService $moduloService,
        private PermissionService $permissionService
    ) {}

    /**
     * Mostrar la vista principal con módulos del usuario
     */
    public function index()
    {
        $usuarioActual = Auth::user();

        if (!$usuarioActual || !$usuarioActual->numero_empleado) {
            return redirect()->route('login')
                ->with('error', 'Debes iniciar sesión para acceder a los módulos');
        }

        $modulos = $this->moduloService->getModulosPrincipalesPorUsuario($usuarioActual->idusuario);

        // Warm-up agresivo de caché: precargar submódulos de TODOS los módulos principales
        // Esto hace que la navegación entre módulos sea instantánea (sin delay al hacer click)
        try {
            foreach ($modulos as $m) {
                // Solo módulos principales (nivel 1)
                if (($m['nivel'] ?? null) === 1) {
                    $nombreModulo = $m['nombre'] ?? '';
                    $rutaModulo = $m['ruta'] ?? '';

                    // 1. Precargar el módulo principal en caché (para buscarModuloPrincipal)
                    if (!empty($nombreModulo)) {
                        $this->moduloService->buscarModuloPrincipal($nombreModulo);
                    }
                    if (!empty($rutaModulo)) {
                        $slugRuta = ltrim(str_replace(['/', '_'], '-', $rutaModulo), '/');
                        if ($slugRuta !== $nombreModulo) {
                            $this->moduloService->buscarModuloPrincipal($slugRuta);
                        }
                    }

                    // 2. Precargar submódulos por nombre del módulo (ej: "Planeación")
                    if (!empty($nombreModulo)) {
                        $this->moduloService->getSubmodulosPorModuloPrincipal(
                            $nombreModulo,
                            $usuarioActual->idusuario
                        );
                    }

                    // 3. También precargar por ruta (por si el nombre tiene acentos/caracteres especiales)
                    // Ej: /planeacion -> "planeacion"
                    if (!empty($rutaModulo) && $rutaModulo !== $nombreModulo) {
                        $slugRuta = ltrim(str_replace(['/', '_'], '-', $rutaModulo), '/');
                        if ($slugRuta !== $nombreModulo) {
                            $this->moduloService->getSubmodulosPorModuloPrincipal(
                                $slugRuta,
                                $usuarioActual->idusuario
                            );
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // Silencioso: es optimización, no debe tumbar la pantalla principal
            if (config('app.debug')) {
                Log::debug('Warmup de caché de submódulos falló', ['error' => $e->getMessage()]);
            }
        }

        $tieneConfiguracion = $modulos->contains('nombre', 'Configuración');

        return view('produccionProceso', [
            'modulos' => $modulos,
            'tieneConfiguracion' => $tieneConfiguracion,
            'pageTitle' => 'Producción en Proceso'
        ]);
    }

    /**
     * Mostrar formulario de creación de usuario
     */
    public function create()
    {
        $modulos = $this->moduloService->getAllModulos();
        $departamentos = SysDepartamentos::orderBy('Depto')->get();

        return view('modulos.usuarios.form_usuario', [
            'usuario' => null,
            'modulos' => $modulos,
            'permisosUsuario' => collect(),
            'departamentos' => $departamentos,
            'isEdit' => false
        ]);
    }

    /**
     * Almacenar un nuevo usuario
     */
    public function store(StoreUsuarioRequest $request)
    {
        try {
            $data = $request->validated();
            $foto = $request->hasFile('foto') ? $request->file('foto') : null;

            // Extraer permisos del request (todos los campos que empiezan con "modulo_")
            $permisos = array_filter($request->all(), function($key) {
                return strpos($key, 'modulo_') === 0;
            }, ARRAY_FILTER_USE_KEY);

            $usuario = $this->usuarioService->create($data, $foto, $permisos);

            return redirect()
                ->route('configuracion.usuarios.select')
                ->with('success', 'Usuario registrado correctamente');
        } catch (\Exception $e) {
            Log::error('Error al crear usuario', ['error' => $e->getMessage()]);
            return back()
                ->with('error', 'No se pudo registrar el usuario. Intenta de nuevo.')
                ->withInput();
        }
    }

    /**
     * Listar usuarios con paginación
     */
    public function select(Request $request)
    {
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 50;

        $result = $this->usuarioRepository->getAll($page, $perPage);

        return view('modulos.usuarios.select', [
            'usuarios' => $result['data'] ?? collect(),
            'current_page' => $result['current_page'] ?? 1,
            'per_page' => $result['per_page'] ?? $perPage,
            'total' => $result['total'] ?? 0,
            'last_page' => $result['last_page'] ?? 1,
            'from' => $result['from'] ?? 0,
            'to' => $result['to'] ?? 0,
        ]);
    }

    /**
     * Obtener empleados por area (API)
     */
    public function obtenerEmpleados(string $area)
    {
        try {
            return Usuario::where('area', $area)->get();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Mostrar QR de un usuario
     */
    public function showQR(int $idusuario)
    {
        $usuario = $this->usuarioRepository->findById($idusuario);

        if (!$usuario) {
            return redirect()->route('configuracion.usuarios.select')
                ->with('error', 'Usuario no encontrado');
        }

        return view('modulos.usuarios.qr', compact('usuario'));
    }

    /**
     * Mostrar formulario de edición de usuario
     */
    public function edit(int $id)
    {
        $usuario = $this->usuarioRepository->findById($id);

        if (!$usuario) {
            return redirect()->route('configuracion.usuarios.select')
                ->with('error', 'Usuario no encontrado');
        }

        $modulos = $this->moduloService->getAllModulos();
        $permisosUsuario = $this->permissionService->getAllPermisosUsuario($usuario->idusuario);
        $departamentos = SysDepartamentos::orderBy('Depto')->get();

        return view('modulos.usuarios.form_usuario', [
            'usuario' => $usuario,
            'modulos' => $modulos,
            'permisosUsuario' => $permisosUsuario,
            'departamentos' => $departamentos,
            'isEdit' => true
        ]);
    }

    /**
     * Actualizar usuario
     */
    public function update(StoreUsuarioRequest $request, int $id)
    {
        try {
            $data = $request->validated();
            $foto = $request->hasFile('foto') ? $request->file('foto') : null;

            // NO procesar permisos en edición, se guardan por AJAX en tiempo real
            // Solo actualizar datos del usuario
            $actualizado = $this->usuarioService->update($id, $data, $foto, []);

            if (!$actualizado) {
                return redirect()->route('configuracion.usuarios.select')
                    ->with('error', 'Usuario no encontrado');
            }

            $usuario = $this->usuarioRepository->findById($id);
            $this->moduloService->limpiarCacheUsuario($id);

            return redirect()
                ->route('configuracion.usuarios.select')
                ->with('success', "Usuario #{$usuario->numero_empleado} actualizado correctamente.");
        } catch (\Exception $e) {
            Log::error('Error al actualizar usuario', [
                'usuario_id' => $id,
                'error' => $e->getMessage()
            ]);
            return back()
                ->with('error', 'No se pudo actualizar el usuario.')
                ->withInput();
        }
    }

    /**
     * Eliminar usuario
     */
    public function destroy(int $id)
    {
        try {
            $usuario = $this->usuarioRepository->findById($id);

            if (!$usuario) {
                return redirect()->route('usuarios.select')
                    ->with('error', 'Usuario no encontrado');
            }

            $numeroEmpleado = $usuario->numero_empleado;
            $this->usuarioService->delete($id);

            return redirect()
                ->route('configuracion.usuarios.select')
                ->with('success', "Usuario #{$numeroEmpleado} eliminado correctamente.");
        } catch (\Exception $e) {
            Log::error('Error al eliminar usuario', [
                'usuario_id' => $id,
                'error' => $e->getMessage()
            ]);

            return redirect()
                ->route('configuracion.usuarios.select')
                ->with('error', 'No se pudo eliminar el usuario. Verifica que no tenga registros relacionados.');
        }
    }

    /**
     * Actualizar permiso individual de un usuario
     */
    public function updatePermiso(Request $request, int $id)
    {
        try {
            $idrol = $request->input('idrol');
            $campo = $request->input('campo'); // 'acceso', 'crear', 'modificar', 'eliminar'
            $valor = $request->input('valor') ? 1 : 0;

            // Buscar el registro existente
            $permiso = SYSUsuariosRoles::where('idusuario', $id)
                ->where('idrol', $idrol)
                ->first();

            if ($permiso) {
                // Si existe, solo actualizar el campo específico usando DB directo
                DB::connection('sqlsrv')
                    ->table('SYSUsuariosRoles')
                    ->where('idusuario', $id)
                    ->where('idrol', $idrol)
                    ->update([
                        $campo => $valor,
                        'assigned_at' => now()
                    ]);
            } else {
                // Si no existe, crear con todos los campos inicializados
                SYSUsuariosRoles::create([
                    'idusuario' => $id,
                    'idrol' => $idrol,
                    'acceso' => $campo === 'acceso' ? $valor : 0,
                    'crear' => $campo === 'crear' ? $valor : 0,
                    'modificar' => $campo === 'modificar' ? $valor : 0,
                    'eliminar' => $campo === 'eliminar' ? $valor : 0,
                    'registrar' => 0,
                    'assigned_at' => now()
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Permiso actualizado correctamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar permiso', [
                'usuario_id' => $id,
                'idrol' => $idrol,
                'campo' => $campo,
                'valor' => $valor,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el permiso'
            ], 500);
        }
    }

    /**
     * Mostrar módulos de configuración
     */
    public function showConfiguracion()
    {
        $usuarioActual = Auth::user();
        $moduloConfiguracion = $this->moduloService->buscarModuloPrincipal('configuracion');

        if (!$moduloConfiguracion) {
            return redirect('/produccionProceso')
                ->with('error', 'Módulo de configuración no encontrado');
        }

        $subModulos = $this->moduloService->getSubmodulosPorModuloPrincipal(
            'configuracion',
            $usuarioActual->idusuario,
            $moduloConfiguracion
        );

        return view('modulos.configuracion', [
            'moduloPrincipal' => 'Configuración',
            'subModulos' => $subModulos
        ]);
    }

    /**
     * Mostrar submódulos de configuración (nivel 3)
     */
    public function showSubModulosConfiguracion(string $serie)
    {
        $usuarioActual = Auth::user();
        $moduloPadre = SYSRoles::where('orden', $serie)->first();

        if (!$moduloPadre) {
            return redirect('/configuracion')
                ->with('error', 'Módulo de configuración no encontrado');
        }

        $subModulos = $this->moduloService->getSubmodulosNivel3(
            $serie,
            $usuarioActual->idusuario
        );

        return view('modulos.submodulos', [
            'moduloPrincipal' => $moduloPadre->modulo,
            'subModulos' => $subModulos,
            'rango' => ['inicio' => $serie, 'nombre' => $moduloPadre->modulo]
        ]);
    }

    /**
     * Mostrar configuracion de tejedores (nivel 3)
     */
    public function showTejedoresConfiguracion()
    {
        $configurarModulo = SYSRoles::where('modulo', 'Configurar')
            ->where('Dependencia', 600)
            ->where('Nivel', 2)
            ->first();

        if ($configurarModulo) {
            return $this->showSubModulosConfiguracion($configurarModulo->orden);
        }

        return $this->showSubModulosConfiguracion('605');
    }

    /**
     * Mostrar submódulos de un módulo principal
     */
    public function showSubModulos(string $moduloPrincipal)
    {
        $usuarioActual = Auth::user();

        // Intentar buscar el módulo principal de múltiples formas
        $moduloPadre = $this->moduloService->buscarModuloPrincipal($moduloPrincipal);

        // Si no encuentra por nombre/ruta, intentar buscar por orden si es numérico
        if (!$moduloPadre && is_numeric($moduloPrincipal)) {
            $moduloPadre = SYSRoles::where('Nivel', 1)
                ->whereNull('Dependencia')
                ->where('orden', $moduloPrincipal)
                ->first();
        }

        // Si aún no encuentra, intentar buscar por ruta exacta de la URL
        if (!$moduloPadre) {
            $rutaBuscada = '/' . ltrim($moduloPrincipal, '/');
            $moduloPadre = SYSRoles::where('Nivel', 1)
                ->whereNull('Dependencia')
                ->where('Ruta', $rutaBuscada)
                ->first();
        }

        if (!$moduloPadre) {
            Log::warning('Módulo no encontrado', [
                'modulo_principal' => $moduloPrincipal,
                'usuario' => $usuarioActual->idusuario
            ]);
            return redirect('/produccionProceso')
                ->with('error', 'Módulo no encontrado. Puede que haya sido eliminado o no tengas acceso.');
        }

        // Verificar que el usuario tenga acceso a este módulo
        $tieneAcceso = SYSUsuariosRoles::where('idusuario', $usuarioActual->idusuario)
            ->where('idrol', $moduloPadre->idrol)
            ->where('acceso', true)
            ->exists();

        if (!$tieneAcceso) {
            Log::warning('Usuario sin acceso al módulo', [
                'modulo' => $moduloPadre->modulo,
                'idrol' => $moduloPadre->idrol,
                'usuario' => $usuarioActual->idusuario
            ]);
            return redirect('/produccionProceso')
                ->with('error', 'No tienes acceso a este módulo.');
        }

        $subModulos = $this->moduloService->getSubmodulosPorModuloPrincipal(
            $moduloPrincipal,
            $usuarioActual->idusuario,
            $moduloPadre
        );

        // Permitir mostrar la vista aunque no haya submódulos (pueden haberse eliminado)
        return view('modulos.submodulos', [
            'moduloPrincipal' => $moduloPadre->modulo,
            'subModulos' => $subModulos,
            'rango' => ['inicio' => $moduloPadre->orden, 'nombre' => $moduloPadre->modulo]
        ]);
    }

    /**
     * Mostrar submódulos de nivel 3
     */
    public function showSubModulosNivel3(string $moduloPadre = '104')
    {
        $usuarioActual = Auth::user();

        try {
            $subModulos = $this->moduloService->getSubmodulosNivel3(
                $moduloPadre,
                $usuarioActual->idusuario
            );

            $moduloPadreInfo = SYSRoles::where('orden', $moduloPadre)->first();

            return view('modulos.submodulos', [
                'moduloPrincipal' => $moduloPadreInfo->modulo ?? "Submódulos de $moduloPadre",
                'subModulos' => $subModulos,
                'rango' => [
                    'inicio' => $moduloPadre,
                    'nombre' => $moduloPadreInfo->modulo ?? 'Submódulos'
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener submódulos nivel 3', [
                'error' => $e->getMessage(),
                'modulo_padre' => $moduloPadre
            ]);
            return redirect('/produccionProceso')
                ->with('error', 'Error al cargar los submódulos');
        }
    }

    /**
     * API: Obtener submódulos de un módulo principal (para precarga)
     */
    public function getSubModulosAPI(string $moduloPrincipal)
    {
        $usuarioActual = Auth::user();

        if (!$usuarioActual) {
            return response()->json(['error' => 'No autenticado'], 401);
        }

        try {
            $subModulos = $this->moduloService->getSubmodulosPorModuloPrincipal(
                $moduloPrincipal,
                $usuarioActual->idusuario
            );

            return response()->json($subModulos->values());
        } catch (\Exception $e) {
            Log::error('Error al obtener submódulos API', [
                'error' => $e->getMessage(),
                'modulo' => $moduloPrincipal
            ]);
            return response()->json(['error' => 'Error al obtener submódulos'], 500);
        }
    }

    /**
     * API: Obtener ruta del módulo padre basado en la ruta actual
     */
    public function getModuloPadre(Request $request)
    {
        try {
            $rutaActual = $request->input('ruta', $request->path());

            // Normalizar ruta
            $rutaActual = '/' . ltrim($rutaActual, '/');

            // Buscar módulo por ruta exacta primero
            $modulo = SYSRoles::where('Ruta', $rutaActual)->first();

            // Si no encuentra, buscar por coincidencia (la ruta más específica que coincida)
            if (!$modulo) {
                $modulo = SYSRoles::where('Ruta', 'LIKE', $rutaActual . '%')
                    ->orderByRaw("CASE WHEN Ruta = ? THEN 0 ELSE 1 END", [$rutaActual])
                    ->orderByRaw('LENGTH(Ruta) DESC')
                    ->orderBy('Nivel', 'desc')
                    ->first();
            }

            // Si aún no encuentra, intentar buscar por partes de la ruta
            if (!$modulo) {
                $partes = array_filter(explode('/', trim($rutaActual, '/')));
                if (count($partes) > 0) {
                    $ultimaParte = end($partes);
                    $modulo = SYSRoles::where('Ruta', 'LIKE', '%' . $ultimaParte . '%')
                        ->orderByRaw('LENGTH(Ruta) DESC')
                        ->orderBy('Nivel', 'desc')
                        ->first();
                }
            }

            if (!$modulo) {
                return response()->json([
                    'success' => false,
                    'rutaPadre' => '/produccionProceso'
                ]);
            }

            // Si es nivel 1, ir a produccionProceso
            if ($modulo->Nivel == 1) {
                return response()->json([
                    'success' => true,
                    'rutaPadre' => '/produccionProceso'
                ]);
            }

            // Si tiene dependencia, buscar el módulo padre
            if ($modulo->Dependencia) {
                $moduloPadre = SYSRoles::where('orden', $modulo->Dependencia)->first();

                if ($moduloPadre && $moduloPadre->Ruta) {
                    return response()->json([
                        'success' => true,
                        'rutaPadre' => $moduloPadre->Ruta
                    ]);
                }
            }

            // Fallback
            return response()->json([
                'success' => false,
                'rutaPadre' => '/produccionProceso'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener módulo padre', [
                'error' => $e->getMessage(),
                'ruta' => $request->input('ruta')
            ]);

            return response()->json([
                'success' => false,
                'rutaPadre' => '/produccionProceso'
            ]);
        }
    }
}
