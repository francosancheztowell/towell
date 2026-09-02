<?php

declare(strict_types=1);

namespace Tests\Unit\Crudo;

use App\Contracts\Crudo\CrudoReadRepository;
use App\Services\Crudo\CrudoDashboardService;
use App\Services\Crudo\CrudoProductionTargetService;
use App\Services\Crudo\CrudoStatusResolver;
use Carbon\Carbon;
use DateTimeImmutable;
use DateTimeZone;
use Tests\TestCase;

final class CrudoDashboardServiceTest extends TestCase
{
    private FakeCrudoReadRepository $repository;

    private CrudoDashboardService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('crudo.bad_quality_percent', 7);
        config()->set('crudo.salons', [
            'JACQUARD' => 'Jacquard',
        ]);
        config()->set('crudo.catalog_cache_seconds', 0);

        $this->repository = new FakeCrudoReadRepository(
            headers: [
                (object) [
                    'RECID' => '1001',
                    'PRODID' => 'ORD-100',
                    'PURCHBARCODE' => 'PB-1001',
                    'PURCHBARCODEORIG' => 'LOTE-PROV-1001',
                    'ORDENTEJIDO' => '36541',
                    'ORDENURDIDO' => '00929',
                    'TRANSDATE' => '2026-07-28 00:00:00',
                    'TELAR' => '201',
                    'PESO' => 40,
                    'PIEZAST1' => 60,
                    'PIEZAST2' => 0,
                    'PIEZAST3' => 40,
                    'PIEZAST4' => 0,
                    'PIEZASTOTAL' => 100,
                    'SEGUNDASTOTAL' => 5,
                    'EMPLID' => '10',
                    'NAMEEMPLE' => 'Operador uno',
                    'OBSERVACIONES' => '',
                    'MODIFIEDDATE' => '2026-07-28 00:00:00',
                    'MODIFIEDTIME' => 3600,
                ],
            ],
            defects: [
                (object) [
                    'RECID' => '2001',
                    'REFRECID' => '1001',
                    'TURNO' => '1',
                    'CODDEFECTOID' => '01',
                    'CANTIDAD' => 2,
                    'DESCRIP' => 'Error de trama',
                ],
                (object) [
                    'RECID' => '2002',
                    'REFRECID' => '1001',
                    'TURNO' => '3',
                    'CODDEFECTOID' => '09',
                    'CANTIDAD' => 3,
                    'DESCRIP' => 'Marra',
                ],
            ],
            machines: [
                [
                    'telar' => '201',
                    'name' => 'JAC 201',
                    'salon' => 'Jacquard',
                    'group' => 'Jacquard Smith',
                    'sequence' => 1,
                ],
                [
                    'telar' => '202',
                    'name' => 'JAC 202',
                    'salon' => 'Jacquard',
                    'group' => 'Jacquard Smith',
                    'sequence' => 2,
                ],
            ],
        );

        $this->service = new CrudoDashboardService(
            $this->repository,
            new CrudoStatusResolver,
            new CrudoProductionTargetService,
        );
    }

    public function test_it_builds_machine_and_global_metrics_without_losing_machines_with_no_data(): void
    {
        $data = $this->service->build($this->date())->toArray();

        $this->assertCount(2, $data['machines']);
        $this->assertSame('201', $data['machines'][0]['telar']);
        $this->assertSame(100.0, $data['machines'][0]['pieces']);
        $this->assertSame(5.0, $data['machines'][0]['seconds']);
        $this->assertSame(40.0, $data['machines'][0]['kilos']);
        $this->assertSame(95.0, $data['machines'][0]['qualityPercent']);
        $this->assertSame('operating', $data['machines'][0]['state']);
        $this->assertSame('no_data', $data['machines'][1]['state']);
        $this->assertSame(100.0, $data['summary']['pieces']);
        $this->assertSame(5.0, $data['summary']['seconds']);
        $this->assertSame(95.0, $data['summary']['qualityPercent']);
        $this->assertArrayNotHasKey('defects', $data['machines'][0]);
        $this->assertArrayNotHasKey('captures', $data['machines'][0]);
        $this->assertSame([], $this->repository->requestedHeaderIds);
        $this->assertSame(1, $this->repository->aggregateCalls);
    }

    public function test_los_telares_fuera_de_operacion_se_dibujan_pero_no_cuentan_en_metricas(): void
    {
        config()->set('crudo.telares_fuera', ['202']);

        $data = $this->service->build($this->date())->toArray();

        // Sigue apareciendo en el plano...
        $this->assertSame(['201', '202'], array_column($data['machines'], 'telar'));
        // ...pero no suma en el resumen ni en las alertas del salón.
        $this->assertSame(1, $data['summary']['total']);
        $this->assertSame(0, $data['summary']['no_data']);
        $this->assertSame(1, $data['areas'][0]['total']);
    }

    public function test_machine_efficiency_uses_the_last_non_null_revision_of_the_last_captured_turn(): void
    {
        $this->repository->efficiencyLines = [
            (object) [
                'NoTelarId' => '201',
                'Turno' => '1',
                'EficienciaR1' => 80.0,
                'EficienciaR2' => 82.0,
                'EficienciaR3' => 84.0,
                'ObsR1' => 'obs uno',
                'ObsR2' => 'obs dos',
                'ObsR3' => 'obs tres',
            ],
            (object) [
                'NoTelarId' => '201',
                'Turno' => '2',
                'EficienciaR1' => 90.0,
                'EficienciaR2' => 91.0,
                'EficienciaR3' => null,
                'ObsR1' => 'obs uno T2',
                'ObsR2' => 'paro de urdido',
                'ObsR3' => null,
            ],
        ];

        $machines = $this->service->build($this->date())->toArray()['machines'];

        $this->assertSame(91.0, $machines[0]['efficiencyPercent']);
        $this->assertSame('paro de urdido', $machines[0]['efficiencyObs']);
        // El telar sin corte capturado se queda en cero, no hereda calidad.
        $this->assertSame(0.0, $machines[1]['efficiencyPercent']);
    }

    public function test_current_in_process_prod_kg_dia_drives_the_historical_low_kilos_state(): void
    {
        $this->repository->programs = [
            (object) [
                'NoTelarId' => '201',
                'NoProduccion' => 'ORDEN-ACTIVA',
                'ProdKgDia' => 100.0,
            ],
        ];

        $machine = $this->service->build($this->date())->toArray()['machines'][0];

        $this->assertSame(100.0, $machine['expectedKilos']);
        $this->assertSame(100.0, $machine['dailyTargetKilos']);
        $this->assertTrue($machine['hasProductionStandard']);
        $this->assertSame('low_kilos', $machine['state']);
    }

    public function test_paros_refresh_every_build_while_production_stays_cached(): void
    {
        config()->set('crudo.production_cache_seconds', 180);
        // Los paros solo aplican al periodo en curso, así que el pulso es de hoy.
        $today = new DateTimeImmutable('today', new DateTimeZone('America/Mexico_City'));

        $this->service->build($today);
        $this->service->build($today);
        $this->service->build($today);

        // La agregación de TI se pide una sola vez; el paro, en cada pulso.
        $this->assertSame(1, $this->repository->aggregateCalls);
        $this->assertSame(3, $this->repository->parosCalls);
    }

    public function test_machine_detail_builds_defects_and_captures_for_a_single_telar(): void
    {
        $detail = $this->service->machineDetail('201', $this->date(), $this->date());

        // El detalle cubre el día de producción completo: los defectos de todos
        // los turnos de la captura entran, ordenados por cantidad descendente.
        $this->assertCount(2, $detail['defects']);
        $this->assertSame('09', $detail['defects'][0]['code']);
        $this->assertSame(3.0, $detail['defects'][0]['quantity']);
        $this->assertSame([
            '1' => 0.0,
            '2' => 0.0,
            '3' => 3.0,
            '4' => 0.0,
            'other' => 0.0,
        ], $detail['defects'][0]['turns']);
        $this->assertSame(1, $detail['captureCount']);
        $this->assertSame(40.0, $detail['kilos']);
        $this->assertSame(2, $detail['defectLineCount']);
        $this->assertSame('1001', $detail['captures'][0]['recId']);
        $this->assertSame('28/07/2026', $detail['captures'][0]['date']);
        $this->assertSame('PB-1001', $detail['captures'][0]['purchBarcode']);
        $this->assertSame('36541', $detail['captures'][0]['weavingOrder']);
        $this->assertSame('00929', $detail['captures'][0]['warpingOrder']);
        // El ORDENURDIDO de la captura resuelve el lote del programa de urdido.
        $this->assertSame('29734-AP-35', $detail['captures'][0]['supplierLot']);
        $this->assertSame(2, $detail['captures'][0]['defectLineCount']);
        $this->assertSame(100.0, $detail['captures'][0]['pieces']);
        $this->assertSame(5.0, $detail['captures'][0]['seconds']);
    }

    public function test_machine_detail_breaks_defects_down_by_capture_turn(): void
    {
        $detail = $this->service->machineDetail('201', $this->date(), $this->date());
        $defectsByCode = array_column($detail['defects'], null, 'code');

        $this->assertSame(2.0, $defectsByCode['01']['turns']['1']);
        $this->assertSame(0.0, $defectsByCode['01']['turns']['3']);
        $this->assertSame(0.0, $defectsByCode['09']['turns']['1']);
        $this->assertSame(3.0, $defectsByCode['09']['turns']['3']);
        $this->assertSame(5.0, array_sum(array_column($detail['defects'], 'quantity')));
    }

    public function test_active_program_exposes_order_model_key_and_ax_item(): void
    {
        $this->repository->programs = [
            (object) [
                'NoTelarId' => '201',
                'NoProduccion' => 'ORD-PROG-201',
                'TamanoClave' => 'MOD-201-GDE',
                'ItemId' => 'AX-201',
                'InventSizeId' => '100X200',
                'FlogsId' => 'CE-FLOG-201',
                'NombreProducto' => 'Producto de prueba',
                'PesoCrudo' => 482.5,
                'NoMarbete' => 120,
                'TotalRollos' => 45.0,
                'TotalPedido' => 3000.0,
                'SaldoPedido' => 1250.0,
            ],
        ];

        $today = new DateTimeImmutable('today', new DateTimeZone('America/Mexico_City'));
        $data = $this->service->build($today)->toArray();

        $this->assertSame([
            'orden' => 'ORD-PROG-201',
            'claveModelo' => 'MOD-201-GDE',
            'itemId' => 'AX-201',
            'inventSizeId' => '100X200',
            'flogId' => 'CE-FLOG-201',
            'nombreProducto' => 'Producto de prueba',
            'pesoCrudo' => 482.5,
            'marbetes' => 120.0,
            'totalRollos' => 45.0,
            'totalPedido' => 3000.0,
            'saldoPedido' => 1250.0,
        ], $data['machines'][0]['programa']);
    }

    public function test_active_stop_uses_the_fault_name_and_formats_the_date_without_seconds(): void
    {
        $this->repository->paros = [
            (object) [
                'MaquinaId' => '201',
                'Falla' => '62',
                'Descripcion' => 'REVERSA',
                'NomEmpl' => 'Operador de prueba',
                'Fecha' => '2026-07-29',
                'Hora' => '15:21:00',
            ],
        ];

        $today = new DateTimeImmutable('today', new DateTimeZone('America/Mexico_City'));
        $machine = $this->service->build($today)->toArray()['machines'][0];

        $this->assertSame('62', $machine['paro']['faultCode']);
        $this->assertSame('REVERSA', $machine['paro']['falla']);
        $this->assertSame('29/07/2026 15:21', $machine['paro']['since']);
        $this->assertStringNotContainsString(':00', $machine['paro']['since']);
    }

    public function test_varios_paros_activos_del_mismo_telar_se_cuentan_y_se_listan(): void
    {
        // Llegan ordenados por fecha y hora descendente: el encabezado es el más reciente.
        $this->repository->paros = [
            (object) [
                'MaquinaId' => '201',
                'Falla' => '62',
                'Descripcion' => 'REVERSA',
                'TipoFallaId' => 'MECANICO',
                'NomEmpl' => 'Juan Pérez',
                'Fecha' => '2026-07-29',
                'Hora' => '15:21:00',
            ],
            (object) [
                'MaquinaId' => '201',
                'Falla' => '80',
                'Descripcion' => 'CORTO',
                'TipoFallaId' => 'ELECTRICO',
                'NomEmpl' => 'Ana López',
                'Fecha' => '2026-07-29',
                'Hora' => '11:02:00',
            ],
        ];

        $today = new DateTimeImmutable('today', new DateTimeZone('America/Mexico_City'));
        $paro = $this->service->build($today)->toArray()['machines'][0]['paro'];

        $this->assertSame(2, $paro['count']);
        $this->assertSame('REVERSA', $paro['falla']);
        $this->assertSame('29/07/2026 15:21', $paro['since']);
        $this->assertCount(2, $paro['todos']);
        $this->assertSame(['REVERSA', 'CORTO'], array_column($paro['todos'], 'falla'));
        $this->assertSame(['MECANICO', 'ELECTRICO'], array_column($paro['todos'], 'tipo'));
        $this->assertSame(['Juan Pérez', 'Ana López'], array_column($paro['todos'], 'reportedBy'));
    }

    public function test_active_stop_still_shows_between_midnight_and_six_thirty(): void
    {
        // El día de producción corre 06:30-06:30: a la 01:00 el tablero abre en
        // el día de producción de ayer, que no coincide con el día de calendario
        // de "hoy". Antes del fix, comparar contra el calendario dejaba
        // $includesToday en false y el paro activo desaparecía del tablero.
        Carbon::setTestNow(Carbon::parse('2026-07-29 01:00:00', 'America/Mexico_City'));

        $this->repository->paros = [
            (object) [
                'MaquinaId' => '201',
                'Falla' => '62',
                'Descripcion' => 'REVERSA',
                'NomEmpl' => 'Operador de prueba',
                'Fecha' => '2026-07-28',
                'Hora' => '23:00:00',
            ],
        ];

        $productionDay = new DateTimeImmutable('2026-07-28', new DateTimeZone('America/Mexico_City'));
        $machine = $this->service->build($productionDay)->toArray()['machines'][0];

        $this->assertSame(1, $this->repository->parosCalls);
        $this->assertSame('REVERSA', $machine['paro']['falla']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function date(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-07-28', new DateTimeZone('America/Mexico_City'));
    }
}

final class FakeCrudoReadRepository implements CrudoReadRepository
{
    /** @var list<int|string> */
    public array $requestedHeaderIds = [];

    public int $aggregateCalls = 0;

    public int $parosCalls = 0;

    /** @var list<object> */
    public array $programs = [];

    /** @var list<object> */
    public array $paros = [];

    /**
     * @param  list<object>  $headers
     * @param  list<object>  $defects
     * @param  list<array<string, int|string|null>>  $machines
     */
    public function __construct(
        private readonly array $headers,
        private readonly array $defects,
        private readonly array $machines,
    ) {}

    public function aggregateHeadersForRange(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $this->aggregateCalls++;
        $grouped = [];

        foreach ($this->headers as $header) {
            $telar = trim((string) $header->TELAR);
            $grouped[$telar] ??= (object) [
                'TELAR' => $telar,
                'captureCount' => 0,
                'pieces' => 0.0,
                'seconds' => 0.0,
                'kilos' => 0.0,
            ];
            $grouped[$telar]->captureCount++;
            $grouped[$telar]->pieces += (float) $header->PIEZASTOTAL;
            $grouped[$telar]->seconds += (float) $header->SEGUNDASTOTAL;
            $grouped[$telar]->kilos += (float) $header->PESO;
        }

        return array_values($grouped);
    }

    public function headersForTelarInRange(string $telar, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return array_values(array_filter(
            $this->headers,
            static fn (object $header): bool => trim((string) $header->TELAR) === $telar,
        ));
    }

    public function defectsForHeaders(array $headerRecIds): array
    {
        $this->requestedHeaderIds = $headerRecIds;

        return $this->defects;
    }

    /** @var list<object> */
    public array $defectTotals = [];

    public function defectTotalsForRange(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return $this->defectTotals;
    }

    /** @var array<string, string> */
    public array $supplierLots = ['00929' => '29734-AP-35'];

    /** @var list<string> */
    public array $requestedWarpingOrders = [];

    public function supplierLotsByWarpingOrder(array $warpingOrders): array
    {
        $this->requestedWarpingOrders = $warpingOrders;

        return array_intersect_key($this->supplierLots, array_flip($warpingOrders));
    }

    public function machines(): array
    {
        return $this->machines;
    }

    /** @var list<string> */
    public array $requestedParosTelares = [];

    public function activeParos(array $telares = []): array
    {
        $this->parosCalls++;
        $this->requestedParosTelares = $telares;

        return $this->paros;
    }

    public function activePrograms(array $telares): array
    {
        return $this->programs;
    }

    /** @var list<object> */
    public array $efficiencyLines = [];

    public function efficiencyLinesForRange(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return $this->efficiencyLines;
    }
}
