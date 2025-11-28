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
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Reader\Xls as XlsReader;

class CodificacionController extends Controller
{
    /** Columnas de la tabla (headers) */
    private const COLUMNAS = [
        'Clave mod.', 'NoProduccion', 'Fecha Orden', 'Fecha Cumplimiento', 'Departamento',
        'Telar Actual', 'Prioridad', 'Modelo', 'Clave Modelo', 'Clave AX', 'Tamaño',
        'Tolerancia', 'Codigo Dibujo', 'Fecha Compromiso', 'Id Flog.', 'Nombre de Formato Logistico',
        'Clave', 'Cantidad a Producir', 'Peine', 'Ancho', 'Largo', 'P_crudo', 'Luchaje',
        'Tra', 'Hilo', 'Codigo Color Trama', 'Nombre Color Trama', 'OBS.', 'Tipo plano',
        'Med plano', 'Tipo de Rizo', 'Altura de Rizo', 'OBS', 'Veloc. Mínima', 'Rizo', 'Hilo',
        'Cuenta', 'OBS.', 'Pie', 'Hilo', 'Cuenta', 'OBS', 'C1', 'OBS', 'C2', 'OBS', 'C3', 'OBS',
        'C4', 'OBS', 'Med. de Cenefa', 'Med de inicio de rizo a cenefa', 'Rasurada', 'Tiras',
        'Repeticiones p/corte', 'No. De Marbetes', 'Cambio de repaso', 'Vendedor', 'No. Orden',
        'Observaciones', 'TRAMA (Ancho Peine)', 'Log. de Lucha Total', 'C1 trama de Fondo', 'Hilo',
        'OBS', 'Pasadas', 'C1', 'Hilo', 'OBS.', 'Cod Color', 'Nombre Color', 'Pasadas', 'C2',
        'Hilo', 'OBS.', 'Cod Color', 'Nombre Color', 'Pasadas', 'C3', 'Hilo', 'OBS.', 'Cod Color',
        'Nombre Color', 'Pasadas', 'C4', 'Hilo', 'OBS.', 'Cod Color', 'Nombre Color', 'Pasadas',
        'CS', 'Hilo', 'OBS.', 'Cod Color', 'Nombre Color', 'Pasadas', 'Total', 'Pasadas Dibujo',
        'Contraccion', 'Tramas cm/Tejido', 'Contrac Rizo', 'Clasificación(KG)', 'KG/Dia',
        'Densidad', 'Pzas/Día/pasadas', 'Pzas/Día/formula', 'Dif', 'Efic', 'Rev', 'Tiras',
        'Pasadas', 'ColumCT', 'ColumCU', 'ColumCV', 'ComprobarModDup'
    ];

    /** Mapeo campo => tipo (date|zero|null para string) */
    private const CAMPOS_MODELO = [
        'TamanoClave' => null,
        'OrdenTejido' => null,
        'FechaTejido' => 'date',
        'FechaCumplimiento' => 'date',
        'SalonTejidoId' => null,
        'NoTelarId' => null,
        'Prioridad' => null,
        'Nombre' => null,
        'ClaveModelo' => null,
        'ItemId' => null,
        'InventSizeId' => null,
        'Tolerancia' => null,
        'CodigoDibujo' => null,
        'FechaCompromiso' => 'date',
        'FlogsId' => null,
        'NombreProyecto' => null,
        'Clave' => null,
        'Pedido' => 'zero',
        'Peine' => 'zero',
        'AnchoToalla' => 'zero',
        'LargoToalla' => 'zero',
        'PesoCrudo' => 'zero',
        'Luchaje' => 'zero',
        'CalibreTrama' => 'zero',
        'CalibreTrama2' => 'zero',
        'CodColorTrama' => null,
        'ColorTrama' => null,
        'FibraId' => null,
        'DobladilloId' => null,
        'MedidaPlano' => 'zero',
        'TipoRizo' => null,
        'AlturaRizo' => 'zero',
        'Obs' => null,
        'VelocidadSTD' => 'zero',
        'CalibreRizo' => null,
        'CalibreRizo2' => null,
        'CuentaRizo' => 'zero',
        'FibraRizo' => null,
        'CalibrePie' => null,
        'CalibrePie2' => null,
        'CuentaPie' => 'zero',
        'FibraPie' => null,
        'Comb1' => null,
        'Obs1' => null,
        'Comb2' => null,
        'Obs2' => null,
        'Comb3' => null,
        'Obs3' => null,
        'Comb4' => null,
        'Obs4' => null,
        'MedidaCenefa' => null,
        'MedIniRizoCenefa' => null,
        'Rasurado' => null,
        'NoTiras' => 'zero',
        'Repeticiones' => 'zero',
        'TotalMarbetes' => 'zero',
        'CambioRepaso' => null,
        'Vendedor' => null,
        'CatCalidad' => null,
        'Obs5' => null,
        'AnchoPeineTrama' => 'zero',
        'LogLuchaTotal' => 'zero',
        'CalTramaFondoC1' => null,
        'CalTramaFondoC12' => null,
        'FibraTramaFondoC1' => null,
        'PasadasTramaFondoC1' => 'zero',
        'CalibreComb1' => null,
        'CalibreComb12' => null,
        'FibraComb1' => null,
        'CodColorC1' => null,
        'NomColorC1' => null,
        'PasadasComb1' => 'zero',
        'CalibreComb2' => null,
        'CalibreComb22' => null,
        'FibraComb2' => null,
        'CodColorC2' => null,
        'NomColorC2' => null,
        'PasadasComb2' => 'zero',
        'CalibreComb3' => null,
        'CalibreComb32' => null,
        'FibraComb3' => null,
        'CodColorC3' => null,
        'NomColorC3' => null,
        'PasadasComb3' => 'zero',
        'CalibreComb4' => null,
        'CalibreComb42' => null,
        'FibraComb4' => null,
        'CodColorC4' => null,
        'NomColorC4' => null,
        'PasadasComb4' => 'zero',
        'CalibreComb5' => null,
        'CalibreComb52' => null,
        'FibraComb5' => null,
        'CodColorC5' => null,
        'NomColorC5' => null,
        'PasadasComb5' => 'zero',
        'Total' => 'zero',
        'PasadasDibujo' => null,
        'Contraccion' => null,
        'TramasCMTejido' => null,
        'ContracRizo' => null,
        'ClasificacionKG' => null,
        'KGDia' => null,
        'Densidad' => null,
        'PzasDiaPasadas' => null,
        'PzasDiaFormula' => null,
        'DIF' => null,
        'EFIC' => null,
        'Rev' => null,
        'TIRAS' => 'zero',
        'PASADAS' => 'zero',
        'ColumCT' => null,
        'ColumCU' => null,
        'ColumCV' => null,
        'ComprobarModDup' => null,
    ];

    /** Campos de fecha para validación */
    private const DATE_FIELDS = ['FechaTejido', 'FechaCumplimiento', 'FechaCompromiso'];

    /** Campos requeridos para creación */
    private const REQUIRED_FIELDS = ['TamanoClave', 'OrdenTejido'];

    /** Obtener configuración de columnas para JavaScript */
    public static function getColumnasConfig(): array
    {
        return array_map(fn($col, $idx) => ['index' => $idx, 'nombre' => $col], self::COLUMNAS, array_keys(self::COLUMNAS));
    }

    /** Obtener campos del modelo con sus tipos */
    public static function getCamposModelo(): array
    {
        return self::CAMPOS_MODELO;
    }

    /** Vista principal - Solo estructura, datos via API */
    public function index()
    {
        try {
            // Solo enviamos la estructura, los datos se cargan via fetch
            $total = Cache::remember('codificacion_total', 300, fn() => ReqModelosCodificados::count());

            return view('catalagos.catalogoCodificacion', [
                'columnas' => self::COLUMNAS,
                'camposModelo' => self::CAMPOS_MODELO,
                'columnasConfig' => self::getColumnasConfig(),
                'totalRegistros' => $total,
                'apiUrl' => '/planeacion/catalogos/codificacion-modelos/api/all-fast',
            ]);
        } catch (\Exception $e) {
            Log::error('CodificacionController::index', ['error' => $e->getMessage()]);
            return view('catalagos.catalogoCodificacion', [
                'columnas' => self::COLUMNAS,
                'camposModelo' => self::CAMPOS_MODELO,
                'columnasConfig' => self::getColumnasConfig(),
                'totalRegistros' => 0,
                'apiUrl' => '/planeacion/catalogos/codificacion-modelos/api/all-fast',
                'error' => 'Error al cargar: ' . $e->getMessage()
            ]);
        }
    }

    public function create()
    {
        return view('catalagos.codificacion-form');
    }

    public function edit($id)
    {
        $codificacion = ReqModelosCodificados::findOrFail($id);
        return view('catalagos.codificacion-form', compact('codificacion'));
    }

    /** API: todos los registros - Optimizado con índice Id */
    public function getAll(): JsonResponse
    {
        try {
            // Consulta optimizada: usa índice de Id, selecciona solo campos necesarios
            $campos = array_merge(['Id'], array_keys(self::CAMPOS_MODELO));

            // Streaming JSON para grandes volúmenes - más eficiente en memoria
            $codificaciones = DB::table('ReqModelosCodificados')
                ->select($campos)
                ->orderByDesc('Id')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $codificaciones,
                'total' => $codificaciones->count()
            ])->header('Cache-Control', 'private, max-age=60'); // Cache 1 min
        } catch (\Exception $e) {
            Log::error('CodificacionController::getAll', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** API: datos compactos para carga rápida - Solo campos esenciales */
    public function getAllFast(): JsonResponse
    {
        try {
            // Solo campos esenciales para la tabla, ordenados por Id (usa índice clustered)
            $campos = array_merge(['Id'], array_keys(self::CAMPOS_MODELO));

            $data = DB::table('ReqModelosCodificados')
                ->select($campos)
                ->orderByDesc('Id')
                ->get()
                ->map(fn($row) => array_values((array) $row));

            return response()->json([
                's' => true,
                'd' => $data,
                't' => $data->count(),
                'c' => $campos
            ])->header('Cache-Control', 'private, max-age=60');
        } catch (\Exception $e) {
            Log::error('CodificacionController::getAllFast', ['error' => $e->getMessage()]);
            return response()->json(['s' => false, 'e' => $e->getMessage()], 500);
        }
    }

    /** API: un registro */
    public function show($id): JsonResponse
    {
        $codificacion = ReqModelosCodificados::find($id);
        return $codificacion
            ? response()->json(['success' => true, 'data' => $codificacion])
            : response()->json(['success' => false, 'message' => 'No encontrado'], 404);
    }

    /** Generar reglas de validación */
    private function getValidationRules(bool $isCreate = true): array
    {
        $rules = [];
        foreach (array_keys(self::CAMPOS_MODELO) as $field) {
            $rules[$field] = in_array($field, self::DATE_FIELDS)
                ? 'sometimes|nullable|date'
                : 'sometimes|nullable';
        }

        if ($isCreate) {
            foreach (self::REQUIRED_FIELDS as $field) {
                $rules[$field] = 'required';
            }
        }

        return $rules;
    }

    /** API: crear */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->getValidationRules(true));

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validación incorrecta',
                'errors' => $validator->errors()
            ], 422);
        }

        $codificacion = ReqModelosCodificados::create($request->only(array_keys(self::CAMPOS_MODELO)));
        Cache::forget('codificacion_total');

        return response()->json([
            'success' => true,
            'message' => 'Registro creado',
            'data' => $codificacion
        ], 201);
    }

    /** API: actualizar */
    public function update(Request $request, $id): JsonResponse
    {
        $codificacion = ReqModelosCodificados::find($id);
        if (!$codificacion) {
            return response()->json(['success' => false, 'message' => 'No encontrado'], 404);
        }

        $validator = Validator::make($request->all(), $this->getValidationRules(false));
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validación incorrecta',
                'errors' => $validator->errors()
            ], 422);
        }

        $codificacion->update($request->only(array_keys(self::CAMPOS_MODELO)));

        return response()->json([
            'success' => true,
            'message' => 'Registro actualizado',
            'data' => $codificacion
        ]);
    }

    /** API: eliminar */
    public function destroy($id): JsonResponse
    {
        $codificacion = ReqModelosCodificados::find($id);
        if (!$codificacion) {
            return response()->json(['success' => false, 'message' => 'No encontrado'], 404);
        }

        $codificacion->delete();
        Cache::forget('codificacion_total');

        return response()->json(['success' => true, 'message' => 'Registro eliminado']);
    }

    /** Procesar Excel */
    public function procesarExcel(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'archivo_excel' => 'required|file|mimes:xlsx,xls|max:10240'
            ]);

            $importId = (string) Str::uuid();
            $path = $request->file('archivo_excel')->getRealPath();
            $ext = strtolower($request->file('archivo_excel')->getClientOriginalExtension());

            $totalRows = null;
            try {
                $reader = $ext === 'xls' ? new XlsReader() : new XlsxReader();
                $info = $reader->listWorksheetInfo($path);
                $totalRows = isset($info[0]['totalRows']) ? max(0, (int)$info[0]['totalRows'] - 2) : null;
            } catch (\Throwable $e) {
                Log::warning('No se pudo obtener totalRows: ' . $e->getMessage());
            }

            Excel::queueImport(new ReqModelosCodificadosImport($importId, $totalRows), $request->file('archivo_excel'));

            return response()->json([
                'success' => true,
                'message' => 'Import encolado',
                'data' => [
                    'import_id' => $importId,
                    'total_rows' => $totalRows,
                    'poll_url' => '/planeacion/catalogos/codificacion-modelos/excel-progress/' . $importId
                ]
            ], 202);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validación fallida', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Error importación Excel', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /** Consultar progreso del import */
    public function importProgress($id): JsonResponse
    {
        $state = Cache::get('excel_import_progress:' . $id);
        if (!$state) {
            return response()->json(['success' => false, 'message' => 'Progreso no encontrado'], 404);
        }

        $pct = !empty($state['total_rows']) && $state['total_rows'] > 0
            ? round(100 * (($state['processed_rows'] ?? 0) / $state['total_rows']), 1)
            : null;

        $errors = array_map(fn($e) => [
            'fila' => $e['fila'] ?? 'N/A',
            'error' => substr($e['error'] ?? 'Error desconocido', 0, 150)
        ], $state['errors'] ?? []);

        return response()->json([
            'success' => true,
            'data' => $state,
            'percent' => $pct,
            'errors' => $errors,
            'has_errors' => !empty($errors)
        ]);
    }

    /** Búsqueda con filtros */
    public function buscar(Request $request): JsonResponse
    {
        $q = ReqModelosCodificados::query();

        $filters = [
            'tamano_clave' => ['TamanoClave', 'like'],
            'orden_tejido' => ['OrdenTejido', 'like'],
            'nombre' => ['Nombre', 'like'],
            'salon_tejido' => ['SalonTejidoId', '='],
            'no_telar' => ['NoTelarId', '='],
            'fecha_desde' => ['FechaTejido', '>='],
            'fecha_hasta' => ['FechaTejido', '<='],
        ];

        foreach ($filters as $param => [$field, $op]) {
            if ($v = $request->get($param)) {
                $q->where($field, $op, $op === 'like' ? "%$v%" : $v);
            }
        }

        try {
            $data = $q->orderByDesc('Id')->get();
            return response()->json([
                'success' => true,
                'data' => $data,
                'total' => $data->count(),
                'mensaje' => $data->isEmpty() ? 'Sin resultados' : null
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /** Estadísticas */
    public function estadisticas(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'total_registros' => ReqModelosCodificados::count(),
                'por_salon' => ReqModelosCodificados::selectRaw('SalonTejidoId, count(*) as total')->groupBy('SalonTejidoId')->get(),
                'por_prioridad' => ReqModelosCodificados::selectRaw('Prioridad, count(*) as total')->groupBy('Prioridad')->get(),
            ]
        ]);
    }
}
