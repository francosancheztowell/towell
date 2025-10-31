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
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\ReqModelosCodificadosImport;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Reader\Xls as XlsReader;

class CodificacionController extends Controller
{
    /** Vista principal */
    public function index()
    {
        try {
            // Intentar obtener datos sin orderBy primero para verificar si hay datos
            $codificaciones = ReqModelosCodificados::get();

            if ($codificaciones->isEmpty()) {
                return view('catalagos.catalogoCodificacion', [
                    'codificaciones' => collect(),
                    'mensaje' => 'No se encontraron registros de codificación disponibles'
                ]);
            }

            // Si hay datos, intentar ordenar por Id, si falla usar otra columna
            try {
                $codificaciones = ReqModelosCodificados::orderBy('Id', 'desc')->get();
            } catch (\Exception $e) {
                // Si falla el orderBy por Id, usar TamanoClave como alternativa
                $codificaciones = ReqModelosCodificados::orderBy('TamanoClave', 'desc')->get();
            }

            return view('catalagos.catalogoCodificacion', compact('codificaciones'));
        } catch (\Exception $e) {
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
        $codificacion = ReqModelosCodificados::findOrFail($id);
        return view('catalagos.codificacion-form', compact('codificacion'));
    }

    /** API: todos */
    public function getAll(): JsonResponse
    {
        try {
            $codificaciones = ReqModelosCodificados::get();

            if ($codificaciones->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'mensaje' => 'No se encontraron registros de codificación disponibles'
                ]);
            }

            // Intentar ordenar por Id, si falla usar TamanoClave
            try {
                $codificaciones = ReqModelosCodificados::orderBy('Id', 'desc')->get();
            } catch (\Exception $e) {
                $codificaciones = ReqModelosCodificados::orderBy('TamanoClave', 'desc')->get();
            }

            return response()->json(['success' => true, 'data' => $codificaciones]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error al cargar los datos: ' . $e->getMessage()
            ]);
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
            // Intentar ordenar por Id, si falla usar TamanoClave
            try {
                $data = $q->orderBy('Id','desc')->get();
            } catch (\Exception $e) {
                $data = $q->orderBy('TamanoClave','desc')->get();
            }

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
