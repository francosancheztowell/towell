<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Usuario;
use App\Models\SYSRoles;
use App\Models\SYSUsuariosRoles;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class UsuarioController extends Controller
{
    // Muestra el formulario de creación de usuario
    public function create()
    {
        // Cargar TODOS los módulos (principales, hijos y nietos)
        $modulos = SYSRoles::orderBy('orden')->get();

        return view('modulos.usuarios.form_usuario', [
            'usuario' => null,
            'modulos' => $modulos,
            'isEdit' => false
        ]);
    }

    // Almacena un nuevo usuario en la base de datos
    public function store(Request $request)
    {
        //dd($request->all());
        try {
            // 1) Validación con los campos correctos de la tabla SYSUsuario
            $data = $request->validate([
                'numero_empleado' => 'required|string|max:50|unique:SYSUsuario,numero_empleado',
                'nombre'          => 'required|string|max:255',
                'contrasenia'     => 'required|string|min:4',
                'area'            => 'nullable|string|max:100',
                'telefono'        => 'nullable|string|max:20',
                'turno'           => 'nullable|string|max:10',
                'foto'            => 'nullable|image|mimes:jpeg,png,jpg,gif,bmp,webp,svg,tiff,tif|max:10240',
                'puesto'          => 'nullable|string|max:100',
                'correo'          => 'nullable|email|max:255',
            ]);

            // 4) Foto (guardar ruta pública)
            if ($request->hasFile('foto')) {
                $storedPath = $request->file('foto')->store('usuarios', 'public');
                $data['foto'] = basename($storedPath); // guardar solo el nombre del archivo
            }

            // 5) Hashear contraseña
            $data['contrasenia'] = Hash::make($data['contrasenia']);

            // 6) remember_token
            $data['remember_token'] = Str::random(60);

            // 7) Crear usuario
            $usuario = Usuario::create($data);

            // 8) Guardar permisos por módulo
            $this->guardarPermisos($request, $usuario->idusuario);

            return redirect()
                ->route('usuarios.select')
                ->with('success', 'Usuario registrado correctamente');
        } catch (ValidationException $e) {
            // Validación: mostramos errores con SweetAlert (ya lo tienes en el Blade)
            return back()->withErrors($e->errors())->withInput();
        } catch (\Throwable $e) {
            Log::error('Error al crear usuario', ['msg' => $e->getMessage()]);
            return back()
                ->with('error', 'No se pudo registrar el usuario. Intenta de nuevo.')
                ->withInput();
        }
    }

    public function select(Request $request)
    {
        $usuarios = Usuario::query()
            ->select('idusuario', 'numero_empleado', 'nombre', 'area', 'turno', 'telefono', 'foto', 'puesto', 'correo', 'enviarMensaje')
            ->orderBy('nombre')
            ->get(); // paginate no funciona aqui :(, ya que tenemos una version 2008 de SQLSERVER, lo que lo hace imposible, la paginacion debe ser a mano.

        return view('modulos.usuarios.select', compact('usuarios'));
    }

    // Mostrar QR de un usuario
    public function showQR($idusuario)
    {
        $usuario = Usuario::findOrFail($idusuario);

        return view('modulos.usuarios.qr', compact('usuario'));
    }

    //CRUD REST, solo edit, update y destroy

    // EDITAR
    public function edit($id)
    {
        // Cargar TODOS los módulos (principales, hijos y nietos)
        $modulos = SYSRoles::orderBy('orden')->get();

        // Obtener el usuario por ID
        $usuario = Usuario::findOrFail($id);

        // Obtener permisos actuales del usuario
        $permisosUsuario = SYSUsuariosRoles::where('idusuario', $usuario->idusuario)
            ->with('rol')
            ->get()
            ->keyBy('idrol');

        return view('modulos.usuarios.form_usuario', [
            'usuario' => $usuario,
            'modulos' => $modulos,
            'permisosUsuario' => $permisosUsuario,
            'isEdit' => true
        ]);
    }

    // ACTUALIZAR
    public function update(Request $request, $id)
    {
        // Validación con los campos correctos de la tabla SYSUsuario
        $data = $request->validate([
            'nombre'   => 'required|string|max:255',
            'area'     => 'nullable|string|max:100',
            'telefono' => 'nullable|string|max:20',
            'turno'    => 'nullable|string|max:10',
            'foto'     => 'nullable|image|mimes:jpeg,png,jpg,gif,bmp,webp,svg,tiff,tif|max:10240',
            'puesto'   => 'nullable|string|max:100',
            'correo'   => 'nullable|email|max:255',
            'contrasenia' => 'nullable|string|min:4',
        ]);

        // Si se sube una nueva foto
        if ($request->hasFile('foto')) {
            // Asegurar carpeta
            if (!Storage::disk('public')->exists('usuarios')) {
                Storage::disk('public')->makeDirectory('usuarios');
            }
            $storedPath = $request->file('foto')->store('usuarios', 'public');
            $data['foto'] = basename($storedPath); // guardar solo el nombre del archivo
        }

        // Si se proporciona una nueva contraseña, hashearla
        if ($request->filled('contrasenia')) {
            $data['contrasenia'] = Hash::make($request->input('contrasenia'));
        } else {
            // Si no se proporciona contraseña, mantener la actual
            unset($data['contrasenia']);
        }

        // Obtener el usuario por ID
        $usuario = Usuario::findOrFail($id);

        // numero_empleado lo dejamos como clave, no editable aquí
        $usuario->update($data);

        // Actualizar permisos
        $this->guardarPermisos($request, $id);

        return redirect()
            ->route('usuarios.select')
            ->with('success', "Usuario #{$usuario->numero_empleado} actualizado correctamente.");
    }

    // ELIMINAR
    public function destroy($id)
    {
        try {
            $usuario = Usuario::findOrFail($id);

            // Eliminar primero los registros relacionados en SYSUsuariosRoles
            SYSUsuariosRoles::where('idusuario', $id)->delete();

            // Luego eliminar el usuario
            $usuario->delete();

            return redirect()
                ->route('usuarios.select')
                ->with('success', "Usuario #{$usuario->numero_empleado} eliminado correctamente.");
        } catch (\Exception $e) {
            Log::error('Error al eliminar usuario', [
                'usuario_id' => $id,
                'error' => $e->getMessage()
            ]);

            return redirect()
                ->route('usuarios.select')
                ->with('error', 'No se pudo eliminar el usuario. Verifica que no tenga registros relacionados.');
        }
    }

    // Método para mostrar módulos principales de configuración (900 y 1000)
    public function showConfiguracion()
    {
        $usuarioActual = Auth::user();
        $idusuario = $usuarioActual->idusuario;

        try {
            // Obtener el módulo Configuración (nivel 1)
            $moduloConfiguracion = SYSRoles::where('modulo', 'Configuración')
                ->where('Nivel', 1)
                ->whereNull('Dependencia')
                ->first();

            if (!$moduloConfiguracion) {
                return redirect('/produccionProceso')->with('error', 'Módulo de configuración no encontrado');
            }

            // Obtener submódulos de nivel 2 que dependen del módulo Configuración
            $subModulosDB = SYSUsuariosRoles::join('SYSRoles as r', 'SYSUsuariosRoles.idrol', '=', 'r.idrol')
                ->where('SYSUsuariosRoles.idusuario', $idusuario)
                ->where('SYSUsuariosRoles.acceso', true)
                ->where('r.Nivel', 2) // Solo submódulos de nivel 2
                ->where('r.Dependencia', $moduloConfiguracion->orden) // Que dependan de Configuración
                ->select('r.idrol', 'r.orden', 'r.modulo', 'r.imagen', 'r.Nivel', 'r.Dependencia',
                        'SYSUsuariosRoles.acceso as usuario_acceso',
                        'SYSUsuariosRoles.crear as usuario_crear',
                        'SYSUsuariosRoles.modificar as usuario_modificar',
                        'SYSUsuariosRoles.eliminar as usuario_eliminar',
                        'SYSUsuariosRoles.registrar as usuario_registrar')
                ->orderBy('r.orden')
                ->get();

            $subModulos = [];
            foreach ($subModulosDB as $moduloDB) {
                $subModulos[] = [
                    'nombre' => $moduloDB->modulo,
                    'imagen' => $moduloDB->imagen ?? 'configuracion.png',
                    'ruta' => $this->generarRutaSubModulo($moduloDB->modulo, $moduloDB->orden),
                    'ruta_tipo' => 'url',
                    'orden' => $moduloDB->orden,
                    'nivel' => $moduloDB->Nivel,
                    'dependencia' => $moduloDB->Dependencia,
                    'acceso' => $moduloDB->usuario_acceso,
                    'crear' => $moduloDB->usuario_crear,
                    'modificar' => $moduloDB->usuario_modificar,
                    'eliminar' => $moduloDB->usuario_eliminar,
                    'registrar' => $moduloDB->usuario_registrar
                ];
            }

            return view('modulos.configuracion', [
                'moduloPrincipal' => 'Configuración',
                'subModulos' => $subModulos
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener módulos de configuración: ' . $e->getMessage());
            return redirect('/produccionProceso')->with('error', 'Error al cargar los módulos de configuración');
        }
    }

    // Método para mostrar sub-módulos de configuración (901-999 o 1001-1099)
    public function showSubModulosConfiguracion($serie)
    {
        $usuarioActual = Auth::user();
        $idusuario = $usuarioActual->idusuario;

        // Ejemplo de serie: '909' (Utilería) → listar nietos nivel 3 con Dependencia = 909
        $ordenPadre = (int) $serie;
        $moduloPadre = SYSRoles::where('orden', $ordenPadre)->first();
        if (!$moduloPadre) {
            return redirect('/configuracion')->with('error', 'Módulo de configuración no encontrado');
        }

        try {
            $subModulosDB = SYSUsuariosRoles::join('SYSRoles as r', 'SYSUsuariosRoles.idrol', '=', 'r.idrol')
                ->where('SYSUsuariosRoles.idusuario', $idusuario)
                ->where('SYSUsuariosRoles.acceso', true)
                ->where('r.Nivel', 3)
                ->where('r.Dependencia', $ordenPadre)
                ->select('r.idrol', 'r.orden', 'r.modulo', 'r.imagen', 'r.Nivel', 'r.Dependencia',
                        'SYSUsuariosRoles.acceso as usuario_acceso',
                        'SYSUsuariosRoles.crear as usuario_crear',
                        'SYSUsuariosRoles.modificar as usuario_modificar',
                        'SYSUsuariosRoles.eliminar as usuario_eliminar',
                        'SYSUsuariosRoles.registrar as usuario_registrar')
                ->orderBy('r.orden')
                ->get();

            $subModulos = [];
            foreach ($subModulosDB as $moduloDB) {
                $subModulos[] = [
                    'nombre' => $moduloDB->modulo,
                    'imagen' => $moduloDB->imagen ?? 'configuracion.png',
                    'ruta' => $this->generarRutaSubModulo($moduloDB->modulo, $moduloDB->orden),
                    'orden' => $moduloDB->orden,
                    'nivel' => $moduloDB->Nivel,
                    'dependencia' => $moduloDB->Dependencia,
                    'acceso' => $moduloDB->usuario_acceso,
                    'crear' => $moduloDB->usuario_crear,
                    'modificar' => $moduloDB->usuario_modificar,
                    'eliminar' => $moduloDB->usuario_eliminar,
                    'registrar' => $moduloDB->usuario_registrar
                ];
            }

            return view('modulos.submodulos', [
                'moduloPrincipal' => $moduloPadre->modulo,
                'subModulos' => $subModulos,
                'rango' => ['inicio' => $ordenPadre, 'nombre' => $moduloPadre->modulo]
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener sub-módulos de configuración: ' . $e->getMessage());
            return redirect('/configuracion')->with('error', 'Error al cargar los sub-módulos');
        }
    }



    // Método para mostrar sub-módulos de un módulo principal
    public function showSubModulos($moduloPrincipal)
    {
        // Permitir slug o nombre exacto
        $slugToNombre = [
            'planeacion' => 'Planeación',
            'tejido' => 'Tejido',
            'urdido' => 'Urdido',
            'engomado' => 'Engomado',
            'atadores' => 'Atadores',
            'tejedores' => 'Tejedores',
            'mantenimiento' => 'Mantenimiento',
            'programa-urd-eng' => 'Programa Urd / Eng',
            'configuracion' => 'Configuración',
        ];

        $buscado = $slugToNombre[$moduloPrincipal] ?? $moduloPrincipal;

        // Obtener el módulo principal por nombre o por orden conocido
        $moduloPadre = SYSRoles::where('modulo', $buscado)
            ->where('Nivel', 1)
            ->whereNull('Dependencia')
            ->first();

        if (!$moduloPadre) {
            // Fallback por rango de orden según slug
            $rangos = [
                'planeacion' => 100,
                'tejido' => 200,
                'urdido' => 300,
                'engomado' => 400,
                'atadores' => 500,
                'tejedores' => 600,
                'programa-urd-eng' => 700,
                'mantenimiento' => 800,
                'configuracion' => 900,
            ];
            if (isset($rangos[$moduloPrincipal])) {
                $moduloPadre = SYSRoles::where('orden', $rangos[$moduloPrincipal])
                    ->where('Nivel', 1)
                    ->whereNull('Dependencia')
                    ->first();
            }
        }

        if (!$moduloPadre) {
            return redirect('/produccionProceso')->with('error', 'Módulo no encontrado');
        }
        $usuarioActual = Auth::user();
        $idusuario = $usuarioActual->idusuario;

        // Cachear submódulos por módulo principal y usuario
        $cacheKey = "submodulos_{$moduloPrincipal}_user_{$idusuario}";
        $subModulos = cache()->remember($cacheKey, 3600, function () use ($idusuario, $moduloPadre) {
            try {
                // Obtener submódulos de nivel 2 que dependen del módulo padre
                $subModulosDB = SYSUsuariosRoles::join('SYSRoles as r', 'SYSUsuariosRoles.idrol', '=', 'r.idrol')
                    ->where('SYSUsuariosRoles.idusuario', $idusuario)
                    ->where('SYSUsuariosRoles.acceso', true)
                    ->where('r.Nivel', 2) // Solo submódulos de nivel 2
                    ->where('r.Dependencia', $moduloPadre->orden) // Que dependan del módulo padre
                    ->select('r.idrol', 'r.orden', 'r.modulo', 'r.imagen', 'r.Nivel', 'r.Dependencia',
                            'SYSUsuariosRoles.acceso as usuario_acceso',
                            'SYSUsuariosRoles.crear as usuario_crear',
                            'SYSUsuariosRoles.modificar as usuario_modificar',
                            'SYSUsuariosRoles.eliminar as usuario_eliminar',
                            'SYSUsuariosRoles.registrar as usuario_registrar')
                    ->orderBy('r.orden') // Ya está ordenado jerárquicamente
                    ->get();

                $subModulos = [];
                foreach ($subModulosDB as $moduloDB) {
                    $subModulos[] = [
                        'nombre' => $moduloDB->modulo,
                        'imagen' => $moduloDB->imagen ?? 'default.png',
                        'ruta' => $this->generarRutaSubModulo($moduloDB->modulo, $moduloDB->orden),
                        'ruta_tipo' => 'url',
                        'orden' => $moduloDB->orden,
                        'nivel' => $moduloDB->Nivel,
                        'dependencia' => $moduloDB->Dependencia,
                        'acceso' => $moduloDB->usuario_acceso,
                        'crear' => $moduloDB->usuario_crear,
                        'modificar' => $moduloDB->usuario_modificar,
                        'eliminar' => $moduloDB->usuario_eliminar,
                        'registrar' => $moduloDB->usuario_registrar
                    ];
                }

                return $subModulos;
            } catch (\Exception $e) {
                Log::error('Error al obtener sub-módulos: ' . $e->getMessage());
                return [];
            }
        });

        return view('modulos.submodulos', [
            'moduloPrincipal' => $moduloPadre->modulo,
            'subModulos' => $subModulos,
            'rango' => ['inicio' => $moduloPadre->orden, 'nombre' => $moduloPadre->modulo]
        ]);
    }

    // Método auxiliar para generar rutas de sub-módulos
    private function generarRutaSubModulo($nombreModulo, $orden)
    {
        // PRIMERO: Mapeo de sub-módulos específicos a sus rutas
        // Esto tiene prioridad sobre la detección automática
        $rutasSubModulos = [
            // Submódulos de Planeación que NO tienen nietos
            'Simulaciones' => '/planeacion/simulaciones',
            'Alineación' => '/planeacion/alineacion',
            'Reportes' => '/planeacion/reportes',
            'Reportes Planeación' => '/planeacion/reportes',
            'Producciones Terminadas' => '/planeacion/producciones-terminadas',
            'Catálogos' => '/planeacion/catalogos',
            'Catalogos' => '/planeacion/catalogos',
            'Catálogos (Cat.)' => '/planeacion/catalogos',
            'Catalogos (Cat.)' => '/planeacion/catalogos',
            'Catálogos de Planeación' => '/planeacion/catalogos',
            'Catalogos de Planeacion' => '/planeacion/catalogos',

            // Submódulos de Catálogos (nietos - nivel 3) - ESTRUCTURA JERÁRQUICA
            'Telares' => '/planeacion/catalogos/telares',
            'Eficiencias STD' => '/planeacion/catalogos/eficiencia',
            'Velocidad STD' => '/planeacion/catalogos/velocidad',
            'Calendarios' => '/planeacion/catalogos/calendarios',
            'Aplicaciones (Cat.)' => '/planeacion/catalogos/aplicaciones',
            'Modelos' => '/planeacion/catalogos/modelos',
            'Matriz Calibres' => '/planeacion/catalogos/matriz-calibres',
            'Matriz Hilos' => '/planeacion/catalogos/matriz-hilos',
            'Codificación Modelos' => '/planeacion/catalogos/codificacion-modelos',

            // Submódulos de Programa Tejido (nietos - nivel 3)
            'Programa Tejido' => '/planeacion/programa-tejido',
            'Programa de Tejido' => '/planeacion/programa-tejido',
            'Programa Tejido (Cat.)' => '/planeacion/programa-tejido',
            'Orden de Cambio' => '/tejido/orden-cambio',
            'Marbetes' => '/tejido/marbetes',

            // Nietos de Inv Telas (nivel 3) - orden 201-1, 201-2, 201-3
            'Jacquard' => '/tejido/inventario-telas/jacquard',
            'Itema' => '/tejido/inventario-telas/itema',
            'Karl Mayer' => '/tejido/karl-mayer',

            // Módulos de Tejido (orden 52) - ESTRUCTURA JERÁRQUICA
            'Inv Telas' => '/tejido/inventario-telas',
            'Marcas Finales- Cortes de Eficiencia' => '/tejido/inventario/marcas-finales',
            'Inv Trama' => '/submodulos-nivel3/203',  // ✅ Usar URL automática para nietos
            'Producción Reenconado Cabezuela' => '/tejido/produccion-reenconado',
            'Configurar' => '/tejido/configurar',

            // Nietos de Inv Trama (nivel 3) - orden 203-1, 203-2 - ESTRUCTURA JERÁRQUICA
            'Nuevo requerimiento' => '/tejido/inventario/trama/nuevo-requerimiento',
            'Consultar requerimiento' => '/tejido/inventario/trama/consultar-requerimiento',

            // Submódulos de Configurar (orden 53)
            'Secuencia Inv Telas' => '/tejido/secuencia-inv-telas',
            'Secuencia Corte de Eficiencia' => '/tejido/secuencia-corte-eficiencia',
            'Secuencia Inv Trama' => '/tejido/secuencia-inv-trama',
            'Secuencia Marcas Finales' => '/tejido/secuencia-marcas-finales',

            // Módulos de Urdido (orden 62)
            'Programa Urdido' => '/urdido/programar-requerimientos',
            'BPM (Buenas Practicas Manufactura) Urd' => '/urdido/bpm',
            'Reportes Urdido' => '/urdido/reportes',
            'Catalogos Julios' => '/urdido/catalogos-julios',
            'Catalogos de Paros' => '/urdido/catalogos-paros',

            // Módulos de Engomado (orden 16)
            'Programa Engomado' => '/engomado/programar-requerimientos',
            'BPM (Buenas Practicas Manufactura) Eng' => '/engomado/bpm',
            'Reportes Engomado' => '/engomado/reportes',
            'Producción Engomado' => '/engomado/produccion',

            // Módulos de Atadores (orden 1)
            'Programa Atadores' => '/atadores/programa',

            // Módulos de Tejedores (orden 48)
            'BPM Tejedores' => '/tejedores/bpm',
            'Desarrolladores' => '/tejedores/desarrolladores',
            'Mecánicos' => '/tejedores/mecanicos',

            // Módulos de Programa Urd/Eng (orden 45)
            'Reservar y Programar' => '/programa-urd-eng/reservar-programar',
            //'Edición de Ordenes Programadas' => '/modulo-edicion-urdido-engomado',


            // Módulos de configuración (nivel 2) - ESTRUCTURA JERÁRQUICA
            'Usuarios' => '/configuracion/usuarios/select',
            'Parametros' => '/configuracion/parametros',
            'Base Datos Principal' => '/configuracion/base-datos',
            'BD Pro (ERP Productivo)' => '/configuracion/bd-pro-productivo',
            'BD Pro (ERP Pruebas)' => '/configuracion/bd-pro-pruebas',
            'BD Tow (ERP Productivo)' => '/configuracion/bd-tow-productivo',
            'BD Tow (ERP Pruebas)' => '/configuracion/bd-tow-pruebas',
            'Ambiente' => '/configuracion/ambiente',
            // 'Utilería' tiene nietos, se maneja automáticamente arriba

            // Nietos de Utilería (nivel 3) - ESTRUCTURA JERÁRQUICA
            'Cargar Catálogos' => '/configuracion/utileria/cargar-catalogos',
            'Cargar Orden de Producción' => '/configuracion/cargar-orden-produccion',
            'Cargar Planeación' => '/configuracion/cargar-planeacion',
            'Modulos' => '/configuracion/utileria/modulos',
        ];

        // Debug temporal para ver el nombre del módulo
        Log::info('Generando ruta para módulo: ' . $nombreModulo . ' - Orden: ' . $orden);

        if (strpos($nombreModulo, 'Catálogo') !== false || strpos($nombreModulo, 'Catalog') !== false) {
            Log::info('Módulo de catálogos detectado: ' . $nombreModulo . ' - Orden: ' . $orden);
        }

        // Si existe en el mapeo específico, usarlo (PRIORIDAD ABSOLUTA)
        if (isset($rutasSubModulos[$nombreModulo])) {
            Log::info('Ruta encontrada en mapeo: ' . $rutasSubModulos[$nombreModulo]);
            return $rutasSubModulos[$nombreModulo];
        }

        // Verificación especial para catálogos (cualquier variación)
        if (strpos(strtolower($nombreModulo), 'catálogo') !== false ||
            strpos(strtolower($nombreModulo), 'catalog') !== false ||
            $orden == '104') {
            Log::info('Redirigiendo catálogos a: /planeacion/catalogos');
            return '/planeacion/catalogos';
        }

        // SEGUNDO: Verificar si este módulo tiene nietos (nivel 3) - si tiene, redirigir a la vista de nietos
        // SOLO si NO está en el mapeo específico
        $tieneNietos = SYSRoles::where('Nivel', 3)
            ->where('Dependencia', $orden)
            ->exists();

        if ($tieneNietos) {
            Log::info('Módulo tiene nietos, redirigiendo a: /submodulos-nivel3/' . $orden);
            return '/submodulos-nivel3/' . $orden;
        }

        // TERCERO: Verificar si el orden contiene separador de nivel 3 (guión o guión bajo)
        // Solo si no está en el mapeo y no tiene nietos
        $posSeparador = (strpos($orden, '-') !== false) ? strpos($orden, '-') : strpos($orden, '_');
        if ($posSeparador !== false) {
            // Extraer el código del módulo padre (ej: de "201-1" o "201_1" extraer "201")
            $moduloPadre = substr($orden, 0, $posSeparador);
            return '/submodulos-nivel3/' . $moduloPadre;
        }

        // CUARTO: Ruta genérica por defecto
        return '/modulo-' . strtolower(str_replace(' ', '-', $nombreModulo));
    }

    // Método para mostrar submódulos de nivel 3 (ej: 201_1, 201_2, etc.)
    public function showSubModulosNivel3($moduloPadre = '104')
    {
        $usuarioActual = Auth::user();
        $idusuario = $usuarioActual->idusuario;

        try {
            // Obtener submódulos de nivel 3 que dependen del submódulo padre (por orden)
            $subModulosDB = SYSUsuariosRoles::join('SYSRoles as r', 'SYSUsuariosRoles.idrol', '=', 'r.idrol')
                ->where('SYSUsuariosRoles.idusuario', $idusuario)
                ->where('SYSUsuariosRoles.acceso', true)
                ->where('r.Nivel', 3)
                ->where('r.Dependencia', $moduloPadre)
                ->select('r.idrol', 'r.orden', 'r.modulo', 'r.imagen', 'r.Nivel', 'r.Dependencia',
                        'SYSUsuariosRoles.acceso as usuario_acceso',
                        'SYSUsuariosRoles.crear as usuario_crear',
                        'SYSUsuariosRoles.modificar as usuario_modificar',
                        'SYSUsuariosRoles.eliminar as usuario_eliminar',
                        'SYSUsuariosRoles.registrar as usuario_registrar')
                ->orderBy('r.orden')
                ->get();

            $subModulos = [];
            foreach ($subModulosDB as $moduloDB) {
                $subModulos[] = [
                    'nombre' => $moduloDB->modulo,
                    'imagen' => $moduloDB->imagen ?? 'default.png',
                    'ruta' => $this->generarRutaSubModulo($moduloDB->modulo, $moduloDB->orden),
                    'ruta_tipo' => 'url',
                    'orden' => $moduloDB->orden,
                    'nivel' => $moduloDB->Nivel,
                    'dependencia' => $moduloDB->Dependencia,
                    'acceso' => $moduloDB->usuario_acceso,
                    'crear' => $moduloDB->usuario_crear,
                    'modificar' => $moduloDB->usuario_modificar,
                    'eliminar' => $moduloDB->usuario_eliminar,
                    'registrar' => $moduloDB->usuario_registrar
                ];
            }

            // Obtener el nombre del módulo padre
            $moduloPadreInfo = SYSRoles::where('orden', $moduloPadre)->first();

            return view('modulos.submodulos', [
                'moduloPrincipal' => $moduloPadreInfo->modulo ?? "Submódulos de $moduloPadre",
                'subModulos' => $subModulos,
                'rango' => ['inicio' => $moduloPadre, 'nombre' => $moduloPadreInfo->modulo ?? "Submódulos"]
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener submódulos nivel 3: ' . $e->getMessage());
            return redirect('/produccionProceso')->with('error', 'Error al cargar los submódulos');
        }
    }

    //METODO para mostrar la vista principal (produccionProceso)
    public function index()
    {
        $usuarioActual = Auth::user();

        // Verificar que el usuario esté autenticado
        if (!$usuarioActual) {
            return redirect()->route('login')->with('error', 'Debes iniciar sesión para acceder a los módulos');
        }

        $idusuario = $usuarioActual->idusuario;

        // Verificar que el usuario tenga número de empleado
        if (!$usuarioActual->numero_empleado) {
            return redirect()->route('login')->with('error', 'Usuario sin número de empleado válido');
        }

        // Cachear módulos en sesión por 60 minutos
        $cacheKey = "modulos_principales_user_{$idusuario}";
        $modulos = cache()->remember($cacheKey, 3600, function () use ($idusuario) {
            try {
                // Obtener SOLO módulos principales de nivel 1 usando la nueva estructura jerárquica
                $modulosDB = SYSUsuariosRoles::join('SYSRoles as r', 'SYSUsuariosRoles.idrol', '=', 'r.idrol')
                    ->where('SYSUsuariosRoles.idusuario', $idusuario)
                    ->where('SYSUsuariosRoles.acceso', true)
                    ->where('r.Nivel', 1) // Solo módulos de nivel 1
                    ->whereNull('r.Dependencia') // Sin dependencia (módulos principales)
                    ->select('r.idrol', 'r.orden', 'r.modulo', 'r.imagen', 'r.Nivel', 'r.Dependencia',
                            'SYSUsuariosRoles.acceso as usuario_acceso',
                            'SYSUsuariosRoles.crear as usuario_crear',
                            'SYSUsuariosRoles.modificar as usuario_modificar',
                            'SYSUsuariosRoles.eliminar as usuario_eliminar',
                            'SYSUsuariosRoles.registrar as usuario_registrar')
                    ->orderBy('r.orden') // Ya está ordenado jerárquicamente
                    ->get();

                // Construir el array de módulos dinámicamente desde la DB
                $modulos = [];
                foreach ($modulosDB as $moduloDB) {
                    $nombreModulo = $moduloDB->modulo;
                    $orden = $moduloDB->orden;

                    // Generar ruta dinámica basada en el nombre del módulo
                    $ruta = $this->generarRutaModuloPrincipal($nombreModulo, $orden);

                    $modulos[] = [
                        'nombre' => $nombreModulo,
                        'imagen' => $moduloDB->imagen ?? 'default.png',
                        'ruta' => $ruta,
                        'ruta_tipo' => 'url',
                        'orden' => $orden,
                        'nivel' => $moduloDB->Nivel,
                        'dependencia' => $moduloDB->Dependencia,
                        'acceso' => $moduloDB->usuario_acceso,
                        'crear' => $moduloDB->usuario_crear,
                        'modificar' => $moduloDB->usuario_modificar,
                        'eliminar' => $moduloDB->usuario_eliminar,
                        'registrar' => $moduloDB->usuario_registrar
                    ];
                }

                return $modulos;
            } catch (\Exception $e) {
                Log::error('Error al obtener módulos de la DB: ' . $e->getMessage());
                return [];
            }
        });

        // Verificar si el usuario tiene permisos de configuración
        $tieneConfiguracion = false;
        foreach ($modulos as $modulo) {
            if ($modulo['nombre'] === 'Configuración') {
                $tieneConfiguracion = true;
                break;
            }
        }

        return view('/produccionProceso', [
            'modulos' => $modulos,
            'tieneConfiguracion' => $tieneConfiguracion
        ]);
    }

    /**
     * API: Obtener todos los submódulos de un módulo principal
     * Para precarga en background
     */
    public function getSubModulosAPI($moduloPrincipal)
    {
        $rangosModulos = [
            'planeacion' => ['inicio' => 100, 'fin' => 199, 'nombre' => 'Planeación'],
            'tejido' => ['inicio' => 200, 'fin' => 299, 'nombre' => 'Tejido'],
            'urdido' => ['inicio' => 300, 'fin' => 399, 'nombre' => 'Urdido'],
            'engomado' => ['inicio' => 400, 'fin' => 499, 'nombre' => 'Engomado'],
            'atadores' => ['inicio' => 500, 'fin' => 599, 'nombre' => 'Atadores'],
            'tejedores' => ['inicio' => 600, 'fin' => 699, 'nombre' => 'Tejedores'],
            'programa-urd-eng' => ['inicio' => 700, 'fin' => 799, 'nombre' => 'Programa Urd / Eng'],
            'mantenimiento' => ['inicio' => 800, 'fin' => 899, 'nombre' => 'Mantenimiento'],
        ];

        if (!isset($rangosModulos[$moduloPrincipal])) {
            return response()->json(['error' => 'Módulo no encontrado'], 404);
        }

        $usuarioActual = Auth::user();
        if (!$usuarioActual) {
            return response()->json(['error' => 'No autenticado'], 401);
        }

        $idusuario = $usuarioActual->idusuario;
        $rango = $rangosModulos[$moduloPrincipal];

        // Usar el mismo caché que showSubModulos
        $cacheKey = "submodulos_{$moduloPrincipal}_user_{$idusuario}";
        $subModulos = cache()->remember($cacheKey, 3600, function () use ($idusuario, $rango) {
            try {
                $codigoModuloPadre = $rango['inicio'];

                // Obtener submódulos de nivel 2 que dependen del módulo padre
                $subModulosDB = SYSUsuariosRoles::join('SYSRoles as r', 'SYSUsuariosRoles.idrol', '=', 'r.idrol')
                    ->where('SYSUsuariosRoles.idusuario', $idusuario)
                    ->where('SYSUsuariosRoles.acceso', true)
                    ->where('r.Nivel', 2)
                    ->where('r.Dependencia', $codigoModuloPadre)
                    ->select('r.orden', 'r.modulo', 'r.imagen')
                    ->orderBy('r.orden')
                    ->get();

                $subModulos = [];
                foreach ($subModulosDB as $moduloDB) {
                    $subModulos[] = [
                        'nombre' => $moduloDB->modulo,
                        'imagen' => $moduloDB->imagen ?? 'default.png',
                        'ruta' => $this->generarRutaSubModulo($moduloDB->modulo, $moduloDB->orden),
                        'orden' => $moduloDB->orden
                    ];
                }

                return $subModulos;
            } catch (\Exception $e) {
                return [];
            }
        });

        return response()->json($subModulos);
    }

    /**
     * Genera la ruta para un módulo principal (nivel 1)
     */
    private function generarRutaModuloPrincipal($nombreModulo, $orden)
    {
        // Mapeo de módulos principales a sus rutas
        $rutasEspeciales = [
            'Planeación' => '/submodulos/planeacion',
            'Tejido' => '/submodulos/tejido',
            'Urdido' => '/submodulos/urdido',
            'Engomado' => '/submodulos/engomado',
            'Atadores' => '/submodulos/atadores',
            'Tejedores' => '/submodulos/tejedores',
            'Mantenimiento' => '/submodulos/mantenimiento',
            'Programa Urd / Eng' => '/submodulos/programa-urd-eng',
            'Configuración' => '/modulo-configuracion',
        ];

        // Si existe una ruta especial, usarla
        if (isset($rutasEspeciales[$nombreModulo])) {
            return $rutasEspeciales[$nombreModulo];
        }

        // Generar ruta genérica basada en el orden
        $slug = strtolower(str_replace(' ', '-', $nombreModulo));
        return "/submodulos/{$slug}";
    }

    /**
     * Guardar permisos de módulos para un usuario
     * @param Request $request
     * @param int $idusuario
     */
    private function guardarPermisos(Request $request, $idusuario)
    {
        // Eliminar permisos anteriores
        SYSUsuariosRoles::where('idusuario', $idusuario)->delete();

        // Obtener TODOS los módulos (principales, hijos y nietos)
        $modulos = SYSRoles::orderBy('orden')->get();

        foreach ($modulos as $modulo) {
            $acceso = $request->has("modulo_{$modulo->idrol}_acceso");
            $crear = $request->has("modulo_{$modulo->idrol}_crear");
            $modificar = $request->has("modulo_{$modulo->idrol}_modificar");
            $eliminar = $request->has("modulo_{$modulo->idrol}_eliminar");

            // Solo guardar si al menos tiene acceso
            if ($acceso) {
                SYSUsuariosRoles::create([
                    'idusuario' => $idusuario,
                    'idrol' => $modulo->idrol,
                    'acceso' => $acceso,
                    'crear' => $crear,
                    'modificar' => $modificar,
                    'eliminar' => $eliminar,
                    'registrar' => $crear, // Usar mismo valor que crear
                ]);
            }
        }
    }
}
