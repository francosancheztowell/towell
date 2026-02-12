<?php

declare(strict_types=1);

namespace App\Http\Controllers\ProgramaUrdEng\ReservarProgramar;

use App\Http\Controllers\Controller;
use App\Services\ProgramaUrdEng\InventarioTelaresService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Devuelve el inventario de telares activos para la vista Reservar y Programar.
 */
class InventarioTelaresController extends Controller
{
    public function __construct(
        private InventarioTelaresService $service
    ) {}

    public function getInventarioTelares(): JsonResponse
    {
        try {
            $q = $this->service->baseQuery();
            $rows = $q->orderBy('no_telar')->orderBy('fecha')->get();

            return response()->json([
                'success' => true,
                'data'    => $this->service->normalizeTelares($rows)->values(),
                'total'   => $rows->count(),
            ]);
        } catch (\Throwable $e) {
            Log::error('getInventarioTelares', ['msg' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Error al obtener inventario de telares'], 500);
        }
    }
}
