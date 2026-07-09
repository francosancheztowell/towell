<?php

namespace Tests\Unit;

use App\Services\Trazabilidad\TrazabilidadFlogsService;
use ReflectionMethod;
use Tests\TestCase;

class TrazabilidadFlogsServiceTest extends TestCase
{
    public function test_line_mapping_includes_invoiced_and_pending_delivery_quantities(): void
    {
        $row = (object) [
            'FACTURADO' => 125.5,
            'PORENTREGAR' => 74.25,
        ];

        $method = new ReflectionMethod(TrazabilidadFlogsService::class, 'mapearLineaFlog');
        $line = $method->invoke(app(TrazabilidadFlogsService::class), $row);

        $this->assertSame('125', $line['facturado']);
        $this->assertSame('74', $line['porEntregar']);
    }

    public function test_lines_table_declares_invoiced_and_pending_delivery_columns(): void
    {
        $view = file_get_contents(resource_path('views/modulos/trazabilidad/_flogs.blade.php'));

        $this->assertStringContainsString(
            "['key' => 'facturado', 'label' => 'Facturado', 'tipo' => 'entero']",
            $view
        );
        $this->assertStringContainsString(
            "['key' => 'porEntregar', 'label' => 'Por entregar', 'tipo' => 'entero']",
            $view
        );
    }
}
