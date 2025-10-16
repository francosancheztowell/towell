<?php

namespace App\Http\Controllers;

use App\Models\ReqEficienciaStd;
use App\Imports\ReqEficienciaStdImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class CatalagoEficienciaController extends Controller
{
    /**
     * Mostrar lista de eficiencias con filtros
     */
    public function index(Request $request)
    {
        // Obtener todos los resultados sin filtros del servidor
        // Los filtros se manejan del lado del cliente con JavaScript
        $eficiencia = ReqEficienciaStd::orderBy('SalonTejidoId')
                                    ->orderBy('NoTelarId')
                                    ->orderBy('FibraId')
                                    ->get();

        // Siempre hay resultados ya que no filtramos del lado del servidor
        $noResults = false;

        // Pasa los resultados
        return view('catalagos.catalagoEficiencia', compact('eficiencia', 'noResults'));
    }

    /**
     * Procesar archivo Excel de eficiencias
     */
    public function procesarExcel(Request $request)
    {
        try {
            // Validar el archivo
            $request->validate([
                'archivo_excel' => 'required|file|mimes:xlsx,xls|max:10240' // 10MB máximo
            ]);

            $archivo = $request->file('archivo_excel');

            Log::info('Procesando archivo Excel de eficiencias', [
                'nombre' => $archivo->getClientOriginalName(),
                'tamaño' => $archivo->getSize(),
                'mime' => $archivo->getMimeType()
            ]);

            DB::beginTransaction();

            try {
                // Crear instancia del importador
                $import = new ReqEficienciaStdImport();

                // Importar el archivo
                Excel::import($import, $archivo);

                // Obtener estadísticas
                $stats = $import->getStats();

                DB::commit();

                Log::info('Excel de eficiencias procesado exitosamente', $stats);

                return response()->json([
                    'success' => true,
                    'message' => 'Archivo procesado exitosamente',
                    'data' => [
                        'registros_procesados' => $stats['processed_rows'],
                        'registros_creados' => $stats['created_rows'],
                        'registros_actualizados' => $stats['updated_rows'],
                        'total_errores' => count($stats['errores']),
                        'errores' => array_slice($stats['errores'], 0, 10) // Primeros 10 errores
                    ]
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Error al procesar Excel de eficiencias: ' . $e->getMessage(), [
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
     * Crear nueva eficiencia
     */
    public function store(Request $request)
    {
        try {
        $request->validate([
                'NoTelarId' => 'required|string|max:10',
                'FibraId' => 'required|string|max:15',
                'Eficiencia' => 'required|numeric|min:0|max:1',
                'Densidad' => 'nullable|string|max:10'
            ]);

            // Extraer el salón del nombre del telar (ej: "JAC 201" -> "Jacquard")
            $salon = $this->extraerSalon($request->NoTelarId);

            // Verificar si ya existe una eficiencia con el mismo telar y fibra
            $eficienciaExistente = ReqEficienciaStd::where('NoTelarId', $request->NoTelarId)
                                                  ->where('FibraId', $request->FibraId)
                                                  ->first();

            if ($eficienciaExistente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe una eficiencia para este telar y tipo de fibra'
                ], 422);
            }

            $eficiencia = ReqEficienciaStd::create([
                'SalonTejidoId' => $salon,
                'NoTelarId' => $request->NoTelarId,
                'FibraId' => $request->FibraId,
                'Eficiencia' => $request->Eficiencia,
                'Densidad' => $request->Densidad ?? 'Normal'
            ]);

            return response()->json([
                'success' => true,
                'message' => "Eficiencia para '{$request->NoTelarId} - {$request->FibraId}' creada exitosamente"
            ]);

        } catch (\Exception $e) {
            Log::error('Error al crear eficiencia: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la eficiencia: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar eficiencia existente
     */
    public function update(Request $request, ReqEficienciaStd $eficiencia)
    {
        try {
            $request->validate([
                'NoTelarId' => 'required|string|max:10',
                'FibraId' => 'required|string|max:15',
                'Eficiencia' => 'required|numeric|min:0|max:1',
                'Densidad' => 'nullable|string|max:10'
            ]);

            // Extraer el salón del nombre del telar
            $salon = $this->extraerSalon($request->NoTelarId);

            // Verificar si ya existe otra eficiencia con el mismo telar y fibra (excluyendo la actual)
            $eficienciaExistente = ReqEficienciaStd::where('NoTelarId', $request->NoTelarId)
                                                  ->where('FibraId', $request->FibraId)
                                                  ->where('id', '!=', $eficiencia->id)
                                                  ->first();

            if ($eficienciaExistente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe otra eficiencia para este telar y tipo de fibra'
                ], 422);
            }

            $eficiencia->update([
                'SalonTejidoId' => $salon,
                'NoTelarId' => $request->NoTelarId,
                'FibraId' => $request->FibraId,
                'Eficiencia' => $request->Eficiencia,
                'Densidad' => $request->Densidad ?? 'Normal'
            ]);

            return response()->json([
                'success' => true,
                'message' => "Eficiencia para '{$request->NoTelarId} - {$request->FibraId}' actualizada exitosamente"
            ]);

        } catch (\Exception $e) {
            Log::error('Error al actualizar eficiencia: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la eficiencia: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar una eficiencia
     */
    public function destroy(ReqEficienciaStd $eficiencia)
    {
        try {
            $telar = $eficiencia->NoTelarId;
            $fibra = $eficiencia->FibraId;
            $eficiencia->delete();

            return response()->json([
                'success' => true,
                'message' => "Eficiencia para '{$telar} - {$fibra}' eliminada exitosamente"
            ]);

        } catch (\Exception $e) {
            Log::error('Error al eliminar eficiencia: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la eficiencia: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Extraer el salón del nombre del telar
     */
    private function extraerSalon($nombreTelar)
    {
        $nombreTelar = trim($nombreTelar);

        // Patrones conocidos
        if (stripos($nombreTelar, 'JAC') !== false) {
            return 'Jacquard';
        } elseif (stripos($nombreTelar, 'Smith') !== false) {
            return 'Smith';
        } elseif (stripos($nombreTelar, 'Itema') !== false) {
            return 'Itema';
        }

        // Si no coincide con ningún patrón, extraer la primera palabra
        $partes = explode(' ', $nombreTelar);
        return $partes[0] ?? 'Desconocido';
    }
}
