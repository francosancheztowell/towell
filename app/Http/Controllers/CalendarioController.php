<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ReqCalendarioTab;
use App\Models\ReqCalendarioLine;
use App\Models\ReqProgramaTejido;
use App\Observers\ReqProgramaTejidoObserver;
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
     * 
     * Antes de eliminar un calendario, se verifica si está siendo utilizado
     * por algún programa de tejido. Si está en uso, se previene la eliminación
     * para mantener la integridad de los datos.
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

            // Verificar si hay programas de tejido usando este calendario
            // Si está en uso, no se puede eliminar para mantener la integridad
            $programasUsando = ReqProgramaTejido::where('CalendarioId', $id)->count();
            
            if ($programasUsando > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "No se puede eliminar el calendario porque está siendo utilizado por {$programasUsando} programa(s) de tejido. Por favor, cambie el calendario de los programas antes de eliminarlo."
                ], 422);
            }

            // Si no está en uso, eliminar también las líneas asociadas
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

            // Recalcular programas de tejido que usan este calendario
            // Esto asegura que las nuevas horas del calendario se reflejen en los programas
            $this->recalcularProgramasPorCalendario($request->CalendarioId);

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
     * 
     * Cuando se actualiza una línea de calendario (especialmente HorasTurno o fechas),
     * se deben recalcular todos los programas de tejido que usan este calendario
     * para que las nuevas horas se reflejen en sus distribuciones diarias.
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

            // Guardar valores anteriores para detectar cambios importantes
            // Estos cambios afectan los cálculos de producción en los programas
            $horasTurnoAnterior = $linea->HorasTurno;
            $fechaInicioAnterior = $linea->FechaInicio;
            $fechaFinAnterior = $linea->FechaFin;
            $calendarioId = $linea->CalendarioId;

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

            // Detectar si cambió algo que afecta los cálculos de producción
            // Si cambió HorasTurno o las fechas, los programas deben recalcularse
            $horasCambio = abs((float)$horasTurnoAnterior - (float)$request->HorasTurno) > 0.0001;
            $fechasCambio = $fechaInicioAnterior != $request->FechaInicio || 
                           $fechaFinAnterior != $request->FechaFin;

            // Si cambió HorasTurno o fechas, recalcular programas afectados
            if ($horasCambio || $fechasCambio) {
                Log::info('Cambio detectado en línea de calendario que afecta cálculos', [
                    'linea_id' => $id,
                    'calendario_id' => $calendarioId,
                    'horas_cambio' => $horasCambio,
                    'fechas_cambio' => $fechasCambio,
                    'horas_anterior' => $horasTurnoAnterior,
                    'horas_nuevo' => $request->HorasTurno
                ]);

                // Recalcular todos los programas que usan este calendario
                $this->recalcularProgramasPorCalendario($calendarioId);
            }

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
     * 
     * Al eliminar una línea de calendario, se deben recalcular los programas
     * que usan ese calendario para ajustar las horas de producción.
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

            // Guardar el CalendarioId antes de eliminar para recalcular programas
            $calendarioId = $linea->CalendarioId;

            $linea->delete();
            Log::info("Línea eliminada exitosamente");

            // Recalcular programas que usan este calendario
            // Esto asegura que la eliminación de la línea se refleje en los programas
            $this->recalcularProgramasPorCalendario($calendarioId);

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

    /**
     * Recalcular todos los programas de tejido que usan un calendario específico
     * 
     * Este método se ejecuta cuando se modifica o elimina una línea de calendario.
     * Busca todos los programas que usan el calendario y dispara el Observer
     * para regenerar sus líneas diarias con las nuevas horas del calendario.
     * 
     * Similar a actualizarLineasPorCambioFactor() en AplicacionesController.
     * 
     * @param string $calendarioId ID del calendario que fue modificado
     * @return void
     */
    private function recalcularProgramasPorCalendario(string $calendarioId)
    {
        try {
            // Buscar todos los programas que usan este calendario
            // Solo considerar programas con fechas válidas
            $programas = ReqProgramaTejido::where('CalendarioId', $calendarioId)
                ->whereNotNull('FechaInicio')
                ->whereNotNull('FechaFinal')
                ->get();

            Log::info('Programas identificados por cambio de calendario', [
                'calendario_id' => $calendarioId,
                'programas_encontrados' => $programas->count()
            ]);

            // Si no hay programas usando este calendario, no hay nada que recalcular
            if ($programas->isEmpty()) {
                Log::info('No hay programas que usar este calendario, no se requiere recálculo');
                return;
            }

            // Disparar el Observer para cada programa para regenerar las líneas diarias
            // El Observer usará las nuevas HorasTurno del calendario en sus cálculos
            $observer = new ReqProgramaTejidoObserver();
            $programasActualizados = 0;
            $programasConError = 0;

            foreach ($programas as $programa) {
                try {
                    // Refrescar el modelo para obtener datos actualizados de la BD
                    $programa->refresh();
                    
                    // Disparar el Observer para recalcular líneas diarias
                    // El Observer detectará el CalendarioId y validará que haya fechas disponibles
                    // Si no hay fechas, el Observer registrará una alerta y no generará líneas
                    $observer->saved($programa);
                    
                    $programasActualizados++;
                } catch (\Exception $e) {
                    $programasConError++;
                    
                    // Verificar si el error es por falta de fechas en el calendario
                    $esErrorFechas = strpos($e->getMessage(), 'No hay fechas disponibles') !== false ||
                                     strpos($e->getMessage(), 'no hay líneas de calendario') !== false;
                    
                    if ($esErrorFechas) {
                        // Error específico: no hay fechas disponibles en el calendario
                        Log::warning('Programa no recalculado: No hay fechas disponibles en calendario', [
                            'programa_id' => $programa->Id,
                            'calendario_id' => $calendarioId,
                            'fecha_inicio' => $programa->FechaInicio,
                            'fecha_fin' => $programa->FechaFinal,
                            'mensaje' => $e->getMessage()
                        ]);
                    } else {
                        // Otro tipo de error
                        Log::error('Error al recalcular programa por cambio de calendario', [
                            'programa_id' => $programa->Id,
                            'calendario_id' => $calendarioId,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);
                    }
                }
            }

            Log::info('Recálculo de programas por cambio de calendario completado', [
                'calendario_id' => $calendarioId,
                'programas_actualizados' => $programasActualizados,
                'programas_con_error' => $programasConError,
                'total_programas' => $programas->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Error al recalcular programas por cambio de calendario: ' . $e->getMessage(), [
                'calendario_id' => $calendarioId,
                'trace' => $e->getTraceAsString()
            ]);
            // No lanzar la excepción para no interrumpir el flujo principal
            // El error ya está registrado en el log
        }
    }
}
