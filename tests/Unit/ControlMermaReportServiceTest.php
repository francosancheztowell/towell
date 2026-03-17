<?php

namespace Tests\Unit;

use App\Models\Engomado\EngProgramaEngomado;
use App\Models\Urdido\UrdJuliosOrden;
use App\Models\Urdido\UrdProgramaUrdido;
use App\Services\Engomado\ControlMermaReportService;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

class ControlMermaReportServiceTest extends TestCase
{
    use UsesSqlsrvSqlite;

    private ControlMermaReportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqlsrvSqlite();
        $this->createControlMermaTables();
        $this->service = app(ControlMermaReportService::class);
    }

    public function test_build_maps_core_fields_and_sequences_per_machine(): void
    {
        UrdProgramaUrdido::create(['Folio' => '00011', 'Cuenta' => '3776', 'Calibre' => 12]);
        UrdProgramaUrdido::create(['Folio' => '00012', 'Cuenta' => '4000', 'Calibre' => 14]);
        UrdProgramaUrdido::create(['Folio' => '00013', 'Cuenta' => '5000', 'Calibre' => 16]);

        UrdJuliosOrden::create(['Folio' => '00011', 'Julios' => 5, 'Obs' => 'RB']);
        UrdJuliosOrden::create(['Folio' => '00011', 'Julios' => 2, 'Obs' => 'MS']);

        EngProgramaEngomado::create([
            'Folio' => '00011',
            'Cuenta' => '3776',
            'Calibre' => 12,
            'MaquinaEng' => 'West Point 2',
            'Merma' => 8,
            'MermaGoma' => 2,
            'Status' => 'Finalizado',
            'FechaFinaliza' => '2026-03-10',
        ]);

        EngProgramaEngomado::create([
            'Folio' => '00012',
            'Cuenta' => '4000',
            'Calibre' => 14,
            'MaquinaEng' => 'West Point 2',
            'Merma' => 4,
            'MermaGoma' => 1,
            'Status' => 'Finalizado',
            'FechaFinaliza' => '2026-03-11',
        ]);

        EngProgramaEngomado::create([
            'Folio' => '00013',
            'Cuenta' => '5000',
            'Calibre' => 16,
            'MaquinaEng' => 'West Point 3',
            'Merma' => 6,
            'MermaGoma' => 1.5,
            'Status' => 'Finalizado',
            'FechaFinaliza' => '2026-03-11',
        ]);

        $rows = $this->service->build('2026-03-01', '2026-03-31');

        $this->assertCount(3, $rows);
        $this->assertSame('00011', $rows[0]['folio']);
        $this->assertSame('WP2', $rows[0]['maquina_label']);
        $this->assertSame(1, $rows[0]['maquina_seq']);
        $this->assertSame('WP2  1', $rows[0]['maquina_display']);
        $this->assertSame('3776', $rows[0]['cuenta']);
        $this->assertSame(12.0, $rows[0]['hilo']);
        $this->assertSame(8.0, $rows[0]['merma_sin_goma']);
        $this->assertSame(2.0, $rows[0]['merma_con_goma']);
        $this->assertSame('RB', $rows[0]['urd_slots'][0]['label']);
        $this->assertSame(5, $rows[0]['urd_slots'][0]['count']);
        $this->assertSame('MS', $rows[0]['urd_slots'][1]['label']);
        $this->assertSame(2, $rows[0]['urd_slots'][1]['count']);
        $this->assertSame(2, $rows[1]['maquina_seq']);
        $this->assertSame('WP3  1', $rows[2]['maquina_display']);
    }

    public function test_build_groups_urd_slots_and_collapses_overflow_into_otros(): void
    {
        UrdProgramaUrdido::create(['Folio' => '00021', 'Cuenta' => '3776', 'Calibre' => 12]);

        UrdJuliosOrden::create(['Folio' => '00021', 'Julios' => 5, 'Obs' => 'RB']);
        UrdJuliosOrden::create(['Folio' => '00021', 'Julios' => 2, 'Obs' => 'MS']);
        UrdJuliosOrden::create(['Folio' => '00021', 'Julios' => 1, 'Obs' => null]);
        UrdJuliosOrden::create(['Folio' => '00021', 'Julios' => 4, 'Obs' => 'DP']);

        EngProgramaEngomado::create([
            'Folio' => '00021',
            'Cuenta' => '3776',
            'Calibre' => 12,
            'MaquinaEng' => 'West Point 2',
            'Merma' => 8,
            'MermaGoma' => 2,
            'Status' => 'Finalizado',
            'FechaFinaliza' => '2026-03-10',
        ]);

        $rows = $this->service->build('2026-03-01', '2026-03-31');

        $this->assertCount(1, $rows);
        $this->assertSame('RB', $rows[0]['urd_slots'][0]['label']);
        $this->assertSame(5, $rows[0]['urd_slots'][0]['count']);
        $this->assertSame('MS', $rows[0]['urd_slots'][1]['label']);
        $this->assertSame(2, $rows[0]['urd_slots'][1]['count']);
        $this->assertSame('OTROS', $rows[0]['urd_slots'][2]['label']);
        $this->assertSame(5, $rows[0]['urd_slots'][2]['count']);
    }
}
