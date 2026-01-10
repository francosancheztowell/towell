<?php

namespace App\Http\Controllers\Planeacion\CatCodificados;

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
     * API: datos compactos para carga rápida en tabla (getAllFast).
     * Optimizado para máxima velocidad: cache, cursor, sin query log.
     */
    public function getAllFast(Request $request): JsonResponse
    {
        try {
            $columnas = CatCodificados::COLUMNS;
            $table    = (new CatCodificados())->getTable();
            $idFilter = $request->filled('id') ? (int) $request->input('id') : null;
            $skipCache = $request->boolean('nocache', false);

            // Clave de cache única por filtro
            $cacheKey = $idFilter
                ? "catcodificacion_fast_id_{$idFilter}"
                : 'catcodificacion_fast_all';

            // Intentar obtener desde cache (60 segundos TTL)
            if (!$skipCache) {
                $cached = Cache::get($cacheKey);
                if ($cached !== null) {
                    return response()->json($cached)
                        ->header('Cache-Control', 'private, max-age=60')
                        ->header('X-Cache', 'HIT');
                }
            }

            // Deshabilitar query log para mejor rendimiento
            DB::connection()->disableQueryLog();

            // Construir SELECT con campos específicos (más eficiente que array)
            $columnsStr = implode(', ', array_map(fn($col) => "[{$col}]", $columnas));

            // Consulta directa ordenada por Id descendente (más nuevos primero)
            $query = DB::table($table)
                ->selectRaw($columnsStr)
                ->orderByDesc('Id');

            // Busqueda directa por Id (index) si se envia ?id=123 (atajo rapido)
            if ($idFilter !== null) {
                $row = $query->where('Id', $idFilter)->limit(1)->first();
                $data = $row ? [array_values((array) $row)] : [];

                $response = [
                    's' => true,
                    'd' => $data,
                    't' => $data ? 1 : 0,
                    'c' => $columnas,
                ];

                // Cachear respuesta (60 segundos)
                if (!$skipCache) {
                    Cache::put($cacheKey, $response, 60);
                }

                return response()->json($response)
                    ->header('Cache-Control', 'private, max-age=60')
                    ->header('X-Cache', 'MISS');
            }

            // Usar cursor() para grandes volúmenes (reduce memoria)
            // Si hay menos de 1000 registros, usar get() es más rápido
            $estimatedCount = Cache::remember(
                'catcodificacion_estimated_count',
                300,
                fn() => DB::table($table)->count()
            );

            $data = $estimatedCount > 1000
                ? $this->fetchWithCursor($query)
                : $this->fetchWithGet($query);

            $response = [
                's' => true,             // success
                'd' => $data,            // data (array de arrays)
                't' => count($data),     // total registros
                'c' => $columnas,        // columnas
            ];

            // Cachear respuesta (60 segundos)
            if (!$skipCache) {
                Cache::put($cacheKey, $response, 60);
            }

            return response()->json($response)
                ->header('Cache-Control', 'private, max-age=60')
                ->header('X-Cache', 'MISS');
        } catch (\Throwable $e) {


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
