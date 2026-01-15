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
     * Mostrar la vista principal de gestiÃ³n de mÃ³dulos
     */
    public function index()
    {
        try {
            // Obtener todos los mÃ³dulos y mÃ³dulos principales para selects
            $modulos = SYSRoles::orderBy('orden', 'ASC')->get();
            $modulosPrincipales = SYSRoles::where('Nivel', 1)
                ->whereNull('Dependencia')
                ->orderBy('orden', 'ASC')
                ->get();

            return view('modulos.gestion-modulos.index', compact('modulos', 'modulosPrincipales'));
        } catch (\Exception $e) {
            Log::error('Error al cargar mÃ³dulos: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error al cargar los mÃ³dulos');
        }
    }

    /**
     * Mostrar el formulario para crear un nuevo mÃ³dulo
     */
    public function create()
    {
        // Obtener mÃ³dulos principales para usar como dependencias
        $modulosPrincipales = SYSRoles::where('Nivel', 1)
            ->whereNull('Dependencia')
            ->orderBy('orden')
            ->get();

        return view('modulos.gestion-modulos.create', compact('modulosPrincipales'));
    }

    /**
     * Almacenar un nuevo mÃ³dulo
     *
     * LÃ“GICA DE CREACIÃ“N DE MÃ“DULOS:
     * ================================
     *
     * 1. CAMPOS REQUERIDOS:
     *    - orden: Identificador Ãºnico (ej: "300", "304", "401-1")
     *    - modulo: Nombre descriptivo del mÃ³dulo
     *    - Nivel: 1 (Principal), 2 (SubmÃ³dulo nivel 2), 3 (SubmÃ³dulo nivel 3)
     *
     * 2. JERARQUÃA DE MÃ“DULOS:
     *    - Nivel 1: MÃ³dulos principales (Dependencia = NULL)
     *      Ejemplo: orden="300", modulo="Reportes Urdido", Nivel=1, Dependencia=NULL
     *
     *    - Nivel 2: SubmÃ³dulos que dependen de un mÃ³dulo Nivel 1
     *      Ejemplo: orden="304", modulo="CatÃ¡logos Julios", Nivel=2, Dependencia="300"
     *
     *    - Nivel 3: SubmÃ³dulos que dependen de un mÃ³dulo Nivel 2
     *      Ejemplo: orden="401-1", modulo="ProducciÃ³n Engomado", Nivel=3, Dependencia="401"
     *
     * 3. REGLAS DE VALIDACIÃ“N:
     *    - El campo "orden" debe ser Ãºnico en toda la tabla
     *    - Si Nivel=1, entonces Dependencia debe ser NULL
     *    - Si Nivel=2 o 3, entonces Dependencia debe existir en otro mÃ³dulo
     *    - La Dependencia debe apuntar al campo "orden" del mÃ³dulo padre
     *
     * 4. PERMISOS (CHECKBOXES):
     *    - acceso: Permite acceder al mÃ³dulo
     *    - crear: Permite crear registros en el mÃ³dulo
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

            // Validar campos bÃ¡sicos
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
                    ->withErrors(['orden' => 'El orden "' . $request->orden . '" ya existe. Debe ser Ãºnico.'])
                    ->withInput();
            }

            // Validar jerarquÃ­a de mÃ³dulos
            $nivel = (int) $request->Nivel;
            $dependencia = $request->Dependencia;

            if ($nivel === 1 && !empty($dependencia)) {
                return back()
                    ->withErrors(['Dependencia' => 'Los mÃ³dulos de Nivel 1 no deben tener dependencia.'])
                    ->withInput();
            }

            if ($nivel > 1 && empty($dependencia)) {
                return back()
                    ->withErrors(['Dependencia' => 'Los mÃ³dulos de Nivel ' . $nivel . ' deben tener una dependencia.'])
                    ->withInput();
            }

            // Si tiene dependencia, verificar que el mÃ³dulo padre exista
            if (!empty($dependencia)) {
                $moduloPadre = SYSRoles::where('orden', $dependencia)->first();

                if (!$moduloPadre) {
                    return back()
                        ->withErrors(['Dependencia' => 'El mÃ³dulo padre con orden "' . $dependencia . '" no existe.'])
                        ->withInput();
                }

                // Validar que el nivel sea correcto respecto al padre
                if ($nivel <= $moduloPadre->Nivel) {
                    return back()
                        ->withErrors(['Nivel' => 'El nivel del submÃ³dulo debe ser mayor que el nivel del mÃ³dulo padre (' . $moduloPadre->Nivel . ').'])
                        ->withInput();
                }
            }

            // Preparar datos para inserciÃ³n
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


            // Crear el mÃ³dulo
            $modulo = SYSRoles::create($data);

            // Actualizar permisos en SYSUsuariosRoles para el nuevo mÃ³dulo
            $permisosActualizados = $this->actualizarPermisosNuevoModulo($modulo);

            // Limpiar cachÃ© de mÃ³dulos para todos los usuarios
            $this->limpiarCacheTodosUsuarios();

            $mensaje = 'MÃ³dulo creado correctamente';
            if ($permisosActualizados > 0) {
                $mensaje .= " y permisos actualizados para {$permisosActualizados} usuario(s)";
            }

            return redirect()->route($this->getModulosIndexRoute())
                ->with('success', $mensaje)
                ->with('show_sweetalert', true);

        } catch (\Exception $e) {
            Log::error('Error al crear mÃ³dulo: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
                'user_authenticated' => Auth::check(),
                'request_data' => $request->all()
            ]);

            return back()
                ->with('error', 'Error al crear el mÃ³dulo: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Mostrar el formulario para editar un mÃ³dulo
     */
    public function edit($id)
    {
        try {

            $modulo = SYSRoles::findOrFail($id);

            // Obtener mÃ³dulos principales para usar como dependencias
            $modulosPrincipales = SYSRoles::where('Nivel', 1)
                ->whereNull('Dependencia')
                ->where('idrol', '!=', $modulo->idrol) // Excluir el mÃ³dulo actual
                ->orderBy('orden')
                ->get();

            return view('modulos.gestion-modulos.edit', compact('modulo', 'modulosPrincipales'));

        } catch (\Exception $e) {
            return redirect()->route($this->getModulosIndexRoute())
                ->with('error', 'Error al cargar el mÃ³dulo para editar: ' . $e->getMessage());
        }
    }

    /**
     * Mostrar el formulario simplificado para editar un mÃ³dulo
     */
    public function editSimple($id)
    {
        try {
            $modulo = SYSRoles::findOrFail($id);

            // Obtener mÃ³dulos principales para usar como dependencias
            $modulosPrincipales = SYSRoles::where('Nivel', 1)
                ->whereNull('Dependencia')
                ->where('idrol', '!=', $modulo->idrol) // Excluir el mÃ³dulo actual
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

            return redirect()->route($this->getModulosIndexRoute())
                ->with('error', 'Error al cargar el mÃ³dulo para editar: ' . $e->getMessage());
        }
    }

    /**
     * Actualizar un mÃ³dulo existente
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

            // Verificar que el orden no exista en otro mÃ³dulo
            $ordenExistente = SYSRoles::where('orden', $request->orden)
                ->where('idrol', '!=', $modulo->idrol)
                ->exists();

            if ($ordenExistente) {
                return back()->withErrors(['orden' => 'El orden ya existe en otro mÃ³dulo'])->withInput();
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

            // Limpiar cachÃ© de mÃ³dulos para todos los usuarios
            $this->limpiarCacheTodosUsuarios();

            return redirect()->route($this->getModulosIndexRoute())
                ->with('success', 'MÃ³dulo actualizado correctamente')
                ->with('show_sweetalert', true);

        } catch (\Exception $e) {
            Log::error('Error al actualizar mÃ³dulo: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
                'user_authenticated' => Auth::check(),
                'module_id' => $id
            ]);
            return back()->with('error', 'Error al actualizar el mÃ³dulo: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Eliminar un mÃ³dulo
     */
    public function destroy($id)
    {
        try {
            $modulo = SYSRoles::findOrFail($id);

            // Verificar si el mÃ³dulo tiene submÃ³dulos dependientes
            $tieneSubmodulos = SYSRoles::where('Dependencia', $modulo->orden)->exists();

            if ($tieneSubmodulos) {
                return back()->with('error', 'No se puede eliminar el mÃ³dulo porque tiene submÃ³dulos dependientes');
            }

            $nombreModulo = $modulo->modulo;

            // Eliminar registros relacionados en SYSUsuariosRoles
            SYSUsuariosRoles::where('idrol', $modulo->idrol)->delete();

            $modulo->delete();

            // Limpiar cachÃ©
            $this->limpiarCacheTodosUsuarios();

            return redirect()->route($this->getModulosIndexRoute())
                ->with('success', "MÃ³dulo '{$nombreModulo}' y permisos asociados eliminados correctamente");

        } catch (\Exception $e) {
            Log::error('Error al eliminar mÃ³dulo: ' . $e->getMessage());
            return back()->with('error', 'Error al eliminar el mÃ³dulo');
        }
    }

    /**
     * Sincronizar permisos de un mÃ³dulo para todos los usuarios (vÃ­a AJAX)
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
     * Obtener mÃ³dulos por nivel (API)
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
                'message' => 'Error al obtener mÃ³dulos'
            ], 500);
        }
    }

    /**
     * Obtener submÃ³dulos de un mÃ³dulo padre (API)
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
                'message' => 'Error al obtener submÃ³dulos'
            ], 500);
        }
    }

    /**
     * Duplicar un mÃ³dulo
     */
    public function duplicar($id)
    {
        try {
            $modulo = SYSRoles::findOrFail($id);

            // Crear una copia del mÃ³dulo
            $nuevoModulo = $modulo->replicate();
            $nuevoModulo->orden = $modulo->orden . '_copia';
            $nuevoModulo->modulo = $modulo->modulo . ' (Copia)';
            $nuevoModulo->save();

            return redirect()->route($this->getModulosIndexRoute())
                ->with('success', "MÃ³dulo '{$modulo->modulo}' duplicado exitosamente");

        } catch (\Exception $e) {
            Log::error('Error al duplicar mÃ³dulo: ' . $e->getMessage());
            return back()->with('error', 'Error al duplicar el mÃ³dulo');
        }
    }

    /**
     * Cambiar el estado de acceso de un mÃ³dulo
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
                'message' => "Acceso {$estado} para el mÃ³dulo '{$modulo->modulo}'",
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
     * Cambiar el estado de un permiso especÃ­fico de un mÃ³dulo
     */
    public function togglePermiso(Request $request, $id)
    {
        try {
            $modulo = SYSRoles::findOrFail($id);

            // Validar que el campo estÃ© presente y sea vÃ¡lido
            $campo = $request->input('campo');
            $valor = $request->input('valor');

            if (!$campo || !in_array($campo, ['crear', 'modificar', 'eliminar', 'reigstrar'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Campo invÃ¡lido: ' . $campo
                ], 400);
            }

            // Convertir valor a boolean
            $valorBool = (bool) $valor;

            // Actualizar el campo especÃ­fico
            $modulo->$campo = $valorBool;
            $modulo->save();

            $estado = $valorBool ? 'activado' : 'desactivado';
            $nombreCampo = ucfirst($campo);

            return response()->json([
                'success' => true,
                'message' => "Permiso '{$nombreCampo}' {$estado} para el mÃ³dulo '{$modulo->modulo}'",
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
     * Actualizar permisos para todos los usuarios cuando se crea un nuevo mÃ³dulo
     *
     * @param SYSRoles $modulo El mÃ³dulo reciÃ©n creado
     * @return int NÃºmero de registros actualizados
     */
    /**
     * Obtener la ruta de regreso para el listado de modulos segun el contexto actual
     */
    private function getModulosIndexRoute(): string
    {
        if (request()->routeIs('modulos.sin.auth.*')) {
            return 'modulos.sin.auth.index';
        }

        if (request()->routeIs('configuracion.utileria.modulos.*')) {
            return 'configuracion.utileria.modulos.index';
        }

        if (request()->routeIs('configuracion.modulos.*') || request()->routeIs('modulos.*')) {
            return 'configuracion.modulos.index';
        }

        return 'configuracion.utileria.modulos.index';
    }

    /**
     * Actualizar permisos para todos los usuarios cuando se crea un nuevo modulo
     *
     * @param SYSRoles $modulo El modulo recien creado
     * @return int Numero de registros actualizados
     */
    private function actualizarPermisosNuevoModulo(SYSRoles $modulo): int
    {
        try {
            // Obtener todos los usuarios
            $usuarios = SYSUsuario::select('idusuario')->get();
            $registrosActualizados = 0;

            foreach ($usuarios as $usuario) {
                // Verificar si ya existe un registro para este usuario y mÃ³dulo
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
            Log::error('Error al actualizar permisos del nuevo mÃ³dulo: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Limpiar cachÃ© de mÃ³dulos para todos los usuarios
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


