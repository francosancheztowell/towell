<?php

declare(strict_types=1);

namespace Tests\Unit\Crudo;

use App\Contracts\Crudo\CrudoDashboardProvider;
use App\Contracts\Crudo\CrudoFlogProvider;
use App\Livewire\Crudo\MachineDetail;
use App\Livewire\Crudo\MachineFlogSummary;
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

        config()->set('crudo.bad_quality_percent', 7);
        $this->provider = new FakeCrudoDashboardProviderForDetail($this->machineData());
        $this->flogProvider = new FakeCrudoFlogProviderForDetail;
        $this->app->instance(CrudoDashboardProvider::class, $this->provider);
        $this->app->instance(CrudoFlogProvider::class, $this->flogProvider);
    }

    public function test_it_opens_and_loads_machine_detail(): void
    {
        $component = Livewire::test(TestableCrudoMachineDetail::class)
            ->dispatch('open-crudo-detail', telar: '201', machine: $this->machineData())
            ->assertSet('selectedTelar', '201')
            ->assertSet('detailLoaded', false)
            ->assertSee('wire:init="loadDetail"', false)
            ->assertSee('Consultando capturas y defectos')
            ->assertSee('crudo-modal-overview', false)
            ->assertSee('Sin alertas en este periodo')
            ->assertSee('Agregar auditoría')
            ->assertDontSee('Órdenes y turnos');

        $this->assertSame(0, $this->provider->detailCalls);
        $this->assertSame(0, $this->flogProvider->calls);

        $component
            ->call('loadDetail')
            ->assertSet('detailLoaded', true)
            ->assertSet('detail.kilos', 40.0)
            ->assertSee('crudo-modal-overview', false)
            ->assertSee('crudo-modal-identity-card', false)
            ->assertSee('crudo-process-defects-panel', false)
            ->assertSee('data-crudo-detail-modal', false)
            ->assertDontSee('data-crudo-audit-modal', false)
            ->assertSee('Órdenes y turnos')
            ->assertSee('meta a esta hora')
            ->assertSee('Fecha')
            ->assertSee('No. Rollo')
            ->assertSee('Orden')
            ->assertDontSee('Orden tejido')
            ->assertSee('PB-1001')
            ->assertSee('28/07/2026')
            ->assertSee('36541')
            ->assertSee('Kg')
            ->assertSee('Pzas')
            ->assertSee('2das')
            ->assertDontSee('<th>Urdido</th>', false)
            ->assertSee('Lote')
            ->assertSee('29734-AP-35')
            ->assertDontSee('Peso (kg)')
            ->assertSee('Error de trama')
            ->assertSee('Defectos registrados')
            ->assertSee('crudo-defect-table', false)
            ->assertSee('Defectos consultados de producción y desglose por turno')
            ->assertSee('T1')
            ->assertSee('T2')
            ->assertSee('T3')
            ->assertSee('T4')
            ->assertSee('Datos del Flog')
            ->assertSee('wire:init="load"', false)
            ->assertSee('Consultando el Flog relacionado')
            ->assertDontSee('crudo-eyebrow', false)
            ->assertSee('Agregar auditoría')
            ->assertSee('data-crudo-open-audit', false)
            ->assertDontSee('data-crudo-audit-content', false)
            ->assertDontSee('Checklist de telares reincidentes de defectos')
            ->call('openAudit')
            ->assertSet('auditModalOpen', true)
            ->assertSee('crudo-audit-inline', false)
            ->assertSee('data-crudo-detail-modal', false)
            ->assertDontSee('crudo-audit-history-panel', false)
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
            ->assertSee('¿El dibujo, picado o cenefa de Jacquard está bien definido?')
            ->assertSee('¿Es correcta la identificación en el julio del lote de hilo y proveedor?')
            ->assertSee('crudo-audit-table', false)
            ->assertSee('crudo-audit-observations', false)
            ->assertSee('Observaciones')
            ->assertSee('Observaciones de la auditoría')
            ->call('close')
            ->assertSet('selectedTelar', null)
            ->assertSet('auditModalOpen', false)
            ->assertDontSee('crudo-modal-overview', false);

        // El detalle sigue visible con el formulario abierto: se reconsulta al abrirlo.
        $this->assertSame(2, $this->provider->detailCalls);
        $this->assertSame(0, $this->flogProvider->calls);
    }

    public function test_open_uses_the_dashboard_context_and_pauses_polling_until_close(): void
    {
        Livewire::test(TestableCrudoMachineDetail::class)
            ->dispatch(
                'open-crudo-detail',
                telar: '201',
                machine: $this->machineData(),
                fecha: '2026-08-04',
                fechaInicio: '2026-08-01',
                fechaFin: '2026-08-03',
                modo: 'rango',
            )
            ->assertSet('fecha', '2026-08-04')
            ->assertSet('fechaInicio', '2026-08-01')
            ->assertSet('fechaFin', '2026-08-03')
            ->assertSet('modo', 'rango')
            ->assertDispatched('crudo-interaction-opened')
            ->call('close')
            ->assertDispatched('crudo-interaction-closed');
    }

    public function test_flog_load_is_isolated_from_the_machine_modal(): void
    {
        $program = ['flogId' => 'CE-NOV25-LGONZ-F001399'];

        Livewire::test(MachineFlogSummary::class, [
            'program' => $program,
            'purchBarcodes' => [' PB-1001 ', 'PB-1001', 'PB-1002'],
        ])
            ->assertSet('loaded', false)
            ->assertSet('purchBarcodes', ['PB-1001', 'PB-1002'])
            ->assertSee('Consultando el Flog relacionado')
            ->call('load')
            ->assertSet('loaded', true)
            ->assertSee('CE-NOV25-LGONZ-F001399')
            ->assertSee('C0001 Cliente de prueba')
            ->assertDontSee('Artículo')
            ->assertDontSee('Tamaño')
            ->assertDontSee('ART-100')
            ->assertDontSee('100X200')
            ->assertSee('Simulación')
            ->assertSee('https://example.test/simulacion-ventas.jpg', false);

        $this->assertSame(1, $this->flogProvider->calls);
        $this->assertSame($program, $this->flogProvider->lastProgram);
        $this->assertSame(['PB-1001', 'PB-1002'], $this->flogProvider->lastBarcodes);
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

    public function test_audit_form_replaces_history_panels_inside_the_detail_modal(): void
    {
        $component = Livewire::test(TestableCrudoMachineDetail::class)
            ->dispatch('open-crudo-detail', telar: '201', machine: $this->machineData());

        $html = $component->html();

        $this->assertSame(1, preg_match_all('/data-crudo-modal(?:\s|>)/', $html));
        $this->assertStringContainsString('crudo-audit-history-panel', $html);
        $this->assertStringContainsString('crudo-paros-history-panel', $html);
        $this->assertStringNotContainsString('data-crudo-save-audit', $html);

        $component->call('openAudit')->assertSet('auditModalOpen', true);
        $html = $component->html();

        // Un solo modal: el formulario ocupa el lugar de auditorías de hoy y paros.
        $this->assertSame(1, preg_match_all('/data-crudo-modal(?:\s|>)/', $html));
        $this->assertStringContainsString('data-crudo-detail-modal', $html);
        $this->assertStringContainsString('crudo-modal-overview', $html);
        $this->assertStringNotContainsString('crudo-audit-history-panel', $html);
        $this->assertStringNotContainsString('crudo-paros-history-panel', $html);
        $this->assertStringContainsString('data-crudo-save-audit', $html);
        $this->assertStringContainsString('data-crudo-save-stop', $html);

        $component->call('closeAudit')->assertSet('auditModalOpen', false);
        $html = $component->html();

        $this->assertStringContainsString('crudo-audit-history-panel', $html);
        $this->assertStringNotContainsString('data-crudo-save-audit', $html);
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

    public function test_el_filtro_de_periodo_de_paros_no_depende_de_livewire(): void
    {
        $html = Livewire::test(TestableCrudoMachineDetail::class)
            ->dispatch('open-crudo-detail', telar: '201', machine: $this->machineData())
            ->call('loadDetail')
            ->html();

        // Radios nativos: el periodo se cambia en el DOM, sin round-trip.
        $this->assertStringContainsString('class="crudo-paros-rango-input"', $html);
        $this->assertStringContainsString('value="2d"', $html);
        $this->assertStringContainsString('value="semana"', $html);
        $this->assertStringContainsString('value="mes"', $html);
        $this->assertStringNotContainsString('parosRango', $html);
    }

    public function test_register_permission_hides_the_button_and_blocks_the_livewire_action(): void
    {
        Livewire::test(DeniedCrudoMachineDetail::class)
            ->dispatch('open-crudo-detail', telar: '201', machine: $this->machineData())
            ->assertDontSee('Agregar auditoría')
            ->assertDontSee('data-crudo-open-audit', false)
            ->call('openAudit')
            ->assertForbidden()
            ->assertSet('auditModalOpen', false);
    }

    public function test_save_actions_are_rendered_only_in_the_audit_modal(): void
    {
        $component = Livewire::test(TestableCrudoMachineDetail::class)
            ->dispatch('open-crudo-detail', telar: '201', machine: $this->machineData());

        $this->assertStringNotContainsString('data-crudo-save-stop', $component->html());

        $component->call('openAudit');

        $this->assertStringNotContainsString('hidden', $this->stopButtonTag($component->html()));
        $this->assertStringContainsString('disabled', $this->auditButtonTag($component->html()));
        $this->assertStringContainsString('aria-disabled="true"', $this->auditButtonTag($component->html()));
        $this->assertStringContainsString('disabled', $this->stopButtonTag($component->html()));
        $this->assertStringContainsString('aria-disabled="true"', $this->stopButtonTag($component->html()));
        $this->assertStringContainsString('Guardar paro', $component->html());
        $this->assertMatchesRegularExpression(
            '/id="crudo-audit-content".*data-crudo-save-audit.*data-crudo-save-stop/s',
            $component->html(),
        );
    }

    public function test_refresh_keeps_the_audit_form_open(): void
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
    }

    public function test_successful_audit_event_returns_to_the_history_panels(): void
    {
        Livewire::test(TestableCrudoMachineDetail::class)
            ->dispatch('open-crudo-detail', telar: '201', machine: $this->machineData())
            ->call('openAudit')
            ->assertSet('selectedTelar', '201')
            ->assertSet('auditModalOpen', true)
            ->dispatch('crudo-auditoria-guardada')
            ->assertSet('selectedTelar', '201')
            ->assertSet('auditModalOpen', false)
            ->assertSee('data-crudo-detail-modal', false)
            ->assertSee('crudo-audit-history-panel', false)
            ->assertDontSee('data-crudo-save-audit', false);
    }

    public function test_product_name_is_in_the_title_and_order_is_next_to_ax_key(): void
    {
        $machine = $this->machineData();
        $machine['programa'] = [
            'orden' => 'ORD-PROG-201',
            'claveModelo' => 'MOD-201-GDE',
            'itemId' => 'AX-201',
            'inventSizeId' => '100X200',
            'flogId' => 'CE-NOV25-LGONZ-F001399',
            'nombreProducto' => 'Producto de prueba',
            'marbetes' => 12,
            'totalRollos' => 99,
            'totalPedido' => 7488,
            'saldoPedido' => 2456,
        ];

        $component = Livewire::test(TestableCrudoMachineDetail::class)
            ->dispatch('open-crudo-detail', telar: '201', machine: $machine)
            ->assertSee('crudo-modal-product', false)
            ->assertSee('Producto de prueba')
            ->assertSee('Orden')
            ->assertSee('ORD-PROG-201')
            ->assertSee('Clave AX')
            ->assertSee('AX-201')
            ->assertSee('crudo-modal-program-field', false)
            ->assertSee('title="ORD-PROG-201"', false)
            ->assertSee('title="AX-201"', false)
            ->assertDontSee('<dt>Nombre</dt>', false)
            ->assertDontSee('Clave modelo')
            ->assertDontSee('Clave ORD-PROG-201')
            ->assertSee('Marbetes')
            ->assertSee('>12</dd>', false)
            ->assertSee('Saldo')
            ->assertSee('>2,456</dd>', false)
            ->assertDontSee('>Rollos</dt>', false)
            ->assertDontSee('>Pedido</dt>', false)
            ->assertDontSee('>99</dd>', false)
            ->assertDontSee('>7,488</dd>', false);

        $html = $component->html();
        $this->assertLessThan(strpos($html, 'Clave AX'), strpos($html, '<dt>Orden</dt>'));
    }

    public function test_a_negative_saldo_pedido_is_highlighted_in_the_modal(): void
    {
        $machine = $this->machineData();
        $machine['programa'] = [
            'orden' => 'ORD-PROG-201',
            'itemId' => 'AX-201',
            'marbetes' => 12,
            'saldoPedido' => -75,
        ];

        Livewire::test(TestableCrudoMachineDetail::class)
            ->dispatch('open-crudo-detail', telar: '201', machine: $machine)
            ->assertSee('is-saldo-negativo', false)
            ->assertSee('>-75<', false)
            ->assertSee('fa-triangle-exclamation', false)
            ->assertSee('(saldo negativo)');
    }

    public function test_a_positive_saldo_pedido_is_not_highlighted_in_the_modal(): void
    {
        $machine = $this->machineData();
        $machine['programa'] = [
            'orden' => 'ORD-PROG-201',
            'itemId' => 'AX-201',
            'marbetes' => 12,
            'saldoPedido' => 75,
        ];

        Livewire::test(TestableCrudoMachineDetail::class)
            ->dispatch('open-crudo-detail', telar: '201', machine: $machine)
            ->assertDontSee('is-saldo-negativo', false)
            ->assertSee('>75</dd>', false);
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
            'tipo' => 'Mecánico',
            'count' => 2,
            'reportedBy' => 'Calidad',
            'depto' => 'Mantenimiento',
            'since' => '29/07/2026 15:21',
        ];

        Livewire::test(TestableCrudoMachineDetail::class)
            ->dispatch('open-crudo-detail', telar: '201', machine: $machine)
            ->assertSee('crudo-modal-status-card has-paro', false)
            ->assertSee('REVERSA')
            ->assertSee('Mecánico')
            ->assertSee('2 paros activos')
            ->assertSee('Calidad')
            ->assertSee('Mantenimiento')
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
            ->call('loadDetail')
            ->assertSee('41 kg')
            ->assertSee('51 kg')
            ->assertSee('meta a esta hora')
            ->assertSee('95%')
            ->assertSee('5% 2das')
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
            ->assertDontSee('¿El dibujo, picado o cenefa de Jacquard está bien definido?')
            ->assertSee('¿Es correcta la identificación en el julio del lote de hilo y proveedor?');
    }

    public function test_refresh_keeps_last_detail_if_sql_fails(): void
    {
        $component = Livewire::test(TestableCrudoMachineDetail::class)
            ->dispatch('open-crudo-detail', telar: '201', machine: $this->machineData())
            ->call('loadDetail')
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

    public function test_initial_filter_context_sync_does_not_close_the_just_opened_detail(): void
    {
        $today = now(config('app.timezone'))->format('Y-m-d');

        Livewire::test(TestableCrudoMachineDetail::class)
            ->dispatch('open-crudo-detail', telar: '201', machine: $this->machineData())
            ->assertSet('selectedTelar', '201')
            ->dispatch(
                'crudo-filtros-cambiados',
                fecha: $today,
                fechaInicio: $today,
                fechaFin: $today,
                modo: 'dia',
            )
            ->assertSet('selectedTelar', '201')
            ->assertSet('machine.telar', '201')
            ->assertSee('data-crudo-detail-modal', false);
    }

    public function test_next_open_uses_the_latest_day_received_from_the_dashboard(): void
    {
        $component = Livewire::test(TestableCrudoMachineDetail::class)
            ->dispatch(
                'crudo-filtros-cambiados',
                fecha: '2026-08-03',
                fechaInicio: '2026-08-03',
                fechaFin: '2026-08-03',
                modo: 'dia',
            )
            ->dispatch('open-crudo-detail', telar: '201', machine: $this->machineData())
            ->call('loadDetail');

        $this->assertSame([
            'telar' => '201',
            'from' => '2026-08-03',
            'to' => '2026-08-03',
        ], $this->provider->detailArguments[array_key_last($this->provider->detailArguments)]);

        $component
            ->dispatch(
                'crudo-filtros-cambiados',
                fecha: '2026-08-04',
                fechaInicio: '2026-08-04',
                fechaFin: '2026-08-04',
                modo: 'dia',
            )
            ->dispatch('open-crudo-detail', telar: '201', machine: $this->machineData())
            ->call('loadDetail');

        $this->assertSame([
            'telar' => '201',
            'from' => '2026-08-04',
            'to' => '2026-08-04',
        ], $this->provider->detailArguments[array_key_last($this->provider->detailArguments)]);
    }

    public function test_next_open_uses_the_latest_range_received_from_the_dashboard(): void
    {
        Livewire::test(TestableCrudoMachineDetail::class)
            ->dispatch(
                'crudo-filtros-cambiados',
                fecha: '2026-08-04',
                fechaInicio: '2026-08-01',
                fechaFin: '2026-08-03',
                modo: 'rango',
            )
            ->dispatch('open-crudo-detail', telar: '201', machine: $this->machineData())
            ->call('loadDetail')
            ->assertSee('meta del rango');

        $this->assertSame([
            'telar' => '201',
            'from' => '2026-08-01',
            'to' => '2026-08-03',
        ], $this->provider->detailArguments[array_key_last($this->provider->detailArguments)]);
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
            'dailyTargetKilos' => 3.0,
            'productionStandardStatus' => 'complete',
            'hasProductionStandard' => true,
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
                'supplierLot' => '29734-AP-35',
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

    private function auditButtonTag(string $html): string
    {
        $matched = preg_match('/<button(?=[^>]*data-crudo-save-audit)[^>]*>/s', $html, $matches);

        $this->assertSame(1, $matched, 'No se encontró el botón Guardar auditoría.');

        return $matches[0];
    }
}

final class FakeCrudoFlogProviderForDetail implements CrudoFlogProvider
{
    public int $calls = 0;

    /** @var array<string, mixed>|null */
    public ?array $lastProgram = null;

    /** @var list<string> */
    public array $lastBarcodes = [];

    public function find(?array $program, array $purchBarcodes = []): array
    {
        $this->calls++;
        $this->lastProgram = $program;
        $this->lastBarcodes = $purchBarcodes;

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

    /** @var list<array{telar: string, from: string, to: string}> */
    public array $detailArguments = [];

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
        bool $forceRefresh = false,
        ?DateTimeImmutable $to = null,
        bool $allowRebuild = true,
    ): array {
        return [];
    }

    public function detail(string $telar, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $this->detailCalls++;
        $this->detailArguments[] = [
            'telar' => $telar,
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
        ];

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

    protected function canRegisterAudit(): bool
    {
        return true;
    }

    protected function authorizeRegisterAudit(): void {}
}

final class DeniedCrudoMachineDetail extends MachineDetail
{
    protected function canRegisterAudit(): bool
    {
        return false;
    }
}
