<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ReqCalendarioTab;
use App\Models\ReqCalendarioLine;
use App\Imports\ReqCalendarioImport;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\ReqCalendarioLineImport;
use App\Imports\ReqCalendarioTabImport;

class CalendarioController extends Controller
{
    public function index(Request $request)
    {
        // Obtener datos reales de la base de datos
        $calendarios = ReqCalendarioTab::orderBy('CalendarioId')->get();
        $lineas = ReqCalendarioLine::orderBy('CalendarioId')->orderBy('FechaInicio')->get();

        // Pasar con los nombres que espera la vista
        return view('catalagos.calendarios', [
            'calendarioTab' => $calendarios,
            'calendarioLine' => $lineas
        ]);
    }

    /**
     * Crear nuevo calendario
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'CalendarioId' => 'required|string|max:20|unique:ReqCalendarioTab,CalendarioId',
                'Nombre' => 'required|string|max:255'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            $calendario = ReqCalendarioTab::create([
                'CalendarioId' => $request->CalendarioId,
                'Nombre' => $request->Nombre
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Calendario creado exitosamente',
                'data' => $calendario
            ]);

        } catch (\Exception $e) {
            Log::error("Error al crear calendario: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Actualizar calendario
     */
    public function update(Request $request, $id)
    {
        try {
            $calendario = ReqCalendarioTab::where('CalendarioId', $id)->first();
            if (!$calendario) {
                return response()->json([
                    'success' => false,
                    'message' => 'Calendario no encontrado'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'Nombre' => 'required|string|max:255'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            $calendario->update([
                'Nombre' => $request->Nombre
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Calendario actualizado exitosamente',
                'data' => $calendario
            ]);

        } catch (\Exception $e) {
            Log::error("Error al actualizar calendario: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Eliminar calendario
     */
    public function destroy($id)
    {
        try {
            $calendario = ReqCalendarioTab::where('CalendarioId', $id)->first();
            if (!$calendario) {
                return response()->json([
                    'success' => false,
                    'message' => 'Calendario no encontrado'
                ], 404);
            }

            // Eliminar también las líneas asociadas
            ReqCalendarioLine::where('CalendarioId', $calendario->CalendarioId)->delete();
            $calendario->delete();

            return response()->json([
                'success' => true,
                'message' => 'Calendario y sus líneas eliminados exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error("Error al eliminar calendario: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Crear nueva línea de calendario
     */
    public function storeLine(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'CalendarioId' => 'required|string|max:20',
                'FechaInicio' => 'required|date',
                'FechaFin' => 'required|date',
                'HorasTurno' => 'required|numeric|min:0',
                'Turno' => 'required|integer|min:1'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Verificar que el calendario existe
            $calendario = ReqCalendarioTab::where('CalendarioId', $request->CalendarioId)->first();
            if (!$calendario) {
                return response()->json([
                    'success' => false,
                    'message' => 'El calendario especificado no existe'
                ], 422);
            }

            $linea = ReqCalendarioLine::create([
                'CalendarioId' => $request->CalendarioId,
                'FechaInicio' => $request->FechaInicio,
                'FechaFin' => $request->FechaFin,
                'HorasTurno' => $request->HorasTurno,
                'Turno' => $request->Turno
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Línea de calendario creada exitosamente',
                'data' => $linea
            ]);

        } catch (\Exception $e) {
            Log::error("Error al crear línea de calendario: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Actualizar línea de calendario
     */
    public function updateLine(Request $request, $id)
    {
        try {
            Log::info("updateLine llamado con ID: {$id}");
            Log::info("Datos recibidos: " . json_encode($request->all()));

            $linea = ReqCalendarioLine::find($id);
            if (!$linea) {
                Log::error("Línea no encontrada con ID: {$id}");
                return response()->json([
                    'success' => false,
                    'message' => 'Línea de calendario no encontrada'
                ], 404);
            }

            Log::info("Línea encontrada: " . json_encode($linea->toArray()));

            $validator = Validator::make($request->all(), [
                'FechaInicio' => 'required|date',
                'FechaFin' => 'required|date',
                'HorasTurno' => 'required|numeric|min:0',
                'Turno' => 'required|integer|min:1'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            Log::info("Actualizando línea con datos:", [
                'FechaInicio' => $request->FechaInicio,
                'FechaFin' => $request->FechaFin,
                'HorasTurno' => $request->HorasTurno,
                'Turno' => $request->Turno
            ]);

            $resultado = $linea->update([
                'FechaInicio' => $request->FechaInicio,
                'FechaFin' => $request->FechaFin,
                'HorasTurno' => $request->HorasTurno,
                'Turno' => $request->Turno
            ]);

            Log::info("Resultado de la actualización: " . ($resultado ? 'true' : 'false'));

            Log::info("Línea actualizada exitosamente");

            // Refrescar el modelo para obtener los datos actualizados
            $lineaActualizada = $linea->fresh();
            if ($lineaActualizada) {
                Log::info("Datos después de actualizar: " . json_encode($lineaActualizada->toArray()));
            } else {
                Log::warning("No se pudo obtener los datos actualizados de la línea");
            }

            return response()->json([
                'success' => true,
                'message' => 'Línea de calendario actualizada exitosamente',
                'data' => $linea
            ]);

        } catch (\Exception $e) {
            Log::error("Error al actualizar línea de calendario: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Eliminar línea de calendario
     */
    public function destroyLine($id)
    {
        try {
            Log::info("destroyLine llamado con ID: {$id}");

            $linea = ReqCalendarioLine::find($id);
            if (!$linea) {
                Log::error("Línea no encontrada con ID: {$id}");
                return response()->json([
                    'success' => false,
                    'message' => 'Línea de calendario no encontrada'
                ], 404);
            }

            Log::info("Línea encontrada para eliminar: " . json_encode($linea->toArray()));

            $linea->delete();
            Log::info("Línea eliminada exitosamente");

            return response()->json([
                'success' => true,
                'message' => 'Línea de calendario eliminada exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error("Error al eliminar línea de calendario: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Procesar archivo Excel de calendarios
     */
    public function procesarExcel(Request $request)
    {
        try {
            // ⚡ Aumentar timeout para procesamiento de Excel grande
            set_time_limit(300); // 5 minutos

            Log::info("========== INICIO PROCESAMIENTO EXCEL ==========");
            Log::info("Método HTTP: {$request->getMethod()}");
            Log::info("Archivos subidos: " . json_encode($request->files->keys()));

            // Obtener el tipo de importación
            $tipo = $request->input('tipo', 'calendarios');
            Log::info("Tipo de importación: {$tipo}");

            // Validar el archivo
            $validator = Validator::make($request->all(), [
                'archivo_excel' => 'required|file|mimes:xlsx,xls|max:10240'
            ]);

            if ($validator->fails()) {
                Log::error("Validación fallida: " . json_encode($validator->errors()->all()));
                return response()->json([
                    'success' => false,
                    'message' => 'Archivo inválido. Debe ser un archivo Excel (.xlsx o .xls) de máximo 10MB.',
                    'errors' => $validator->errors()
                ], 400);
            }

            $archivo = $request->file('archivo_excel');
            $nombreArchivo = $archivo->getClientOriginalName();
            $rutaTemporal = $archivo->getPathname();

            Log::info("Archivo validado correctamente");
            Log::info("Nombre: {$nombreArchivo}");
            Log::info("Ruta temporal: {$rutaTemporal}");
            Log::info("Tamaño: {$archivo->getSize()} bytes");

            // Usar transacciones
            DB::beginTransaction();
            Log::info("Transacción iniciada");

            try {
                Log::info("Importando archivo con importador según tipo...");

                // Usar importador según el tipo
                if ($tipo === 'lineas') {
                    Log::info("Usando ReqCalendarioLineImport para líneas");
                    $importador = new ReqCalendarioLineImport();
                } else {
                    Log::info("Usando ReqCalendarioTabImport para calendarios");
                    $importador = new ReqCalendarioTabImport();
                }

                Excel::import($importador, $archivo);

                Log::info("Importador completó procesamiento");

                // Obtener estadísticas
                $stats = $importador->getStats();

                Log::info("Estadísticas obtenidas:", $stats);

                DB::commit();
                Log::info("Transacción confirmada (COMMIT)");

                Log::info("Excel procesado exitosamente", $stats);

                return response()->json([
                    'success' => true,
                    'message' => 'Archivo procesado exitosamente',
                    'data' => [
                        'registros_procesados' => $stats['procesados'],
                        'registros_creados' => $stats['creados'],
                        'registros_actualizados' => 0,
                        'total_errores' => count($stats['errores']),
                        'errores' => array_slice($stats['errores'], 0, 10)
                    ]
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("ERROR en transacción: {$e->getMessage()}", [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
                Log::error("Transacción revertida (ROLLBACK)");

                return response()->json([
                    'success' => false,
                    'message' => 'Error interno del servidor al procesar el archivo Excel: ' . $e->getMessage()
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error("EXCEPCIÓN GENERAL en procesarExcel: {$e->getMessage()}", [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error inesperado: ' . $e->getMessage()
            ], 500);
        }
    }
}
