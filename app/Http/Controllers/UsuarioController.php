<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUsuarioRequest;
use App\Models\Sistema\SysDepartamentos;
use App\Models\Sistema\SYSRoles;
use App\Models\Sistema\SYSUsuariosRoles;
use App\Models\Sistema\Usuario;
use App\Repositories\UsuarioRepository;
use App\Services\ModuloService;
use App\Services\PermissionService;
use App\Services\UsuarioService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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

        if (! $usuarioActual || ! $usuarioActual->numero_empleado) {
            return redirect()->route('login')
                ->with('error', 'Debes iniciar sesión para acceder a los módulos');
        }

        return view('produccionProceso', [
            'modulos' => $this->moduloService->getModulosPrincipalesPorUsuario($usuarioActual->idusuario),
        ]);
    }

    /**
     * Mostrar formulario de creación de usuario
     */
    public function create()
    {
        $modulos = $this->moduloService->getAllModulos();
        $departamentos = SysDepartamentos::orderBy('Depto')->get();

        $modulosDescendientes = $this->obtenerDescendientesPorIdrolRaiz($modulos);

        return view('modulos.usuarios.form_usuario', [
            'usuario' => null,
            'modulos' => $modulos,
            'permisosUsuario' => collect(),
            'departamentos' => $departamentos,
            'isEdit' => false,
            'modulosDescendientes' => $modulosDescendientes,
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
            $permisos = array_filter($request->all(), function ($key) {
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
     * Listar todos los usuarios (sin paginación)
     */
    public function select(Request $request)
    {
        $usuarios = $this->usuarioRepository->getAllForSelect();

        return view('modulos.usuarios.select', [
            'usuarios' => $usuarios,
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

        if (! $usuario) {
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

        if (! $usuario) {
            return redirect()->route('configuracion.usuarios.select')
                ->with('error', 'Usuario no encontrado');
        }

        $modulos = $this->moduloService->getAllModulos();
        $permisosUsuario = $this->permissionService->getAllPermisosUsuario($usuario->idusuario);
        $departamentos = SysDepartamentos::orderBy('Depto')->get();

        $modulosDescendientes = $this->obtenerDescendientesPorIdrolRaiz($modulos);

        return view('modulos.usuarios.form_usuario', [
            'usuario' => $usuario,
            'modulos' => $modulos,
            'permisosUsuario' => $permisosUsuario,
            'departamentos' => $departamentos,
            'isEdit' => true,
            'modulosDescendientes' => $modulosDescendientes,
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

            // Extraer permisos del request (todos los campos que empiezan con "modulo_")
            $permisos = array_filter($request->all(), function ($key) {
                return strpos($key, 'modulo_') === 0;
            }, ARRAY_FILTER_USE_KEY);

            // Actualizar usuario y permisos desde el formulario
            $actualizado = $this->usuarioService->update($id, $data, $foto, $permisos);

            if (! $actualizado) {
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
                'error' => $e->getMessage(),
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

            if (! $usuario) {
                return redirect()->route('configuracion.usuarios.select')
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
                'error' => $e->getMessage(),
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
            $campo = $request->input('campo'); // 'acceso', 'crear', 'modificar', 'eliminar', 'registrar'
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
                        'assigned_at' => now(),
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
                    'registrar' => $campo === 'registrar' ? $valor : 0,
                    'assigned_at' => now(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Permiso actualizado correctamente',
            ]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar permiso', [
                'usuario_id' => $id,
                'idrol' => $idrol,
                'campo' => $campo,
                'valor' => $valor,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el permiso',
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

        if (! $moduloConfiguracion) {
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
            'subModulos' => $subModulos,
        ]);
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
        // Optimización: Usa índice IX_SYSRoles_orden para búsqueda rápida
        if (! $moduloPadre && is_numeric($moduloPrincipal)) {
            $moduloPadre = SYSRoles::where('Nivel', 1)
                ->whereNull('Dependencia')
                ->where('orden', $moduloPrincipal)
                ->select('idrol', 'orden', 'modulo', 'imagen', 'Ruta', 'Nivel', 'Dependencia')
                ->first();
        }

        // Si aún no encuentra, intentar buscar por ruta exacta de la URL
        // Optimización: Ruta está en INCLUDE del índice IX_SYSRoles_Nivel_Dependencia
        if (! $moduloPadre) {
            $rutaBuscada = '/'.ltrim($moduloPrincipal, '/');
            $moduloPadre = SYSRoles::where('Nivel', 1)
                ->whereNull('Dependencia')
                ->where('Ruta', $rutaBuscada)
                ->select('idrol', 'orden', 'modulo', 'imagen', 'Ruta', 'Nivel', 'Dependencia')
                ->first();
        }

        if (! $moduloPadre) {
            Log::warning('Módulo no encontrado', [
                'modulo_principal' => $moduloPrincipal,
                'usuario' => $usuarioActual->idusuario,
            ]);

            return redirect('/produccionProceso')
                ->with('error', 'Módulo no encontrado. Puede que haya sido eliminado o no tengas acceso.');
        }

        // Verificar que el usuario tenga acceso a este módulo
        // Optimización: Usa índice IX_SYSUsuariosRoles_idrol_idusuario_acceso
        $tieneAcceso = SYSUsuariosRoles::where('idrol', $moduloPadre->idrol)
            ->where('idusuario', $usuarioActual->idusuario)
            ->where('acceso', true)
            ->exists();

        if (! $tieneAcceso) {
            Log::warning('Usuario sin acceso al módulo', [
                'modulo' => $moduloPadre->modulo,
                'idrol' => $moduloPadre->idrol,
                'usuario' => $usuarioActual->idusuario,
            ]);

            return redirect('/produccionProceso')
                ->with('error', 'No tienes acceso a este módulo.');
        }

        $subModulos = $this->moduloService->getSubmodulosPorModuloPrincipal(
            $moduloPrincipal,
            $usuarioActual->idusuario,
            $moduloPadre
        );

        // Si el módulo no tiene submódulos pero sí una Ruta propia (página directa),
        // redirigir a esa página en lugar de mostrar un grid vacío. Esto permite que
        // enlaces antiguos tipo /submodulos/{orden} apunten a la Ruta real del módulo
        // (p. ej. /submodulos/1000 → /trazabilidad).
        if (count($subModulos) === 0) {
            $rutaPropia = trim((string) ($moduloPadre->Ruta ?? ''));
            if ($rutaPropia !== '' && ! str_starts_with($rutaPropia, '/submodulos')) {
                $rutaNormalizada = '/'.ltrim(str_replace('\\', '/', $rutaPropia), '/');
                // Evitar bucle si justamente se llegó por la propia Ruta del módulo
                if ($rutaNormalizada !== '/'.ltrim($moduloPrincipal, '/')) {
                    return redirect($rutaNormalizada);
                }
            }
        }

        // Permitir mostrar la vista aunque no haya submódulos (pueden haberse eliminado)
        return view('modulos.submodulos', [
            'moduloPrincipal' => $moduloPadre->modulo,
            'subModulos' => $subModulos,
        ]);
    }

    /**
     * Mostrar submódulos de nivel 3 de un módulo padre, identificado por su orden.
     */
    public function showSubModulosNivel3(string $moduloPadre = '104')
    {
        $moduloPadreInfo = SYSRoles::where('orden', $moduloPadre)
            ->select('orden', 'modulo')
            ->first();

        if (! $moduloPadreInfo) {
            Log::warning('Módulo padre de nivel 3 no encontrado', ['modulo_padre' => $moduloPadre]);

            return redirect(ModuloService::RUTA_INICIO)
                ->with('error', 'Módulo no encontrado. Puede que haya sido eliminado.');
        }

        return view('modulos.submodulos', [
            'moduloPrincipal' => $moduloPadreInfo->modulo,
            'subModulos' => $this->moduloService->getSubmodulosNivel3(
                $moduloPadre,
                Auth::user()->idusuario
            ),
        ]);
    }

    /**
     * Obtener para cada módulo de Nivel 1 el listado de idrol de sus descendientes (Nivel 2 y 3).
     * Se usa en el formulario de usuario para cascada de permisos.
     *
     * @param  Collection  $modulos
     * @return array [ idrol_raiz => [ idrol_hijo1, idrol_hijo2, ... ], ... ]
     */
    private function obtenerDescendientesPorIdrolRaiz($modulos): array
    {
        $porOrden = $modulos->keyBy('orden');
        $descendientes = [];

        foreach ($modulos as $m) {
            if ((int) $m->Nivel === 1) {
                $descendientes[$m->idrol] = [];
            }
        }

        foreach ($modulos as $m) {
            if ((int) $m->Nivel === 2 && $m->Dependencia !== null) {
                $raiz = $porOrden->get($m->Dependencia);
                if ($raiz && (int) $raiz->Nivel === 1) {
                    $descendientes[$raiz->idrol][] = $m->idrol;
                }
            } elseif ((int) $m->Nivel === 3 && $m->Dependencia !== null) {
                $padre = $porOrden->get($m->Dependencia);
                if ($padre && (int) $padre->Nivel === 2 && $padre->Dependencia !== null) {
                    $raiz = $porOrden->get($padre->Dependencia);
                    if ($raiz && (int) $raiz->Nivel === 1) {
                        $descendientes[$raiz->idrol][] = $m->idrol;
                    }
                }
            }
        }

        return $descendientes;
    }
}
