<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Helpers\TurnoHelper;

class MarcasController extends Controller
{
    /**
     * Mostrar la vista de nuevas marcas (nuevo-marcas.blade.php)
     */
    public function index(Request $request)
    {
        try {
            // Permisos: solo permitir acceso a creación si el usuario tiene permiso de crear
            $permisos = $this->getPermisosMarcas();
            if (!$permisos || !$permisos->acceso || !$permisos->crear) {
                // Si no tiene permiso para crear, redirigir a la vista de consulta con mensaje
                return redirect()->route('marcas.consultar')
                    ->with('error', 'No tienes permisos para crear nuevas marcas');
            }
            Log::info('Cargando vista de nuevas marcas');

            // Si viene con un folio específico para editar, permitirlo aunque haya otros en proceso
            if ($request->has('folio')) {
                Log::info('Cargando folio específico para editar: ' . $request->folio);
                // Aquí no redirigimos, permitimos cargar la vista con el folio específico
            } else {
                // Verificar si hay algún folio en proceso solo si no viene con folio específico
                $folioEnProceso = DB::table('TejMarcas')
                    ->where('Status', 'En Proceso')
                    ->orderBy('Date', 'desc')
                    ->first();

                // Si hay un folio en proceso, redirigir automáticamente a editarlo
                if ($folioEnProceso) {
                    Log::info('Redirigiendo a folio en proceso desde nuevo: ' . $folioEnProceso->Folio);
                    return redirect()->route('marcas.nuevo', ['folio' => $folioEnProceso->Folio])
                        ->with('warning', 'Hay un folio en proceso. Se ha redirigido automáticamente para continuar editándolo.');
                }
            }

            // Intentar primero con InvSecuenciaMarcas, si falla usar InvSecuenciaTelares
            try {
                $telares = DB::table('InvSecuenciaMarcas')
                    ->orderBy('Orden', 'asc')
                    ->select('NoTelarId', 'SalonId')
                    ->get();
                Log::info('Telares obtenidos de InvSecuenciaMarcas: ' . $telares->count());
            } catch (\Exception $e) {
                Log::warning('InvSecuenciaMarcas no existe o columnas distintas, usando InvSecuenciaTelares: ' . $e->getMessage());
                // En InvSecuenciaTelares la columna es NoTelar; mapear como NoTelarId
                $telares = DB::table('InvSecuenciaTelares')
                    ->orderBy('Secuencia', 'asc')
                    ->selectRaw('NoTelar as NoTelarId')
                    ->get()
                    ->map(function ($row) {
                        // No existe SalonId en InvSecuenciaTelares; se completará desde ReqProgramaTejido vía JS
                        $row->SalonId = null;
                        return $row;
                    });
                Log::info('Telares obtenidos de InvSecuenciaTelares (mapeados): ' . $telares->count());
            }

            return view('modulos.marcas-finales.nuevo-marcas', compact('telares'));
        } catch (\Exception $e) {
            Log::error('Error al cargar vista de marcas: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            // Si hay error con las tablas, intentar con array vacío
            $telares = collect([]);

            return view('modulos.marcas-finales.nuevo-marcas', compact('telares'));
        }
    }

    /**
     * Mostrar la vista para consultar marcas (consultar-marcas.blade.php)
     */
    public function consultar()
    {
        try {
            // Obtener permisos para condicionar botones en la vista
            $permisos = $this->getPermisosMarcas();
            // Obtener todas las marcas (folios en proceso primero, luego por fecha descendente)
            $marcas = DB::table('TejMarcas')
                ->select('Folio', 'Date', 'Turno', 'numero_empleado', 'Status')
                ->orderByRaw("CASE WHEN Status = 'En Proceso' THEN 0 ELSE 1 END")
                ->orderByDesc('Date')
                ->get();

            // Obtener el último folio creado (el más reciente, o el en proceso si existe)
            $ultimoFolio = $marcas->first();

            return view('modulos.marcas-finales.consultar-marcas-finales', compact('marcas', 'ultimoFolio', 'permisos'));
        } catch (\Exception $e) {
            Log::error('Error al consultar marcas: ' . $e->getMessage());
            // Devolver vista con colección vacía para no romper la UI
            $marcas = collect([]);
            $ultimoFolio = null;
            $permisos = $this->getPermisosMarcas();
            return view('modulos.marcas-finales.consultar-marcas-finales', compact('marcas', 'ultimoFolio', 'permisos'));
        }
    }

    /**
     * Generar un nuevo folio para marcas
     */
    public function generarFolio()
    {
        try {
            $usuario = Auth::user();
            $permisos = $this->getPermisosMarcas();
            if (!$permisos || !$permisos->acceso || !$permisos->crear) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autorizado para generar folios'
                ], 403);
            }
            
            // Obtener el último folio de la tabla TejMarcas
            $ultimoFolio = DB::table('TejMarcas')
                ->orderBy('Folio', 'desc')
                ->value('Folio');

            if ($ultimoFolio) {
                // Extraer los dígitos del folio y continuar la secuencia
                $soloDigitos = preg_replace('/\D/', '', $ultimoFolio);
                $numero = intval($soloDigitos) + 1;
                // Nuevo prefijo solicitado: FM
                $nuevoFolio = 'FM' . str_pad($numero, 4, '0', STR_PAD_LEFT);
            } else {
                // Primer folio
                $nuevoFolio = 'FM0001';
            }

            return response()->json([
                'success' => true,
                'folio' => $nuevoFolio,
                'usuario' => $usuario->nombre ?? 'Usuario',
                'numero_empleado' => $usuario->numero_empleado ?? ''
            ]);

        } catch (\Exception $e) {
            Log::error('Error al generar folio de marcas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al generar folio'
            ], 500);
        }
    }

    /**
     * Obtener datos STD (%Efi) desde ReqProgramaTejido
     */
    public function obtenerDatosSTD()
    {
        try {
            Log::info('=== Iniciando obtenerDatosSTD ===');
            
            // Verificar conexión a base de datos
            try {
                DB::connection()->getPdo();
                Log::info('Conexión a base de datos OK');
            } catch (\Exception $e) {
                Log::error('Error de conexión a BD: ' . $e->getMessage());
                throw new \Exception('No se pudo conectar a la base de datos');
            }
            
            // Obtener secuencia de telares - intentar primero InvSecuenciaMarcas, luego InvSecuenciaTelares
            Log::info('Consultando secuencia de telares...');
            try {
                $secuencia = DB::table('InvSecuenciaMarcas')
                    ->orderBy('Orden', 'asc')
                    ->select('NoTelarId', 'SalonId')
                    ->get();
                Log::info('Telares obtenidos de InvSecuenciaMarcas: ' . $secuencia->count());
            } catch (\Exception $e) {
                Log::warning('InvSecuenciaMarcas no disponible, usando InvSecuenciaTelares');
                // En InvSecuenciaTelares la columna es NoTelar; mapear como NoTelarId
                $secuencia = DB::table('InvSecuenciaTelares')
                    ->orderBy('Secuencia', 'asc')
                    ->selectRaw('NoTelar as NoTelarId')
                    ->get()
                    ->map(function ($row) {
                        $row->SalonId = null; // Se derivará de ReqProgramaTejido
                        return $row;
                    });
                Log::info('Telares obtenidos de InvSecuenciaTelares (mapeados): ' . $secuencia->count());
            }

            if ($secuencia->isEmpty()) {
                Log::warning('No se encontraron telares en las tablas de secuencia');
                return response()->json([
                    'success' => true,
                    'datos' => []
                ]);
            }

            $datos = [];
            foreach ($secuencia as $row) {
                try {
                    $noTelar = $row->NoTelarId;
                    
                    Log::info("Procesando telar: {$noTelar}");
                    
                    // Buscar eficiencia en ReqProgramaTejido con manejo de errores
                    $eficiencia = null;
                    try {
                        $eficiencia = DB::table('ReqProgramaTejido')
                            ->where('NoTelarId', $noTelar)
                            ->orderBy('FechaInicio', 'desc')
                            ->first();
                    } catch (\Exception $e) {
                        Log::warning("Error al buscar eficiencia para telar {$noTelar}: " . $e->getMessage());
                    }

                    // Usar EficienciaSTD cuando exista
                    $porcentajeEfi = $eficiencia ? number_format(($eficiencia -> EficienciaSTD ?? $eficiencia -> EficienciaSTD ?? 0) *100, 0) :0;

                    Log::info("Telar {$noTelar} - Salón: {$row->SalonId} - Eficiencia: {$porcentajeEfi}");

                    $datos[] = [
                        'telar' => $noTelar,
                        // Preferir el salón real desde ReqProgramaTejido cuando esté disponible
                        'salon' => $eficiencia->SalonTejidoId ?? ($row->SalonId ?? '-'),
                        'porcentaje_efi' => $porcentajeEfi
                    ];
                } catch (\Exception $e) {
                    Log::error("Error procesando telar {$noTelar}: " . $e->getMessage());
                    // Continuar con el siguiente telar
                    continue;
                }
            }

            Log::info('Total de datos procesados exitosamente: ' . count($datos));

            return response()->json([
                'success' => true,
                'datos' => $datos
            ]);

        } catch (\Exception $e) {
            Log::error('Error crítico en obtenerDatosSTD: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos STD: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Guardar o actualizar datos de marcas
     */
    public function store(Request $request)
    {
        try {
            $folio = $request->input('folio');
            $fecha = $request->input('fecha') ?: now()->toDateString();
            $turno = $request->input('turno') ?: TurnoHelper::getTurnoActual();
            $status = $request->input('status', 'En Proceso');
            $lineas = $request->input('lineas', []);

            $usuario = Auth::user();

            $permisos = $this->getPermisosMarcas();
            if (!$permisos || !$permisos->acceso) {
                return response()->json([
                    'success' => false,
                    'message' => 'Acceso no autorizado'
                ], 403);
            }

            // Determinar si es creación o actualización
            $existe = DB::table('TejMarcas')->where('Folio', $folio)->exists();
            if (!$existe && (!$permisos->crear)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autorizado para crear marcas'
                ], 403);
            }
            if ($existe && (!$permisos->modificar)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autorizado para modificar marcas'
                ], 403);
            }

            // Verificar si ya existe el registro
            // $existe ya determinado arriba

            if ($existe) {
                // Actualizar registro existente
                DB::table('TejMarcas')
                    ->where('Folio', $folio)
                    ->update([
                        'Date' => $fecha,
                        'Turno' => $turno,
                        'Status' => $status,
                        'updated_at' => now()
                    ]);
            } else {
                // Crear nuevo registro
                DB::table('TejMarcas')->insert([
                    'Folio'           => $folio,
                    'Date'            => $fecha,
                    'Turno'           => $turno,
                    'Status'          => $status,
                    // columnas existentes según el modelo TejMarcas
                    'numero_empleado' => $usuario->numero_empleado ?? null,
                    'nombreEmpl'      => $usuario->nombre ?? null,
                    'created_at'      => now(),
                    'updated_at'      => now()
                ]);
            }

            // Guardar líneas (eliminar las existentes y crear nuevas)
            DB::table('TejMarcasLine')->where('Folio', $folio)->delete();

            foreach ($lineas as $linea) {
                $noTelar = $linea['NoTelarId'];

                // Obtener salón y eficiencia estándar desde ReqProgramaTejido (último registro por telar)
                $std = DB::table('ReqProgramaTejido')
                    ->where('NoTelarId', $noTelar)
                    ->orderBy('FechaInicio', 'desc')
                    ->select('SalonTejidoId', 'EficienciaSTD')
                    ->first();

                // Determinar eficiencia a guardar: usar porcentaje enviado si viene, si no usar STD
                $efiPercent = isset($linea['PorcentajeEfi']) ? intval($linea['PorcentajeEfi']) : null;
                $efiDecimal = $efiPercent !== null ? ($efiPercent / 100) : ($std->EficienciaSTD ?? null);

                DB::table('TejMarcasLine')->insert([
                    'Folio'          => $folio,
                    'Date'           => $fecha,
                    'Turno'          => $turno,
                    'SalonTejidoId'  => $std->SalonTejidoId ?? null,
                    'NoTelarId'      => $noTelar,
                    'Eficiencia'     => $efiDecimal,
                    'Marcas'         => $linea['Marcas'] ?? 0,
                    'Trama'          => $linea['Trama'] ?? 0,
                    'Pie'            => $linea['Pie'] ?? 0,
                    'Rizo'           => $linea['Rizo'] ?? 0,
                    'Otros'          => $linea['Otros'] ?? 0,
                    'created_at'     => now(),
                    'updated_at'     => now()
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Datos guardados correctamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al guardar marcas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar datos'
            ], 500);
        }
    }

    /**
     * Obtener una marca específica por folio
     */
    public function show($folio)
    {
        try {
            $marca = DB::table('TejMarcas')
                ->where('Folio', $folio)
                ->first();

            if (!$marca) {
                return response()->json([
                    'success' => false,
                    'message' => 'Marca no encontrada'
                ], 404);
            }

            // Obtener líneas
            $lineas = DB::table('TejMarcasLine')
                ->where('Folio', $folio)
                ->orderBy('NoTelarId')
                ->get();

            return response()->json([
                'success' => true,
                'marca' => $marca,
                'lineas' => $lineas
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener marca: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener marca'
            ], 500);
        }
    }

    /**
     * Actualizar una marca existente
     */
    public function update(Request $request, $folio)
    {
        // Reutilizar el método store
        return $this->store($request);
    }

    /**
     * Finalizar una marca (cambiar status a "Finalizado")
     */
    public function finalizar($folio)
    {
        try {
            $permisos = $this->getPermisosMarcas();
            if (!$permisos || !$permisos->acceso || (!$permisos->eliminar && !$permisos->modificar)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autorizado para finalizar'
                ], 403);
            }
            $actualizado = DB::table('TejMarcas')
                ->where('Folio', $folio)
                ->update([
                    'Status' => 'Finalizado',
                    'updated_at' => now()
                ]);

            if ($actualizado) {
                return response()->json([
                    'success' => true,
                    'message' => 'Marca finalizada correctamente'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Marca no encontrada'
                ], 404);
            }

        } catch (\Exception $e) {
            Log::error('Error al finalizar marca: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al finalizar marca'
            ], 500);
        }
    }

    /**
     * Obtener permisos del módulo Marcas Finales para el usuario autenticado
     */
    private function getPermisosMarcas()
    {
        $user = Auth::user();
        if (!$user) return null;
        return DB::table('SYSUsuariosRoles')
            ->join('SYSRoles', 'SYSUsuariosRoles.idrol', '=', 'SYSRoles.idrol')
            ->where('SYSUsuariosRoles.idusuario', $user->idusuario)
            ->where('SYSUsuariosRoles.acceso', true)
            ->where(function($query) {
                $query->where('SYSRoles.modulo', 'LIKE', '%Marcas Finales%')
                      ->orWhere('SYSRoles.modulo', 'LIKE', '%Nuevas Marcas Finales%')
                      ->orWhere('SYSRoles.modulo', 'LIKE', '%marcas finales%')
                      ->orWhere('SYSRoles.modulo', 'LIKE', '%nuevas marcas finales%');
            })
            ->select('SYSUsuariosRoles.*', 'SYSRoles.modulo')
            ->first();
    }
}
