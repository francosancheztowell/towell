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
