<?php

declare(strict_types=1);

namespace Tests\Unit\Reportes;

use App\Support\Reportes\FechaReporte;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class FechaReporteTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_lee_iso_y_dia_mes_anio_al_inicio_del_dia(): void
    {
        $iso = FechaReporte::parse('2026-03-01');
        $dmy = FechaReporte::parse(' 01/03/2026 ');

        $this->assertSame('2026-03-01 00:00:00', $iso->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-01 00:00:00', $dmy->format('Y-m-d H:i:s'));
        $this->assertSame(1, $dmy->day);
        $this->assertSame(3, $dmy->month);
    }

    public function test_vacio_es_hoy_al_inicio_del_dia(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-10 18:45:00'));

        $this->assertSame('2026-10-10 00:00:00', FechaReporte::parse('   ')->format('Y-m-d H:i:s'));
    }

    public function test_cae_a_carbon_si_ningun_formato_corto_aplica(): void
    {
        $fecha = FechaReporte::parse('2026-03-01 15:40:00');

        $this->assertSame('2026-03-01 00:00:00', $fecha->format('Y-m-d H:i:s'));
    }
}
