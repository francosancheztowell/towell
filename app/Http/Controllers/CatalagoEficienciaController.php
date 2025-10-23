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
        // Aumentar tiempo límite de ejecución para archivos grandes
        set_time_limit(300); // 5 minutos

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
            // Validación rápida
            $request->validate([
                'NoTelarId' => 'required|string|max:10',
                'FibraId' => 'required|string|max:20',
                'Eficiencia' => 'required|numeric|min:0|max:1',
                'Densidad' => 'nullable|string|max:10'
            ]);

            // Usar solo el número del telar para evitar problemas de longitud
            $salon = $request->SalonTejidoId ?: 'JACQUARD'; // Usar salón enviado o JACQUARD por defecto

            // Verificar duplicados en una sola consulta
            if (ReqEficienciaStd::where('SalonTejidoId', $salon)
                               ->where('NoTelarId', $request->NoTelarId)
                               ->where('FibraId', $request->FibraId)
                               ->where('Densidad', $request->Densidad ?? 'Normal')
                               ->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe una eficiencia para este telar y tipo de fibra'
                ], 422);
            }

            // Crear registro
            ReqEficienciaStd::create([
                'SalonTejidoId' => $salon,
                'NoTelarId' => $request->NoTelarId, // Solo el número del telar
                'FibraId' => $request->FibraId,
                'Eficiencia' => $request->Eficiencia,
                'Densidad' => $request->Densidad ?? 'Normal'
            ]);

            return response()->json([
                'success' => true,
                'message' => "Eficiencia para '{$salon} {$request->NoTelarId} - {$request->FibraId}' creada exitosamente"
            ]);

        } catch (\Exception $e) {
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
                'NoTelarId' => 'required|string|max:20',
                'FibraId' => 'required|string|max:30',
                'Eficiencia' => 'required|numeric|min:0|max:1',
                'Densidad' => 'nullable|string|max:10'
            ]);

            // Usar solo el número del telar para evitar problemas de longitud
            $salon = $request->SalonTejidoId ?: 'JACQUARD'; // Usar salón enviado o JACQUARD por defecto

            // Verificar duplicados excluyendo el registro actual
            if (ReqEficienciaStd::where('SalonTejidoId', $salon)
                               ->where('NoTelarId', $request->NoTelarId)
                               ->where('FibraId', $request->FibraId)
                               ->where('Densidad', $request->Densidad ?? 'Normal')
                               ->where('Id', '!=', (int)$eficiencia->Id)
                               ->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe otra eficiencia para este telar y tipo de fibra'
                ], 422);
            }

            // Actualizar registro
            $eficiencia->update([
                'SalonTejidoId' => $salon,
                'NoTelarId' => $request->NoTelarId, // Solo el número del telar
                'FibraId' => $request->FibraId,
                'Eficiencia' => $request->Eficiencia,
                'Densidad' => $request->Densidad ?? 'Normal'
            ]);

            return response()->json([
                'success' => true,
                'message' => "Eficiencia para '{$salon} {$request->NoTelarId} - {$request->FibraId}' actualizada exitosamente"
            ]);

        } catch (\Exception $e) {
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
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la eficiencia: ' . $e->getMessage()
            ], 500);
        }
    }

}
