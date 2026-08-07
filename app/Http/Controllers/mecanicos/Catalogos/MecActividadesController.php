<?php

namespace App\Http\Controllers\mecanicos\Catalogos;

use App\Http\Controllers\Controller;
use App\Models\Mecanicos\MecActividadesModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MecActividadesController extends Controller
{
    private const MODULO = 'Actividades Mecanicos';

    public function index(): View
    {
        $actividades = MecActividadesModel::query()
            ->orderBy('Orden')
            ->orderBy('Id')
            ->get(['Id', 'Orden', 'Actividad']);

        return view('modulos.mecanicos.catalogos.actividades.index', [
            'actividades' => $actividades,
            'moduloPermiso' => self::MODULO,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (function_exists('userCan') && ! userCan('crear', self::MODULO)) {
            return response()->json(['ok' => false, 'message' => 'No tienes permiso para crear.'], 403);
        }

        $validated = $request->validate([
            'Orden' => ['required', 'integer', 'min:1'],
            'Actividad' => ['required', 'string', 'max:100'],
        ]);

        $actividad = MecActividadesModel::create($validated);

        return response()->json([
            'ok' => true,
            'message' => 'Actividad creada correctamente.',
            'item' => [
                'Id' => $actividad->Id,
                'Orden' => $actividad->Orden,
                'Actividad' => $actividad->Actividad,
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $actividad = MecActividadesModel::query()->findOrFail($id, ['Id', 'Orden', 'Actividad']);

        return response()->json([
            'ok' => true,
            'data' => $actividad,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if (function_exists('userCan') && ! userCan('modificar', self::MODULO)) {
            return response()->json(['ok' => false, 'message' => 'No tienes permiso para modificar.'], 403);
        }

        $actividad = MecActividadesModel::query()->findOrFail($id);

        $validated = $request->validate([
            'Orden' => ['required', 'integer', 'min:1'],
            'Actividad' => ['required', 'string', 'max:100'],
        ]);

        $actividad->update($validated);

        return response()->json([
            'ok' => true,
            'message' => 'Actividad actualizada correctamente.',
            'item' => [
                'Id' => $actividad->Id,
                'Orden' => $actividad->Orden,
                'Actividad' => $actividad->Actividad,
            ],
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        if (function_exists('userCan') && ! userCan('eliminar', self::MODULO)) {
            return response()->json(['ok' => false, 'message' => 'No tienes permiso para eliminar.'], 403);
        }

        $actividad = MecActividadesModel::query()->findOrFail($id);
        $actividad->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Actividad eliminada correctamente.',
        ]);
    }
}
