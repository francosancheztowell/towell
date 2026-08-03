<?php

declare(strict_types=1);

namespace Tests\Unit\Crudo;

use App\Contracts\Crudo\CrudoReadRepository;
use App\Services\Crudo\CrudoDashboardService;
use App\Services\Crudo\CrudoStatusResolver;
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

        config()->set('crudo.bad_quality_percent', 10);
        config()->set('crudo.daily_kg_target', [
            'Jacquard' => 0,
            'Sin clasificar' => 0,
        ]);
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
        );
    }

    public function test_it_builds_machine_and_global_metrics_without_losing_machines_with_no_data(): void
    {
        $data = $this->service->build($this->date(), 'todos')->toArray();

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
        $this->assertSame([], $this->repository->requestedHeaderIds);
        $this->assertSame(1, $this->repository->aggregateCalls);
    }

    public function test_shift_filter_uses_the_matching_piece_column_and_defect_turn(): void
    {
        $data = $this->service->build($this->date(), '1')->toArray();
        $machine = $data['machines'][0];

        $this->assertSame(60.0, $machine['pieces']);
        $this->assertSame(2.0, $machine['seconds']);
        $this->assertSame(24.0, $machine['kilos']);
        $this->assertSame(['1001'], $this->repository->requestedHeaderIds);
        // El snapshot compartido (build/buildRange) ya no arma defectos/capturas por
        // telar — eso se pide aparte, en vivo, vía machineDetail() al abrir el modal.
        $this->assertSame([], $machine['defects']);
        $this->assertSame([], $machine['captures']);
    }

    public function test_machine_detail_builds_defects_and_captures_for_a_single_telar(): void
    {
        $detail = $this->service->machineDetail('201', $this->date(), $this->date(), '1');

        $this->assertCount(1, $detail['defects']);
        $this->assertSame('01', $detail['defects'][0]['code']);
        $this->assertSame(2.0, $detail['defects'][0]['quantity']);
        $this->assertSame([
            '1' => 2.0,
            '2' => 0.0,
            '3' => 0.0,
            '4' => 0.0,
            'other' => 0.0,
        ], $detail['defects'][0]['turns']);
        $this->assertSame(1, $detail['captureCount']);
        $this->assertSame(24.0, $detail['kilos']);
        $this->assertSame(1, $detail['defectLineCount']);
        $this->assertSame('1001', $detail['captures'][0]['recId']);
        $this->assertSame('PB-1001', $detail['captures'][0]['purchBarcode']);
        $this->assertSame(1, $detail['captures'][0]['defectLineCount']);
        $this->assertSame(60.0, $detail['captures'][0]['pieces']);
        $this->assertSame(2.0, $detail['captures'][0]['seconds']);
    }

    public function test_machine_detail_breaks_defects_down_by_shift(): void
    {
        $detail = $this->service->machineDetail('201', $this->date(), $this->date(), 'todos');
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
            ],
        ];

        $today = new DateTimeImmutable('today', new DateTimeZone('America/Mexico_City'));
        $data = $this->service->build($today, 'todos')->toArray();

        $this->assertSame([
            'orden' => 'ORD-PROG-201',
            'claveModelo' => 'MOD-201-GDE',
            'itemId' => 'AX-201',
            'inventSizeId' => '100X200',
            'flogId' => 'CE-FLOG-201',
            'nombreProducto' => 'Producto de prueba',
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
        $machine = $this->service->build($today, 'todos')->toArray()['machines'][0];

        $this->assertSame('62', $machine['paro']['faultCode']);
        $this->assertSame('REVERSA', $machine['paro']['falla']);
        $this->assertSame('29/07/2026 15:21', $machine['paro']['since']);
        $this->assertStringNotContainsString(':00', $machine['paro']['since']);
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

    public function headersForDate(DateTimeImmutable $date): array
    {
        return $this->headers;
    }

    public function headersForRange(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return $this->headers;
    }

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

    public function machines(): array
    {
        return $this->machines;
    }

    public function activeParos(): array
    {
        return $this->paros;
    }

    public function activePrograms(array $telares): array
    {
        return $this->programs;
    }
}
