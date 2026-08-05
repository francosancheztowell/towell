<?php

declare(strict_types=1);

namespace Tests\Unit\Crudo;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

final class CrudoPerformanceStructureTest extends TestCase
{
    public function test_the_loom_geometry_is_shared_instead_of_repeated_per_machine(): void
    {
        $machine = [
            'telar' => '201',
            'name' => 'JAC 201',
            'state' => 'operating',
            'stateLabel' => 'En operación',
            'pieces' => 100.0,
            'seconds' => 5.0,
            'kilos' => 40.6,
            'qualityPercent' => 94.6,
        ];

        $html = Blade::render(
            '@for ($index = 0; $index < 39; $index++) <x-crudo.machine-card :machine="$machine" /> @endfor',
            compact('machine'),
        );

        $this->assertSame(1, substr_count($html, 'id="crudo-loom-symbol"'));
        $this->assertSame(39, substr_count($html, '<use href="#crudo-loom-symbol"'));
        $this->assertSame(1, substr_count($html, 'class="crudo-loom-body"'));
    }

    public function test_dashboard_has_no_animated_gradient_or_per_card_svg_filter(): void
    {
        $css = file_get_contents(resource_path('css/crudo/dashboard.css'));
        $typescript = file_get_contents(resource_path('js/crudo/dashboard.ts'));

        $this->assertIsString($css);
        $this->assertIsString($typescript);
        $this->assertStringNotContainsString('linear-gradient', $css);
        $this->assertStringNotContainsString('drop-shadow', $css);
        $this->assertStringNotContainsString('backdrop-filter', $css);
        $this->assertStringNotContainsString('crudo-skeleton-shimmer', $css);
        $this->assertStringNotContainsString('is-changed', $typescript);
        $this->assertStringNotContainsString('mutationObserver.observe(machineGrid', $typescript);
        $this->assertStringContainsString('mutationObserver.observe(dataElement', $typescript);
        $this->assertStringNotContainsString('auditDefectObserver.observe(document.body', $typescript);
        $this->assertStringContainsString('auditDefectObserver.observe(dashboard', $typescript);
    }

    public function test_the_machine_floor_is_a_stable_livewire_island(): void
    {
        $dashboard = file_get_contents(resource_path('views/livewire/crudo/dashboard.blade.php'));
        $floor = file_get_contents(resource_path('views/livewire/crudo/machine-floor.blade.php'));

        $this->assertIsString($dashboard);
        $this->assertIsString($floor);
        $this->assertStringContainsString('<livewire:crudo.machine-floor', $dashboard);
        $this->assertStringNotContainsString('<x-crudo.machine-card', $dashboard);
        $this->assertStringContainsString('<x-crudo.machine-card', $floor);
        $this->assertStringNotContainsString('wire:poll', $floor);
    }

    public function test_modal_close_keeps_the_backdrop_until_livewire_finishes(): void
    {
        $css = file_get_contents(resource_path('css/crudo/dashboard.css'));

        $this->assertIsString($css);
        $this->assertDoesNotMatchRegularExpression(
            '/\.crudo-modal-backdrop\.is-closing\s*\{[^}]*display\s*:\s*none/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.crudo-modal-backdrop\.is-closing\s*\{[^}]*cursor\s*:\s*wait/s',
            $css,
        );
    }

    public function test_audit_history_requests_are_aborted_when_their_modal_is_removed(): void
    {
        $typescript = file_get_contents(resource_path('js/crudo/dashboard.ts'));

        $this->assertIsString($typescript);
        $this->assertStringContainsString('auditHistoryRequests.abortDisconnected()', $typescript);
        $this->assertMatchesRegularExpression(
            '/fetch\(url,\s*\{[^}]*signal:/s',
            $typescript,
        );
    }
}
