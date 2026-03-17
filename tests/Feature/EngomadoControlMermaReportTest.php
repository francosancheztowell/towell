<?php

namespace Tests\Feature;

use App\Models\Engomado\EngProgramaEngomado;
use App\Models\Urdido\UrdJuliosOrden;
use App\Models\Urdido\UrdProgramaUrdido;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

class EngomadoControlMermaReportTest extends TestCase
{
    use UsesSqlsrvSqlite;

    private int $initialOutputBufferLevel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->initialOutputBufferLevel = ob_get_level();
        $this->useSqlsrvSqlite();
        $this->createControlMermaTables(includeAuthTable: true);
    }

    protected function tearDown(): void
    {
        while (ob_get_level() > $this->initialOutputBufferLevel) {
            ob_end_clean();
        }

        parent::tearDown();
    }

    public function test_reporte_control_merma_only_shows_finalized_orders_within_range(): void
    {
        $usuario = $this->createUsuario();
        $this->actingAs($usuario);

        UrdProgramaUrdido::create([
            'Folio' => '00011',
            'Cuenta' => '3776',
            'Calibre' => 12,
        ]);

        UrdProgramaUrdido::create([
            'Folio' => '00022',
            'Cuenta' => '4888',
            'Calibre' => 14,
        ]);

        UrdProgramaUrdido::create([
            'Folio' => '00033',
            'Cuenta' => '5999',
            'Calibre' => 16,
        ]);

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
            'Folio' => '00022',
            'Cuenta' => '4888',
            'Calibre' => 14,
            'MaquinaEng' => 'West Point 3',
            'Merma' => 5,
            'MermaGoma' => 1,
            'Status' => 'En Proceso',
            'FechaFinaliza' => '2026-03-11',
        ]);

        EngProgramaEngomado::create([
            'Folio' => '00033',
            'Cuenta' => '5999',
            'Calibre' => 16,
            'MaquinaEng' => 'West Point 2',
            'Merma' => 3,
            'MermaGoma' => 0.5,
            'Status' => 'Finalizado',
            'FechaFinaliza' => '2026-04-01',
        ]);

        $response = $this->get(route('engomado.reportes.control-merma', [
            'fecha_ini' => '2026-03-01',
            'fecha_fin' => '2026-03-31',
        ]));

        $response->assertOk();
        $response->assertViewHas('filas', function ($filas) {
            return $filas->count() === 1
                && $filas->first()['folio'] === '00011'
                && $filas->first()['maquina_label'] === 'WP2';
        });

        $response->assertSee('00011');
        $response->assertDontSee('00022');
        $response->assertDontSee('00033');
    }
}
