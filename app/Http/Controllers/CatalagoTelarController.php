<?php

namespace App\Http\Controllers;

use App\Models\CatalagoTelar;
use App\Models\ReqTelares;
use App\Exports\TelaresPlantillaExport;
use App\Imports\ReqTelaresImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class CatalagoTelarController extends Controller
{
    public function index(Request $request)
    {
        try {
            // Obtener datos reales desde la base de datos usando ReqTelares
            $query = ReqTelares::query();

            // Aplicar filtros de búsqueda
            if ($request->filled('salon')) {
                $query->where('SalonTejidoId', 'like', "%{$request->salon}%");
            }

            if ($request->filled('telar')) {
                $query->where('NoTelarId', 'like', "%{$request->telar}%");
            }

            if ($request->filled('nombre')) {
                $query->where('Nombre', 'like', "%{$request->nombre}%");
            }

            if ($request->filled('grupo')) {
                $query->where('Grupo', 'like', "%{$request->grupo}%");
            }

            // Obtener resultados ordenados
            $telares = $query->orderBy('SalonTejidoId')
                           ->orderBy('NoTelarId')
                           ->get();

            // Verifica si hay resultados
            $noResults = $telares->isEmpty();

            // Pasa los resultados y el estado de "sin resultados"
            return view('catalagos.catalagoTelares', compact('telares', 'noResults'));

        } catch (\Exception $e) {
            Log::error('Error al obtener telares: ' . $e->getMessage());

            // En caso de error, mostrar datos vacíos
            $telares = collect();
            $noResults = true;

            return view('catalagos.catalagoTelares', compact('telares', 'noResults'))
                ->with('error', 'Error al cargar los datos de telares');
        }
    }


    public function show($id)
    {
        // Solo para evitar el error
        return redirect()->route('telares.index');
    }

    /**
     * Procesar archivo Excel subido
     */
    public function procesarExcel(Request $request)
    {
        try {
            // Validar que se haya subido un archivo
            $validator = Validator::make($request->all(), [
                'archivo_excel' => 'required|file|mimes:xlsx,xls|max:10240' // 10MB máximo
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Archivo inválido. Debe ser un archivo Excel (.xlsx o .xls) de máximo 10MB.',
                    'errors' => $validator->errors()
                ], 400);
            }

            $archivo = $request->file('archivo_excel');
            $nombreArchivo = $archivo->getClientOriginalName();
            Log::info('Procesando archivo Excel: ' . $nombreArchivo);

            // Usar transacciones para asegurar consistencia
            DB::beginTransaction();

            try {
                // Contar registros antes de la importación
                $registrosAntes = ReqTelares::count();

                // Procesar el archivo Excel usando la clase de importación
                $importador = new ReqTelaresImport();
                Excel::import($importador, $archivo);

                // Obtener estadísticas del importador
                $stats = $importador->getStats();

                // Contar registros después de la importación
                $registrosDespues = ReqTelares::count();
                $registrosImportados = $registrosDespues - $registrosAntes;

                // Log de estadísticas para debugging
                Log::info('Estadísticas de importación de telares', [
                    'stats' => $stats,
                    'registros_antes' => $registrosAntes,
                    'registros_despues' => $registrosDespues,
                    'registros_importados' => $registrosImportados
                ]);

                DB::commit();

                // Preparar respuesta
                $mensaje = "Archivo {$nombreArchivo} procesado exitosamente. ";
                $mensaje .= "Registros procesados: {$stats['processed_rows']}, ";
                $mensaje .= "Creados: {$stats['created_rows']}, ";
                $mensaje .= "Actualizados: {$stats['updated_rows']}, ";
                $mensaje .= "Filas saltadas: {$stats['skipped_rows']}";

                if (!empty($stats['errores'])) {
                    $totalErrores = count($stats['errores']);
                    $mensaje .= ". Errores encontrados: {$totalErrores}";
                }

                return response()->json([
                    'success' => true,
                    'message' => $mensaje,
                    'data' => [
                        'registros_procesados' => $stats['processed_rows'],
                        'registros_creados' => $stats['created_rows'],
                        'registros_actualizados' => $stats['updated_rows'],
                        'errores' => array_slice($stats['errores'], 0, 10), // Primeros 10 errores
                        'total_errores' => count($stats['errores']),
                        'filas_saltadas' => $stats['skipped_rows']
                    ]
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Error al procesar Excel de telares: ' . $e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Error interno del servidor al procesar el archivo Excel: ' . $e->getMessage()
                ], 500);
            }

        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $errores = [];
            foreach ($e->failures() as $failure) {
                $errores[] = "Fila {$failure->row()}: " . implode(', ', $failure->errors());
            }

            return response()->json([
                'success' => false,
                'message' => 'Error de validación en el archivo: ' . implode('; ', $errores)
            ], 422);

        } catch (\Exception $e) {
            Log::error('Error al procesar Excel: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor al procesar el archivo Excel: ' . $e->getMessage()
            ], 500);
        }
    }


    /**
     * Mostrar formulario para crear un nuevo telar
     */
    public function create()
    {
        return view('catalagos.create-telar');
    }

    /**
     * Guardar un nuevo telar
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'SalonTejidoId' => 'required|string|max:20',
                'NoTelarId' => 'required|string|max:10',
                'Grupo' => 'nullable|string|max:30'
            ]);

            // Verificar si ya existe un telar con el mismo salón y número
            $telarExistente = ReqTelares::where('SalonTejidoId', $request->SalonTejidoId)
                                      ->where('NoTelarId', $request->NoTelarId)
                                      ->first();

            if ($telarExistente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe un telar con el mismo salón y número'
                ], 422);
            }

            // Generar nombre automáticamente
            $nombre = $this->generarNombre($request->SalonTejidoId, $request->NoTelarId);

            $telar = ReqTelares::create([
                'SalonTejidoId' => $request->SalonTejidoId,
                'NoTelarId' => $request->NoTelarId,
                'Nombre' => $nombre,
                'Grupo' => $request->Grupo
            ]);

            return response()->json([
                'success' => true,
                'message' => "Telar '{$nombre}' creado exitosamente"
            ]);

        } catch (\Exception $e) {
            Log::error('Error al crear telar: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el telar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar formulario para editar un telar
     */
    public function edit(ReqTelares $telar)
    {
        return view('catalagos.edit-telar', compact('telar'));
    }

    /**
     * Actualizar un telar existente
     */
    public function update(Request $request, $uniqueId)
    {
        try {
            $request->validate([
                'SalonTejidoId' => 'required|string|max:20',
                'NoTelarId' => 'required|string|max:10',
                'Grupo' => 'nullable|string|max:30'
            ]);

            // Buscar el telar por uniqueId (SalonTejidoId_NoTelarId)
            $partes = explode('_', $uniqueId);
            if (count($partes) !== 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID de telar inválido'
                ], 400);
            }

            $salon = $partes[0];
            $telarNum = $partes[1];

            $telar = ReqTelares::where('SalonTejidoId', $salon)
                              ->where('NoTelarId', $telarNum)
                              ->first();

            if (!$telar) {
                return response()->json([
                    'success' => false,
                    'message' => 'Telar no encontrado'
                ], 404);
            }

            // Verificar si ya existe otro telar con el mismo salón y número (excluyendo el actual)
            $telarExistente = ReqTelares::where('SalonTejidoId', $request->SalonTejidoId)
                                      ->where('NoTelarId', $request->NoTelarId)
                                      ->where('id', '!=', $telar->id)
                                      ->first();

            if ($telarExistente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe otro telar con el mismo salón y número'
                ], 422);
            }

            // Generar nombre automáticamente
            $nombre = $this->generarNombre($request->SalonTejidoId, $request->NoTelarId);

            $telar->update([
                'SalonTejidoId' => $request->SalonTejidoId,
                'NoTelarId' => $request->NoTelarId,
                'Nombre' => $nombre,
                'Grupo' => $request->Grupo
            ]);

            return response()->json([
                'success' => true,
                'message' => "Telar '{$nombre}' actualizado exitosamente"
            ]);

        } catch (\Exception $e) {
            Log::error('Error al actualizar telar: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el telar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar un telar
     */
    public function destroy($uniqueId)
    {
        try {
            // Buscar el telar por uniqueId (SalonTejidoId_NoTelarId)
            $partes = explode('_', $uniqueId);
            if (count($partes) !== 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID de telar inválido'
                ], 400);
            }

            $salon = $partes[0];
            $telarNum = $partes[1];

            $telar = ReqTelares::where('SalonTejidoId', $salon)
                              ->where('NoTelarId', $telarNum)
                              ->first();

            if (!$telar) {
                return response()->json([
                    'success' => false,
                    'message' => 'Telar no encontrado'
                ], 404);
            }

            $nombre = $telar->Nombre;
            $telar->delete();

            return response()->json([
                'success' => true,
                'message' => "Telar '{$nombre}' eliminado exitosamente"
            ]);

        } catch (\Exception $e) {
            Log::error('Error al eliminar telar: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el telar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Genera el nombre del telar basado en el salón y número
     */
    private function generarNombre($salon, $telar)
    {
        if (empty($salon) || empty($telar)) {
            return null;
        }

        // Convertir a mayúsculas para comparación
        $salonUpper = strtoupper(trim($salon));

        // Determinar el prefijo basado en el salón
        if (strpos($salonUpper, 'JACQUARD') !== false) {
            $prefijo = 'JAC';
        } elseif (strpos($salonUpper, 'SMITH') !== false) {
            $prefijo = 'Smith';
        } else {
            // Si no coincide con los patrones conocidos, usar las primeras 3 letras del salón
            $prefijo = strtoupper(substr(trim($salon), 0, 3));
        }

        return $prefijo . ' ' . $telar;
    }

    /**
     * Mostrar vista de fallas de telares
     */
    public function falla()
    {
        return view('telares.falla');
    }

}
