<?php

namespace App\Http\Controllers\Planeacion\CatCodificados;

use App\Http\Controllers\Controller;
use App\Imports\CatCodificadosImport;
use App\Models\Planeacion\Catalogos\CatCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
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
     * Mostrar la vista principal del catálogo de codificación.
     */
    public function index()
    {
        try {
            $columnas = CatCodificados::COLUMNS;
            $total = Cache::remember(
                'catcodificacion_total',
                300,
                fn () => CatCodificados::count()
            );

            return view('catcodificacion.index', [
                'columnas'       => $columnas,
                'totalRegistros' => $total,
                'apiUrl'         => '/planeacion/codificacion/api/all-fast',
            ]);
        } catch (\Throwable $e) {
            Log::error('CatCodificacionController::index', [
                'error' => $e->getMessage(),
            ]);

            return view('catcodificacion.index', [
                'columnas'       => CatCodificados::COLUMNS,
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


            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el archivo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * API: órdenes en proceso de ReqProgramaTejido (EnProceso = 1).
     * Devuelve lista de NoProduccion para el campo Orden Tejido del modal.
     */
    public function ordenesEnProceso(Request $request): JsonResponse
    {
        try {
            $ordenes = ReqProgramaTejido::query()
                ->enProceso(true)
                ->ordenado()
                ->get(['Id', 'NoProduccion', 'NoTelarId', 'SalonTejidoId', 'ItemId', 'NombreProducto'])
                ->map(function ($r) {
                    return [
                        'id' => $r->Id,
                        'noProduccion' => $r->NoProduccion !== null && $r->NoProduccion !== '' ? (string) $r->NoProduccion : null,
                        'noTelarId' => $r->NoTelarId,
                        'salonTejidoId' => $r->SalonTejidoId,
                        'itemId' => $r->ItemId,
                        'nombreProducto' => $r->NombreProducto,
                    ];
                })
                ->filter(fn ($r) => $r['noProduccion'] !== null)
                ->values();

            return response()->json([
                's' => true,
                'd' => $ordenes,
            ]);
        } catch (\Throwable $e) {
            Log::error('CatCodificacionController::ordenesEnProceso', ['error' => $e->getMessage()]);
            return response()->json([
                's' => false,
                'e' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * API: datos compactos para carga rápida en tabla (getAllFast).
     * Optimizado para máxima velocidad: cache, cursor, sin query log.
     */
/**
     * API: Optimización Extrema con PDO puro.
     * Salta la hidratación de Eloquent/QueryBuilder.
     */
    public function getAllFast(Request $request): JsonResponse
    {
        try {
            $columnas = CatCodificados::COLUMNS;
            $idFilter = $request->filled('id') ? (int) $request->input('id') : null;
            $skipCache = $request->boolean('nocache', false);

            $cacheKey = $idFilter
                ? "catcodificacion_fast_id_{$idFilter}"
                : 'catcodificacion_fast_all';

            // 1. CACHE HIT
            if (!$skipCache && ($cached = Cache::get($cacheKey))) {
                return response()->json($cached)
                    ->header('Cache-Control', 'private, max-age=60')
                    ->header('X-Cache', 'HIT');
            }

            // 2. Query Builder (compatible SQL Server / MySQL - PDO LIMIT falla en SQL Server)
            $query = CatCodificados::query()->select($columnas)->orderBy('Id', 'desc');

            if ($idFilter) {
                $query->where('Id', $idFilter)->limit(1);
            }

            $registros = $query->get();

            $data = $registros->map(function ($row) use ($columnas) {
                $arr = [];
                foreach ($columnas as $col) {
                    $arr[] = $row->getAttribute($col);
                }
                return $arr;
            })->all();

            $response = [
                's' => true,
                'd' => $data,
                't' => count($data),
                'c' => $columnas,
            ];

            // 3. CACHE MISS
            if (!$skipCache) {
                Cache::put($cacheKey, $response, 60);
            }

            return response()->json($response)
                ->header('Cache-Control', 'private, max-age=60')
                ->header('X-Cache', 'MISS');

        } catch (\Throwable $e) {
            Log::error('CatCodificacionController::getAllFast', [
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
     * Fetch usando get() - más rápido para datasets pequeños (<1000 registros)
     */
    private function fetchWithGet($query): array
    {
        $data = $query->get();

        // Optimización: convertir directamente sin map intermedio
        $result = [];
        foreach ($data as $row) {
            $result[] = array_values((array) $row);
        }

        return $result;
    }

    /**
     * Fetch usando cursor() - más eficiente en memoria para datasets grandes
     */
    private function fetchWithCursor($query): array
    {
        $result = [];

        // Cursor procesa fila por fila sin cargar todo en memoria
        foreach ($query->cursor() as $row) {
            $result[] = array_values((array) $row);
        }

        return $result;
    }

    /**
     * Invalidar cache de getAllFast
     */
    public static function clearCache(?int $id = null): void
    {
        if ($id !== null) {
            Cache::forget("catcodificacion_fast_id_{$id}");
        }
        Cache::forget('catcodificacion_fast_all');
        Cache::forget('catcodificacion_estimated_count');
        Cache::forget('catcodificacion_total');
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
