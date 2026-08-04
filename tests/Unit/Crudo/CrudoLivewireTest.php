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

    public function test_it_renders_the_dashboard(): void
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
            ->assertSee('crudo-loom-number-text', false);
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
            ->assertNotDispatched('crudo-refrescado');

        $this->assertSame(2, $this->provider->getCalls);
        $this->assertSame([false, false], $this->provider->allowRebuildSeen);
    }

    public function test_historical_and_range_views_do_not_keep_polling(): void
    {
        Livewire::test(TestableCrudoDashboard::class)
            ->set('fecha', '2026-07-01')
            ->assertDontSee('wire:poll.visible', false)
            ->set('modo', 'rango')
            ->assertDontSee('wire:poll.visible', false);
    }

    public function test_every_filter_change_dispatches_the_detail_close_event(): void
    {
        foreach ([
            ['fecha', now()->subDay()->format('Y-m-d')],
            ['fechaInicio', now()->subDays(2)->format('Y-m-d')],
            ['fechaFin', now()->subDay()->format('Y-m-d')],
            ['modo', 'rango'],
            ['turno', '2'],
        ] as [$property, $value]) {
            Livewire::test(TestableCrudoDashboard::class)
                ->set($property, $value)
                ->assertDispatched('crudo-filtros-cambiados');
        }
    }

    public function test_summary_production_and_percentages_are_rendered_as_rounded_integers(): void
    {
        $data = $this->dashboardData();
        $data['machines'][0]['kilos'] = 40.6;
        $data['machines'][0]['qualityPercent'] = 94.6;
        $data['summary']['kilos'] = 40.6;
        $data['summary']['qualityPercent'] = 94.6;
        $data['summary']['efficiencyPercent'] = 80.5;
        $this->app->instance(CrudoDashboardProvider::class, new FakeCrudoDashboardProvider($data));

        Livewire::test(TestableCrudoDashboard::class)
            ->assertSee('>41 kg</span>', false)
            ->assertSee('>95%</span>', false)
            ->assertSee('<strong>41</strong>', false)
            ->assertSee('<strong>95%</strong>', false)
            ->assertSee('<strong>81%</strong>', false);
    }

    public function test_tablet_keeps_the_summary_in_a_compact_sidebar(): void
    {
        $css = file_get_contents(resource_path('css/crudo/dashboard.css'));

        $this->assertIsString($css);
        $matched = preg_match(
            '/@media \(min-width: 641px\) and \(max-width: 1050px\),\s*\(hover: none\) and \(pointer: coarse\) and \(min-width: 641px\) and \(max-width: 1366px\) \{(?<rules>.*?)\n\}\n\n@media \(max-width: 860px\)/s',
            $css,
            $matches,
        );

        $this->assertSame(1, $matched);
        $tabletRules = $matches['rules'];
        $this->assertStringContainsString(
            'grid-template-columns: clamp(9.5rem, 14vw, 10.25rem) minmax(0, 1fr)',
            $tabletRules,
        );
        $this->assertStringContainsString('.crudo-sidebar {', $tabletRules);
        $this->assertStringContainsString('grid-template-columns: minmax(0, 1fr)', $tabletRules);
        $this->assertStringContainsString('.crudo-kpi-grid i {', $tabletRules);
        $this->assertStringContainsString('display: none', $tabletRules);
        $this->assertStringContainsString('grid-template-columns: 1.5rem 1.65rem minmax(0, 1fr)', $tabletRules);
        $this->assertStringContainsString(
            'grid-template-columns: minmax(0, 0.42fr) minmax(0, 1fr) minmax(0, 1.15fr)',
            $tabletRules,
        );
        $this->assertStringContainsString(
            'grid-template-columns: repeat(4, minmax(0, 1fr))',
            $tabletRules,
        );
        $this->assertStringContainsString('[data-crudo-detail-modal] .crudo-modal {', $tabletRules);
        $this->assertStringContainsString('width: min(72rem, calc(100vw - 3rem))', $tabletRules);
        $this->assertStringContainsString('max-height: 92vh', $tabletRules);
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
                    'turns' => [
                        '1' => 2.0,
                        '2' => 1.0,
                        '3' => 0.0,
                        '4' => 2.0,
                        'other' => 0.0,
                    ],
                ]],
                'captures' => [[
                    'recId' => '1001',
                    'order' => 'ORD-100',
                    'date' => '28/07/2026',
                    'purchBarcode' => 'PB-1001',
                    'weavingOrder' => '36541',
                    'supplierLot' => 'LOTE-PROV-1001',
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
