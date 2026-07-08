<?php

namespace Tests\Unit;

use Tests\TestCase;

class TrazabilidadFlogsLayoutTest extends TestCase
{
    public function test_important_information_uses_its_own_row_and_both_notes_scroll_after_three_lines(): void
    {
        $view = file_get_contents(resource_path('views/modulos/trazabilidad/_flogs.blade.php'));
        $styles = file_get_contents(resource_path('views/modulos/trazabilidad/index.blade.php'));

        $this->assertStringContainsString(
            'flog-cliente-grid__celda--info flog-cliente-grid__celda--full-row',
            $view
        );
        $this->assertSame(2, substr_count($view, 'flog-cliente-grid__valor--limitado'));
        $this->assertStringContainsString('.flog-cliente-grid__celda--full-row', $styles);
        $this->assertStringContainsString('grid-column: 1 / -1;', $styles);
        $this->assertStringContainsString('.flog-cliente-grid__valor--limitado', $styles);
        $this->assertStringContainsString('overflow-y: auto;', $styles);
        $this->assertStringContainsString('max-height: calc(3 * 1.35em);', $styles);
    }
}
