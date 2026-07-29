<?php

declare(strict_types=1);

namespace Tests\Unit\Crudo;

use App\Contracts\Crudo\CrudoDashboardProvider;
use App\Livewire\Crudo\Dashboard;
use DateTimeImmutable;
use Livewire\Livewire;
use Tests\TestCase;

final class CrudoLivewireTest extends TestCase
{
    private FakeCrudoDashboardProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('crudo.bad_quality_percent', 10);
        $this->provider = new FakeCrudoDashboardProvider($this->dashboardData());
        $this->app->instance(CrudoDashboardProvider::class, $this->provider);
    }

    public function test_it_renders_the_dashboard_and_machine_detail(): void
    {
        Livewire::test(TestableCrudoDashboard::class)
            ->assertSee('Estado de máquinas')
            ->assertSee('Producción del periodo')
            ->assertSee('crudo-panel-overview', false)
            ->assertDontSee('Lectura del semáforo')
            ->assertSee('Salón Jacquard')
            ->assertSee('crudo-navbar-toolbar', false)
            ->assertDontSee('crudo-toolbar', false)
            ->assertSee('JAC 201')
            ->assertSee('95.0% calidad')
            ->assertSee('crudo-loom-body', false)
            ->assertSee('crudo-loom-number-text', false)
            ->call('selectMachine', '201')
            ->assertSet('selectedTelar', '201')
            ->assertSee('Órdenes y turnos')
            ->assertSee('ORD-100')
            ->assertSee('Error de trama')
            ->call('closeMachine')
            ->assertSet('selectedTelar', null);
    }

    public function test_it_normalizes_shift_and_forces_refresh_on_manual_action(): void
    {
        Livewire::test(TestableCrudoDashboard::class)
            ->set('turno', '9')
            ->assertSet('turno', 'todos')
            ->call('refreshNow')
            ->assertDispatched('crudo-refrescado');

        $this->assertTrue($this->provider->forceRefreshSeen);
    }

    /**
     * @return array<string, mixed>
     */
    private function dashboardData(): array
    {
        return [
            'date' => now()->format('Y-m-d'),
            'shift' => 'todos',
            'machines' => [[
                'telar' => '201',
                'name' => 'JAC 201',
                'salon' => 'Jacquard',
                'group' => 'Jacquard Smith',
                'sequence' => 1,
                'captureCount' => 1,
                'pieces' => 100.0,
                'seconds' => 5.0,
                'kilos' => 4.0,
                'qualityPercent' => 95.0,
                'secondsPercent' => 5.0,
                'expectedKilos' => 3.0,
                'state' => 'operating',
                'stateLabel' => 'En operación',
                'stateIcon' => 'fa-circle-check',
                'orders' => ['ORD-100'],
                'operators' => ['Operador uno'],
                'defects' => [[
                    'code' => '01',
                    'description' => 'Error de trama',
                    'quantity' => 5.0,
                ]],
                'captures' => [[
                    'recId' => '1001',
                    'order' => 'ORD-100',
                    'operator' => 'Operador uno',
                    'weight' => 40.0,
                    'piecesT1' => 100.0,
                    'piecesT2' => 0.0,
                    'piecesT3' => 0.0,
                    'piecesT4' => 0.0,
                    'pieces' => 100.0,
                    'seconds' => 5.0,
                    'observations' => '',
                ]],
                'lastUpdatedAt' => now()->toIso8601String(),
            ]],
            'summary' => [
                'bad_quality' => 0,
                'low_kilos' => 0,
                'operating' => 1,
                'no_data' => 0,
                'total' => 1,
                'pieces' => 100.0,
                'seconds' => 5.0,
                'kilos' => 4.0,
                'qualityPercent' => 95.0,
            ],
            'areas' => [[
                'name' => 'Jacquard',
                'badQuality' => 0,
                'lowKilos' => 0,
                'operating' => 1,
                'noData' => 0,
                'total' => 1,
            ]],
            'generatedAt' => now()->toIso8601String(),
            'cacheState' => 'fresh',
            'sourceError' => null,
        ];
    }
}

final class FakeCrudoDashboardProvider implements CrudoDashboardProvider
{
    public bool $forceRefreshSeen = false;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        private readonly array $data,
    ) {}

    public function get(DateTimeImmutable $date, string $shift, bool $forceRefresh = false): array
    {
        $this->forceRefreshSeen = $this->forceRefreshSeen || $forceRefresh;

        return [
            ...$this->data,
            'date' => $date->format('Y-m-d'),
            'shift' => $shift,
        ];
    }
}

final class TestableCrudoDashboard extends Dashboard
{
    protected function authorizeAccess(): void {}
}
