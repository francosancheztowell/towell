<?php

namespace App\Http\Controllers\Planeacion\CatCodificados;

use App\Http\Controllers\Controller;
use App\Http\Requests\Planeacion\StoreCatCodificadosExcelRequest;
use App\Imports\CatCodificadosImport;
use App\Imports\QueuedCatCodificadosImport;
use App\Models\Planeacion\Catalogos\CatCodificados;
use App\Models\Planeacion\ReqModelosCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Services\Planeacion\CatCodificados\Excel\CatCodificadosExcelHeaderMapper;
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
    private const INLINE_IMPORT_ROW_THRESHOLD = 20;

    public function __construct(
        private readonly CatCodificadosExcelHeaderMapper $headerMapper,
    ) {
    }

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
    public function procesarExcel(StoreCatCodificadosExcelRequest $request): JsonResponse
    {
        try {
            $file = $request->file('archivo_excel');
            if ($file === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se recibio un archivo Excel valido.',
                ], 422);
            }

            $importId = (string) Str::uuid();
            $path = $file->getRealPath();
            if ($path === false) {
                throw new \RuntimeException('No fue posible acceder al archivo temporal.');
            }

            $ext = strtolower($file->getClientOriginalExtension());
            $headers = $this->readHeaderRow($path, $ext);
            $mapping = $this->headerMapper->map($headers);

            if ($mapping['errors'] !== []) {
                return response()->json([
                    'success' => false,
                    'message' => 'La plantilla de Excel no coincide con CatCodificados.',
                    'errors' => [
                        'headers' => $mapping['errors'],
                    ],
                ], 422);
            }

            $totalRows = $this->resolveTotalRows($path, $ext);

            $processedInline = $this->shouldProcessInline($totalRows);
            $import = $processedInline
                ? new CatCodificadosImport($importId, $totalRows, $mapping['columnMap'])
                : new QueuedCatCodificadosImport($importId, $totalRows, $mapping['columnMap']);

            if ($processedInline) {
                Excel::import($import, $file);
            } else {
                Excel::queueImport($import, $file);
            }

            $state = Cache::get('excel_import_progress:' . $importId, []);

            return response()->json([
                'success' => true,
                'message' => $processedInline
                    ? 'Importación procesada correctamente'
                    : 'Importación encolada correctamente',
                'data' => [
                    'import_id' => $importId,
                    'total_rows' => $totalRows,
                    'completed' => $processedInline,
                    'queued' => !$processedInline,
                    'summary' => $processedInline ? $this->buildImportSummary($state) : null,
                    'poll_url' => url('/planeacion/codificacion/excel-progress/' . $importId),
                    'cancel_url' => url('/planeacion/codificacion/excel-cancel/' . $importId),
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
     * API: obtener registro de CatCodificados por OrdenTejido para el modal Peso Muestra.
     * Devuelve ActualizaLmat, PesoMuestra, BomId (Lista Mat) para cargar/actualizar el formulario.
     */
    public function getCatCodificadosPorOrden(string $ordenTejido): JsonResponse
    {
        try {
            $ordenTejido = trim($ordenTejido);
            if ($ordenTejido === '') {
                return response()->json(['s' => false, 'e' => 'Orden Tejido requerido'], 400);
            }

            $registro = CatCodificados::query()
                ->where('OrdenTejido', $ordenTejido)
                ->first(['OrdenTejido', 'TelarId', 'ItemId', 'InventSizeId', 'Nombre', 'ClaveModelo', 'ActualizaLmat', 'PesoMuestra', 'BomId', 'BomName']);

            if (!$registro) {
                return response()->json([
                    's' => true,
                    'd' => null,
                    'message' => 'No existe registro en CatCodificados para esta orden',
                ]);
            }

            $actualizaLmat = $registro->ActualizaLmat === true || $registro->ActualizaLmat === 1 || $registro->ActualizaLmat === '1';
            $pesoMuestra = $registro->PesoMuestra !== null && $registro->PesoMuestra !== '' ? (float) $registro->PesoMuestra : null;
            $bomId = $registro->BomId !== null ? (string) $registro->BomId : '';
            $bomName = $registro->BomName !== null ? (string) $registro->BomName : '';

            // LMAT: consultar en BD sqlsrv_ti (BOMTABLE + BOMVERSION) como en LiberarOrdenesController.
            // Si no hay InventSizeId, no se filtra por tamaño y se devuelven todos los BOM del artículo.
            $listaLmat = [];
            $itemId = $registro->ItemId !== null ? trim((string) $registro->ItemId) : '';
            $inventSizeId = $registro->InventSizeId !== null && trim((string) $registro->InventSizeId) !== ''
                ? trim((string) $registro->InventSizeId) : null;
            if ($itemId !== '') {
                $listaLmat = $this->queryLmatDesdeTi($itemId, $inventSizeId);
                // Si tenemos lista y el registro tenía BomId pero no BomName, rellenar BomName desde TI
                if ($bomId !== '' && $bomName === '' && count($listaLmat) > 0) {
                    foreach ($listaLmat as $item) {
                        if (isset($item['bomId']) && (string) $item['bomId'] === $bomId) {
                            $bomName = isset($item['bomName']) ? (string) $item['bomName'] : '';
                            break;
                        }
                    }
                }
            }

            return response()->json([
                's' => true,
                'd' => [
                    'ordenTejido'   => (string) $registro->OrdenTejido,
                    'telarId'       => $registro->TelarId !== null ? (string) $registro->TelarId : '',
                    'itemId'        => $registro->ItemId !== null ? (string) $registro->ItemId : '',
                    'nombre'        => $registro->Nombre ?? $registro->ClaveModelo ?? '',
                    'actualizaLmat' => $actualizaLmat,
                    'pesoMuestra'   => $pesoMuestra,
                    'bomId'         => $bomId,
                    'bomName'       => $bomName,
                    'listaLmat'     => $listaLmat,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('CatCodificacionController::getCatCodificadosPorOrden', [
                'ordenTejido' => $ordenTejido ?? '',
                'error'       => $e->getMessage(),
            ]);
            return response()->json([
                's' => false,
                'e' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * API: Actualizar PesoMuestra, ActualizaLmat y BomId en CatCodificados, ReqProgramaTejido y ReqModelosCodificados.
     */
    public function actualizarPesoMuestraLmat(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'ordenTejido'   => 'required|string',
                'pesoMuestra'   => 'nullable|numeric|min:0',
                'actualizaLmat' => 'required|boolean',
                'bomId'         => 'nullable|string|max:20',
            ]);

            $ordenTejido   = trim((string) $validated['ordenTejido']);
            $pesoMuestra   = isset($validated['pesoMuestra']) && $validated['pesoMuestra'] !== null ? (float) $validated['pesoMuestra'] : null;
            $actualizaLmat = (bool) $validated['actualizaLmat'];
            // Si Act Lmat está desactivado, forzar BomId y BomName a null
            $bomId = $actualizaLmat
                ? (isset($validated['bomId']) && $validated['bomId'] !== '' && $validated['bomId'] !== null ? trim((string) $validated['bomId']) : null)
                : null;

            // Obtener BomName desde sqlsrv_ti si tenemos BomId
            $bomName = null;
            if ($bomId !== null && $bomId !== '') {
                // Necesitamos ItemId e InventSizeId para buscar BomName
                $catCod = CatCodificados::query()
                    ->where('OrdenTejido', $ordenTejido)
                    ->first(['ItemId', 'InventSizeId']);
                if ($catCod && $catCod->ItemId) {
                    $invSize = $catCod->InventSizeId !== null ? trim((string) $catCod->InventSizeId) : null;
                    $listaLmat = $this->queryLmatDesdeTi((string) $catCod->ItemId, $invSize !== '' ? $invSize : null);
                    foreach ($listaLmat as $item) {
                        if (isset($item['bomId']) && (string) $item['bomId'] === $bomId) {
                            $bomName = isset($item['bomName']) ? (string) $item['bomName'] : null;
                            break;
                        }
                    }
                }
            }

            $actualizados = [];

            // 1. Actualizar CatCodificados
            $catCod = CatCodificados::query()
                ->where('OrdenTejido', $ordenTejido)
                ->first();
            if ($catCod) {
                $catCod->PesoMuestra   = $pesoMuestra;
                $catCod->ActualizaLmat  = $actualizaLmat;
                if ($bomId !== null) {
                    $catCod->BomId = $bomId;
                    if ($bomName !== null) {
                        $catCod->BomName = $bomName;
                    }
                } else {
                    $catCod->BomId     = null;
                    $catCod->BomName   = null;
                }
                $catCod->save();
                $actualizados[] = 'CatCodificados';
            }

            // 2. Actualizar ReqProgramaTejido (buscar por NoProduccion = ordenTejido)
            $reqProg = ReqProgramaTejido::query()
                ->where('NoProduccion', $ordenTejido)
                ->get();
            foreach ($reqProg as $prog) {
                $prog->PesoMuestra  = $pesoMuestra;
                $prog->ActualizaLmat = $actualizaLmat;
                if ($bomId !== null) {
                    $prog->BomId = $bomId;
                    if ($bomName !== null) {
                        $prog->BomName = $bomName;
                    }
                } else {
                    $prog->BomId   = null;
                    $prog->BomName = null;
                }
                $prog->save();
            }
            if ($reqProg->count() > 0) {
                $actualizados[] = 'ReqProgramaTejido (' . $reqProg->count() . ' registro(s))';
            }

            // 3. Actualizar ReqModelosCodificados (buscar por OrdenTejido)
            $reqModelos = ReqModelosCodificados::query()
                ->where('OrdenTejido', $ordenTejido)
                ->get();
            foreach ($reqModelos as $modelo) {
                $modelo->PesoMuestra = $pesoMuestra;
                $modelo->save();
            }
            if ($reqModelos->count() > 0) {
                $actualizados[] = 'ReqModelosCodificados (' . $reqModelos->count() . ' registro(s))';
            }

            // Limpiar caché para que la siguiente recarga de la tabla traiga datos actualizados
            Cache::forget('catcodificacion_fast_all');

            return response()->json([
                's' => true,
                'message' => 'Datos actualizados correctamente',
                'actualizados' => $actualizados,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                's' => false,
                'e' => 'Validación fallida: ' . implode(', ', $e->errors()),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('CatCodificacionController::actualizarPesoMuestraLmat', [
                'ordenTejido' => $request->input('ordenTejido', ''),
                'error'       => $e->getMessage(),
            ]);
            return response()->json([
                's' => false,
                'e' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Consulta LMAT (Lista de materiales) en la BD sqlsrv_ti (BOMTABLE + BOMVERSION).
     * Misma lógica que LiberarOrdenesController: si inventSizeId está vacío, no filtra por tamaño
     * para devolver todos los BOM disponibles del artículo.
     *
     * @return array<int, array{bomId: string, bomName: string}>
     */
    private function queryLmatDesdeTi(string $itemId, ?string $inventSizeId = null): array
    {
        try {
            $itemId = trim($itemId);
            if ($itemId === '') {
                return [];
            }

            $itemIdWithSuffix = $itemId . '-1';
            $query = DB::connection('sqlsrv_ti')
                ->table('BOMTABLE as BT')
                ->join('BOMVERSION as BV', 'BV.BOMID', '=', 'BT.BOMID')
                ->select('BT.BOMID as bomId', 'BT.NAME as bomName')
                ->where('BV.ITEMID', $itemIdWithSuffix);

            // Solo filtrar por tamaño si viene informado (igual que LiberarOrdenesController)
            if ($inventSizeId !== null && trim((string) $inventSizeId) !== '') {
                $query->where('BT.TWINVENTSIZEID', trim($inventSizeId));
            }

            $results = $query->orderBy('BT.BOMID')->limit(50)->get();

            if ($results->isEmpty()) {
                return [];
            }

            return $results->map(fn ($r) => [
                'bomId'   => $r->bomId !== null ? (string) $r->bomId : '',
                'bomName' => $r->bomName !== null ? (string) $r->bomName : '',
            ])->values()->all();
        } catch (\Throwable $e) {
            Log::warning('CatCodificacionController::queryLmatDesdeTi', [
                'itemId'       => $itemId,
                'inventSizeId' => $inventSizeId ?? '',
                'error'        => $e->getMessage(),
            ]);
            return [];
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
                    ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
                    ->header('Pragma', 'no-cache')
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
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
                ->header('Pragma', 'no-cache')
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
     * API: Obtener registros de CatCodificados que comparten la misma OrdCompartida.
     * Devuelve todos los registros con el mismo valor de OrdCompartida para mostrar en el modal de balancear.
     */
    public function registrosOrdCompartida(string $ordCompartida): JsonResponse
    {
        try {
            $ordCompartida = trim($ordCompartida);
            if ($ordCompartida === '' || $ordCompartida === '0') {
                return response()->json([
                    'success' => false,
                    'message' => 'OrdCompartida requerida',
                    'registros' => [],
                ], 400);
            }

            // Convertir a entero si es posible, sino buscar como string
            $ordCompartidaInt = is_numeric($ordCompartida) ? (int) $ordCompartida : null;

            $registros = CatCodificados::query()
                ->where('OrdCompartida', $ordCompartidaInt ?? $ordCompartida)
                ->orderBy('OrdCompartidaLider', 'desc') // Los líderes primero
                ->orderBy('Id', 'asc')
                ->get([
                    'OrdenTejido',
                    'TelarId',
                    'Nombre',
                    'ClaveModelo',
                    'Cantidad',
                    'Produccion',
                    'Saldos',
                    'TotalSegundas',
                    'OrdCompartida',
                    'OrdCompartidaLider',
                ])
                ->map(function ($registro) {
                    return [
                        'OrdenTejido' => $registro->OrdenTejido !== null ? (string) $registro->OrdenTejido : '',
                        'TelarId' => $registro->TelarId !== null ? (string) $registro->TelarId : '',
                        'Nombre' => $registro->Nombre ?? '',
                        'ClaveModelo' => $registro->ClaveModelo ?? '',
                        'Cantidad' => $registro->Cantidad !== null ? (string) $registro->Cantidad : '',
                        'Produccion' => $registro->Produccion !== null ? (string) $registro->Produccion : '',
                        'Saldos' => $registro->Saldos !== null ? (string) $registro->Saldos : '',
                        'TotalSegundas' => $registro->TotalSegundas !== null ? (string) $registro->TotalSegundas : '',
                        'OrdCompartida' => $registro->OrdCompartida,
                        'OrdCompartidaLider' => $registro->OrdCompartidaLider,
                    ];
                })
                ->values()
                ->all();

            // Verificar si hay algún registro con OrdCompartidaLider activo
            $tieneLideres = false;
            foreach ($registros as $registro) {
                $esLider = $registro['OrdCompartidaLider'] === 1
                    || $registro['OrdCompartidaLider'] === true
                    || $registro['OrdCompartidaLider'] === '1';
                if ($esLider) {
                    $tieneLideres = true;
                    break;
                }
            }

            return response()->json([
                'success' => true,
                'message' => count($registros) > 0 ? 'Registros encontrados' : 'No se encontraron registros compartidos',
                'registros' => $registros,
                'tieneLideres' => $tieneLideres,
            ]);
        } catch (\Throwable $e) {
            Log::error('CatCodificacionController::registrosOrdCompartida', [
                'ordCompartida' => $ordCompartida ?? '',
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener registros compartidos: ' . $e->getMessage(),
                'registros' => [],
            ], 500);
        }
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

    public static function progressCacheKey(string $id): string
    {
        return 'excel_import_progress:' . $id;
    }

    public static function cancellationCacheKey(string $id): string
    {
        return 'excel_import_cancelled:' . $id;
    }

    /**
     * Consultar progreso del import encolado.
     */
    public function importProgress(string $id): JsonResponse
    {
        $state = Cache::get(self::progressCacheKey($id));

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

    public function cancelImport(string $id): JsonResponse
    {
        $key = self::progressCacheKey($id);
        $state = Cache::get($key);

        if (!$state) {
            return response()->json([
                'success' => false,
                'message' => 'Importacion no encontrada',
            ], 404);
        }

        if (($state['status'] ?? null) === 'done') {
            return response()->json([
                'success' => false,
                'message' => 'La importacion ya termino',
            ], 409);
        }

        Cache::put(self::cancellationCacheKey($id), true, 3600);

        $state['status'] = 'cancelled';
        $state['cancelled'] = true;
        $state['has_errors'] = !empty($state['errors'] ?? []);
        Cache::put($key, $state, 3600);

        $deletedJobs = $this->deletePendingQueuedImportJobs($id);

        return response()->json([
            'success' => true,
            'message' => 'Importacion cancelada',
            'data' => [
                'import_id' => $id,
                'status' => 'cancelled',
                'deleted_jobs' => $deletedJobs,
            ],
        ]);
    }

    /**
     * @return array<int, mixed>
     */
    private function readHeaderRow(string $path, string $extension): array
    {
        $reader = $this->makeSpreadsheetReader($extension);
        $reader->setReadDataOnly(true);

        $spreadsheet = $reader->load($path);

        try {
            $sheet = $spreadsheet->getSheet(0);
            $highestColumn = $sheet->getHighestDataColumn(1);
            $range = sprintf('A1:%s1', $highestColumn);

            return $sheet->rangeToArray($range, null, true, true, false)[0] ?? [];
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }
    }

    private function resolveTotalRows(string $path, string $extension): ?int
    {
        try {
            $reader = $this->makeSpreadsheetReader($extension);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($path);

            try {
                $sheet = $spreadsheet->getSheet(0);
                $highestRow = $sheet->getHighestDataRow();
                if ($highestRow < 2) {
                    return 0;
                }

                $columns = $this->headerMapper->expectedColumns();
                $lastColumn = end($columns);
                if ($lastColumn === false) {
                    return 0;
                }

                $totalRows = 0;

                for ($row = 2; $row <= $highestRow; $row++) {
                    $range = sprintf('A%d:%s%d', $row, $lastColumn, $row);
                    $values = $sheet->rangeToArray($range, null, true, true, false)[0] ?? [];

                    if ($this->rowHasMeaningfulData($values)) {
                        $totalRows++;
                    }
                }

                return $totalRows;
            } finally {
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);
            }
        } catch (\Throwable $e) {
            Log::warning('CatCodificacionController::procesarExcel totalRows', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function makeSpreadsheetReader(string $extension): XlsReader|XlsxReader
    {
        return $extension === 'xls'
            ? new XlsReader()
            : new XlsxReader();
    }

    private function shouldProcessInline(?int $totalRows): bool
    {
        return $totalRows !== null && $totalRows <= self::INLINE_IMPORT_ROW_THRESHOLD;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function buildImportSummary(array $state): array
    {
        $rawErrors = $state['errors'] ?? [];
        $errors = [];

        if (is_array($rawErrors)) {
            foreach ($rawErrors as $err) {
                $errors[] = [
                    'fila' => $err['fila'] ?? 'N/A',
                    'error' => mb_substr($err['error'] ?? 'Error desconocido', 0, 150),
                ];
            }
        }

        return [
            'status' => $state['status'] ?? 'done',
            'processed_rows' => (int) ($state['processed_rows'] ?? 0),
            'created' => (int) ($state['created'] ?? 0),
            'updated' => (int) ($state['updated'] ?? 0),
            'error_count' => (int) ($state['error_count'] ?? 0),
            'errors' => $errors,
        ];
    }

    private function deletePendingQueuedImportJobs(string $importId): int
    {
        if (config('queue.default') !== 'database') {
            return 0;
        }

        $connection = config('queue.connections.database.connection');
        $table = config('queue.connections.database.table', 'jobs');

        try {
            return DB::connection($connection)
                ->table($table)
                ->where('payload', 'like', '%' . $importId . '%')
                ->delete();
        } catch (\Throwable $e) {
            Log::warning('CatCodificacionController::cancelImport delete jobs', [
                'import_id' => $importId,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * @param  array<int, mixed>  $values
     */
    private function rowHasMeaningfulData(array $values): bool
    {
        foreach ($values as $value) {
            if (!$this->isNullLikeSpreadsheetValue($value)) {
                return true;
            }
        }

        return false;
    }

    private function isNullLikeSpreadsheetValue(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            $normalized = mb_strtolower(trim($value));

            return in_array($normalized, ['', 'na', 'n/a', 'null', '-', 'nan'], true);
        }

        return false;
    }
}
