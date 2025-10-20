<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SYSRoles;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class ModulosController extends Controller
{
    /**
     * Mostrar la vista principal de gestión de módulos
     */
    public function index()
    {
        try {
            // Obtener todos los módulos ordenados jerárquicamente
            $modulos = SYSRoles::orderBy('Dependencia', 'ASC')
                ->orderBy('Nivel', 'ASC')
                ->orderBy('orden', 'ASC')
                ->get();

            return view('modulos.gestion-modulos.index', compact('modulos'));
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
     */
    public function store(Request $request)
    {
        try {
            Log::info('Store method called', [
                'user_id' => Auth::id(),
                'user_authenticated' => Auth::check(),
                'request_data' => $request->all()
            ]);

            Log::info('Store request recibido:', $request->all());
            Log::info('Usuario autenticado:', ['user_id' => Auth::id(), 'user' => Auth::user()]);

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
                return back()->withErrors(['orden' => 'El orden ya existe'])->withInput();
            }

            $data = $request->except(['imagen_archivo']);

            // Asegurar que los checkboxes tengan valores booleanos correctos
            $data['acceso'] = $request->has('acceso') ? true : false;
            $data['crear'] = $request->has('crear') ? true : false;
            $data['modificar'] = $request->has('modificar') ? true : false;
            $data['eliminar'] = $request->has('eliminar') ? true : false;
            $data['reigstrar'] = $request->has('reigstrar') ? true : false;

            // Manejar subida de imagen
            if ($request->hasFile('imagen_archivo')) {
                $imagen = $request->file('imagen_archivo');
                $nombreImagen = time() . '_' . $imagen->getClientOriginalName();
                $imagen->move(public_path('images/fotos_modulos'), $nombreImagen);
                $data['imagen'] = $nombreImagen;
            }

            Log::info('Datos a crear:', $data);
            $modulo = SYSRoles::create($data);

            return redirect()->route('configuracion.utileria.modulos.edit', $modulo->idrol)
                ->with('success', "Módulo '{$modulo->modulo}' creado exitosamente");

        } catch (\Exception $e) {
            Log::error('Error al crear módulo: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
                'user_authenticated' => Auth::check()
            ]);
            return back()->with('error', 'Error al crear el módulo: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Mostrar el formulario para editar un módulo
     */
    public function edit($id)
    {
        try {
            Log::info('Edit method called', [
                'id' => $id,
                'user_id' => Auth::id(),
                'user_authenticated' => Auth::check(),
                'user' => Auth::user()
            ]);

            $modulo = SYSRoles::findOrFail($id);

            Log::info('Module found for edit', [
                'module_id' => $modulo->idrol,
                'module_name' => $modulo->modulo,
                'module_orden' => $modulo->orden
            ]);

            // Obtener módulos principales para usar como dependencias
            $modulosPrincipales = SYSRoles::where('Nivel', 1)
                ->whereNull('Dependencia')
                ->where('idrol', '!=', $modulo->idrol) // Excluir el módulo actual
                ->orderBy('orden')
                ->get();

            Log::info('Edit view data prepared', [
                'module' => $modulo->toArray(),
                'modulos_principales_count' => $modulosPrincipales->count()
            ]);

            return view('modulos.gestion-modulos.edit', compact('modulo', 'modulosPrincipales'));

        } catch (\Exception $e) {
            Log::error('Error in edit method', [
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
     * Mostrar el formulario simplificado para editar un módulo
     */
    public function editSimple($id)
    {
        try {
            Log::info('EditSimple method called', [
                'id' => $id,
                'user_id' => Auth::id(),
                'user_authenticated' => Auth::check()
            ]);

            $modulo = SYSRoles::findOrFail($id);

            // Obtener módulos principales para usar como dependencias
            $modulosPrincipales = SYSRoles::where('Nivel', 1)
                ->whereNull('Dependencia')
                ->where('idrol', '!=', $modulo->idrol) // Excluir el módulo actual
                ->orderBy('orden')
                ->get();

            Log::info('EditSimple view data prepared', [
                'module' => $modulo->toArray(),
                'modulos_principales_count' => $modulosPrincipales->count()
            ]);

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
            Log::info('Update method called', [
                'id' => $id,
                'user_id' => Auth::id(),
                'user_authenticated' => Auth::check(),
                'request_data' => $request->all()
            ]);

            $modulo = SYSRoles::findOrFail($id);

            Log::info('Update request recibido:', $request->all());
            Log::info('Módulo a actualizar:', ['id' => $modulo->idrol, 'modulo' => $modulo->modulo]);

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

            Log::info('Datos a actualizar:', $data);
            $modulo->update($data);

            return redirect()->route('configuracion.utileria.modulos.edit', $modulo->idrol)
                ->with('success', "Módulo '{$modulo->modulo}' actualizado exitosamente");

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
            $modulo->delete();

            return redirect()->route('configuracion.utileria.modulos')
                ->with('success', "Módulo '{$nombreModulo}' eliminado exitosamente");

        } catch (\Exception $e) {
            Log::error('Error al eliminar módulo: ' . $e->getMessage());
            return back()->with('error', 'Error al eliminar el módulo');
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

            Log::info("TogglePermiso - Módulo ID: {$modulo->idrol}, Campo: {$campo}, Valor: {$valor}");

            if (!$campo || !in_array($campo, ['crear', 'modificar', 'eliminar', 'reigstrar'])) {
                Log::warning("TogglePermiso - Campo inválido: {$campo}");
                return response()->json([
                    'success' => false,
                    'message' => 'Campo inválido: ' . $campo
                ], 400);
            }

            // Convertir valor a boolean
            $valorBool = (bool) $valor;

            Log::info("TogglePermiso - Actualizando campo '{$campo}' a " . ($valorBool ? 'true' : 'false') . " para módulo {$modulo->idrol}");

            // Actualizar el campo específico
            $modulo->$campo = $valorBool;
            $modulo->save();

            $estado = $valorBool ? 'activado' : 'desactivado';
            $nombreCampo = ucfirst($campo);

            Log::info("TogglePermiso - Guardado exitoso para módulo {$modulo->idrol}");

            return response()->json([
                'success' => true,
                'message' => "Permiso '{$nombreCampo}' {$estado} para el módulo '{$modulo->modulo}'",
                'campo' => $campo,
                'valor' => $valorBool
            ]);

        } catch (\Exception $e) {
            Log::error("TogglePermiso - Error al cambiar permiso: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar el permiso: ' . $e->getMessage()
            ], 500);
        }
    }
}
