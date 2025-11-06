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
    public function index()
    {
        try {
            Log::info('Cargando vista de nuevas marcas');
            
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

            return view('modulos.nuevo-marcas', compact('telares'));
        } catch (\Exception $e) {
            Log::error('Error al cargar vista de marcas: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            // Si hay error con las tablas, intentar con array vacío
            $telares = collect([]);
            
            return view('modulos.nuevo-marcas', compact('telares'));
        }
    }

    /**
     * Mostrar la vista para consultar marcas (consultar-marcas.blade.php)
     */
    public function consultar()
    {
        try {
            // Obtener todas las marcas ordenadas por fecha descendente
            // Ajustar según el nombre real de tu tabla y modelo
            $marcas = DB::table('TejMarcas')
                ->leftJoin('SYSUsuario', 'TejMarcas.idusuario', '=', 'SYSUsuario.idusuario')
                ->select('TejMarcas.*', 'SYSUsuario.nombre as nombreEmpl', 'SYSUsuario.numero_empleado')
                ->orderBy('TejMarcas.Date', 'desc')
                ->orderBy('TejMarcas.created_at', 'desc')
                ->get();

            // Cargar líneas para cada marca
            foreach ($marcas as $marca) {
                $marca->lineas = DB::table('TejMarcasLine')
                    ->where('Folio', $marca->Folio)
                    ->orderBy('NoTelarId')
                    ->get();
            }

            // Evitar caché del navegador para esta vista
            return response()
                ->view('modulos.consultar-marcas', compact('marcas'))
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0');
            
        } catch (\Exception $e) {
            Log::error('Error al consultar marcas: ' . $e->getMessage());
            
            // Si hay error, retornar vista vacía y sin caché
            $marcas = collect([]);
            return response()
                ->view('modulos.consultar-marcas', compact('marcas'))
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0');
        }
    }

    /**
     * Generar un nuevo folio para marcas
     */
    public function generarFolio()
    {
        try {
            $usuario = Auth::user();
            
            // Obtener el último folio de la tabla TejMarcas
            $ultimoFolio = DB::table('TejMarcas')
                ->orderBy('Folio', 'desc')
                ->value('Folio');

            if ($ultimoFolio) {
                // Extraer el número del folio (asumiendo formato MR0001)
                $numero = intval(substr($ultimoFolio, 2)) + 1;
                $nuevoFolio = 'MR' . str_pad($numero, 4, '0', STR_PAD_LEFT);
            } else {
                // Primer folio
                $nuevoFolio = 'MR0001';
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
                            ->orderBy('FechaModificacion', 'desc')
                            ->first();
                    } catch (\Exception $e) {
                        Log::warning("Error al buscar eficiencia para telar {$noTelar}: " . $e->getMessage());
                    }

                    $porcentajeEfi = $eficiencia ? ($eficiencia->Eficiencia ?? 0) : 0;

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
            $fecha = $request->input('fecha');
            $turno = $request->input('turno');
            $status = $request->input('status', 'En Proceso');
            $lineas = $request->input('lineas', []);

            $usuario = Auth::user();

            // Verificar si ya existe el registro
            $existe = DB::table('TejMarcas')->where('Folio', $folio)->exists();

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
                    'Folio' => $folio,
                    'Date' => $fecha,
                    'Turno' => $turno,
                    'Status' => $status,
                    'idusuario' => $usuario->idusuario,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            // Guardar líneas (eliminar las existentes y crear nuevas)
            DB::table('TejMarcasLine')->where('Folio', $folio)->delete();

            foreach ($lineas as $linea) {
                DB::table('TejMarcasLine')->insert([
                    'Folio' => $folio,
                    'NoTelarId' => $linea['NoTelarId'],
                    'PorcentajeEfi' => $linea['PorcentajeEfi'] ?? null,
                    'Trama' => $linea['Trama'] ?? 0,
                    'Pie' => $linea['Pie'] ?? 0,
                    'Rizo' => $linea['Rizo'] ?? 0,
                    'Otros' => $linea['Otros'] ?? 0,
                    'Marcas' => $linea['Marcas'] ?? 0,
                    'created_at' => now(),
                    'updated_at' => now()
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
}
