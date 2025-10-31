<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Imports\ReqProgramaTejidoSimpleImport;
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
            set_time_limit(300); // 5 minutos

            Log::info("========== INICIO PROCESAMIENTO EXCEL PROGRAMA TEJIDO ==========");
            Log::info("Método HTTP: {$request->getMethod()}");
            Log::info("URL completa: " . $request->fullUrl());
            Log::info("Headers: " . json_encode($request->headers->all()));
            Log::info("Archivos subidos: " . json_encode($request->files->keys()));
            Log::info("Datos del request: " . json_encode($request->all()));
            Log::info("Token CSRF recibido: " . $request->input('_token'));
            Log::info("Token CSRF esperado: " . csrf_token());
            Log::info("Usuario autenticado: " . (Auth::check() ? 'Sí' : 'No'));
            if (Auth::check()) {
                Log::info("ID Usuario: " . Auth::id());
                Log::info("Usuario: " . Auth::user()->name ?? 'Sin nombre');
            }

            // Validar el archivo
            Log::info("Iniciando validación del archivo...");
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

            Log::info("✅ Validación del archivo exitosa");

            $archivo = $request->file('excel_file');
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
                Log::info("Importando archivo con ReqProgramaTejidoSimpleImport...");

                // Obtener estadísticas antes de la importación
                $registrosAntes = \App\Models\ReqProgramaTejido::count();
                Log::info("Registros existentes antes de la importación: {$registrosAntes}");

                // Eliminar todos los registros existentes (evitar TRUNCATE por posibles FKs)
                Log::info("Eliminando registros existentes (DELETE ALL)...");
                try {
                    // Borrado en bloque
                    DB::table('ReqProgramaTejido')->delete();
                    Log::info("Registros existentes eliminados con DELETE");
                } catch (\Throwable $delEx) {
                    Log::warning("Fallo al eliminar con DELETE: " . $delEx->getMessage());
                    // Fallback: intentar borrado chunked para bases con restricciones/locks
                    try {
                        $totalBefore = \App\Models\ReqProgramaTejido::count();
                        $chunk = 1000;
                        \App\Models\ReqProgramaTejido::query()->orderBy('Id')
                            ->chunkById($chunk, function ($rows) {
                                $ids = $rows->pluck('Id')->all();
                                DB::table('ReqProgramaTejido')->whereIn('Id', $ids)->delete();
                            }, 'Id');
                        $totalAfter = \App\Models\ReqProgramaTejido::count();
                        Log::info("Borrado chunked completado", ['antes'=>$totalBefore,'despues'=>$totalAfter]);
                    } catch (\Throwable $delEx2) {
                        Log::error("No se pudo limpiar ReqProgramaTejido: " . $delEx2->getMessage());
                        throw $delEx2;
                    }
                }

                // Importar usando el importador específico
                $import = new ReqProgramaTejidoSimpleImport();
                Excel::import($import, $archivo);

                // Obtener estadísticas del importador
                $estadisticas = $import->getStats();
                $this->processedRows = $estadisticas['processed_rows'];
                $this->skippedRows = $estadisticas['skipped_rows'];

                // Actualizar estado EnProceso automáticamente (no romper si el comando no está registrado)
                try {
                Log::info("Actualizando estado EnProceso después de la importación...");
                Artisan::call('programa-tejido:actualizar-estado-proceso');
                Log::info("Estado EnProceso actualizado exitosamente");
                } catch (\Throwable $cmdEx) {
                    Log::warning("No se pudo ejecutar el comando de actualización de estado: " . $cmdEx->getMessage());
                }

                // Obtener estadísticas después de la importación
                $registrosDespues = \App\Models\ReqProgramaTejido::count();
                Log::info("Registros después de la importación: {$registrosDespues}");

                DB::commit();
                Log::info("Transacción confirmada exitosamente");

                Log::info("========== FIN PROCESAMIENTO EXCEL ==========");
                Log::info("Estadísticas finales:", $estadisticas);

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

            } catch (\Exception $e) {
                DB::rollback();
                Log::error("Error durante la importación: " . $e->getMessage());
                Log::error("Stack trace: " . $e->getTraceAsString());

                return response()->json([
                    'success' => false,
                    'message' => 'Error al procesar el archivo: ' . $e->getMessage()
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error("Error general en procesarExcel: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor: ' . $e->getMessage()
            ], 500);
        }
    }
}
