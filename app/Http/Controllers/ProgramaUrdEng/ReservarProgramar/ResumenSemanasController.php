<?php

declare(strict_types=1);

namespace App\Http\Controllers\ProgramaUrdEng\ReservarProgramar;

use App\Http\Controllers\Controller;
use App\Services\ProgramaUrdEng\ResumenSemanasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ResumenSemanasController extends Controller
{
    public function __construct(
        private ResumenSemanasService $service
    ) {}

    public function getResumenSemanas(Request $request): JsonResponse
    {
        try {
            $raw = $request->input('telares') ?? $request->query('telares');
            $telares = $this->parseTelares($raw);
            $usarFallbackMetros = (bool) $request->boolean('fallback_metros', true);

            $resultado = $this->service->generar($telares, $usarFallbackMetros);

            $statusCode = ($resultado['success'] ?? true) ? 200 : 400;

            return response()->json($resultado, $statusCode);
        } catch (\Throwable $e) {
            Log::error('getResumenSemanas', ['msg' => $e->getMessage()]);
            $semanas = $this->service->construirSemanas(5);
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener resumen de semanas: ' . $e->getMessage(),
                'data'    => ['rizo' => [], 'pie' => []],
                'semanas' => $semanas,
            ], 500);
        }
    }

    private function parseTelares($raw): array
    {
        if (!$raw) return [];

        if (is_string($raw)) {
            $decoded = json_decode(urldecode($raw), true);
            return is_array($decoded) ? $decoded : [];
        }

        return is_array($raw) ? $raw : [];
    }
}
