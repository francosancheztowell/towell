<?php

namespace Tests\Unit;

use App\Services\Trazabilidad\TrazabilidadMatrixService;
use Illuminate\Support\Collection;
use ReflectionMethod;
use Tests\TestCase;

class TrazabilidadMatrixLayoutTest extends TestCase
{
    public function test_total_column_is_rendered_immediately_after_area(): void
    {
        $view = file_get_contents(resource_path('views/modulos/trazabilidad/_resultado.blade.php'));

        $header = $this->between($view, '<thead>', '</thead>');
        $bodyArea = $this->between($view, '{{-- Columna de área (sticky) --}}', '{{-- Sub-filas:');
        $detail = $this->between($view, '{{-- Artículo / color (sticky) --}}', '@endforeach'."\n".'                            @endif');
        $footer = $this->between($view, '<tfoot>', '</tfoot>');

        $this->assertLessThan(
            strpos($header, '@foreach ($fechas as $fecha)'),
            strpos($header, '>Total<')
        );
        $this->assertLessThan(
            strpos($bodyArea, '{{-- Celdas de valores --}}'),
            strpos($bodyArea, '{{-- Total por área --}}')
        );
        $this->assertLessThan(
            strpos($detail, '{{-- Valores por fecha del artículo/color --}}'),
            strpos($detail, '{{-- Total de la sub-fila --}}')
        );
        $this->assertLessThan(
            strpos($footer, '@foreach ($fechas as $i => $fecha)'),
            strpos($footer, '$granTotal')
        );
    }

    public function test_total_column_is_sticky_after_the_fixed_area_column(): void
    {
        $view = file_get_contents(resource_path('views/modulos/trazabilidad/_resultado.blade.php'));

        $this->assertSame(4, substr_count($view, 'sticky left-[350px]'));
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

    private function between(string $content, string $start, string $end): string
    {
        $startAt = strpos($content, $start);
        $endAt = strpos($content, $end, $startAt);

        $this->assertNotFalse($startAt);
        $this->assertNotFalse($endAt);

        return substr($content, $startAt, $endAt - $startAt);
    }
}
