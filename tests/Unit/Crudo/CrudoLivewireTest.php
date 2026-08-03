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
            ->assertSee('95%')
            ->assertSee('crudo-loom-body', false)
            ->assertSee('crudo-loom-number-text', false)
            ->call('selectMachine', '201')
            ->assertSet('selectedTelar', '201')
            ->assertSet('selectedMachineDetail.kilos', 40.0)
            ->assertSee('crudo-modal-overview', false)
            ->assertSee('crudo-modal-identity-card', false)
            ->assertDontSee('crudo-modal-header', false)
            ->assertSee('Órdenes y turnos')
            ->assertSee('1001')
            ->assertSee('Peso captura (kg)')
            ->assertSee('ORD-100')
            ->assertSee('Error de trama')
            ->assertSee('Checklist de telares reincidentes de defectos')
            ->assertSee('¿La alineación coincide con la orden?')
            ->assertSee('¿El dibujo de Jacquard está bien definido?')
            ->assertSee('¿Es correcta la identificación en el julio del lote de hilo y proveedor?')
            ->assertSee('crudo-audit-table', false)
            ->call('closeMachine')
            ->assertSet('selectedTelar', null);
    }

    public function test_it_renders_an_active_stop_inside_the_compact_status_card(): void
    {
        $data = $this->dashboardData();
        $data['machines'][0]['state'] = 'paro';
        $data['machines'][0]['stateLabel'] = 'Paro';
        $data['machines'][0]['stateIcon'] = 'fa-triangle-exclamation';
        $data['machines'][0]['paro'] = [
            'falla' => 'Falla mecánica',
            'descripcion' => 'Se detuvo el telar para revisión.',
            'reportedBy' => 'Calidad',
            'since' => '31/07/2026 08:15',
        ];

        $this->provider = new FakeCrudoDashboardProvider($data);
        $this->app->instance(CrudoDashboardProvider::class, $this->provider);

        Livewire::test(TestableCrudoDashboard::class)
            ->call('selectMachine', '201')
            ->assertSee('crudo-modal-status-card has-paro', false)
            ->assertSee('Falla mecánica')
            ->assertSee('Desde 31/07/2026 08:15')
            ->assertDontSee('crudo-paro-alert', false);
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

    public function test_poll_performs_only_one_dashboard_read_per_livewire_action(): void
    {
        Livewire::test(TestableCrudoDashboard::class)
            ->call('refreshDashboard')
            ->assertDispatched('crudo-refrescado');

        $this->assertSame(2, $this->provider->getCalls);
    }

    public function test_historical_and_range_views_do_not_keep_polling(): void
    {
        Livewire::test(TestableCrudoDashboard::class)
            ->set('fecha', '2026-07-01')
            ->assertDontSee('wire:poll.visible', false)
            ->set('modo', 'rango')
            ->assertDontSee('wire:poll.visible', false);
    }

    public function test_poll_refreshes_open_detail_and_preserves_the_last_detail_if_sql_fails(): void
    {
        $component = Livewire::test(TestableCrudoDashboard::class)
            ->call('selectMachine', '201')
            ->assertSet('selectedMachineDetail.kilos', 40.0);

        $this->provider->failDetail = true;

        $component
            ->call('refreshDashboard')
            ->assertSet('selectedMachineDetail.kilos', 40.0)
            ->assertSet(
                'selectedMachineDetailError',
                'No fue posible actualizar el detalle. El resumen puede seguir mostrando el último dato disponible.',
            )
            ->assertSee('No fue posible actualizar el detalle');

        $this->assertSame(2, $this->provider->detailCalls);
    }

    public function test_opening_and_closing_the_modal_does_not_force_a_synchronous_rebuild(): void
    {
        Livewire::test(TestableCrudoDashboard::class)
            ->call('selectMachine', '201')
            ->call('closeMachine');

        // El primer render (mount) sí puede reconstruir; los renders disparados
        // por selectMachine/closeMachine deben pedir allowRebuild=false.
        $this->assertSame(
            [true, false, false],
            $this->provider->allowRebuildSeen,
        );
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
                    'defectLineCount' => 1,
                    'observations' => '',
                ]],
                'lastUpdatedAt' => now()->toIso8601String(),
                'paro' => null,
                'programa' => null,
            ]],
            'summary' => [
                'paro' => 0,
                'bad_quality' => 0,
                'low_kilos' => 0,
                'operating' => 1,
                'no_data' => 0,
                'total' => 1,
                'pieces' => 100.0,
                'seconds' => 5.0,
                'kilos' => 4.0,
                'expectedKilos' => 3.0,
                'qualityPercent' => 95.0,
                'efficiencyPercent' => 100.0,
            ],
            'areas' => [[
                'name' => 'Jacquard',
                'paro' => 0,
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

    public int $getCalls = 0;

    public bool $failDetail = false;

    public int $detailCalls = 0;

    /** @var list<bool> */
    public array $allowRebuildSeen = [];

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        private readonly array $data,
    ) {}

    public function get(
        DateTimeImmutable $date,
        string $shift,
        bool $forceRefresh = false,
        ?DateTimeImmutable $to = null,
        bool $allowRebuild = true,
    ): array {
        $this->getCalls++;
        $this->allowRebuildSeen[] = $allowRebuild;
        $this->forceRefreshSeen = $this->forceRefreshSeen || $forceRefresh;

        return [
            ...$this->data,
            'date' => $date->format('Y-m-d'),
            'shift' => $shift,
        ];
    }

    public function detail(string $telar, DateTimeImmutable $from, DateTimeImmutable $to, string $shift): array
    {
        $this->detailCalls++;

        if ($this->failDetail) {
            throw new \RuntimeException('SQL no disponible');
        }

        foreach ($this->data['machines'] as $machine) {
            if ($machine['telar'] === $telar) {
                return [
                    'captureCount' => $machine['captureCount'],
                    'pieces' => $machine['pieces'],
                    'seconds' => $machine['seconds'],
                    'kilos' => 40.0,
                    'qualityPercent' => $machine['qualityPercent'],
                    'secondsPercent' => $machine['secondsPercent'],
                    'orders' => $machine['orders'],
                    'operators' => $machine['operators'],
                    'lastUpdatedAt' => $machine['lastUpdatedAt'],
                    'defectLineCount' => 1,
                    'defects' => $machine['defects'],
                    'captures' => $machine['captures'],
                ];
            }
        }

        return [
            'captureCount' => 0,
            'pieces' => 0.0,
            'seconds' => 0.0,
            'kilos' => 0.0,
            'qualityPercent' => 0.0,
            'secondsPercent' => 0.0,
            'orders' => [],
            'operators' => [],
            'lastUpdatedAt' => null,
            'defectLineCount' => 0,
            'defects' => [],
            'captures' => [],
        ];
    }
}

final class TestableCrudoDashboard extends Dashboard
{
    protected function authorizeAccess(): void {}
}
