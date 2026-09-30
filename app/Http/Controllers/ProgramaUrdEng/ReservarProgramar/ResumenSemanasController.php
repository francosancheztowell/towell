<?php

declare(strict_types=1);

namespace App\Http\Controllers\ProgramaUrdEng\ReservarProgramar;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ProgramaUrdEng\Concerns\RespuestasErrorUrdEng;
use App\Services\ProgramaUrdEng\ResumenSemanasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResumenSemanasController extends Controller
{
    use RespuestasErrorUrdEng;

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
            return $this->errorServidor($e, 'ProgramaUrdEng.getResumenSemanas', 'Error al obtener resumen de semanas', [
                'data' => ['rizo' => [], 'pie' => []],
                'semanas' => $this->service->construirSemanas(5),
            ]);
        }
    }

    private function parseTelares($raw): array
    {
        if (! $raw) {
            return [];
        }

        if (is_string($raw)) {
            $decoded = json_decode(urldecode($raw), true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($raw) ? $raw : [];
    }
}
