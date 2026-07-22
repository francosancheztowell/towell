<?php

namespace Tests\Unit;

use App\Services\Trazabilidad\TrazabilidadMatrixService;
use Illuminate\Support\Collection;
use ReflectionMethod;
use Tests\TestCase;

class TrazabilidadMatrixLayoutTest extends TestCase
{
    public function test_matrix_sections_are_hidden_from_the_current_summary_screen(): void
    {
        $view = file_get_contents(resource_path('views/modulos/trazabilidad/_resultado.blade.php'));

        $this->assertStringNotContainsString('<table', $view);
        $this->assertStringNotContainsString('Producción por día y área', $view);
        $this->assertStringNotContainsString('data-pane=', $view);
    }

    public function test_first_summary_card_uses_half_of_the_desktop_grid(): void
    {
        $view = file_get_contents(resource_path('views/modulos/trazabilidad/_resultado.blade.php'));
        $flog = file_get_contents(resource_path('views/modulos/trazabilidad/resumen/_flog.blade.php'));

        $this->assertStringContainsString('lg:grid-cols-2', $view);
        $this->assertStringContainsString('min-h-[290px]', $flog);
        $this->assertStringContainsString('<h3 class="font-bold text-slate-800">Flog</h3>', $flog);
    }

    public function test_date_keys_are_ordered_from_most_recent_to_oldest(): void
    {
        $method = new ReflectionMethod(TrazabilidadMatrixService::class, 'ordenarClavesFechas');
        /** @var Collection<int, string> $dates */
        $dates = $method->invoke(
            app(TrazabilidadMatrixService::class),
            collect(['2026-01-05', '2026-03-20', '2026-02-14', '2026-03-20'])
        );

        $this->assertSame(
            ['2026-03-20', '2026-02-14', '2026-01-05'],
            $dates->all()
        );
    }

    public function test_dates_are_grouped_by_month_week_and_day_without_losing_their_indices(): void
    {
        $method = new ReflectionMethod(TrazabilidadMatrixService::class, 'construirColumnasPeriodos');
        /** @var array<int, array<string, mixed>> $columns */
        $columns = $method->invoke(
            app(TrazabilidadMatrixService::class),
            collect(['2026-07-02', '2026-07-01', '2026-06-30', '2026-06-29'])
        );

        $this->assertSame(
            ['mes', 'semana', 'dia', 'dia', 'mes', 'semana', 'dia', 'dia'],
            array_column($columns, 'nivel')
        );
        $this->assertSame('2026-07', $columns[0]['clave']);
        $this->assertSame([0, 1], $columns[0]['indices']);
        $this->assertSame('Semana 27', $columns[1]['label']);
        $this->assertSame([0, 1], $columns[1]['indices']);
        $this->assertSame('2026-07-02', $columns[2]['clave']);
        $this->assertSame([2, 3], $columns[4]['indices']);
        $this->assertNotSame($columns[1]['semanaClave'], $columns[5]['semanaClave']);
    }

    public function test_matrix_detail_exposes_month_week_controls_and_expand_all_button(): void
    {
        $base = [
            'mesClave' => '2026-07',
            'indices' => [0],
            'destacada' => false,
        ];
        $view = view('modulos.trazabilidad.resumen._matriz_detalle', [
            'columnasPeriodos' => [
                array_merge($base, [
                    'nivel' => 'mes', 'clave' => '2026-07', 'semanaClave' => null,
                    'label' => 'Julio 2026', 'subLabel' => '1 día',
                ]),
                array_merge($base, [
                    'nivel' => 'semana', 'clave' => '2026-07-w2026-27', 'semanaClave' => '2026-07-w2026-27',
                    'label' => 'Semana 27', 'subLabel' => '29 jun–05 jul',
                ]),
            ],
            'hayFlog' => false,
            'filtros' => ['flog' => ''],
            'info' => null,
            'areas' => [],
            'totales' => [],
            'decimales' => 0,
        ])->render();
        $script = file_get_contents(resource_path('js/trazabilidad/index.js'));
        $styles = file_get_contents(resource_path('css/trazabilidad/index.css'));
        preg_match('/#resultado \.traza-matriz-periodos \{([^}]*)\}/', $styles, $tableRule);

        $this->assertStringContainsString('data-expandir-periodos', $view);
        $this->assertStringContainsString('data-periodo-toggle="mes"', $view);
        $this->assertStringContainsString('data-periodo-toggle="semana"', $view);
        $this->assertStringContainsString("'[data-expandir-periodos]'", $script);
        $this->assertStringContainsString("'[data-periodo-toggle]'", $script);
        $this->assertStringContainsString('marcarSubtotalPeriodo', $script);
        $this->assertStringContainsString('Subtotal mes', $view);
        $this->assertStringContainsString('Subtotal semana', $view);
        $this->assertStringContainsString('.traza-periodo-subtotal-abierto', $styles);
        $this->assertArrayHasKey(1, $tableRule);
        $this->assertStringNotContainsString('min-width: 100%;', $tableRule[1]);
    }
}
