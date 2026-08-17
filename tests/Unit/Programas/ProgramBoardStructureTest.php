<?php

declare(strict_types=1);

namespace Tests\Unit\Programas;

use App\Http\Controllers\Engomado\ProgramaEngomado\ProgramarEngomadoController;
use App\Http\Controllers\Urdido\ProgramaUrdido\ProgramarUrdidoController;
use Tests\TestCase;

class ProgramBoardStructureTest extends TestCase
{
    public function test_module_shells_are_thin_and_keep_the_legacy_views(): void
    {
        foreach ([
            'urdido/programar-urdido-livewire.blade.php' => 'module="urdido"',
            'engomado/programar-engomado-livewire.blade.php' => 'module="engomado"',
        ] as $relativePath => $componentParameter) {
            $path = resource_path("views/modulos/{$relativePath}");
            $view = file_get_contents($path);

            $this->assertLessThan(40, count(file($path)));
            $this->assertStringContainsString('<livewire:urd-eng.program-board', $view);
            $this->assertStringContainsString($componentParameter, $view);
            $this->assertStringContainsString("@vite('resources/css/urd-eng/program-board.css')", $view);
            $this->assertStringContainsString("@vite('resources/js/urd-eng/program-board.ts')", $view);
            $this->assertStringNotContainsString('<script>', $view);
        }

        $this->assertFileExists(resource_path('views/modulos/urdido/programar-urdido.blade.php'));
        $this->assertFileExists(resource_path('views/modulos/engomado/programar-engomado.blade.php'));
    }

    public function test_livewire_view_has_no_inline_script_and_typescript_is_modular(): void
    {
        $view = file_get_contents(resource_path('views/livewire/urd-eng/program-board.blade.php'));
        $entryPath = resource_path('js/urd-eng/program-board.ts');
        $entry = file_get_contents($entryPath);

        $this->assertStringNotContainsString('<script>', $view);
        $this->assertStringNotContainsString('@script', $view);
        $this->assertLessThan(60, count(file($entryPath)));
        $this->assertStringContainsString("from './sortable-board'", $entry);
        $this->assertStringContainsString("from './fullscreen'", $entry);
        $this->assertStringContainsString("from './feedback'", $entry);
        $this->assertFileDoesNotExist(resource_path('js/urd-eng/index.js'));
    }

    public function test_current_program_routes_render_the_livewire_board(): void
    {
        $this->assertSame(
            'modulos.urdido.programar-urdido-livewire',
            $this->app->make(ProgramarUrdidoController::class)->index()->name()
        );
        $this->assertSame(
            'modulos.engomado.programar-engomado-livewire',
            $this->app->make(ProgramarEngomadoController::class)->index()->name()
        );
    }

    public function test_legacy_views_stay_reachable_as_a_fallback(): void
    {
        $urdidoView = $this->app->make(ProgramarUrdidoController::class)->legacy();
        $engomadoView = $this->app->make(ProgramarEngomadoController::class)->legacy();

        $this->assertSame('modulos.urdido.programar-urdido', $urdidoView->name());
        $this->assertSame('modulos.engomado.programar-engomado', $engomadoView->name());
        $this->assertArrayHasKey('programaRoutes', $urdidoView->getData());
        $this->assertArrayHasKey('programaRoutes', $engomadoView->getData());
    }
}
