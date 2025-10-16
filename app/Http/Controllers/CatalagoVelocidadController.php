<?php

namespace App\Http\Controllers;

use App\Models\ReqVelocidadStd;
use App\Imports\ReqVelocidadStdImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class CatalagoVelocidadController extends Controller
{
    /**
     * Mostrar lista de velocidades
     */
    public function index(Request $request)
    {
        // Obtener todos los resultados sin filtros del servidor
        // Los filtros se manejan del lado del cliente con JavaScript
        $velocidad = ReqVelocidadStd::orderBy('SalonTejidoId')
                                    ->orderBy('NoTelarId')
                                    ->orderBy('FibraId')
                                    ->get();

        // Siempre hay resultados ya que no filtramos del lado del servidor
        $noResults = false;

        // Pasa los resultados
        return view('catalagos.catalagoVelocidad', compact('velocidad', 'noResults'));
    }

    /**
     * Procesar archivo Excel de velocidades
     */
    public function procesarExcel(Request $request)
    {
        try {
            // Validar el archivo
            $validator = Validator::make($request->all(), [
                'archivo_excel' => 'required|file|mimes:xlsx,xls|max:10240'
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
            Log::info('Procesando archivo Excel de velocidades: ' . $nombreArchivo);

            // Usar transacciones
            DB::beginTransaction();

            try {
                // Procesar el archivo
                $importador = new ReqVelocidadStdImport();
                Excel::import($importador, $archivo);

                // Obtener estadísticas
                $stats = $importador->getStats();

                DB::commit();

                Log::info('Excel de velocidades procesado exitosamente', $stats);

                return response()->json([
                    'success' => true,
                    'message' => 'Archivo procesado exitosamente',
                    'data' => [
                        'registros_procesados' => $stats['processed_rows'],
                        'registros_creados' => $stats['created_rows'],
                        'registros_actualizados' => $stats['updated_rows'],
                        'total_errores' => count($stats['errores']),
                        'errores' => array_slice($stats['errores'], 0, 10)
                    ]
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Error al procesar Excel de velocidades: ' . $e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Error interno del servidor al procesar el archivo Excel: ' . $e->getMessage()
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Error en procesarExcel: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error inesperado: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear nueva velocidad
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'NoTelarId' => 'required|string|max:50',
                'FibraId' => 'required|string|max:50',
                'RPM' => 'required|integer|min:0',
                'Densidad' => 'nullable|string|max:50'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validación fallida: ' . implode(', ', $validator->errors()->all())
                ], 422);
            }

            $velocidadExistente = ReqVelocidadStd::where('NoTelarId', $request->NoTelarId)
                                                ->where('FibraId', $request->FibraId)
                                                ->where('Densidad', $request->Densidad ?? 'Normal')
                                                ->first();

            if ($velocidadExistente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe una velocidad con los mismos datos'
                ], 422);
            }

            $salon = explode(' ', $request->NoTelarId)[0] ?? null;

            $velocidad = ReqVelocidadStd::create([
                'SalonTejidoId' => $salon,
                'NoTelarId' => $request->NoTelarId,
                'FibraId' => $request->FibraId,
                'RPM' => $request->RPM,
                'Densidad' => $request->Densidad ?? 'Normal'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Velocidad creada exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al crear velocidad: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la velocidad: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar velocidad
     */
    public function update(Request $request, ReqVelocidadStd $velocidad)
    {
        try {
            $validator = Validator::make($request->all(), [
                'NoTelarId' => 'required|string|max:50',
                'FibraId' => 'required|string|max:50',
                'RPM' => 'required|integer|min:0',
                'Densidad' => 'nullable|string|max:50'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validación fallida'
                ], 422);
            }

            $velocidadExistente = ReqVelocidadStd::where('NoTelarId', $request->NoTelarId)
                                                ->where('FibraId', $request->FibraId)
                                                ->where('Densidad', $request->Densidad ?? 'Normal')
                                                ->where('id', '!=', $velocidad->id)
                                                ->first();

            if ($velocidadExistente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe otra velocidad con los mismos datos'
                ], 422);
            }

            $salon = explode(' ', $request->NoTelarId)[0] ?? null;

            $velocidad->update([
                'SalonTejidoId' => $salon,
                'NoTelarId' => $request->NoTelarId,
                'FibraId' => $request->FibraId,
                'RPM' => $request->RPM,
                'Densidad' => $request->Densidad ?? 'Normal'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Velocidad actualizada exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al actualizar velocidad: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la velocidad: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar velocidad
     */
    public function destroy(ReqVelocidadStd $velocidad)
    {
        try {
            $velocidad->delete();

            return response()->json([
                'success' => true,
                'message' => 'Velocidad eliminada exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al eliminar velocidad: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la velocidad: ' . $e->getMessage()
            ], 500);
        }
    }
}
