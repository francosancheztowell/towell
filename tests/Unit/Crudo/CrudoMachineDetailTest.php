<?php

declare(strict_types=1);

namespace Tests\Unit\Crudo;

use App\Contracts\Crudo\CrudoDashboardProvider;
use App\Livewire\Crudo\MachineDetail;
use DateTimeImmutable;
use Livewire\Livewire;
use Tests\TestCase;

final class CrudoMachineDetailTest extends TestCase
{
    private FakeCrudoDashboardProviderForDetail $provider;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('crudo.bad_quality_percent', 10);
        $this->provider = new FakeCrudoDashboardProviderForDetail($this->machineData());
        $this->app->instance(CrudoDashboardProvider::class, $this->provider);
    }

    public function test_it_opens_and_loads_machine_detail(): void
    {
        Livewire::test(TestableCrudoMachineDetail::class)
            ->dispatch('open-crudo-detail', telar: '201', machine: $this->machineData())
            ->assertSet('selectedTelar', '201')
            ->assertSet('detail.kilos', 40.0)
            ->assertSee('crudo-modal-overview', false)
            ->assertSee('crudo-modal-identity-card', false)
            ->assertSee('Órdenes y turnos')
            ->assertSee('Meta a esta hora')
            ->assertSee('PurchBarcode')
            ->assertSee('PB-1001')
            ->assertSee('Peso captura (kg)')
            ->assertSee('Error de trama')
            ->assertSee('crudo-defect-table', false)
            ->assertSee('Defectos encontrados y desglose por turno')
            ->assertSee('T1')
            ->assertSee('T2')
            ->assertSee('T3')
            ->assertSee('T4')
            ->assertSee('Checklist de telares reincidentes de defectos')
            ->assertSee('¿La alineación coincide con la orden?')
            ->assertSee('¿El dibujo de Jacquard está bien definido?')
            ->assertSee('¿Es correcta la identificación en el julio del lote de hilo y proveedor?')
            ->assertSee('crudo-audit-table', false)
            ->call('close')
            ->assertSet('selectedTelar', null)
            ->assertDontSee('crudo-modal-overview', false);
    }

    public function test_it_renders_an_active_stop_inside_the_compact_status_card(): void
    {
        $machine = $this->machineData();
        $machine['state'] = 'paro';
        $machine['stateLabel'] = 'Paro';
        $machine['stateIcon'] = 'fa-triangle-exclamation';
        $machine['paro'] = [
            'falla' => 'Falla mecánica',
            'descripcion' => 'Se detuvo el telar para revisión.',
            'reportedBy' => 'Calidad',
            'since' => '31/07/2026 08:15',
        ];

        Livewire::test(TestableCrudoMachineDetail::class)
            ->dispatch('open-crudo-detail', telar: '201', machine: $machine)
            ->assertSee('crudo-modal-status-card has-paro', false)
            ->assertSee('Falla mecánica')
            ->assertSee('Desde 31/07/2026 08:15')
            ->assertDontSee('crudo-paro-alert', false);
    }

    public function test_jacquard_drawing_question_is_hidden_for_other_saloons(): void
    {
        $machine = $this->machineData();
        $machine['salon'] = 'Smith';
        $machine['group'] = 'Smith';
        $machine['name'] = 'SMI 201';

        Livewire::test(TestableCrudoMachineDetail::class)
            ->dispatch('open-crudo-detail', telar: '201', machine: $machine)
            ->assertSee('2 puntos')
            ->assertSee('¿La alineación coincide con la orden?')
            ->assertDontSee('¿El dibujo de Jacquard está bien definido?')
            ->assertSee('¿Es correcta la identificación en el julio del lote de hilo y proveedor?');
    }

    public function test_refresh_keeps_last_detail_if_sql_fails(): void
    {
        $component = Livewire::test(TestableCrudoMachineDetail::class)
            ->dispatch('open-crudo-detail', telar: '201', machine: $this->machineData())
            ->assertSet('detail.kilos', 40.0);

        $this->provider->failDetail = true;

        $component
            ->dispatch('crudo-refrescado')
            ->assertSet('detail.kilos', 40.0)
            ->assertSet(
                'detailError',
                'No fue posible actualizar el detalle. El resumen puede seguir mostrando el último dato disponible.',
            )
            ->assertSee('No fue posible actualizar el detalle');

        $this->assertSame(2, $this->provider->detailCalls);
    }

    /**
     * @return array<string, mixed>
     */
    private function machineData(): array
    {
        return [
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
                'purchBarcode' => 'PB-1001',
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
        ];
    }
}

final class FakeCrudoDashboardProviderForDetail implements CrudoDashboardProvider
{
    public bool $failDetail = false;

    public int $detailCalls = 0;

    /**
     * @param  array<string, mixed>  $machine
     */
    public function __construct(
        private readonly array $machine,
    ) {}

    public function get(
        DateTimeImmutable $date,
        string $shift,
        bool $forceRefresh = false,
        ?DateTimeImmutable $to = null,
        bool $allowRebuild = true,
    ): array {
        return [];
    }

    public function detail(string $telar, DateTimeImmutable $from, DateTimeImmutable $to, string $shift): array
    {
        $this->detailCalls++;

        if ($this->failDetail) {
            throw new \RuntimeException('SQL no disponible');
        }

        if ($telar !== $this->machine['telar']) {
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

        return [
            'captureCount' => $this->machine['captureCount'],
            'pieces' => $this->machine['pieces'],
            'seconds' => $this->machine['seconds'],
            'kilos' => 40.0,
            'qualityPercent' => $this->machine['qualityPercent'],
            'secondsPercent' => $this->machine['secondsPercent'],
            'orders' => $this->machine['orders'],
            'operators' => $this->machine['operators'],
            'lastUpdatedAt' => $this->machine['lastUpdatedAt'],
            'defectLineCount' => 1,
            'defects' => $this->machine['defects'],
            'captures' => $this->machine['captures'],
        ];
    }
}

final class TestableCrudoMachineDetail extends MachineDetail
{
    protected function authorizeAccess(): void {}
}
