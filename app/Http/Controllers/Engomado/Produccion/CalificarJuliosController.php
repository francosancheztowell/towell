<?php

namespace App\Http\Controllers\Engomado\Produccion;

use App\Http\Controllers\Controller;
use App\Models\Engomado\CatDefectosUrdEng;
use App\Models\Engomado\EngProduccionEngomado;
use App\Models\Urdido\UrdProduccionUrdido;
use App\Support\Http\Concerns\HandlesApiErrors;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Calificar julios con un defecto del catálogo CatDefectosUrdEng.
 * Dos variantes con el mismo contrato: julios de Urdido (desde Producción Engomado) y
 * registros de Engomado (desde Edición Engomado). Vista: modulos/urdido/comun/calificar-julios.
 */
class CalificarJuliosController extends Controller
{
    use HandlesApiErrors;

    private const COLUMNAS = [
        'Id', 'Folio', 'NoJulio',
        'Fecha',
        'Metros1', 'Metros2', 'Metros3',
        'NomEmpl1', 'NomEmpl2', 'NomEmpl3',
        'ClaveDefecto', 'Penalizacion',
        'OperadorDefecto', 'NoEmplDefecto', 'FechaDefecto',
    ];

    private function ensureCanEdit(): void
    {
        if (! function_exists('userCan') || ! userCan('modificar', 'Producción Engomado')) {
            abort(403, 'Sin permisos para calificar julios');
        }
    }

    public function getJulios(Request $request): JsonResponse
    {
        return $this->listar($request, UrdProduccionUrdido::query()
            ->orderByRaw('CASE WHEN ISNUMERIC(NoJulio) = 1 THEN CAST(NoJulio AS INT) ELSE 99999 END ASC')
            ->orderBy('NoJulio', 'asc'));
    }

    public function getJuliosEng(Request $request): JsonResponse
    {
        return $this->listar($request, EngProduccionEngomado::query()->orderBy('Id'));
    }

    public function calificar(Request $request): JsonResponse
    {
        return $this->guardar($request, UrdProduccionUrdido::class, 'Julio no encontrado', 'Julio calificado correctamente');
    }

    public function calificarEng(Request $request): JsonResponse
    {
        return $this->guardar($request, EngProduccionEngomado::class, 'Registro no encontrado', 'Registro calificado correctamente');
    }

    /** @param  Builder<UrdProduccionUrdido>|Builder<EngProduccionEngomado>  $consulta  consulta ordenada del modelo de la variante */
    private function listar(Request $request, Builder $consulta): JsonResponse
    {
        $request->validate(['folio' => 'required|string|max:50']);

        try {
            $julios = $consulta->where('Folio', $request->input('folio'))->get(self::COLUMNAS);

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
            return $this->apiErrorResponse($e, 'CalificarJulios - error al listar', 'Error al cargar los julios');
        }
    }

    /** @param  class-string<UrdProduccionUrdido|EngProduccionEngomado>  $modelo */
    private function guardar(Request $request, string $modelo, string $noEncontrado, string $exito): JsonResponse
    {
        $this->ensureCanEdit();
        $request->validate([
            'julio_id' => 'required|integer',
            'defecto_id' => 'nullable|integer',
        ]);

        try {
            $julio = $modelo::query()->whereKey((int) $request->julio_id)->first();
            if (! $julio) {
                return $this->apiClientErrorResponse($noEncontrado, 404);
            }

            if ($request->defecto_id === null || $request->defecto_id === '') {
                $julio->forceFill([
                    'ClaveDefecto' => null,
                    'Penalizacion' => null,
                    'OperadorDefecto' => null,
                    'NoEmplDefecto' => null,
                    'FechaDefecto' => null,
                ]);
            } else {
                $defecto = CatDefectosUrdEng::find($request->defecto_id);
                if (! $defecto) {
                    return $this->apiClientErrorResponse('Defecto no encontrado', 404);
                }

                $user = Auth::user();
                $julio->forceFill([
                    'ClaveDefecto' => $defecto->Id,
                    'Penalizacion' => $defecto->Penalizacion,
                    'OperadorDefecto' => $user ? ($user->nombre ?? null) : null,
                    'NoEmplDefecto' => $user ? ($user->numero_empleado ?? null) : null,
                    'FechaDefecto' => now(),
                ]);
            }

            $julio->save();
            $julio->refresh();

            return response()->json([
                'success' => true,
                'message' => $exito,
                'data' => $julio,
            ]);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'CalificarJulios - error al guardar', 'Error al guardar la calificación');
        }
    }
}
