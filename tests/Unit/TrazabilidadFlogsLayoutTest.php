<?php

namespace Tests\Unit;

use Tests\TestCase;

class TrazabilidadFlogsLayoutTest extends TestCase
{
    public function test_special_notice_and_important_information_share_a_row_and_scroll(): void
    {
        $view = file_get_contents(resource_path('views/modulos/trazabilidad/_flogs.blade.php'));

        $this->assertStringContainsString("['Aviso especial', \$general['avisoEspecialTxt']", $view);
        $this->assertStringContainsString("['Información importante', \$general['infoImportante']", $view);
        $this->assertStringContainsString('md:grid-cols-2', $view);
        $this->assertStringContainsString('max-h-20 overflow-y-auto', $view);
    }
}
