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

        $data = $this->matrix->build($filters->toArray());
        $html = view('modulos.trazabilidad.resumen._matriz_detalle', [
            ...$data,
            'filtros' => $filters->toArray(),
        ])->render();

        return $this->detailResponse('matrix', $html, self::matrixMeta($data), $startedAt);
    }

    /**
     * Datos compactos con los que el navegador arma, al expandir un mes o una
     * semana, las columnas de semana/día (el HTML solo trae las de mes) y las
     * filas de artículo/color. Los valores van dispersos: solo índices con valor.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function matrixMeta(array $data): array
    {
        $disperso = static fn (array $valores): array => array_filter(
            $valores,
            static fn (mixed $valor): bool => $valor !== null,
        );

        return [
            'areas' => count($data['areas']),
            'periodos' => count($data['columnasPeriodos']),
            'decimales' => $data['decimales'],
            'detalles' => array_map(
                static fn (array $area): array => $area['detalles'],
                $data['areas'],
            ),
            'filas' => array_map(
                static fn (array $area): array => [
                    'text' => $area['text'],
                    'tint' => $area['tint'],
                    'valores' => (object) $disperso($area['valores']),
                    ...self::heatmap($disperso($area['bgs'])),
                ],
                $data['areas'],
            ),
            'totales' => (object) $disperso($data['totales']),
            'columnas' => array_map(
                static fn (array $periodo): array => [
                    'nivel' => $periodo['nivel'],
                    'indices' => $periodo['indices'],
                    'mesClave' => $periodo['mesClave'],
                    'semanaClave' => $periodo['semanaClave'],
                    'label' => $periodo['label'],
                    'subLabel' => $periodo['subLabel'],
                    'destacada' => $periodo['destacada'],
                ],
                $data['columnasPeriodos'],
            ),
        ];
    }

    /**
     * El heatmap del servicio llega como "rgba(r,g,b,alfa)" por día; al JSON va
     * el rgb del área una vez y solo el alfa por día (disperso).
     *
     * @param  array<int, string>  $bgs
     * @return array{rgb: string, alfas: object}
     */
    private static function heatmap(array $bgs): array
    {
        $rgb = '';
        $alfas = [];
        foreach ($bgs as $indice => $bg) {
            if (preg_match('/^rgba\((\d+,\d+,\d+),([\d.]+)\)$/', $bg, $partes) === 1) {
                $rgb = $partes[1];
                $alfas[$indice] = (float) $partes[2];
            }
        }

        return ['rgb' => $rgb, 'alfas' => (object) $alfas];
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
