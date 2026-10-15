<?php

declare(strict_types=1);

namespace Tests\Unit\Mecanicos;

use App\Services\Mecanicos\ReporteOtFechaService;
use InvalidArgumentException;
use Tests\TestCase;

class ReporteOtFechaServiceTest extends TestCase
{
    private ReporteOtFechaService $servicio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->servicio = new ReporteOtFechaService;
    }

    /** @return array<string, mixed> */
    private function renglon(array $datos = []): array
    {
        return array_merge([
            'cve' => '2002',
            'nombre' => 'PEDRO LÓPEZ',
            'fecha' => '2026-10-01',
            'folio' => 'MEC00001',
            'telar' => '201',
            'falla' => 'Rotura de hilo',
            'trabajos' => ['Ajusto' => true, 'Reparo' => false, 'Cambio' => false, 'Lubrico' => false, 'FaltaRefacc' => false],
            'hora_inicio' => '08:00:00',
            'hora_fin' => '09:15:00',
            'total_minutos' => 75,
            'estatus' => 'Terminado',
            'comentarios' => 'Se ajustó',
        ], $datos);
    }

    public function test_rango_valido_regresa_desde_y_hasta(): void
    {
        $this->assertSame(
            ['desde' => '2026-10-01', 'hasta' => '2026-10-05'],
            $this->servicio->validarRango('2026-10-01', '2026-10-05'),
        );
    }

    public function test_rango_de_un_solo_dia_es_valido(): void
    {
        $this->assertSame(
            ['desde' => '2026-10-01', 'hasta' => '2026-10-01'],
            $this->servicio->validarRango('2026-10-01', '2026-10-01'),
        );
    }

    public function test_rango_con_fecha_inicial_mayor_lanza_error(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('La fecha inicial no puede ser mayor que la fecha final.');

        $this->servicio->validarRango('2026-10-05', '2026-10-01');
    }

    public function test_rango_con_fecha_mal_formada_lanza_error(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->servicio->validarRango('2026-02-30', '2026-03-01');
    }

    public function test_agrupa_por_mecanico_ordenado_por_nombre_con_totales(): void
    {
        $reporte = $this->servicio->armarReporte(['desde' => '2026-10-01', 'hasta' => '2026-10-02'], [
            $this->renglon(['cve' => '3003', 'nombre' => 'ZAPATA', 'folio' => 'MEC00002', 'total_minutos' => 30]),
            $this->renglon(['folio' => 'MEC00001', 'total_minutos' => 75]),
            $this->renglon(['folio' => 'MEC00001', 'hora_inicio' => '10:00:00', 'hora_fin' => '10:20:00', 'total_minutos' => 20]),
        ]);

        $this->assertSame(['PEDRO LÓPEZ', 'ZAPATA'], array_column($reporte['mecanicos'], 'nombre'));
        $pedro = $reporte['mecanicos'][0];
        $this->assertSame(1, $pedro['total_ots']);
        $this->assertSame(2, $pedro['total_trabajos']);
        $this->assertSame(95, $pedro['total_minutos']);
        $this->assertSame(2, $reporte['total_ots']);
        $this->assertSame(3, $reporte['total_trabajos']);
        $this->assertSame(125, $reporte['total_minutos']);
    }

    public function test_trabajo_que_cruza_medianoche_queda_en_el_dia_que_empezo(): void
    {
        $reporte = $this->servicio->armarReporte(['desde' => '2026-10-01', 'hasta' => '2026-10-01'], [
            $this->renglon(['fecha' => '2026-10-01', 'hora_inicio' => '23:50:00', 'hora_fin' => '00:05:00', 'total_minutos' => null]),
        ]);

        $renglon = $reporte['mecanicos'][0]['renglones'][0];
        $this->assertSame('2026-10-01', $renglon['fecha']);
        $this->assertSame('23:50', $renglon['hora_inicio']);
        $this->assertSame('00:05', $renglon['hora_fin']);
        $this->assertTrue($renglon['cruza_dia']);
        $this->assertSame(15, $renglon['minutos']);
    }

    public function test_renglones_ordenados_por_fecha_y_hora_de_inicio(): void
    {
        $reporte = $this->servicio->armarReporte(['desde' => '2026-10-01', 'hasta' => '2026-10-02'], [
            $this->renglon(['fecha' => '2026-10-02', 'hora_inicio' => '07:00:00']),
            $this->renglon(['fecha' => '2026-10-01', 'hora_inicio' => '14:00:00']),
            $this->renglon(['fecha' => '2026-10-01', 'hora_inicio' => '08:00:00']),
        ]);

        $orden = array_map(
            fn (array $r): string => $r['fecha'].' '.$r['hora_inicio'],
            $reporte['mecanicos'][0]['renglones'],
        );
        $this->assertSame(['2026-10-01 08:00', '2026-10-01 14:00', '2026-10-02 07:00'], $orden);
    }

    public function test_describe_los_trabajos_marcados(): void
    {
        $reporte = $this->servicio->armarReporte(['desde' => '2026-10-01', 'hasta' => '2026-10-01'], [
            $this->renglon(['trabajos' => ['Ajusto' => true, 'Reparo' => true, 'Cambio' => false, 'Lubrico' => '1', 'FaltaRefacc' => 0]]),
        ]);

        $this->assertSame('Ajustó, Reparó, Lubricó', $reporte['mecanicos'][0]['renglones'][0]['trabajo']);
    }

    public function test_sin_renglones_no_hay_mecanicos(): void
    {
        $reporte = $this->servicio->armarReporte(['desde' => '2026-10-01', 'hasta' => '2026-10-01'], []);

        $this->assertSame([], $reporte['mecanicos']);
        $this->assertSame(0, $reporte['total_minutos']);
    }

    public function test_formato_de_duracion(): void
    {
        $this->assertSame('0 min', ReporteOtFechaService::duracion(0));
        $this->assertSame('45 min', ReporteOtFechaService::duracion(45));
        $this->assertSame('1 h 05 min', ReporteOtFechaService::duracion(65));
        $this->assertSame('26 h 00 min', ReporteOtFechaService::duracion(1560));
    }
}
