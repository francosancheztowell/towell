<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Imports\CatCodificadosImport;
use App\Models\catcodificados\CatCodificados;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Reader\Xls as XlsReader;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;

class CatCodificacionController extends Controller
{
    /**
     * Columnas usadas en vista y API (una sola fuente de verdad)
     */
    private const COLUMNS = [
        'NumOrden', 'FechaOrden', 'FechaCumplimiento', 'Departamento', 'TelarId', 'Prioridad',
        'Modelo', 'ClaveModelo', 'Tamano', 'InventSizeId', 'Tolerancia', 'CodigoDibujo', 'FechaCompromiso',
        'FlogsId', 'Clave', 'Cantidad', 'Peine', 'Ancho', 'Largo', 'P_crudo', 'Luchaje', 'Tra', 'Hilo',
        'CodColorTrama', 'NombreColorTrama', 'OBS_Trama', 'Tipoplano', 'Medplano', 'TipoRizo', 'AlturaRizo',
        'OBS', 'VelocMinima', 'Rizo', 'Hilo_2', 'Cuenta', 'OBS_2', 'Pie', 'Hilo_3', 'Cuenta_2', 'OBS_3',
        'C1', 'OBS_11', 'C2', 'OBS_12', 'C3', 'OBS_13', 'C4', 'OBS_14', 'MedCenefa', 'MedInicioRizoCenefa',
        'Razurada', 'TIRAS', 'RepeticionesP/corte', 'NoMarbete', 'CambioRepaso', 'Vendedor', 'NoOrden',
        'Observaciones', 'TramaAnchoPeine', 'LogLuchaTotal', 'C1TramaFondo', 'Hilo_4', 'OBS_4', 'PASADAS',
        'C1_2', 'Hilo_5', 'OBS_5', 'CodColor', 'NombreColor', 'PASADAS_2', 'C2_2', 'Hilo_6', 'OBS_6',
        'CodColor_2', 'NombreColor_2', 'PASADAS_3', 'C3_2', 'Hilo_7', 'OBS_7', 'CodColor_3', 'NombreColor_3',
        'PASADAS_4', 'C4_2', 'Hilo_8', 'OBS_8', 'Cod Color_4', 'NombreColor_4', 'PASADAS_5', 'C5', 'Hilo_9',
        'OBS_9', 'Cod Color_5', 'NombreColor_5', 'PASADAS_6', 'TOTAL', 'RespInicio', 'HrInicio', 'HrTermino',
        'MinutosCambio', 'PesoMuestra', 'RegAlinacion', 'estecamponotienenombre1', 'OBSParaPro',
        'CantidadProducir_2', 'Tejidas', 'pzaXrollo',
    ];

    /**
     * Mostrar la vista principal del catálogo de codificación.
     */
    public function index()
    {
        try {
            $total = Cache::remember(
                'catcodificacion_total',
                300,
                fn () => CatCodificados::count()
            );

            return view('catcodificacion.index', [
                'columnas'       => self::COLUMNS,
                'totalRegistros' => $total,
                'apiUrl'         => '/planeacion/codificacion/api/all-fast',
            ]);
        } catch (\Throwable $e) {
            Log::error('CatCodificacionController::index', [
                'error' => $e->getMessage(),
            ]);

            return view('catcodificacion.index', [
                'columnas'       => self::COLUMNS,
                'totalRegistros' => 0,
                'apiUrl'         => '/planeacion/codificacion/api/all-fast',
                'error'          => 'Error al cargar: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Procesar archivo Excel (encolar import).
     */
    public function procesarExcel(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'archivo_excel' => 'required|file|mimes:xlsx,xls|max:10240',
            ]);

            $file = $request->file('archivo_excel');
            $importId = (string) Str::uuid();
            $path = $file->getRealPath();
            $ext = strtolower($file->getClientOriginalExtension());

            $totalRows = null;

            try {
                $reader = $ext === 'xls' ? new XlsReader() : new XlsxReader();
                $info   = $reader->listWorksheetInfo($path);

                if (isset($info[0]['totalRows'])) {
                    $totalRows = max(0, (int) $info[0]['totalRows'] - 1); // -1 cabecera
                }
            } catch (\Throwable $e) {
                Log::warning('CatCodificacionController::procesarExcel totalRows', [
                    'error' => $e->getMessage(),
                ]);
            }

            Excel::queueImport(
                new CatCodificadosImport($importId, $totalRows),
                $file
            );

            return response()->json([
                'success' => true,
                'message' => 'Importación encolada correctamente',
                'data'    => [
                    'import_id' => $importId,
                    'total_rows'=> $totalRows,
                    'poll_url'  => url('/planeacion/codificacion/excel-progress/' . $importId),
                ],
            ], 202);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validación fallida',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('CatCodificacionController::procesarExcel', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el archivo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * API: datos compactos para carga rápida en tabla (getAllFast).
     * Máxima velocidad: sin ordenamiento, consulta directa, sin query log.
     */
    public function getAllFast(): JsonResponse
    {
        try {
            $columnas = self::COLUMNS;
            $table    = (new CatCodificados())->getTable();

            // Deshabilitar query log para mejor rendimiento
            DB::connection()->disableQueryLog();

            // Consulta directa SIN ordenamiento para máxima velocidad
            // El ordenamiento es costoso y no es necesario para la carga inicial
            $data = DB::table($table)
                ->select($columnas)
                ->get();


            $mapped = $data->map(fn($row) => array_values((array) $row));

            return response()->json([
                's' => true,             // success
                'd' => $mapped,          // data (array de arrays)
                't' => $mapped->count(), // total registros
                'c' => $columnas,        // columnas
            ])->header('Cache-Control', 'private, max-age=60');
        } catch (\Throwable $e) {
            Log::error('CatCodificacionController::getAllFast - ERROR', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                's' => false,
                'e' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Consultar progreso del import encolado.
     */
    public function importProgress(string $id): JsonResponse
    {
        $state = Cache::get('excel_import_progress:' . $id);

        if (!$state) {
            return response()->json([
                'success' => false,
                'message' => 'Progreso no encontrado',
            ], 404);
        }

        $processed = (int) ($state['processed_rows'] ?? 0);
        $totalRows = (int) ($state['total_rows'] ?? 0);

        $percent = $totalRows > 0
            ? round(100 * ($processed / $totalRows), 1)
            : null;

        // Normalizar errores (si existen)
        $rawErrors = $state['errors'] ?? [];
        $errors = [];

        if (is_array($rawErrors)) {
            foreach ($rawErrors as $err) {
                $errors[] = [
                    'fila'  => $err['fila']  ?? 'N/A',
                    'error' => mb_substr($err['error'] ?? 'Error desconocido', 0, 150),
                ];
            }
        }

        return response()->json([
            'success'   => true,
            'data'      => $state,
            'percent'   => $percent,
            'errors'    => $errors,
            'has_errors'=> !empty($errors),
        ]);
    }
}
