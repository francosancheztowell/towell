<?php

declare(strict_types=1);

namespace Tests\Unit\Mecanicos;

use App\Exports\ReporteOtDiariasExport;
use App\Services\Mecanicos\ReporteOtDiariasService;
use InvalidArgumentException;
use Tests\TestCase;

class ReporteOtDiariasServiceTest extends TestCase
{
    private ReporteOtDiariasService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ReporteOtDiariasService;
    }

    public function test_rango_desde_lunes_cubre_siete_dias_y_semana_iso(): void
    {
        $rango = $this->service->rangoDesde('2026-07-06');

        $this->assertSame('2026-07-06', $rango['desde']);
        $this->assertSame('2026-07-12', $rango['hasta']);
        $this->assertSame(28, $rango['semana_iso']);
        $this->assertSame('SEMANA 28', $rango['etiqueta_semana']);
        $this->assertCount(7, $rango['dias']);
        $this->assertSame('2026-07-06', $rango['dias'][0]['fecha']);
        $this->assertSame('2026-07-12', $rango['dias'][6]['fecha']);
        $this->assertSame('lunes, 6 de julio de 2026', $rango['dias'][0]['etiqueta']);
        $this->assertSame('domingo, 12 de julio de 2026', $rango['dias'][6]['etiqueta']);
    }

    public function test_fecha_invalida_lanza(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->rangoDesde('2026-13-01');
    }

    public function test_dos_renglones_mismo_folio_terminado_cuentan_dos_realizadas(): void
    {
        $reporte = $this->service->armarReporte('2026-07-06', [
            ['cve' => '100', 'nombre' => 'SANTIAGO BAEZ'],
        ], [
            $this->renglon('100', 'SANTIAGO BAEZ', '2026-07-06', 60, 'Terminado'),
            $this->renglon('100', 'SANTIAGO BAEZ', '2026-07-06', 90, 'Terminado'),
        ]);

        $dia = $reporte['mecanicos'][0]['dias']['2026-07-06'];
        $this->assertSame(2.0, $dia['realizadas']);
        $this->assertSame(0.0, $dia['firmadas']);
        $this->assertSame(150.0, $dia['ocupacion']);
    }

    public function test_renglones_autorizados_cuentan_en_realizadas_y_firmadas(): void
    {
        $reporte = $this->service->armarReporte('2026-07-06', [
            ['cve' => '100', 'nombre' => 'SANTIAGO BAEZ'],
        ], [
            $this->renglon('100', 'SANTIAGO BAEZ', '2026-07-06', 60, 'Autorizado'),
            $this->renglon('100', 'SANTIAGO BAEZ', '2026-07-06', 60, 'Autorizado'),
        ]);

        $dia = $reporte['mecanicos'][0]['dias']['2026-07-06'];
        $this->assertSame(2.0, $dia['realizadas']);
        $this->assertSame(2.0, $dia['firmadas']);
    }

    public function test_folio_activo_solo_suma_ocupacion(): void
    {
        $reporte = $this->service->armarReporte('2026-07-06', [
            ['cve' => '100', 'nombre' => 'SANTIAGO BAEZ'],
        ], [
            $this->renglon('100', 'SANTIAGO BAEZ', '2026-07-06', 90, 'Activo'),
        ]);

        $dia = $reporte['mecanicos'][0]['dias']['2026-07-06'];
        $this->assertSame(0.0, $dia['realizadas']);
        $this->assertSame(0.0, $dia['firmadas']);
        $this->assertSame(90.0, $dia['ocupacion']);
    }

    public function test_folio_cancelado_no_cuenta_realizadas_pero_suma_minutos(): void
    {
        $reporte = $this->service->armarReporte('2026-07-06', [
            ['cve' => '100', 'nombre' => 'SANTIAGO BAEZ'],
        ], [
            $this->renglon('100', 'SANTIAGO BAEZ', '2026-07-06', 40, 'Cancelado'),
        ]);

        $dia = $reporte['mecanicos'][0]['dias']['2026-07-06'];
        $this->assertSame(0.0, $dia['realizadas']);
        $this->assertSame(0.0, $dia['firmadas']);
        $this->assertSame(40.0, $dia['ocupacion']);
    }

    public function test_formulas_de_excel_de_referencia(): void
    {
        $reporte = $this->service->armarReporte(
            '2026-07-06',
            [['cve' => '100', 'nombre' => 'SANTIAGO BAEZ']],
            $this->renglonesOcupacionUniforme('100', 'SANTIAGO BAEZ', 3780.0, 15, 'Autorizado'),
            ['100' => ['ot_trama' => 2.0, 'cumplidas_trama' => 1.9, 'ocupacion_pct' => 100.0]],
        );

        $fila = $reporte['mecanicos'][0];
        $this->assertSame(15.0, $fila['realizadas_semana']);
        $this->assertSame(15.0, $fila['firmadas_semana']);
        $this->assertSame(17.0, $fila['total_realizadas']);
        $this->assertSame(16.9, $fila['total_cumplidas']);
        $this->assertSame(3780.0, $fila['min_semana']);
        $this->assertSame(112.5, $fila['pct_capacidad']);
        $this->assertSame(99.4, $fila['pct_cumplimiento']);
        $this->assertSame(99.7, $fila['pct_ot_final']);
    }

    public function test_cumplimiento_es_cero_si_no_hay_realizadas(): void
    {
        $reporte = $this->service->armarReporte('2026-07-06', [
            ['cve' => '100', 'nombre' => 'SANTIAGO BAEZ'],
        ], []);

        $this->assertSame(0.0, $reporte['mecanicos'][0]['pct_cumplimiento']);
        $this->assertSame(0.0, $reporte['mecanicos'][0]['pct_ot_final']);
    }

    public function test_pie_suma_trama_y_promedia_ot_final(): void
    {
        $reporte = $this->service->armarReporte(
            '2026-07-06',
            [
                ['cve' => '100', 'nombre' => 'A'],
                ['cve' => '200', 'nombre' => 'B'],
            ],
            [],
            [
                '100' => ['ot_trama' => 2.0, 'cumplidas_trama' => 1.0, 'ocupacion_pct' => 100.0],
                '200' => ['ot_trama' => 9.0, 'cumplidas_trama' => 8.0, 'ocupacion_pct' => 80.0],
            ],
        );

        $this->assertSame(11.0, $reporte['pie']['ot_trama']);
        $this->assertSame(9.0, $reporte['pie']['cumplidas_trama']);
        $this->assertSame(75.0, $reporte['mecanicos'][0]['pct_ot_final']);
        $this->assertSame(84.5, $reporte['mecanicos'][1]['pct_ot_final']);
        $this->assertSame(79.8, $reporte['pie']['pct_ot_final']);
    }

    public function test_renglon_huerfano_agrega_fila_al_final(): void
    {
        $reporte = $this->service->armarReporte('2026-07-06', [
            ['cve' => '100', 'nombre' => 'SANTIAGO BAEZ'],
        ], [
            $this->renglon('999', 'LEGACY', '2026-07-07', 30, 'Calificado'),
        ]);

        $this->assertCount(2, $reporte['mecanicos']);
        $this->assertSame('999', $reporte['mecanicos'][1]['cve']);
        $this->assertSame('LEGACY', $reporte['mecanicos'][1]['nombre']);
        $this->assertSame(1.0, $reporte['mecanicos'][1]['dias']['2026-07-07']['realizadas']);
    }

    public function test_export_excel_tiene_33_columnas(): void
    {
        $reporte = $this->service->armarReporte('2026-07-06', [
            ['cve' => '100', 'nombre' => 'SANTIAGO BAEZ'],
        ], []);

        $filas = (new ReporteOtDiariasExport($reporte))->array();
        $this->assertCount(33, $filas[6]);
        $this->assertCount(33, $filas[7]);
        $this->assertSame('SANTIAGO BAEZ', $filas[8][0]);
        $this->assertCount(33, $filas[8]);
        $this->assertCount(33, $filas[9]);
    }

    /**
     * @return array{cve: string, nombre: string, fecha: string, minutos: float, estatus: string}
     */
    private function renglon(string $cve, string $nombre, string $fecha, float $minutos, string $estatus): array
    {
        return [
            'cve' => $cve,
            'nombre' => $nombre,
            'fecha' => $fecha,
            'minutos' => $minutos,
            'estatus' => $estatus,
        ];
    }

    /**
     * @return list<array{cve: string, nombre: string, fecha: string, minutos: float, estatus: string}>
     */
    private function renglonesOcupacionUniforme(
        string $cve,
        string $nombre,
        float $minutosTotales,
        int $realizadas,
        string $estatus,
    ): array {
        $rango = $this->service->rangoDesde('2026-07-06');
        $porRenglon = $minutosTotales / $realizadas;
        $renglones = [];
        $fecha = $rango['dias'][0]['fecha'];
        for ($i = 0; $i < $realizadas; $i++) {
            $renglones[] = $this->renglon($cve, $nombre, $fecha, $porRenglon, $estatus);
        }

        return $renglones;
    }
}
