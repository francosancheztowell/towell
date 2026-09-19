<?php

declare(strict_types=1);

namespace App\Http\Controllers\ProgramaUrdEng\ReservarProgramar;

use App\Http\Controllers\Controller;
use App\Services\ProgramaUrdEng\InventarioTelaresService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Devuelve el inventario de telares activos para la vista Reservar y Programar.
 */
class InventarioTelaresController extends Controller
{
    public function __construct(
        private InventarioTelaresService $service
    ) {}

    public function getInventarioTelares(Request $request): JsonResponse
    {
        try {
            $q = $this->service->baseQuery();

            // El front manda ?filtros=[{"columna":"no_telar","valor":"401"},...] desde que
            // existe la pantalla; hasta ahora se ignoraban aqui y se filtraba en el navegador.
            $filtros = json_decode((string) $request->query('filtros', '[]'), true);

            if (is_array($filtros) && $filtros !== []) {
                $this->service->applyFiltros($q, $filtros);
            }
            $rows = $q->orderBy('no_telar')->orderBy('fecha')->get();

            return response()->json([
                'success' => true,
                'data' => $this->service->normalizeTelares($rows)->values(),
                'total' => $rows->count(),
            ]);
        } catch (\Throwable $e) {
            Log::error('getInventarioTelares', ['msg' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Error al obtener inventario de telares'], 500);
        }
    }
}
