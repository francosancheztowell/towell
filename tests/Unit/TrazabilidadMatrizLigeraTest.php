<?php

namespace Tests\Unit;

use App\Http\Controllers\Trazabilidad\TrazabilidadDetailController;
use ReflectionMethod;
use Tests\TestCase;

/**
 * La matriz de detalle pinta solo las columnas de mes; las de semana/día se
 * construyen en el navegador con los datos compactos de `meta`.
 */
class TrazabilidadMatrizLigeraTest extends TestCase
{
    /** @return array<string, mixed> */
    private function datos(): array
    {
        $periodo = static fn (string $nivel, string $clave, ?string $semana, array $indices, bool $destacada = false): array => [
            'nivel' => $nivel,
            'clave' => $clave,
            'mesClave' => '2026-07',
            'semanaClave' => $semana,
            'label' => 'L-'.$clave,
            'subLabel' => 'S-'.$clave,
            'indices' => $indices,
            'destacada' => $destacada,
        ];

        return [
            'columnasPeriodos' => [
                $periodo('mes', '2026-07', null, [0, 1, 2]),
                $periodo('semana', '2026-07-w2026-27', '2026-07-w2026-27', [0, 1, 2]),
                $periodo('dia', '2026-07-05', '2026-07-w2026-27', [0], true),
                $periodo('dia', '2026-07-04', '2026-07-w2026-27', [1], true),
                $periodo('dia', '2026-07-03', '2026-07-w2026-27', [2]),
            ],
            'hayFlog' => false,
            'filtros' => ['flog' => ''],
            'info' => null,
            'areas' => [[
                'nombre' => 'Crudo',
                'label' => 'Crudo',
                'dot' => '#94a3b8',
                'text' => '#475569',
                'tint' => '#eef2f7',
                'valores' => [1500, null, 300],
                'bgs' => ['rgba(148,163,184,0.6)', null, 'rgba(148,163,184,0.2)'],
                'detalles' => [],
            ]],
            'totales' => [1500, null, 300],
            'decimales' => 0,
        ];
    }

    public function test_html_only_carries_month_columns(): void
    {
        $html = view('modulos.trazabilidad.resumen._matriz_detalle', $this->datos())->render();

        $this->assertStringContainsString('data-periodo-toggle="mes"', $html);
        $this->assertStringContainsString('1,800', $html);
        $this->assertStringContainsString('data-area-index="0"', $html);
        $this->assertStringNotContainsString('data-periodo-nivel="semana"', $html);
        $this->assertStringNotContainsString('data-periodo-nivel="dia"', $html);
        $this->assertStringNotContainsString('L-2026-07-05', $html);
        $this->assertStringContainsString('data-expandir-periodos', $html);
    }

    public function test_meta_carries_sparse_values_heatmap_and_column_labels(): void
    {
        $method = new ReflectionMethod(TrazabilidadDetailController::class, 'matrixMeta');
        $meta = json_decode(json_encode($method->invoke(null, $this->datos())), true);

        $this->assertSame(['0' => 1500, '2' => 300], $meta['filas'][0]['valores']);
        $this->assertSame(['0' => 1500, '2' => 300], $meta['totales']);
        $this->assertSame('148,163,184', $meta['filas'][0]['rgb']);
        $this->assertEquals(['0' => 0.6, '2' => 0.2], $meta['filas'][0]['alfas']);
        $this->assertSame('#eef2f7', $meta['filas'][0]['tint']);
        $this->assertSame('#475569', $meta['filas'][0]['text']);
        $this->assertCount(5, $meta['columnas']);
        $this->assertSame('L-2026-07-05', $meta['columnas'][2]['label']);
        $this->assertSame('S-2026-07-05', $meta['columnas'][2]['subLabel']);
        $this->assertTrue($meta['columnas'][2]['destacada']);
        $this->assertSame('2026-07-w2026-27', $meta['columnas'][1]['semanaClave']);
    }

    public function test_script_builds_week_and_day_columns_lazily(): void
    {
        $script = file_get_contents(resource_path('js/trazabilidad/matrix-detail.ts'));

        $this->assertStringContainsString('ensureMonthColumns', $script);
        $this->assertStringContainsString('meta.filas', $script);
        $this->assertStringContainsString('meta.totales', $script);
        $this->assertStringNotContainsString('innerHTML', $script);
    }
}
