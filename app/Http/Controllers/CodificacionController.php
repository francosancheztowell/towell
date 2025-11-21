<?php

namespace App\Http\Controllers;

use App\Models\ReqModelosCodificados;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Pagination\LengthAwarePaginator;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\ReqModelosCodificadosImport;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Reader\Xls as XlsReader;

class CodificacionController extends Controller
{
    /** Vista principal - Paginación híbrida: servidor (2000) + cliente */
    public function index()
    {
        try {
            // Paginación del servidor: 2000 registros por página
            $perPage = 2000;
            $page = request()->get('page', 1);
            $offset = ($page - 1) * $perPage;
            $startRow = $offset + 1;
            $endRow = $offset + $perPage;

            // Obtener total de registros
            $total = ReqModelosCodificados::count();

            // Consulta compatible con SQL Server 2008 usando ROW_NUMBER()
            $sql = "
                SELECT * FROM (
                    SELECT
                        [Id], [TamanoClave], [OrdenTejido], [FechaTejido], [FechaCumplimiento], [SalonTejidoId], [NoTelarId],
                        [Prioridad], [Nombre], [ClaveModelo], [ItemId], [InventSizeId], [Tolerancia], [CodigoDibujo],
                        [FechaCompromiso], [FlogsId], [NombreProyecto], [Clave], [Pedido], [Peine], [AnchoToalla],
                        [LargoToalla], [PesoCrudo], [Luchaje], [CalibreTrama], [CalibreTrama2], [CodColorTrama],
                        [ColorTrama], [FibraId], [DobladilloId], [MedidaPlano], [TipoRizo], [AlturaRizo], [Obs],
                        [VelocidadSTD], [CalibreRizo], [CalibreRizo2], [CuentaRizo], [FibraRizo], [CalibrePie],
                        [CalibrePie2], [CuentaPie], [FibraPie], [Comb1], [Obs1], [Comb2], [Obs2], [Comb3], [Obs3],
                        [Comb4], [Obs4], [MedidaCenefa], [MedIniRizoCenefa], [Rasurado], [NoTiras], [Repeticiones],
                        [TotalMarbetes], [CambioRepaso], [Vendedor], [CatCalidad], [Obs5], [AnchoPeineTrama],
                        [LogLuchaTotal], [CalTramaFondoC1], [CalTramaFondoC12], [FibraTramaFondoC1], [PasadasTramaFondoC1],
                        [CalibreComb1], [CalibreComb12], [FibraComb1], [CodColorC1], [NomColorC1], [PasadasComb1],
                        [CalibreComb2], [CalibreComb22], [FibraComb2], [CodColorC2], [NomColorC2], [PasadasComb2],
                        [CalibreComb3], [CalibreComb32], [FibraComb3], [CodColorC3], [NomColorC3], [PasadasComb3],
                        [CalibreComb4], [CalibreComb42], [FibraComb4], [CodColorC4], [NomColorC4], [PasadasComb4],
                        [CalibreComb5], [CalibreComb52], [FibraComb5], [CodColorC5], [NomColorC5], [PasadasComb5],
                        [Total], [PasadasDibujo], [Contraccion], [TramasCMTejido], [ContracRizo], [ClasificacionKG],
                        [KGDia], [Densidad], [PzasDiaPasadas], [PzasDiaFormula], [DIF], [EFIC], [Rev], [TIRAS],
                        [PASADAS], [ColumCT], [ColumCU], [ColumCV], [ComprobarModDup],
                        ROW_NUMBER() OVER (ORDER BY [Id] DESC) AS RowNum
                    FROM [ReqModelosCodificados]
                ) AS NumberedTable
                WHERE RowNum BETWEEN ? AND ?
                ORDER BY RowNum
            ";

            $results = DB::select($sql, [$startRow, $endRow]);

            // Convertir resultados a modelos Eloquent
            $codificaciones = collect($results)->map(function ($item) {
                unset($item->RowNum);
                return ReqModelosCodificados::hydrate([(array)$item])->first();
            });

            // Crear paginador para navegación del servidor
            $codificaciones = new LengthAwarePaginator(
                $codificaciones,
                $total,
                $perPage,
                $page,
                ['path' => request()->url(), 'query' => request()->query()]
            );

            return view('catalagos.catalogoCodificacion', compact('codificaciones'));
        } catch (\Exception $e) {
            Log::error('Error en CodificacionController::index', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return view('catalagos.catalogoCodificacion', [
                'codificaciones' => collect(),
                'error' => 'Error al cargar los datos: ' . $e->getMessage()
            ]);
        }
    }

    public function create()
    {
        return view('catalagos.codificacion-form');
    }

    public function edit($id)
    {
        // Usar TamanoClave como primary key
        $codificacion = ReqModelosCodificados::findOrFail($id);
        return view('catalagos.codificacion-form', compact('codificacion'));
    }

    /** API: todos - Optimizado para rendimiento máximo */
    public function getAll(): JsonResponse
    {
        try {
            // Consulta optimizada: solo columnas necesarias + orden por primary key
            $codificaciones = ReqModelosCodificados::select([
                'Id', 'TamanoClave', 'OrdenTejido', 'FechaTejido', 'FechaCumplimiento', 'SalonTejidoId', 'NoTelarId',
                'Prioridad', 'Nombre', 'ClaveModelo', 'ItemId', 'InventSizeId', 'Tolerancia', 'CodigoDibujo',
                'FechaCompromiso', 'FlogsId', 'NombreProyecto', 'Clave', 'Pedido', 'Peine', 'AnchoToalla',
                'LargoToalla', 'PesoCrudo', 'Luchaje', 'CalibreTrama', 'CalibreTrama2', 'CodColorTrama',
                'ColorTrama', 'FibraId', 'DobladilloId', 'MedidaPlano', 'TipoRizo', 'AlturaRizo', 'Obs',
                'VelocidadSTD', 'CalibreRizo', 'CalibreRizo2', 'CuentaRizo', 'FibraRizo', 'CalibrePie',
                'CalibrePie2', 'CuentaPie', 'FibraPie', 'Comb1', 'Obs1', 'Comb2', 'Obs2', 'Comb3', 'Obs3',
                'Comb4', 'Obs4', 'MedidaCenefa', 'MedIniRizoCenefa', 'Rasurado', 'NoTiras', 'Repeticiones',
                'TotalMarbetes', 'CambioRepaso', 'Vendedor', 'CatCalidad', 'Obs5', 'AnchoPeineTrama',
                'LogLuchaTotal', 'CalTramaFondoC1', 'CalTramaFondoC12', 'FibraTramaFondoC1', 'PasadasTramaFondoC1',
                'CalibreComb1', 'CalibreComb12', 'FibraComb1', 'CodColorC1', 'NomColorC1', 'PasadasComb1',
                'CalibreComb2', 'CalibreComb22', 'FibraComb2', 'CodColorC2', 'NomColorC2', 'PasadasComb2',
                'CalibreComb3', 'CalibreComb32', 'FibraComb3', 'CodColorC3', 'NomColorC3', 'PasadasComb3',
                'CalibreComb4', 'CalibreComb42', 'FibraComb4', 'CodColorC4', 'NomColorC4', 'PasadasComb4',
                'CalibreComb5', 'CalibreComb52', 'FibraComb5', 'CodColorC5', 'NomColorC5', 'PasadasComb5',
                'Total', 'PasadasDibujo', 'Contraccion', 'TramasCMTejido', 'ContracRizo', 'ClasificacionKG',
                'KGDia', 'Densidad', 'PzasDiaPasadas', 'PzasDiaFormula', 'DIF', 'EFIC', 'Rev', 'TIRAS',
                'PASADAS', 'ColumCT', 'ColumCU', 'ColumCV', 'ComprobarModDup'
            ])
            ->orderBy('Id', 'desc')
            ->get();

            return response()->json([
                'success' => true,
                'data' => $codificaciones,
                'total' => $codificaciones->count()
            ]);
        } catch (\Exception $e) {
            Log::error('Error en CodificacionController::getAll', [
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Error al cargar los datos: ' . $e->getMessage()
            ], 500);
        }
    }

    /** API: uno */
    public function show($id): JsonResponse
    {
        $codificacion = ReqModelosCodificados::find($id);
        if (!$codificacion) {
            return response()->json(['success' => false, 'message' => 'Registro no encontrado'], 404);
        }
        return response()->json(['success' => true, 'data' => $codificacion]);
    }

    /**
     * Valida sin forzar tipos: por defecto everything = string|null.
     * Solo marcamos fechas como 'date' para permitir Carbon si vienen con formato válido.
     */
    private function validationRules(array $input, bool $isCreate = true): array
    {
        // Campos del modelo (todas las columnas)
        $fillable = (new ReqModelosCodificados)->getFillable();

        // Por defecto: permitir string o null (no convertimos a número)
        $rules = [];
        foreach ($fillable as $col) {
            $rules[$col] = 'sometimes|nullable'; // no forzar tipo
        }

        // Reglas más específicas
        $dateFields = ['FechaTejido','FechaCumplimiento','FechaCompromiso'];
        foreach ($dateFields as $d) {
            if (array_key_exists($d, $rules)) {
                $rules[$d] = 'sometimes|nullable|date';
            }
        }

        // Si quieres exigir llaves mínimas para crear, actívalas:
        if ($isCreate) {
            foreach (['TamanoClave','OrdenTejido'] as $req) {
                if (array_key_exists($req, $rules)) {
                    $rules[$req] = 'required';
                }
            }
        }

        return $rules;
    }

    /** API: crear */
    public function store(Request $request): JsonResponse
    {
        $rules = $this->validationRules($request->all(), true);
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validación incorrecta',
                'errors'  => $validator->errors()
            ], 422);
        }

        // Guardamos exactamente lo que viene (sin castear)
        $codificacion = ReqModelosCodificados::create($request->only((new ReqModelosCodificados)->getFillable()));

        return response()->json([
            'success' => true,
            'message' => 'Registro creado',
            'data'    => $codificacion
        ], 201);
    }

    /** API: actualizar */
    public function update(Request $request, $id): JsonResponse
    {
        $codificacion = ReqModelosCodificados::find($id);
        if (!$codificacion) {
            return response()->json(['success' => false, 'message' => 'Registro no encontrado'], 404);
        }

        $rules = $this->validationRules($request->all(), false);
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validación incorrecta',
                'errors'  => $validator->errors()
            ], 422);
        }

        $codificacion->update($request->only($codificacion->getFillable()));

        return response()->json([
            'success' => true,
            'message' => 'Registro actualizado',
            'data'    => $codificacion
        ]);
    }

    /** API: eliminar */
    public function destroy($id): JsonResponse
    {
        $codificacion = ReqModelosCodificados::find($id);
        if (!$codificacion) {
            return response()->json(['success' => false, 'message' => 'Registro no encontrado'], 404);
        }
        $codificacion->delete();
        return response()->json(['success' => true, 'message' => 'Registro eliminado']);
    }

    /** Procesar Excel usando tu import */
    public function procesarExcel(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'archivo_excel' => 'required|file|mimes:xlsx,xls|max:10240'
            ]);

            // Crear un importId único para el proceso y estimar total de filas
            $importId = (string) Str::uuid();

            // Obtener ruta temporal del archivo subido
            $path = $request->file('archivo_excel')->getRealPath();
            $totalRows = null;
            try {
                $ext = strtolower($request->file('archivo_excel')->getClientOriginalExtension() ?? '');
                if ($ext === 'xls') {
                    $reader = new XlsReader();
                } else {
                    $reader = new XlsxReader();
                }
                // listWorksheetInfo es rápido y no carga todo en memoria
                $info = $reader->listWorksheetInfo($path);
                $totalRows = isset($info[0]['totalRows']) ? max(0, (int)$info[0]['totalRows'] - 2) : null; // restar 2 filas de encabezado
            } catch (\Throwable $e) {
                Log::warning('No se pudo obtener totalRows del excel: ' . $e->getMessage());
            }

            $import = new ReqModelosCodificadosImport($importId, $totalRows);

            // Encolar el import (debe tener queue configurada en el proyecto)
            Excel::queueImport($import, $request->file('archivo_excel'));

            return response()->json([
                'success' => true,
                'message' => 'Import encolado correctamente',
                'data' => [
                    'import_id' => $importId,
                    'total_rows' => $totalRows,
                    'poll_url' => '/planeacion/catalogos/codificacion-modelos/excel-progress/' . $importId
                ]
            ], 202);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validación fallida',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error en importación de ReqModelosCodificados', [
                'archivo' => $request->file('archivo_excel')?->getClientOriginalName(),
                'error' => $e->getMessage(),
                'stack' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el archivo: ' . $e->getMessage(),
                'error_type' => class_basename($e)
            ], 500);
        }
    }

    /** Consultar progreso del import por importId */
    public function importProgress($id): JsonResponse
    {
        try {
            $key = 'excel_import_progress:' . $id;
            $state = Cache::get($key);
            if (!$state) {
                return response()->json(['success' => false, 'message' => 'Progreso no encontrado'], 404);
            }

            // Calcular porcentaje si es posible
            $pct = null;
            if (!empty($state['total_rows']) && $state['total_rows'] > 0) {
                $pct = round(100 * (($state['processed_rows'] ?? 0) / $state['total_rows']), 1);
            }

            // Extraer errores del estado para enviarlos en el response
            $errors = [];
            if (isset($state['errors']) && is_array($state['errors'])) {
                $errors = array_map(function($error) {
                    return [
                        'fila' => $error['fila'] ?? 'N/A',
                        'error' => substr($error['error'] ?? 'Error desconocido', 0, 150)
                    ];
                }, $state['errors']);
            }

            return response()->json([
                'success' => true,
                'data' => $state,
                'percent' => $pct,
                'errors' => $errors,
                'has_errors' => !empty($errors)
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /** Búsqueda simple con filtros comunes */
    public function buscar(Request $request): JsonResponse
    {
        $q = ReqModelosCodificados::query();

        if ($v = $request->get('tamano_clave')) $q->where('TamanoClave', 'like', "%$v%");
        if ($v = $request->get('orden_tejido')) $q->where('OrdenTejido', 'like', "%$v%");
        if ($v = $request->get('nombre'))       $q->where('Nombre', 'like', "%$v%");
        if ($v = $request->get('salon_tejido')) $q->where('SalonTejidoId', $v);
        if ($v = $request->get('no_telar'))     $q->where('NoTelarId', $v);
        if ($v = $request->get('fecha_desde'))  $q->where('FechaTejido', '>=', $v);
        if ($v = $request->get('fecha_hasta'))  $q->where('FechaTejido', '<=', $v);

        try {
            // Ordenar directamente por TamanoClave (primary key, más rápido)
            $data = $q->orderBy('Id', 'desc')->get();

            if ($data->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'total' => 0,
                    'mensaje' => 'No se encontraron registros que coincidan con los filtros'
                ]);
            }

            return response()->json(['success'=>true,'data'=>$data,'total'=>$data->count()]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error en la búsqueda: ' . $e->getMessage()
            ]);
        }
    }

    /** Estadísticas básicas */
    public function estadisticas(): JsonResponse
    {
        $total = ReqModelosCodificados::count();
        $porSalon = ReqModelosCodificados::select('SalonTejidoId', DB::raw('count(*) as total'))
            ->groupBy('SalonTejidoId')->get();
        $porPrioridad = ReqModelosCodificados::select('Prioridad', DB::raw('count(*) as total'))
            ->groupBy('Prioridad')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_registros' => $total,
                'por_salon'       => $porSalon,
                'por_prioridad'   => $porPrioridad,
            ]
        ]);
    }
}
