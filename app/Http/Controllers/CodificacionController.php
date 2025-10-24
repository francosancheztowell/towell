<?php

namespace App\Http\Controllers;

use App\Models\ReqModelosCodificados;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\ReqModelosCodificadosImport;

class CodificacionController extends Controller
{
    /** Vista principal */
    public function index()
    {
        $codificaciones = ReqModelosCodificados::orderBy('Id', 'desc')->get();
        return view('catalagos.catalogoCodificacion', compact('codificaciones'));
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
        $codificaciones = ReqModelosCodificados::orderBy('Id', 'desc')->get();
        return response()->json(['success' => true, 'data' => $codificaciones]);
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
        $request->validate([
            'archivo_excel' => 'required|file|mimes:xlsx,xls|max:10240'
        ]);

        $import = new ReqModelosCodificadosImport();
        Excel::import($import, $request->file('archivo_excel'));

        return response()->json([
            'success' => true,
            'message' => 'Archivo procesado',
            'data' => [
                'registros_procesados'  => $import->getRowCount(),
                'registros_creados'     => $import->getCreatedCount(),
                'registros_actualizados'=> $import->getUpdatedCount(),
                'errores'               => $import->getErrors(),
            ]
        ]);
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

        $data = $q->orderBy('Id','desc')->get();
        return response()->json(['success'=>true,'data'=>$data,'total'=>$data->count()]);
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
