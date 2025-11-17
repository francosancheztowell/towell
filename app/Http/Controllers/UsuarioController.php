<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUsuarioRequest;
use App\Repositories\UsuarioRepository;
use App\Services\ModuloService;
use App\Services\UsuarioService;
use App\Services\PermissionService;
use App\Models\SYSRoles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        return view('modulos.usuarios.form_usuario', [
            'usuario' => null,
            'modulos' => $modulos,
            'permisosUsuario' => collect(),
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

        return view('modulos.usuarios.form_usuario', [
            'usuario' => $usuario,
            'modulos' => $modulos,
            'permisosUsuario' => $permisosUsuario,
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

            // Extraer permisos del request (todos los campos que empiezan con "modulo_")
            $permisos = array_filter($request->all(), function($key) {
                return strpos($key, 'modulo_') === 0;
            }, ARRAY_FILTER_USE_KEY);

            $actualizado = $this->usuarioService->update($id, $data, $foto, $permisos);

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
     * Mostrar módulos de configuración
     */
    public function showConfiguracion()
    {
        $usuarioActual = Auth::user();
        $moduloConfiguracion = SYSRoles::modulosPrincipales()
            ->where('modulo', 'Configuración')
            ->first();

        if (!$moduloConfiguracion) {
            return redirect('/produccionProceso')
                ->with('error', 'Módulo de configuración no encontrado');
        }

        $subModulos = $this->moduloService->getSubmodulosPorModuloPrincipal(
            'configuracion',
            $usuarioActual->idusuario
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
     * Mostrar submódulos de un módulo principal
     */
    public function showSubModulos(string $moduloPrincipal)
    {
        $usuarioActual = Auth::user();
        $moduloPadre = $this->moduloService->buscarModuloPrincipal($moduloPrincipal);

        if (!$moduloPadre) {
            return redirect('/produccionProceso')
                ->with('error', 'Módulo no encontrado');
        }

        $subModulos = $this->moduloService->getSubmodulosPorModuloPrincipal(
            $moduloPrincipal,
            $usuarioActual->idusuario
        );

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
}
