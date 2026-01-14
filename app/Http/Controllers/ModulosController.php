<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sistema\SYSRoles;
use App\Models\Sistema\SYSUsuario;
use App\Models\Sistema\SYSUsuariosRoles;
use App\Services\ModuloService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class ModulosController extends Controller
{
    protected $moduloService;

    public function __construct(ModuloService $moduloService)
    {
        $this->moduloService = $moduloService;
    }

    /**
     * Mostrar la vista principal de gestión de módulos
     */
    public function index()
    {
        try {
            // Obtener todos los módulos y módulos principales para selects
            $modulos = SYSRoles::orderBy('orden', 'ASC')->get();
            $modulosPrincipales = SYSRoles::where('Nivel', 1)
                ->whereNull('Dependencia')
                ->orderBy('orden', 'ASC')
                ->get();

            return view('modulos.gestion-modulos.index', compact('modulos', 'modulosPrincipales'));
        } catch (\Exception $e) {
            Log::error('Error al cargar módulos: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error al cargar los módulos');
        }
    }

    /**
     * Mostrar el formulario para crear un nuevo módulo
     */
    public function create()
    {
        // Obtener módulos principales para usar como dependencias
        $modulosPrincipales = SYSRoles::where('Nivel', 1)
            ->whereNull('Dependencia')
            ->orderBy('orden')
            ->get();

        return view('modulos.gestion-modulos.create', compact('modulosPrincipales'));
    }

    /**
     * Almacenar un nuevo módulo
     *
     * LÓGICA DE CREACIÓN DE MÓDULOS:
     * ================================
     *
     * 1. CAMPOS REQUERIDOS:
     *    - orden: Identificador único (ej: "300", "304", "401-1")
     *    - modulo: Nombre descriptivo del módulo
     *    - Nivel: 1 (Principal), 2 (Submódulo nivel 2), 3 (Submódulo nivel 3)
     *
     * 2. JERARQUÍA DE MÓDULOS:
     *    - Nivel 1: Módulos principales (Dependencia = NULL)
     *      Ejemplo: orden="300", modulo="Reportes Urdido", Nivel=1, Dependencia=NULL
     *
     *    - Nivel 2: Submódulos que dependen de un módulo Nivel 1
     *      Ejemplo: orden="304", modulo="Catálogos Julios", Nivel=2, Dependencia="300"
     *
     *    - Nivel 3: Submódulos que dependen de un módulo Nivel 2
     *      Ejemplo: orden="401-1", modulo="Producción Engomado", Nivel=3, Dependencia="401"
     *
     * 3. REGLAS DE VALIDACIÓN:
     *    - El campo "orden" debe ser único en toda la tabla
     *    - Si Nivel=1, entonces Dependencia debe ser NULL
     *    - Si Nivel=2 o 3, entonces Dependencia debe existir en otro módulo
     *    - La Dependencia debe apuntar al campo "orden" del módulo padre
     *
     * 4. PERMISOS (CHECKBOXES):
     *    - acceso: Permite acceder al módulo
     *    - crear: Permite crear registros en el módulo
     *    - modificar: Permite modificar registros
     *    - eliminar: Permite eliminar registros
     *    - reigstrar: Permiso especial de registro
     *
     * 5. IMAGEN (OPCIONAL):
     *    - Se almacena en public/images/fotos_modulos/
     *    - El nombre del archivo se guarda en el campo "imagen"
     */
    public function store(Request $request)
    {
        try {

            // Validar campos básicos
            $validator = Validator::make($request->all(), [
                'orden' => 'required|string|max:50',
                'modulo' => 'required|string|max:255',
                'acceso' => 'boolean',
                'crear' => 'boolean',
                'modificar' => 'boolean',
                'eliminar' => 'boolean',
                'reigstrar' => 'boolean',
                'imagen_archivo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'Dependencia' => 'nullable|string|max:50',
                'Nivel' => 'required|integer|min:1|max:3',
            ]);

            if ($validator->fails()) {
                return back()->withErrors($validator)->withInput();
            }

            // Verificar que el orden no exista
            $ordenExistente = SYSRoles::where('orden', $request->orden)->exists();
            if ($ordenExistente) {
                return back()
                    ->withErrors(['orden' => 'El orden "' . $request->orden . '" ya existe. Debe ser único.'])
                    ->withInput();
            }

            // Validar jerarquía de módulos
            $nivel = (int) $request->Nivel;
            $dependencia = $request->Dependencia;

            if ($nivel === 1 && !empty($dependencia)) {
                return back()
                    ->withErrors(['Dependencia' => 'Los módulos de Nivel 1 no deben tener dependencia.'])
                    ->withInput();
            }

            if ($nivel > 1 && empty($dependencia)) {
                return back()
                    ->withErrors(['Dependencia' => 'Los módulos de Nivel ' . $nivel . ' deben tener una dependencia.'])
                    ->withInput();
            }

            // Si tiene dependencia, verificar que el módulo padre exista
            if (!empty($dependencia)) {
                $moduloPadre = SYSRoles::where('orden', $dependencia)->first();

                if (!$moduloPadre) {
                    return back()
                        ->withErrors(['Dependencia' => 'El módulo padre con orden "' . $dependencia . '" no existe.'])
                        ->withInput();
                }

                // Validar que el nivel sea correcto respecto al padre
                if ($nivel <= $moduloPadre->Nivel) {
                    return back()
                        ->withErrors(['Nivel' => 'El nivel del submódulo debe ser mayor que el nivel del módulo padre (' . $moduloPadre->Nivel . ').'])
                        ->withInput();
                }
            }

            // Preparar datos para inserción
            $data = $request->except(['imagen_archivo', 'from_sweetalert']);

            // Convertir checkboxes a valores booleanos (0 o 1)
            $data['acceso'] = $request->has('acceso') ? 1 : 0;
            $data['crear'] = $request->has('crear') ? 1 : 0;
            $data['modificar'] = $request->has('modificar') ? 1 : 0;
            $data['eliminar'] = $request->has('eliminar') ? 1 : 0;
            $data['reigstrar'] = $request->has('reigstrar') ? 1 : 0;

            // Si nivel es 1, asegurar que Dependencia sea NULL
            if ($nivel === 1) {
                $data['Dependencia'] = null;
            }

            // Manejar subida de imagen
            if ($request->hasFile('imagen_archivo')) {
                $imagen = $request->file('imagen_archivo');
                $nombreImagen = time() . '_' . $imagen->getClientOriginalName();

                // Crear directorio si no existe
                $rutaImagenes = public_path('images/fotos_modulos');
                if (!file_exists($rutaImagenes)) {
                    mkdir($rutaImagenes, 0777, true);
                }

                $imagen->move($rutaImagenes, $nombreImagen);
                $data['imagen'] = $nombreImagen;
            } else {
                $data['imagen'] = null;
            }


            // Crear el módulo
            $modulo = SYSRoles::create($data);

            // Actualizar permisos en SYSUsuariosRoles para el nuevo módulo
            $permisosActualizados = $this->actualizarPermisosNuevoModulo($modulo);

            // Limpiar caché de módulos para todos los usuarios
            $this->limpiarCacheTodosUsuarios();

            $mensaje = 'Módulo creado correctamente';
            if ($permisosActualizados > 0) {
                $mensaje .= " y permisos actualizados para {$permisosActualizados} usuario(s)";
            }

            return redirect()->route('configuracion.utileria.modulos')
                ->with('success', $mensaje)
                ->with('show_sweetalert', true);

        } catch (\Exception $e) {
            Log::error('Error al crear módulo: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
                'user_authenticated' => Auth::check(),
                'request_data' => $request->all()
            ]);

            return back()
                ->with('error', 'Error al crear el módulo: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Mostrar el formulario para editar un módulo
     */
    public function edit($id)
    {
        try {

            $modulo = SYSRoles::findOrFail($id);

            // Obtener módulos principales para usar como dependencias
            $modulosPrincipales = SYSRoles::where('Nivel', 1)
                ->whereNull('Dependencia')
                ->where('idrol', '!=', $modulo->idrol) // Excluir el módulo actual
                ->orderBy('orden')
                ->get();

            return view('modulos.gestion-modulos.edit', compact('modulo', 'modulosPrincipales'));

        } catch (\Exception $e) {
            return redirect()->route('configuracion.utileria.modulos')
                ->with('error', 'Error al cargar el módulo para editar: ' . $e->getMessage());
        }
    }

    /**
     * Mostrar el formulario simplificado para editar un módulo
     */
    public function editSimple($id)
    {
        try {
            $modulo = SYSRoles::findOrFail($id);

            // Obtener módulos principales para usar como dependencias
            $modulosPrincipales = SYSRoles::where('Nivel', 1)
                ->whereNull('Dependencia')
                ->where('idrol', '!=', $modulo->idrol) // Excluir el módulo actual
                ->orderBy('orden')
                ->get();

            return view('modulos.gestion-modulos.edit-simple', compact('modulo', 'modulosPrincipales'));

        } catch (\Exception $e) {
            Log::error('Error in editSimple method', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
                'user_authenticated' => Auth::check()
            ]);

            return redirect()->route('configuracion.utileria.modulos')
                ->with('error', 'Error al cargar el módulo para editar: ' . $e->getMessage());
        }
    }

    /**
     * Actualizar un módulo existente
     */
    public function update(Request $request, $id)
    {
        try {

            $modulo = SYSRoles::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'orden' => 'required|string|max:50',
                'modulo' => 'required|string|max:255',
                'acceso' => 'boolean',
                'crear' => 'boolean',
                'modificar' => 'boolean',
                'eliminar' => 'boolean',
                'reigstrar' => 'boolean',
                'imagen_archivo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'Dependencia' => 'nullable|string|max:50',
                'Nivel' => 'required|integer|min:1|max:3',
            ]);

            if ($validator->fails()) {
                return back()->withErrors($validator)->withInput();
            }

            // Verificar que el orden no exista en otro módulo
            $ordenExistente = SYSRoles::where('orden', $request->orden)
                ->where('idrol', '!=', $modulo->idrol)
                ->exists();

            if ($ordenExistente) {
                return back()->withErrors(['orden' => 'El orden ya existe en otro módulo'])->withInput();
            }

            $data = $request->except(['imagen_archivo']);

            // Asegurar que los checkboxes tengan valores booleanos correctos
            $data['acceso'] = $request->has('acceso') ? true : false;
            $data['crear'] = $request->has('crear') ? true : false;
            $data['modificar'] = $request->has('modificar') ? true : false;
            $data['eliminar'] = $request->has('eliminar') ? true : false;
            $data['reigstrar'] = $request->has('reigstrar') ? true : false;

            // Manejar subida de nueva imagen
            if ($request->hasFile('imagen_archivo')) {
                // Eliminar imagen anterior si existe
                if ($modulo->imagen && file_exists(public_path('images/fotos_modulos/' . $modulo->imagen))) {
                    unlink(public_path('images/fotos_modulos/' . $modulo->imagen));
                }

                $imagen = $request->file('imagen_archivo');
                $nombreImagen = time() . '_' . $imagen->getClientOriginalName();
                $imagen->move(public_path('images/fotos_modulos'), $nombreImagen);
                $data['imagen'] = $nombreImagen;
            }

            $modulo->update($data);

            // Limpiar caché de módulos para todos los usuarios
            $this->limpiarCacheTodosUsuarios();

            return redirect()->route('configuracion.utileria.modulos')
                ->with('success', 'Módulo actualizado correctamente')
                ->with('show_sweetalert', true);

        } catch (\Exception $e) {
            Log::error('Error al actualizar módulo: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
                'user_authenticated' => Auth::check(),
                'module_id' => $id
            ]);
            return back()->with('error', 'Error al actualizar el módulo: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Eliminar un módulo
     */
    public function destroy($id)
    {
        try {
            $modulo = SYSRoles::findOrFail($id);

            // Verificar si el módulo tiene submódulos dependientes
            $tieneSubmodulos = SYSRoles::where('Dependencia', $modulo->orden)->exists();

            if ($tieneSubmodulos) {
                return back()->with('error', 'No se puede eliminar el módulo porque tiene submódulos dependientes');
            }

            $nombreModulo = $modulo->modulo;

            // Eliminar registros relacionados en SYSUsuariosRoles
            SYSUsuariosRoles::where('idrol', $modulo->idrol)->delete();

            $modulo->delete();

            // Limpiar caché
            $this->limpiarCacheTodosUsuarios();

            return redirect()->route('configuracion.utileria.modulos')
                ->with('success', "Módulo '{$nombreModulo}' y permisos asociados eliminados correctamente");

        } catch (\Exception $e) {
            Log::error('Error al eliminar módulo: ' . $e->getMessage());
            return back()->with('error', 'Error al eliminar el módulo');
        }
    }

    /**
     * Sincronizar permisos de un módulo para todos los usuarios (vía AJAX)
     */
    public function sincronizarPermisos($id)
    {
        try {
            $modulo = SYSRoles::findOrFail($id);
            $registrosActualizados = $this->actualizarPermisosNuevoModulo($modulo);

            return response()->json([
                'success' => true,
                'message' => "Permisos sincronizados para {$registrosActualizados} usuario(s)",
                'registros' => $registrosActualizados
            ]);
        } catch (\Exception $e) {
            Log::error('Error al sincronizar permisos: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al sincronizar permisos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener módulos por nivel (API)
     */
    public function getModulosPorNivel($nivel)
    {
        try {
            $modulos = SYSRoles::where('Nivel', $nivel)
                ->orderBy('orden')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $modulos
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener módulos'
            ], 500);
        }
    }

    /**
     * Obtener submódulos de un módulo padre (API)
     */
    public function getSubmodulos($dependencia)
    {
        try {
            $submodulos = SYSRoles::where('Dependencia', $dependencia)
                ->orderBy('Nivel', 'ASC')
                ->orderBy('orden', 'ASC')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $submodulos
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener submódulos'
            ], 500);
        }
    }

    /**
     * Duplicar un módulo
     */
    public function duplicar($id)
    {
        try {
            $modulo = SYSRoles::findOrFail($id);

            // Crear una copia del módulo
            $nuevoModulo = $modulo->replicate();
            $nuevoModulo->orden = $modulo->orden . '_copia';
            $nuevoModulo->modulo = $modulo->modulo . ' (Copia)';
            $nuevoModulo->save();

            return redirect()->route('configuracion.utileria.modulos')
                ->with('success', "Módulo '{$modulo->modulo}' duplicado exitosamente");

        } catch (\Exception $e) {
            Log::error('Error al duplicar módulo: ' . $e->getMessage());
            return back()->with('error', 'Error al duplicar el módulo');
        }
    }

    /**
     * Cambiar el estado de acceso de un módulo
     */
    public function toggleAcceso($id)
    {
        try {
            $modulo = SYSRoles::findOrFail($id);

            $modulo->acceso = !$modulo->acceso;
            $modulo->save();

            $estado = $modulo->acceso ? 'activado' : 'desactivado';
            return response()->json([
                'success' => true,
                'message' => "Acceso {$estado} para el módulo '{$modulo->modulo}'",
                'acceso' => $modulo->acceso
            ]);

        } catch (\Exception $e) {
            Log::error('Error al cambiar estado de acceso: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar el estado de acceso'
            ], 500);
        }
    }

    /**
     * Cambiar el estado de un permiso específico de un módulo
     */
    public function togglePermiso(Request $request, $id)
    {
        try {
            $modulo = SYSRoles::findOrFail($id);

            // Validar que el campo esté presente y sea válido
            $campo = $request->input('campo');
            $valor = $request->input('valor');

            if (!$campo || !in_array($campo, ['crear', 'modificar', 'eliminar', 'reigstrar'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Campo inválido: ' . $campo
                ], 400);
            }

            // Convertir valor a boolean
            $valorBool = (bool) $valor;

            // Actualizar el campo específico
            $modulo->$campo = $valorBool;
            $modulo->save();

            $estado = $valorBool ? 'activado' : 'desactivado';
            $nombreCampo = ucfirst($campo);

            return response()->json([
                'success' => true,
                'message' => "Permiso '{$nombreCampo}' {$estado} para el módulo '{$modulo->modulo}'",
                'campo' => $campo,
                'valor' => $valorBool
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar el permiso: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar permisos para todos los usuarios cuando se crea un nuevo módulo
     *
     * @param SYSRoles $modulo El módulo recién creado
     * @return int Número de registros actualizados
     */
    private function actualizarPermisosNuevoModulo(SYSRoles $modulo): int
    {
        try {
            // Obtener todos los usuarios
            $usuarios = SYSUsuario::select('idusuario')->get();
            $registrosActualizados = 0;

            foreach ($usuarios as $usuario) {
                // Verificar si ya existe un registro para este usuario y módulo
                $permisoExistente = SYSUsuariosRoles::where('idusuario', $usuario->idusuario)
                    ->where('idrol', $modulo->idrol)
                    ->first();

                if ($permisoExistente) {
                    // Si existe, actualizar los permisos a 1
                    $permisoExistente->update([
                        'acceso' => 1,
                        'crear' => 1,
                        'modificar' => 1,
                        'eliminar' => 1,
                        'registrar' => 1
                    ]);
                    $registrosActualizados++;
                } else {
                    // Si no existe, crear el registro con permisos en 1
                    SYSUsuariosRoles::create([
                        'idusuario' => $usuario->idusuario,
                        'idrol' => $modulo->idrol,
                        'acceso' => 1,
                        'crear' => 1,
                        'modificar' => 1,
                        'eliminar' => 1,
                        'registrar' => 1,
                        'assigned_at' => now()
                    ]);
                    $registrosActualizados++;
                }
            }

            return $registrosActualizados;
        } catch (\Exception $e) {
            Log::error('Error al actualizar permisos del nuevo módulo: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Limpiar caché de módulos para todos los usuarios
     */
    private function limpiarCacheTodosUsuarios(): void
    {
        try {
            $usuarios = SYSUsuario::select('idusuario')->get();
            foreach ($usuarios as $usuario) {
                $this->moduloService->limpiarCacheUsuario($usuario->idusuario);
            }
        } catch (\Exception $e) {
        }
    }
}
