<?php

declare(strict_types=1);

namespace Tests\Unit\Crudo;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

final class CrudoPerformanceStructureTest extends TestCase
{
    public function test_the_loom_image_is_shared_instead_of_repeated_per_machine(): void
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
            'salon' => 'Jacquard',
        ];

        $html = Blade::render(
            '@for ($index = 0; $index < 39; $index++) <x-crudo.machine-card :machine="$machine" /> @endfor',
            compact('machine'),
        );

        // Los 39 telares apuntan a las mismas 3 fotos: nada de geometría SVG
        // repetida por tarjeta ni una imagen distinta por máquina.
        $this->assertSame(39, substr_count($html, 'data-crudo-loom'));
        $this->assertSame(39, substr_count($html, 'images/crudo/jacquard.webp'));
        $this->assertStringNotContainsString('<svg', $html);
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
        $this->assertStringNotContainsString('auditDefectObserver.observe(dashboard', $typescript);
        $this->assertStringContainsString('auditDefectObserver.observe(crudoRoot', $typescript);
        $this->assertStringContainsString("const CRUDO_ROOT_SELECTOR = '[data-crudo-root]'", $typescript);
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

    public function test_modal_program_keys_are_stacked_without_inner_cards(): void
    {
        $css = file_get_contents(resource_path('css/crudo/dashboard.css'));

        $this->assertIsString($css);
        $this->assertMatchesRegularExpression(
            '/\.crudo-modal-program\s*\{[^}]*display:\s*grid/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.crudo-modal-program-field\s*\{(?:(?!background:|border:)[\s\S])*?\}/',
            $css,
        );
    }

    public function test_audit_history_requests_are_aborted_when_their_modal_is_removed(): void
    {
        $typescript = file_get_contents(resource_path('js/crudo/dashboard.ts'));

        $this->assertIsString($typescript);
        $this->assertStringContainsString('auditHistoryRequests.abortDisconnected()', $typescript);
        $this->assertMatchesRegularExpression(
            '/http\.get[^(]*\(url,\s*\{[^}]*signal:/s',
            $typescript,
        );
    }
}
