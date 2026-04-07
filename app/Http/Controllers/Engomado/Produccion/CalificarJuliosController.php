<?php

namespace App\Http\Controllers\Engomado\Produccion;

use App\Http\Controllers\Controller;
use App\Models\Engomado\CatDefectosUrdEng;
use App\Models\Engomado\EngProduccionEngomado;
use App\Models\Urdido\UrdProduccionUrdido;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CalificarJuliosController extends Controller
{
    private function ensureCanEdit(): void
    {
        if (! function_exists('userCan') || ! userCan('modificar', 'Producción Engomado')) {
            abort(403, 'Sin permisos para calificar julios');
        }
    }

    public function getJulios(Request $request): JsonResponse
    {
        try {
            $request->validate(['folio' => 'required|string|max:50']);
            $folio = $request->input('folio');

            $julios = UrdProduccionUrdido::where('Folio', $folio)
                ->orderByRaw('CASE WHEN ISNUMERIC(NoJulio) = 1 THEN CAST(NoJulio AS INT) ELSE 99999 END ASC')
                ->orderBy('NoJulio', 'asc')
                ->get([
                    'Id', 'Folio', 'NoJulio',
                    'ClaveDefecto', 'Penalizacion',
                    'OperadorDefecto', 'NoEmplDefecto', 'FechaDefecto',
                ]);

            $defectos = CatDefectosUrdEng::where('Activo', 1)
                ->orderBy('Penalizacion')
                ->orderBy('Clave')
                ->get(['Id', 'Clave', 'Penalizacion', 'Defecto']);

            return response()->json([
                'success' => true,
                'julios' => $julios,
                'defectos' => $defectos,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function calificar(Request $request): JsonResponse
    {
        $this->ensureCanEdit();
        try {
            $request->validate([
                'julio_id' => 'required|integer',
                'defecto_id' => 'nullable|integer',
            ]);

            $julio = UrdProduccionUrdido::find($request->julio_id);
            if (! $julio) {
                return response()->json(['success' => false, 'error' => 'Julio no encontrado'], 404);
            }

            if ($request->defecto_id === null || $request->defecto_id === '') {
                $julio->ClaveDefecto = null;
                $julio->Penalizacion = null;
                $julio->OperadorDefecto = null;
                $julio->NoEmplDefecto = null;
                $julio->FechaDefecto = null;
            } else {
                $defecto = CatDefectosUrdEng::find($request->defecto_id);
                if (! $defecto) {
                    return response()->json(['success' => false, 'error' => 'Defecto no encontrado'], 404);
                }

                $user = Auth::user();
                $julio->ClaveDefecto = $defecto->Id;
                $julio->Penalizacion = $defecto->Penalizacion;
                $julio->OperadorDefecto = $user ? ($user->nombre ?? null) : null;
                $julio->NoEmplDefecto = $user ? ($user->numero_empleado ?? null) : null;
                $julio->FechaDefecto = now();
            }

            $julio->save();
            $julio->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Julio calificado correctamente',
                'data' => $julio,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'error' => 'Validación', 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function getJuliosEng(Request $request): JsonResponse
    {
        try {
            $request->validate(['folio' => 'required|string|max:50']);
            $folio = $request->input('folio');

            $julios = EngProduccionEngomado::where('Folio', $folio)
                ->orderBy('Id')
                ->get([
                    'Id', 'Folio', 'NoJulio',
                    'ClaveDefecto', 'Penalizacion',
                    'OperadorDefecto', 'NoEmplDefecto', 'FechaDefecto',
                ]);

            $defectos = CatDefectosUrdEng::where('Activo', 1)
                ->orderBy('Penalizacion')
                ->orderBy('Clave')
                ->get(['Id', 'Clave', 'Penalizacion', 'Defecto']);

            return response()->json([
                'success' => true,
                'julios' => $julios,
                'defectos' => $defectos,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function calificarEng(Request $request): JsonResponse
    {
        $this->ensureCanEdit();
        try {
            $request->validate([
                'julio_id' => 'required|integer',
                'defecto_id' => 'nullable|integer',
            ]);

            $julio = EngProduccionEngomado::find($request->julio_id);
            if (! $julio) {
                return response()->json(['success' => false, 'error' => 'Registro no encontrado'], 404);
            }

            if ($request->defecto_id === null || $request->defecto_id === '') {
                $julio->ClaveDefecto = null;
                $julio->Penalizacion = null;
                $julio->OperadorDefecto = null;
                $julio->NoEmplDefecto = null;
                $julio->FechaDefecto = null;
            } else {
                $defecto = CatDefectosUrdEng::find($request->defecto_id);
                if (! $defecto) {
                    return response()->json(['success' => false, 'error' => 'Defecto no encontrado'], 404);
                }

                $user = Auth::user();
                $julio->ClaveDefecto = $defecto->Id;
                $julio->Penalizacion = $defecto->Penalizacion;
                $julio->OperadorDefecto = $user ? ($user->nombre ?? null) : null;
                $julio->NoEmplDefecto = $user ? ($user->numero_empleado ?? null) : null;
                $julio->FechaDefecto = now();
            }

            $julio->save();
            $julio->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Registro calificado correctamente',
                'data' => $julio,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'error' => 'Validación', 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
