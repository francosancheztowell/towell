<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Models\Ventas\TwHistPronosModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Solo lectura: dbo.TwHistoricosPronostico (Plan).
 */
final class TwHistPronosController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless(
            function_exists('userCan') && userCan('acceso', (string) config('ventas.permission_module')),
            403,
            'No tienes acceso al módulo de Ventas.'
        );

        $validated = $request->validate([
            'anio' => ['nullable', 'integer'],
        ]);

        $anio = (int) ($validated['anio'] ?? now()->year);

        $registros = TwHistPronosModel::query()
            ->where('ANIO', $anio)
            ->get();

        return response()->json([
            'anio' => $anio,
            'total' => $registros->count(),
            'data' => $registros,
        ]);
    }
}
