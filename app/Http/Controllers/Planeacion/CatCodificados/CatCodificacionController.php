<?php

namespace App\Http\Controllers\Planeacion\CatCodificados;

use App\Http\Controllers\Controller;
use App\Imports\CatCodificadosImport;
use App\Models\Planeacion\Catalogos\CatCodificados;
use App\Models\Planeacion\ReqModelosCodificados;
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
