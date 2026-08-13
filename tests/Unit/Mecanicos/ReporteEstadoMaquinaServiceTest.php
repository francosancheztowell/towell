<?php

declare(strict_types=1);

namespace Tests\Unit\Mecanicos;

use App\Services\Mecanicos\ReporteEstadoMaquinaService;
use InvalidArgumentException;
use Tests\TestCase;

class ReporteEstadoMaquinaServiceTest extends TestCase
{
    private ReporteEstadoMaquinaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ReporteEstadoMaquinaService;
    }

    public function test_rango_promedio_recorta_la_semana_al_mes(): void
    {
        $rango = $this->service->rangoPromedio('2026-08', '2026-07-27');

        $this->assertSame('2026-08-01', $rango['desde']);
        $this->assertSame('2026-08-02', $rango['hasta']);
        $this->assertSame('2026-07-27', $rango['lunes']);
        $this->assertSame('2026-08-02', $rango['domingo']);
    }

    public function test_rango_promedio_de_semana_completa_dentro_del_mes(): void
    {
        $rango = $this->service->rangoPromedio('2026-08', '2026-08-10');

        $this->assertSame('2026-08-10', $rango['desde']);
        $this->assertSame('2026-08-16', $rango['hasta']);
    }

    public function test_semanas_que_tocan_agosto_2026_incluyen_cruce_de_mes(): void
    {
        $semanas = $this->service->semanasQueTocanMes('2026-08');
        $lunes = array_column($semanas, 'lunes');

        $this->assertContains('2026-07-27', $lunes);
        $this->assertContains('2026-08-31', $lunes);
        $this->assertSame('2026-08-01', $semanas[0]['desde']);
        $this->assertSame('2026-08-02', $semanas[0]['hasta']);
    }

    public function test_redondeo_estandar(): void
    {
        $this->assertSame(0, $this->service->redondearCalificacion(null));
        $this->assertSame(1, $this->service->redondearCalificacion(1.4));
        $this->assertSame(2, $this->service->redondearCalificacion(1.5));
        $this->assertSame(3, $this->service->redondearCalificacion(2.5));
    }

    public function test_semana_fuera_del_mes_lanza(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->rangoPromedio('2026-08', '2026-06-01');
    }
}
