<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Imports\ReqProgramaTejidoSimpleImport;
use App\Imports\ReqProgramaTejidoUpdateImport;
use App\Models\Planeacion\ReqProgramaTejido;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Artisan;
use App\Observers\ReqProgramaTejidoObserver;
use Carbon\Carbon;
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
            // Usar transacciones
            DB::beginTransaction();
            try {
                $registrosAntes = ReqProgramaTejido::count();
                try {
                    DB::statement('TRUNCATE TABLE ReqProgramaTejidoLine');
                    DB::statement('TRUNCATE TABLE ReqProgramaTejido');
                } catch (\Throwable $truncEx) {
                    try {
                        DB::table('ReqProgramaTejidoLine')->delete();
                        DB::table('ReqProgramaTejido')->delete();
                        DB::statement('DBCC CHECKIDENT (ReqProgramaTejido, RESEED, 0)');
                        DB::statement('DBCC CHECKIDENT (ReqProgramaTejidoLine, RESEED, 0)');
                    } catch (\Throwable $delEx) {
                        Log::error("No se pudo limpiar las tablas: " . $delEx->getMessage());
                        throw $delEx;
                    }
                }

                ReqProgramaTejido::unsetEventDispatcher();

                // Deshabilitar query log para mejor rendimiento durante importación masiva
                DB::connection()->disableQueryLog();

                $import = new ReqProgramaTejidoSimpleImport();
                Excel::import($import, $archivo);

                ReqProgramaTejido::observe(ReqProgramaTejidoObserver::class);

                $estadisticas = $import->getStats();
                $this->processedRows = $estadisticas['processed_rows'];
                $this->skippedRows = $estadisticas['skipped_rows'];

                // Regenerar líneas para todos los registros importados (de forma eficiente y en batch)
                // Obtener solo registros con fechas válidas en batch
                $registrosConFechas = ReqProgramaTejido::whereNotNull('FechaInicio')
                    ->whereNotNull('FechaFinal')
                    ->where('FechaInicio', '!=', '')
                    ->where('FechaFinal', '!=', '')
                    ->select('Id', 'FechaInicio', 'FechaFinal', 'SalonTejidoId', 'NoTelarId')
                    ->get();

                $totalRegenerados = 0;
                $saltadosPorFecha = 0;
                $errores = 0;
                $observer = new ReqProgramaTejidoObserver();

                // Procesar en chunks para evitar sobrecarga de memoria
                $idsValidos = [];
                foreach ($registrosConFechas as $registro) {
                    try {
                        // Validar que las fechas sean razonables (no años antiguos como 1933)
                        $fechaInicio = Carbon::parse($registro->FechaInicio);
                        $fechaFinal = Carbon::parse($registro->FechaFinal);

                        // Validar que las fechas sean del año 2000 en adelante
                        if ($fechaInicio->year < 2000 || $fechaFinal->year < 2000) {
                            $saltadosPorFecha++;
                            continue;
                        }

                        // Validar que FechaFinal sea mayor que FechaInicio
                        if ($fechaFinal->lte($fechaInicio)) {
                            $saltadosPorFecha++;
                            continue;
                        }

                        $idsValidos[] = $registro->Id;
                    } catch (\Throwable $lineEx) {
                        $saltadosPorFecha++;
                    }
                }

                // Cargar y procesar registros en batch para mejor rendimiento
                // Procesar en chunks más grandes para reducir consultas
                $chunks = array_chunk($idsValidos, 100);
                foreach ($chunks as $chunkIds) {
                    $registrosCompletos = ReqProgramaTejido::whereIn('Id', $chunkIds)->get();
                    foreach ($registrosCompletos as $registroCompleto) {
                        try {
                            $observer->saved($registroCompleto);
                            $totalRegenerados++;
                        } catch (\Throwable $lineEx) {
                            $errores++;
                        }
                    }
                }

                // Actualizar estado EnProceso automáticamente (no romper si el comando no está registrado)
                try {
                    Artisan::call('programa-tejido:actualizar-estado-proceso');
                } catch (\Throwable $cmdEx) {
                    // Silenciar error si el comando no está disponible
                }

                // Obtener estadísticas después de la importación
                $registrosDespues = ReqProgramaTejido::count();

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

                return response()->json($response);

            } catch (\Throwable $e) {
                // Verificar que haya una transacción activa antes de hacer rollback
                if (DB::transactionLevel() > 0) {
                    try {
                        DB::rollback();
                    } catch (\Exception $rollbackEx) {
                        Log::error("Error al hacer rollback: " . $rollbackEx->getMessage());
                    }
                }

                // Asegurar que el Observer se re-habilite incluso si hay error
                ReqProgramaTejido::observe(ReqProgramaTejidoObserver::class);

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

    /**
     * Procesar archivo Excel de programa tejido (modo actualización - NO elimina existentes)
     */
    public function procesarExcelUpdate(Request $request)
    {
        try {
            // Aumentar timeout para procesamiento de Excel grande
            set_time_limit(600); // 10 minutos
            ini_set('max_execution_time', 600);

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
            $registrosAntes = ReqProgramaTejido::count();

            DB::beginTransaction();
            try {
                ReqProgramaTejido::unsetEventDispatcher();

                // Deshabilitar query log para mejor rendimiento
                DB::connection()->disableQueryLog();

                $import = new ReqProgramaTejidoUpdateImport();
                Excel::import($import, $archivo);

                ReqProgramaTejido::observe(ReqProgramaTejidoObserver::class);

                $estadisticas = $import->getStats();
                $this->processedRows = $estadisticas['processed_rows'];
                $this->skippedRows = $estadisticas['skipped_rows'];
                $updatedRows = $estadisticas['updated_rows'] ?? 0;

                // Regenerar líneas para registros actualizados (solo los que tienen fechas válidas)
                $registrosConFechas = ReqProgramaTejido::whereNotNull('FechaInicio')
                    ->whereNotNull('FechaFinal')
                    ->where('FechaInicio', '!=', '')
                    ->where('FechaFinal', '!=', '')
                    ->select('Id', 'FechaInicio', 'FechaFinal', 'SalonTejidoId', 'NoTelarId')
                    ->get();

                $totalRegenerados = 0;
                $saltadosPorFecha = 0;
                $errores = 0;
                $observer = new ReqProgramaTejidoObserver();

                $idsValidos = [];
                foreach ($registrosConFechas as $registro) {
                    try {
                        $fechaInicio = Carbon::parse($registro->FechaInicio);
                        $fechaFinal = Carbon::parse($registro->FechaFinal);

                        if ($fechaInicio->year < 2000 || $fechaFinal->year < 2000) {
                            $saltadosPorFecha++;
                            continue;
                        }

                        if ($fechaFinal->lte($fechaInicio)) {
                            $saltadosPorFecha++;
                            continue;
                        }

                        $idsValidos[] = $registro->Id;
                    } catch (\Throwable $lineEx) {
                        $saltadosPorFecha++;
                    }
                }

                // Procesar en chunks
                $chunks = array_chunk($idsValidos, 100);
                foreach ($chunks as $chunkIds) {
                    $registrosCompletos = ReqProgramaTejido::whereIn('Id', $chunkIds)->get();
                    foreach ($registrosCompletos as $registroCompleto) {
                        try {
                            $observer->saved($registroCompleto);
                            $totalRegenerados++;
                        } catch (\Throwable $lineEx) {
                            $errores++;
                        }
                    }
                }

                // Actualizar estado EnProceso automáticamente
                try {
                    Artisan::call('programa-tejido:actualizar-estado-proceso');
                } catch (\Throwable $cmdEx) {
                    // Silenciar error si el comando no está disponible
                }

                $registrosDespues = ReqProgramaTejido::count();

                DB::commit();

                $response = [
                    'success' => true,
                    'message' => 'Archivo procesado exitosamente. Registros actualizados sin eliminar existentes.',
                    'processed' => $this->processedRows,
                    'updated' => $updatedRows,
                    'created' => $this->processedRows - $updatedRows,
                    'skipped' => $this->skippedRows,
                    'deleted' => 0,
                    'total_before' => $registrosAntes,
                    'total_after' => $registrosDespues,
                    'errors' => []
                ];

                return response()->json($response);

            } catch (\Throwable $e) {
                if (DB::transactionLevel() > 0) {
                    try {
                        DB::rollback();
                    } catch (\Exception $rollbackEx) {
                        Log::error("Error al hacer rollback: " . $rollbackEx->getMessage());
                    }
                }

                ReqProgramaTejido::observe(ReqProgramaTejidoObserver::class);

                Log::error("Error durante la importación de actualización", [
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
            Log::error("Error general en procesarExcelUpdate", [
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
