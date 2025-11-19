<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Imports\ReqProgramaTejidoSimpleImport;
use App\Models\ReqProgramaTejido;
use App\Models\ReqProgramaTejidoLine;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;

class ConfiguracionController extends Controller
{
    private int $processedRows = 0;
    private int $skippedRows = 0;

    /**
     * Mostrar la vista de carga de planeación
     */
    public function cargarPlaneacion()
    {
        return view('modulos.configuracion.cargar-planeacion');
    }

    /**
     * Procesar archivo Excel de programa tejido
     */
    public function procesarExcel(Request $request)
    {
        try {
            // Aumentar timeout para procesamiento de Excel grande
            set_time_limit(600); // 10 minutos para importación + regeneración de líneas
            ini_set('max_execution_time', 600);

            Log::info("========== INICIO PROCESAMIENTO EXCEL PROGRAMA TEJIDO ==========");
            if (Auth::check()) {
                Log::info("ID Usuario: " . Auth::id());
                Log::info("Usuario: " . Auth::user()->name ?? 'Sin nombre');
            }
            $validator = Validator::make($request->all(), [
                'excel_file' => 'required|file|mimes:xlsx,xls|max:10240'
            ]);

            if ($validator->fails()) {
                Log::error("Validación fallida: " . json_encode($validator->errors()->all()));
                return response()->json([
                    'success' => false,
                    'message' => 'Archivo inválido. Debe ser un archivo Excel (.xlsx o .xls) de máximo 10MB.',
                    'errors' => $validator->errors()
                ], 400);
            }

            $archivo = $request->file('excel_file');
            $nombreArchivo = $archivo->getClientOriginalName();
            $rutaTemporal = $archivo->getPathname();

            // Usar transacciones
            DB::beginTransaction();

            try {
                // Obtener estadísticas antes de la importación
                $registrosAntes = ReqProgramaTejido::count();
                $lineasAntes = ReqProgramaTejidoLine::count();
                Log::info("Registros existentes antes de la importación: {$registrosAntes} registros, {$lineasAntes} líneas");

                // TRUNCATE para reiniciar los IDs (autoincrementables)
                // Primero truncar las líneas (tabla dependiente) y luego la tabla principal
                Log::info("Truncando tablas para reiniciar IDs...");
                try {
                    // Primero truncar ReqProgramaTejidoLine (tabla dependiente con FK)
                    DB::statement('TRUNCATE TABLE ReqProgramaTejidoLine');
                    Log::info("Tabla ReqProgramaTejidoLine truncada (IDs reiniciados)");

                    // Luego truncar ReqProgramaTejido (tabla principal)
                    DB::statement('TRUNCATE TABLE ReqProgramaTejido');
                    Log::info("Tabla ReqProgramaTejido truncada (IDs reiniciados)");
                } catch (\Throwable $truncEx) {
                    Log::warning("Fallo al truncar: " . $truncEx->getMessage() . ". Intentando con DELETE...");
                    // Fallback: usar DELETE si TRUNCATE falla (por ejemplo, si hay otras FKs activas)
                    try {
                        // Eliminar líneas primero
                        DB::table('ReqProgramaTejidoLine')->delete();
                        // Luego eliminar registros principales
                        DB::table('ReqProgramaTejido')->delete();
                        // Reiniciar contador de identidad manualmente después de DELETE
                        DB::statement('DBCC CHECKIDENT (ReqProgramaTejido, RESEED, 0)');
                        DB::statement('DBCC CHECKIDENT (ReqProgramaTejidoLine, RESEED, 0)');
                        Log::info("Registros eliminados con DELETE y contadores de identidad reiniciados manualmente");
                    } catch (\Throwable $delEx) {
                        Log::error("No se pudo limpiar las tablas: " . $delEx->getMessage());
                        throw $delEx;
                    }
                }

                // Deshabilitar Observer temporalmente para evitar regeneración de líneas durante importación masiva
                \App\Models\ReqProgramaTejido::unsetEventDispatcher();

                Log::info("Observers deshabilitados para importación masiva");

                // Importar usando el importador específico
                $import = new ReqProgramaTejidoSimpleImport();
                Excel::import($import, $archivo);

                // Re-habilitar Observer
                \App\Models\ReqProgramaTejido::observe(\App\Observers\ReqProgramaTejidoObserver::class);

                Log::info("Observers re-habilitados después de importación");

                // Obtener estadísticas del importador
                $estadisticas = $import->getStats();
                $this->processedRows = $estadisticas['processed_rows'];
                $this->skippedRows = $estadisticas['skipped_rows'];

                // Regenerar líneas para todos los registros importados (de forma eficiente y en batch)
                Log::info("Regenerando líneas para registros importados...");

                // Obtener solo registros con fechas válidas en batch
                $registrosConFechas = ReqProgramaTejido::whereNotNull('FechaInicio')
                    ->whereNotNull('FechaFinal')
                    ->where('FechaInicio', '!=', '')
                    ->where('FechaFinal', '!=', '')
                    ->select('Id', 'FechaInicio', 'FechaFinal')
                    ->get();

                $totalRegenerados = 0;
                $observer = new \App\Observers\ReqProgramaTejidoObserver();

                // Procesar en chunks para evitar sobrecarga de memoria
                $chunks = $registrosConFechas->chunk(50);
                foreach ($chunks as $chunk) {
                    foreach ($chunk as $registro) {
                        try {
                            // Validar que las fechas sean razonables (no años antiguos como 1933)
                            $fechaInicio = \Carbon\Carbon::parse($registro->FechaInicio);
                            $fechaFinal = \Carbon\Carbon::parse($registro->FechaFinal);

                            // Validar que las fechas sean del año 2000 en adelante
                            if ($fechaInicio->year < 2000 || $fechaFinal->year < 2000) {
                                Log::warning("Registro {$registro->Id} tiene fechas inválidas (año < 2000), saltando", [
                                    'FechaInicio' => $registro->FechaInicio,
                                    'FechaFinal' => $registro->FechaFinal
                                ]);
                                continue;
                            }

                            // Cargar el registro completo solo cuando sea necesario
                            $registroCompleto = ReqProgramaTejido::find($registro->Id);
                            if ($registroCompleto) {
                                $observer->saved($registroCompleto);
                                $totalRegenerados++;

                                // Log cada 50 registros para monitorear progreso
                                if ($totalRegenerados % 50 === 0) {
                                    Log::info("Progreso regeneración líneas: {$totalRegenerados}/" . $registrosConFechas->count());
                                }
                            }
                        } catch (\Throwable $lineEx) {
                            Log::warning("Error regenerando líneas para registro {$registro->Id}: " . $lineEx->getMessage());
                        }
                    }
                }

                Log::info("Líneas regeneradas: {$totalRegenerados} registros procesados de " . $registrosConFechas->count() . " con fechas válidas");

                // Actualizar estado EnProceso automáticamente (no romper si el comando no está registrado)
                try {
                Log::info("Actualizando estado EnProceso después de la importación...");
                Artisan::call('programa-tejido:actualizar-estado-proceso');
                Log::info("Estado EnProceso actualizado exitosamente");
                } catch (\Throwable $cmdEx) {
                    Log::warning("No se pudo ejecutar el comando de actualización de estado: " . $cmdEx->getMessage());
                }

                // Obtener estadísticas después de la importación
                $registrosDespues = ReqProgramaTejido::count();
                Log::info("Registros después de la importación: {$registrosDespues}");

                DB::commit();

                $response = [
                    'success' => true,
                    'message' => 'Archivo procesado exitosamente. Registros existentes eliminados y nuevos registros creados.',
                    'processed' => $this->processedRows,
                    'created' => $this->processedRows,
                    'updated' => 0,
                    'skipped' => $this->skippedRows,
                    'deleted' => $registrosAntes,
                    'total_before' => $registrosAntes,
                    'total_after' => $registrosDespues,
                    'errors' => []
                ];

                Log::info("Respuesta JSON que se enviará:", $response);

                return response()->json($response);

            } catch (\Throwable $e) {
                DB::rollback();

                // Asegurar que el Observer se re-habilite incluso si hay error
                \App\Models\ReqProgramaTejido::observe(\App\Observers\ReqProgramaTejidoObserver::class);

                Log::error("Error durante la importación", [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Error al procesar el archivo: ' . $e->getMessage(),
                    'error_details' => config('app.debug') ? [
                        'file' => $e->getFile(),
                        'line' => $e->getLine()
                    ] : null
                ], 500);
            }

        } catch (\Throwable $e) {
            Log::error("Error general en procesarExcel", [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor: ' . $e->getMessage(),
                'error_details' => config('app.debug') ? [
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ] : null
            ], 500);
        }
    }
}
