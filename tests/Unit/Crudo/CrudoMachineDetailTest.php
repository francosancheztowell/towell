<?php

declare(strict_types=1);

namespace Tests\Unit\Crudo;

use App\Contracts\Crudo\CrudoDashboardProvider;
use App\Contracts\Crudo\CrudoFlogProvider;
use App\Livewire\Crudo\MachineDetail;
use DateTimeImmutable;
use Livewire\Livewire;
use Tests\TestCase;

final class CrudoMachineDetailTest extends TestCase
{
    private FakeCrudoDashboardProviderForDetail $provider;

    private FakeCrudoFlogProviderForDetail $flogProvider;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('crudo.bad_quality_percent', 10);
        $this->provider = new FakeCrudoDashboardProviderForDetail($this->machineData());
        $this->flogProvider = new FakeCrudoFlogProviderForDetail;
        $this->app->instance(CrudoDashboardProvider::class, $this->provider);
        $this->app->instance(CrudoFlogProvider::class, $this->flogProvider);
    }

    public function test_it_opens_and_loads_machine_detail(): void
    {
        Livewire::test(TestableCrudoMachineDetail::class)
            ->dispatch('open-crudo-detail', telar: '201', machine: $this->machineData())
            ->assertSet('selectedTelar', '201')
            ->assertSet('detail.kilos', 40.0)
            ->assertSee('crudo-modal-overview', false)
            ->assertSee('crudo-modal-identity-card', false)
            ->assertSee('crudo-process-defects-panel', false)
            ->assertSee('data-crudo-detail-modal', false)
            ->assertDontSee('data-crudo-audit-modal', false)
            ->assertSee('Órdenes y turnos')
            ->assertSee('Meta a esta hora')
            ->assertSee('No. Rollo')
            ->assertSee('PB-1001')
            ->assertSee('Peso (kg)')
            ->assertSee('Error de trama')
            ->assertSee('Defectos registrados')
            ->assertSee('crudo-defect-table', false)
            ->assertSee('Defectos consultados de producción y desglose por turno')
            ->assertSee('T1')
            ->assertSee('T2')
            ->assertSee('T3')
            ->assertSee('T4')
            ->assertSee('Datos del Flog')
            ->assertDontSee('crudo-eyebrow', false)
            ->assertSee('CE-NOV25-LGONZ-F001399')
            ->assertSee('C0001 Cliente de prueba')
            ->assertSee('ART-100')
            ->assertSee('100X200')
            ->assertSee('Simulación')
            ->assertSee('https://example.test/simulacion-ventas.jpg', false)
            ->assertSee('Agregar auditoría')
            ->assertSee('data-crudo-open-audit', false)
            ->assertDontSee('data-crudo-audit-content', false)
            ->assertDontSee('Checklist de telares reincidentes de defectos')
            ->call('openAudit')
            ->assertSet('auditModalOpen', true)
            ->assertSee('data-crudo-audit-modal', false)
            ->assertDontSee('data-crudo-detail-modal', false)
            ->assertSee('Nueva auditoría · JAC 201')
            ->assertSee('data-crudo-audit-content', false)
            ->assertSee('crudo-audit-defects-panel', false)
            ->assertSee('Defecto 1')
            ->assertSee('Defecto 5')
            ->assertSee('Cargando catálogo...')
            ->assertSee('Hasta cinco defectos del catálogo de Calidad.')
            ->assertDontSee('Agregar defecto')
            ->assertSee('Checklist de telares reincidentes de defectos')
            ->assertSee('Bien / Mal')
            ->assertSee('data-crudo-audit-result', false)
            ->assertSee('¿La alineación coincide con la orden?')
            ->assertSee('¿El dibujo de Jacquard está bien definido?')
            ->assertSee('¿Es correcta la identificación en el julio del lote de hilo y proveedor?')
            ->assertSee('crudo-audit-table', false)
            ->assertSee('crudo-audit-observations', false)
            ->assertSee('Observaciones')
            ->assertSee('Observaciones de la auditoría')
            ->call('close')
            ->assertSet('selectedTelar', null)
            ->assertSet('auditModalOpen', false)
            ->assertSet('flogSummary', null)
            ->assertDontSee('crudo-modal-overview', false);

        $this->assertSame(1, $this->flogProvider->calls);
    }

    public function test_checklist_results_start_empty_and_use_one_cyclic_control_per_question(): void
    {
        $component = Livewire::test(TestableCrudoMachineDetail::class)
            ->dispatch('open-crudo-detail', telar: '201', machine: $this->machineData())
            ->call('openAudit');

        $html = $component->html();
        $resultControlCount = preg_match_all('/data-crudo-audit-result(?:\s|>)/', $html);

        $this->assertSame(3, $resultControlCount);
        $this->assertSame(3, substr_count($html, 'data-state="empty"'));
        $this->assertSame(3, substr_count($html, 'data-crudo-audit-result-input'));
        $this->assertStringContainsString('Pregunta 1: Sin evaluar', $html);
        $this->assertStringNotContainsString('type="radio"', $html);
    }

    public function test_it_renders_exactly_five_quality_catalog_selects(): void
    {
        $component = Livewire::test(TestableCrudoMachineDetail::class)
            ->dispatch('open-crudo-detail', telar: '201', machine: $this->machineData())
            ->call('openAudit');

        $html = $component->html();

        $this->assertSame(5, substr_count($html, 'data-crudo-quality-defect-select'));
        $this->assertSame(5, substr_count($html, 'name="crudo-audit-defect[]"'));
        $this->assertStringContainsString('/api/mantenimiento/fallas/Calidad', $html);
        $this->assertStringNotContainsString('data-crudo-add-audit-defect', $html);
        $this->assertStringNotContainsString('data-crudo-remove-audit-defect', $html);
    }

    public function test_detail_and_audit_modals_are_mutually_exclusive(): void
    {
        $component = Livewire::test(TestableCrudoMachineDetail::class)
            ->dispatch('open-crudo-detail', telar: '201', machine: $this->machineData());

        $html = $component->html();

        $this->assertSame(1, preg_match_all('/data-crudo-modal(?:\s|>)/', $html));
        $this->assertStringContainsString('data-crudo-detail-modal', $html);
        $this->assertStringNotContainsString('data-crudo-audit-modal', $html);
        $this->assertStringNotContainsString('data-crudo-save-audit', $html);

        $component->call('openAudit')->assertSet('auditModalOpen', true);
        $html = $component->html();

        $this->assertSame(1, preg_match_all('/data-crudo-modal(?:\s|>)/', $html));
        $this->assertStringNotContainsString('data-crudo-detail-modal', $html);
        $this->assertStringContainsString('data-crudo-audit-modal', $html);
        $this->assertStringContainsString('data-crudo-save-audit', $html);
        $this->assertStringContainsString('data-crudo-save-stop', $html);
    }

    public function test_today_audits_are_visible_before_the_new_audit_disclosure(): void
    {
        $component = Livewire::test(TestableCrudoMachineDetail::class)
            ->dispatch('open-crudo-detail', telar: '201', machine: $this->machineData());

        $html = $component->html();
        $historyPosition = strpos($html, 'class="crudo-detail-panel crudo-audit-history-panel"');
        $openButtonPosition = strpos($html, 'data-crudo-open-audit');

        $this->assertIsInt($historyPosition);
        $this->assertIsInt($openButtonPosition);
        $this->assertLessThan($openButtonPosition, $historyPosition);
        $this->assertStringNotContainsString('data-crudo-audit-content', $html);
    }

    public function test_save_actions_are_rendered_only_in_the_audit_modal(): void
    {
        $component = Livewire::test(TestableCrudoMachineDetail::class)
            ->dispatch('open-crudo-detail', telar: '201', machine: $this->machineData());

        $this->assertStringNotContainsString('data-crudo-save-stop', $component->html());

        $component->call('openAudit');

        $this->assertStringNotContainsString('hidden', $this->stopButtonTag($component->html()));
        $this->assertStringContainsString('Guardar paro', $component->html());
        $this->assertMatchesRegularExpression(
            '/id="crudo-audit-content".*data-crudo-save-audit.*data-crudo-save-stop/s',
            $component->html(),
        );
    }

    public function test_refresh_does_not_requery_or_rebuild_an_open_audit_modal(): void
    {
        $component = Livewire::test(TestableCrudoMachineDetail::class)
            ->dispatch('open-crudo-detail', telar: '201', machine: $this->machineData())
            ->call('openAudit')
            ->assertSet('auditModalOpen', true)
            ->assertSee('Nueva auditoría · JAC 201');

        $component
            ->dispatch('crudo-refrescado')
            ->assertSet('auditModalOpen', true)
            ->assertSee('Nueva auditoría · JAC 201')
            ->call('close')
            ->assertSet('auditModalOpen', false)
            ->assertSet('selectedTelar', null);

        $this->assertSame(1, $this->provider->detailCalls);
        $this->assertSame(1, $this->flogProvider->calls);
    }

    public function test_program_order_is_in_the_title_and_model_key_is_next_to_ax_key(): void
    {
        $machine = $this->machineData();
        $machine['programa'] = [
            'orden' => 'ORD-PROG-201',
            'claveModelo' => 'MOD-201-GDE',
            'itemId' => 'AX-201',
            'inventSizeId' => '100X200',
            'flogId' => 'CE-NOV25-LGONZ-F001399',
            'nombreProducto' => 'Producto de prueba',
        ];

        Livewire::test(TestableCrudoMachineDetail::class)
            ->dispatch('open-crudo-detail', telar: '201', machine: $machine)
            ->assertSee('crudo-modal-order', false)
            ->assertSee('ORD-PROG-201')
            ->assertSee('Clave modelo')
            ->assertSee('MOD-201-GDE')
            ->assertSee('Clave AX')
            ->assertSee('AX-201')
            ->assertDontSee('Clave ORD-PROG-201');
    }

    public function test_it_renders_an_active_stop_inside_the_compact_status_card(): void
    {
        $machine = $this->machineData();
        $machine['state'] = 'paro';
        $machine['stateLabel'] = 'Paro';
        $machine['stateIcon'] = 'fa-triangle-exclamation';
        $machine['paro'] = [
            'faultCode' => '62',
            'falla' => 'REVERSA',
            'descripcion' => 'REVERSA',
            'reportedBy' => 'Calidad',
            'since' => '29/07/2026 15:21',
        ];

        Livewire::test(TestableCrudoMachineDetail::class)
            ->dispatch('open-crudo-detail', telar: '201', machine: $machine)
            ->assertSee('crudo-modal-status-card has-paro', false)
            ->assertSee('REVERSA')
            ->assertDontSee('>62<', false)
            ->assertSee('Desde 29/07/2026 15:21')
            ->assertDontSee('15:21:00')
            ->assertDontSee('crudo-paro-alert', false);
    }

    public function test_production_target_and_percentages_are_rounded_in_the_status_header(): void
    {
        $this->provider->detailKilos = 40.6;
        $this->provider->detailQualityPercent = 94.6;
        $this->provider->detailSecondsPercent = 5.4;
        $machine = $this->machineData();
        $machine['expectedKilos'] = 50.6;

        Livewire::test(TestableCrudoMachineDetail::class)
            ->dispatch('open-crudo-detail', telar: '201', machine: $machine)
            ->assertSee('41 kg')
            ->assertSee('Meta a esta hora')
            ->assertSee('51 kg')
            ->assertSee('95%')
            ->assertSee('5% segundas')
            ->assertDontSee('40.6 kg')
            ->assertDontSee('94.6%');
    }

    public function test_jacquard_drawing_question_is_hidden_for_other_saloons(): void
    {
        $machine = $this->machineData();
        $machine['salon'] = 'Smith';
        $machine['group'] = 'Smith';
        $machine['name'] = 'SMI 201';

        Livewire::test(TestableCrudoMachineDetail::class)
            ->dispatch('open-crudo-detail', telar: '201', machine: $machine)
            ->call('openAudit')
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

    public function test_filter_changes_close_any_retained_machine_detail(): void
    {
        Livewire::test(TestableCrudoMachineDetail::class)
            ->dispatch('open-crudo-detail', telar: '201', machine: $this->machineData())
            ->call('openAudit')
            ->assertSet('selectedTelar', '201')
            ->assertSet('auditModalOpen', true)
            ->dispatch('crudo-filtros-cambiados')
            ->assertSet('selectedTelar', null)
            ->assertSet('machine', null)
            ->assertSet('detail', null)
            ->assertSet('auditModalOpen', false)
            ->assertDontSee('data-crudo-modal', false);
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

    private function stopButtonTag(string $html): string
    {
        $matched = preg_match('/<button(?=[^>]*data-crudo-save-stop)[^>]*>/s', $html, $matches);

        $this->assertSame(1, $matched, 'No se encontró el botón rojo de Guardar paro.');

        return $matches[0];
    }
}

final class FakeCrudoFlogProviderForDetail implements CrudoFlogProvider
{
    public int $calls = 0;

    public function find(?array $program, array $purchBarcodes = []): array
    {
        $this->calls++;

        return [
            'status' => 'ok',
            'source' => 'program_flog',
            'flog' => 'CE-NOV25-LGONZ-F001399',
            'client' => 'C0001 Cliente de prueba',
            'clientAccount' => 'C0001',
            'clientName' => 'Cliente de prueba',
            'itemId' => 'ART-100',
            'inventSizeId' => '100X200',
            'simulationSalesUrl' => 'https://example.test/simulacion-ventas.jpg',
            'simulationDesignUrl' => null,
            'lineMatched' => true,
        ];
    }
}

final class FakeCrudoDashboardProviderForDetail implements CrudoDashboardProvider
{
    public bool $failDetail = false;

    public int $detailCalls = 0;

    public float $detailKilos = 40.0;

    public ?float $detailQualityPercent = null;

    public ?float $detailSecondsPercent = null;

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
            'kilos' => $this->detailKilos,
            'qualityPercent' => $this->detailQualityPercent ?? $this->machine['qualityPercent'],
            'secondsPercent' => $this->detailSecondsPercent ?? $this->machine['secondsPercent'],
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
