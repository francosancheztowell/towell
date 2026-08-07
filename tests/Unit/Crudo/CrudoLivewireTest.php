<?php

declare(strict_types=1);

namespace Tests\Unit\Crudo;

use App\Contracts\Crudo\CrudoDashboardProvider;
use App\Livewire\Crudo\Dashboard;
use Carbon\Carbon;
use DateTimeImmutable;
use Livewire\Livewire;
use Tests\TestCase;

final class CrudoLivewireTest extends TestCase
{
    private FakeCrudoDashboardProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('crudo.bad_quality_percent', 7);
        config()->set('crudo.poll_seconds', 15);
        $this->provider = new FakeCrudoDashboardProvider($this->dashboardData());
        $this->app->instance(CrudoDashboardProvider::class, $this->provider);
    }

    public function test_it_renders_the_dashboard(): void
    {
        Livewire::test(TestableCrudoDashboard::class)
            ->assertSee('1 telares')
            ->assertSee('Producción del periodo')
            ->assertSee('crudo-panel-overview', false)
            ->assertDontSee('Lectura del semáforo')
            ->assertSee('<h2>Jacquard</h2>', false)
            ->assertSee('crudo-navbar-toolbar', false)
            ->assertDontSee('crudo-toolbar', false)
            ->assertSee('JAC 201')
            ->assertSee('95%')
            ->assertSee('images/crudo/jacquard.webp', false)
            ->assertSee('crudo-loom-number', false);
    }

    public function test_it_forces_refresh_on_manual_action(): void
    {
        Livewire::test(TestableCrudoDashboard::class)
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

    public function test_a_stale_snapshot_does_not_accelerate_polling_to_two_seconds(): void
    {
        $data = $this->dashboardData();
        $data['cacheState'] = 'stale';
        $this->app->instance(CrudoDashboardProvider::class, new FakeCrudoDashboardProvider($data));

        Livewire::test(TestableCrudoDashboard::class)
            ->assertSee('wire:poll.visible.15s', false)
            ->assertDontSee('wire:poll.visible.2s', false);
    }

    public function test_poll_is_removed_while_a_modal_interaction_is_open(): void
    {
        Livewire::test(TestableCrudoDashboard::class)
            ->assertSee('wire:poll.visible.15s', false)
            ->dispatch('crudo-interaction-opened')
            ->assertSet('interactionPaused', true)
            ->assertDontSee('wire:poll.visible', false)
            ->dispatch('crudo-interaction-closed')
            ->assertSet('interactionPaused', false)
            ->assertSee('wire:poll.visible.15s', false);
    }

    public function test_machine_detail_is_mounted_as_a_sibling_of_the_dashboard(): void
    {
        $dashboard = file_get_contents(resource_path('views/livewire/crudo/dashboard.blade.php'));
        $page = file_get_contents(resource_path('views/modulos/crudo/index.blade.php'));

        $this->assertIsString($dashboard);
        $this->assertIsString($page);
        $this->assertStringNotContainsString('<livewire:crudo.machine-detail', $dashboard);
        $this->assertStringContainsString('data-crudo-root', $page);
        $this->assertMatchesRegularExpression(
            '/<livewire:crudo\.dashboard\s*\/>\s*<livewire:crudo\.machine-detail\s*\/>/',
            $page,
        );
    }

    public function test_dashboard_exposes_one_canonical_filter_context_for_the_detail(): void
    {
        $today = $this->productionDay();

        Livewire::test(TestableCrudoDashboard::class)
            ->assertSee('data-crudo-fecha="'.$today.'"', false)
            ->assertSee('data-crudo-fecha-inicio="'.$today.'"', false)
            ->assertSee('data-crudo-fecha-fin="'.$today.'"', false)
            ->assertSee('data-crudo-modo="dia"', false);
    }

    public function test_historical_and_range_views_do_not_keep_polling(): void
    {
        Livewire::test(TestableCrudoDashboard::class)
            ->set('fecha', '2026-07-01')
            ->assertDontSee('wire:poll.visible', false)
            ->set('modo', 'rango')
            ->assertDontSee('wire:poll.visible', false);
    }

    public function test_before_six_thirty_it_opens_on_the_running_production_day_and_keeps_polling(): void
    {
        // Un supervisor que entra a las 05:00 sigue dentro del día de producción
        // anterior; abrir en la fecha de calendario le mostraba un tablero vacío.
        Carbon::setTestNow(Carbon::parse('2026-08-05 05:00:00', config('app.timezone')));

        try {
            Livewire::test(TestableCrudoDashboard::class)
                ->assertSet('fecha', '2026-08-04')
                ->assertSee('wire:poll.visible', false);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_after_six_thirty_it_opens_on_the_calendar_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-05 07:00:00', config('app.timezone')));

        try {
            Livewire::test(TestableCrudoDashboard::class)->assertSet('fecha', '2026-08-05');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_every_filter_change_dispatches_the_detail_close_event(): void
    {
        foreach ([
            ['fecha', now()->subDay()->format('Y-m-d')],
            ['fechaInicio', now()->subDays(2)->format('Y-m-d')],
            ['fechaFin', now()->subDay()->format('Y-m-d')],
            ['modo', 'rango'],
        ] as [$property, $value]) {
            Livewire::test(TestableCrudoDashboard::class)
                ->set($property, $value)
                ->assertDispatched('crudo-filtros-cambiados');
        }
    }

    public function test_filter_change_dispatches_the_complete_period_context_for_the_detail(): void
    {
        $today = $this->productionDay();
        $selectedDate = now(config('app.timezone'))->subDay()->format('Y-m-d');

        Livewire::test(TestableCrudoDashboard::class)
            ->set('fecha', $selectedDate)
            ->assertDispatched(
                'crudo-filtros-cambiados',
                fecha: $selectedDate,
                fechaInicio: $today,
                fechaFin: $today,
                modo: 'dia',
            );
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
        $this->assertStringContainsString('width: min(66rem, calc(100vw - 5rem))', $tabletRules);
        $this->assertStringContainsString('max-height: 76vh', $tabletRules);
        $this->assertStringContainsString(
            'grid-template-columns: minmax(11rem, 1.35fr) repeat(4, minmax(0, 0.75fr))',
            $tabletRules,
        );
        $this->assertStringContainsString(
            'grid-template-columns: minmax(0, 1.6fr) minmax(8rem, 0.65fr) minmax(9rem, 0.75fr)',
            $tabletRules,
        );
        $this->assertStringContainsString('.crudo-orders-table .crudo-orders-col-lot {', $tabletRules);
        $this->assertStringContainsString('width: 30%', $tabletRules);
        $this->assertStringContainsString('.crudo-orders-table td {', $tabletRules);
        $this->assertStringContainsString('font-size: 0.78rem', $tabletRules);
        $this->assertStringContainsString('.crudo-flog-simulation img {', $tabletRules);
        $this->assertStringContainsString('height: 5rem', $tabletRules);
    }

    public function test_desktop_gives_orders_more_width_than_defects_and_flog(): void
    {
        $css = file_get_contents(resource_path('css/crudo/dashboard.css'));

        $this->assertIsString($css);
        $this->assertStringContainsString(
            'grid-template-columns: minmax(22rem, 1.2fr) minmax(17rem, 0.85fr) minmax(19rem, 0.95fr)',
            $css,
        );
    }

    private function productionDay(): string
    {
        return now(config('app.timezone'))
            ->subMinutes((int) config('crudo.production_day_start_minutes', 390))
            ->format('Y-m-d');
    }

    /**
     * @return array<string, mixed>
     */
    public function test_el_contador_de_estado_abre_el_desglose_con_hora_reporte_y_paros(): void
    {
        $data = $this->dashboardData();
        $data['machines'][0]['state'] = 'paro';
        $data['machines'][0]['stateLabel'] = 'Paro';
        $data['machines'][0]['paro'] = [
            'reportedBy' => 'Juan Pérez',
            'faultCode' => '62',
            'falla' => 'REVERSA',
            'tipo' => 'MECANICO',
            'since' => '29/07/2026 15:21',
            'descripcion' => 'REVERSA',
            'count' => 2,
            'todos' => [
                ['reportedBy' => 'Juan Pérez', 'falla' => 'REVERSA', 'tipo' => 'MECANICO', 'since' => '29/07/2026 15:21'],
                ['reportedBy' => 'Ana López', 'falla' => 'CORTO', 'tipo' => 'ELECTRICO', 'since' => '29/07/2026 11:02'],
            ],
        ];
        $data['summary']['paro'] = 1;
        $data['summary']['operating'] = 0;
        $this->app->instance(CrudoDashboardProvider::class, new FakeCrudoDashboardProvider($data));

        Livewire::test(TestableCrudoDashboard::class)
            ->assertDontSee('crudo-modal-estado', false)
            ->call('abrirEstado', 'paro')
            ->assertSet('estadoDetalle', 'paro')
            ->assertSee('crudo-modal-estado', false)
            ->assertSee('2 paros')
            ->assertSee('29/07/2026 15:21')
            ->assertSee('29/07/2026 11:02')
            ->assertSee('Juan Pérez')
            ->assertSee('Ana López')
            ->assertSee('REVERSA')
            ->assertSee('CORTO')
            ->call('cerrarEstado')
            ->assertSet('estadoDetalle', null)
            ->assertDontSee('crudo-modal-estado', false);
    }

    public function test_el_desglose_solo_lista_los_telares_de_ese_estado(): void
    {
        Livewire::test(TestableCrudoDashboard::class)
            // El único telar del fixture está en operación.
            ->call('abrirEstado', 'paro')
            ->assertSee('Ningún telar en este estado ahora mismo.')
            ->call('abrirEstado', 'operating')
            ->assertSee('JAC 201')
            ->assertSee('Calidad')
            ->assertSee('95.0%');
    }

    public function test_el_desglose_ignora_estados_inventados(): void
    {
        Livewire::test(TestableCrudoDashboard::class)
            ->call('abrirEstado', 'lo-que-sea')
            ->assertSet('estadoDetalle', null);
    }

    public function test_el_desglose_ordena_los_telares_por_numero(): void
    {
        $data = $this->dashboardData();
        $base = $data['machines'][0];
        $data['machines'] = [];

        // Llegan como los ordena el tablero (por salón), no por número.
        foreach ([['305', 'Smith'], ['201', 'Jacquard'], ['1102', 'Karl Mayer'], ['202', 'Jacquard']] as [$telar, $salon]) {
            $data['machines'][] = ['telar' => $telar, 'name' => 'TEL '.$telar, 'salon' => $salon] + $base;
        }
        $data['summary']['operating'] = 4;
        $data['summary']['total'] = 4;
        $this->app->instance(CrudoDashboardProvider::class, new FakeCrudoDashboardProvider($data));

        $html = Livewire::test(TestableCrudoDashboard::class)
            ->call('abrirEstado', 'operating')
            ->html();

        $lista = substr($html, strpos($html, 'crudo-estado-list'));
        $posiciones = array_map(
            static fn (string $telar): int => strpos($lista, '>'.$telar.'</span>'),
            ['201', '202', '305', '1102'],
        );

        $ordenadas = $posiciones;
        sort($ordenadas);
        $this->assertSame($ordenadas, $posiciones);
    }

    private function dashboardData(): array
    {
        return [
            'date' => now()->format('Y-m-d'),
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
                    'warpingOrder' => '00929',
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
        ];
    }

    public function detail(string $telar, DateTimeImmutable $from, DateTimeImmutable $to): array
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
