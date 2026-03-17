<?php

namespace Tests\Unit;

use App\Models\Tejido\TejEficiencia;
use App\Models\Tejido\TejEficienciaLine;
use App\Models\Tejido\TejMarcas;
use App\Models\Tejido\TejMarcasLine;
use App\Services\Tejido\PromedioParosEficienciaReportService;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

class PromedioParosEficienciaReportServiceTest extends TestCase
{
    use UsesSqlsrvSqlite;

    private PromedioParosEficienciaReportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqlsrvSqlite();
        $this->createTejidoPromedioParosTables();
        $this->service = app(PromedioParosEficienciaReportService::class);
    }

    public function test_build_uses_latest_finalized_marcas_and_latest_cortes_per_date_turn(): void
    {
        TejMarcas::create([
            'Folio' => 'M-FIN-OLD',
            'Date' => '2026-03-03',
            'Turno' => 1,
            'Status' => 'Finalizado',
        ]);

        TejMarcas::create([
            'Folio' => 'M-PROC',
            'Date' => '2026-03-03',
            'Turno' => 1,
            'Status' => 'En Proceso',
        ]);

        TejMarcas::create([
            'Folio' => 'M-FIN-NEW',
            'Date' => '2026-03-03',
            'Turno' => 1,
            'Status' => 'Finalizado',
        ]);

        TejMarcas::where('Folio', 'M-FIN-OLD')->update([
            'created_at' => '2026-03-03 08:00:00',
            'updated_at' => '2026-03-03 08:00:00',
        ]);
        TejMarcas::where('Folio', 'M-PROC')->update([
            'created_at' => '2026-03-03 12:00:00',
            'updated_at' => '2026-03-03 12:00:00',
        ]);
        TejMarcas::where('Folio', 'M-FIN-NEW')->update([
            'created_at' => '2026-03-03 10:00:00',
            'updated_at' => '2026-03-03 10:00:00',
        ]);

        TejMarcasLine::create([
            'Folio' => 'M-FIN-OLD',
            'Date' => '2026-03-03',
            'Turno' => 1,
            'NoTelarId' => '201',
            'Eficiencia' => 80,
            'Trama' => 1,
            'Pie' => 1,
            'Rizo' => 1,
            'Otros' => 1,
            'Marcas' => 10,
        ]);

        TejMarcasLine::create([
            'Folio' => 'M-FIN-NEW',
            'Date' => '2026-03-03',
            'Turno' => 1,
            'NoTelarId' => '201',
            'Eficiencia' => 95.5,
            'Trama' => 12,
            'Pie' => 3,
            'Rizo' => 5,
            'Otros' => 7,
            'Marcas' => 120,
        ]);

        TejMarcasLine::where('Folio', 'M-FIN-OLD')->update([
            'created_at' => '2026-03-03 08:00:00',
            'updated_at' => '2026-03-03 08:00:00',
        ]);
        TejMarcasLine::where('Folio', 'M-FIN-NEW')->update([
            'created_at' => '2026-03-03 10:00:00',
            'updated_at' => '2026-03-03 10:00:00',
        ]);

        TejEficiencia::create([
            'Folio' => 'C-OLD',
            'Date' => '2026-03-03',
            'Turno' => 1,
            'Status' => 'En Proceso',
        ]);

        TejEficiencia::create([
            'Folio' => 'C-NEW',
            'Date' => '2026-03-03',
            'Turno' => 1,
            'Status' => 'Finalizado',
        ]);

        TejEficiencia::where('Folio', 'C-OLD')->update([
            'created_at' => '2026-03-03 09:00:00',
            'updated_at' => '2026-03-03 09:00:00',
        ]);
        TejEficiencia::where('Folio', 'C-NEW')->update([
            'created_at' => '2026-03-03 12:00:00',
            'updated_at' => '2026-03-03 12:00:00',
        ]);

        TejEficienciaLine::create([
            'Folio' => 'C-OLD',
            'Date' => '2026-03-03',
            'Turno' => 1,
            'NoTelarId' => '201',
            'RpmR1' => 200,
            'RpmR2' => 220,
            'RpmR3' => 240,
        ]);

        TejEficienciaLine::create([
            'Folio' => 'C-NEW',
            'Date' => '2026-03-03',
            'Turno' => 1,
            'NoTelarId' => '201',
            'RpmR1' => 300,
            'RpmR2' => 330,
        ]);

        TejEficienciaLine::create([
            'Folio' => 'C-NEW',
            'Date' => '2026-03-03',
            'Turno' => 1,
            'NoTelarId' => '299',
            'RpmR1' => 280,
        ]);

        TejEficienciaLine::where('Folio', 'C-OLD')->update([
            'created_at' => '2026-03-03 09:00:00',
            'updated_at' => '2026-03-03 09:00:00',
        ]);
        TejEficienciaLine::where('Folio', 'C-NEW')->where('NoTelarId', '201')->update([
            'created_at' => '2026-03-03 12:00:00',
            'updated_at' => '2026-03-03 12:00:00',
        ]);
        TejEficienciaLine::where('Folio', 'C-NEW')->where('NoTelarId', '299')->update([
            'created_at' => '2026-03-03 12:05:00',
            'updated_at' => '2026-03-03 12:05:00',
        ]);

        $report = $this->service->build('2026-03-03', '2026-03-03');

        $this->assertCount(1, $report['days']);
        $this->assertSame('2026-03-03', $report['days'][0]['date_key']);
        $this->assertSame('MA 1T', $report['days'][0]['turn_labels'][1]);

        $metrics201 = $report['metrics']['2026-03-03'][1]['201'];
        $this->assertSame(12, $metrics201['paros_trama']);
        $this->assertSame(3, $metrics201['paros_urdimbre']);
        $this->assertSame(5, $metrics201['paros_rizo']);
        $this->assertSame(7, $metrics201['paros_otros']);
        $this->assertSame(120, $metrics201['marcas']);
        $this->assertSame(96, $metrics201['eficiencia']);
        $this->assertSame(315.0, $metrics201['rpm']);

        $metrics299 = $report['metrics']['2026-03-03'][1]['299'];
        $this->assertNull($metrics299['paros_trama']);
        $this->assertNull($metrics299['eficiencia']);
        $this->assertSame(280.0, $metrics299['rpm']);
    }

    public function test_build_averages_rpm_only_with_captured_values_and_leaves_efficiency_empty_without_marcas(): void
    {
        TejEficiencia::create([
            'Folio' => 'C-TURN-2',
            'Date' => '2026-03-04',
            'Turno' => 2,
            'Status' => 'En Proceso',
            'updated_at' => '2026-03-04 10:00:00',
            'created_at' => '2026-03-04 10:00:00',
        ]);

        TejEficienciaLine::create([
            'Folio' => 'C-TURN-2',
            'Date' => '2026-03-04',
            'Turno' => 2,
            'NoTelarId' => '201',
            'RpmR1' => 300,
            'RpmR2' => 330,
            'RpmR3' => 360,
            'updated_at' => '2026-03-04 10:00:00',
            'created_at' => '2026-03-04 10:00:00',
        ]);

        TejEficienciaLine::create([
            'Folio' => 'C-TURN-2',
            'Date' => '2026-03-04',
            'Turno' => 2,
            'NoTelarId' => '202',
            'RpmR1' => 310,
            'RpmR2' => 320,
            'updated_at' => '2026-03-04 10:01:00',
            'created_at' => '2026-03-04 10:01:00',
        ]);

        TejEficienciaLine::create([
            'Folio' => 'C-TURN-2',
            'Date' => '2026-03-04',
            'Turno' => 2,
            'NoTelarId' => '203',
            'RpmR3' => 290,
            'updated_at' => '2026-03-04 10:02:00',
            'created_at' => '2026-03-04 10:02:00',
        ]);

        TejEficienciaLine::create([
            'Folio' => 'C-TURN-2',
            'Date' => '2026-03-04',
            'Turno' => 2,
            'NoTelarId' => '204',
            'RpmR1' => 0,
            'updated_at' => '2026-03-04 10:03:00',
            'created_at' => '2026-03-04 10:03:00',
        ]);

        $report = $this->service->build('2026-03-04', '2026-03-04');

        $metrics = $report['metrics']['2026-03-04'][2];

        $this->assertSame(330.0, $metrics['201']['rpm']);
        $this->assertNull($metrics['201']['eficiencia']);
        $this->assertSame(315.0, $metrics['202']['rpm']);
        $this->assertNull($metrics['202']['eficiencia']);
        $this->assertSame(290.0, $metrics['203']['rpm']);
        $this->assertNull($metrics['203']['eficiencia']);
        $this->assertNull($metrics['204']['rpm']);
        $this->assertNull($metrics['204']['eficiencia']);
    }
}
