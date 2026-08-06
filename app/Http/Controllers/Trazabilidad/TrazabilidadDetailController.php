<?php

declare(strict_types=1);

namespace App\Http\Controllers\Trazabilidad;

use App\Http\Controllers\Controller;
use App\Http\Requests\Trazabilidad\TrazabilidadDetailRequest;
use App\Services\Trazabilidad\TrazabilidadFlogsService;
use App\Services\Trazabilidad\TrazabilidadMatrixService;
use App\Services\Trazabilidad\TrazabilidadProduccionService;
use Illuminate\Http\JsonResponse;

final class TrazabilidadDetailController extends Controller
{
    public function __construct(
        private readonly TrazabilidadMatrixService $matrix,
        private readonly TrazabilidadProduccionService $production,
        private readonly TrazabilidadFlogsService $flogs,
    ) {}

    public function matrix(TrazabilidadDetailRequest $request): JsonResponse
    {
        $startedAt = hrtime(true);
        $filters = $request->filters();
        abort_unless($filters->hasAny(), 422, 'Selecciona al menos un filtro.');

        $data = $this->matrix->build($filters->toArray(), $filters->metrica);
        $html = view('modulos.trazabilidad.resumen._matriz_detalle', [
            ...$data,
            'filtros' => $filters->toArray(),
        ])->render();

        return $this->detailResponse('matrix', $html, [
            'areas' => count($data['areas']),
            'periodos' => count($data['columnasPeriodos']),
            'decimales' => $data['decimales'],
            'detalles' => array_map(
                static fn (array $area): array => $area['detalles'],
                $data['areas'],
            ),
            'columnas' => array_map(
                static fn (array $periodo): array => [
                    'nivel' => $periodo['nivel'],
                    'indices' => $periodo['indices'],
                    'mesClave' => $periodo['mesClave'],
                    'semanaClave' => $periodo['semanaClave'],
                ],
                $data['columnasPeriodos'],
            ),
        ], $startedAt);
    }

    public function production(TrazabilidadDetailRequest $request): JsonResponse
    {
        $startedAt = hrtime(true);
        $filters = $request->filters();
        abort_unless($filters->hasAny(), 422, 'Selecciona al menos un filtro.');

        $data = $this->production->build($filters->toArray());
        $html = view('modulos.trazabilidad._produccion', [
            'produccion' => $data,
            'filtros' => $filters->toArray(),
        ])->render();

        return $this->detailResponse('production', $html, [
            'ordenesCrudo' => count($data['crudo']['ordenes']),
            'maquinasTenido' => count($data['rollosTenido']['maquinas']),
        ], $startedAt);
    }

    public function flog(TrazabilidadDetailRequest $request): JsonResponse
    {
        $startedAt = hrtime(true);
        $filters = $request->filters();
        $data = $this->flogs->build($filters->flog);
        $html = view('modulos.trazabilidad._flogs', [
            'flogs' => $data,
            'filtros' => $filters->toArray(),
        ])->render();

        return $this->detailResponse('flog', $html, [
            'estado' => $data['estado'],
            'lineas' => count($data['lineas']),
        ], $startedAt);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function detailResponse(
        string $type,
        string $html,
        array $meta,
        int $startedAt,
    ): JsonResponse {
        $durationMs = round((hrtime(true) - $startedAt) / 1_000_000, 1);

        return response()
            ->json([
                'html' => $html,
                'meta' => [
                    'type' => $type,
                    'durationMs' => $durationMs,
                    ...$meta,
                ],
            ])
            ->header('Cache-Control', 'private, no-store')
            ->header('Server-Timing', "app;dur={$durationMs}");
    }
}
