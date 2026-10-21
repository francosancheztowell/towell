<?php

namespace Tests\Unit;

use App\Services\Urdido\OeeUrdidoService;
use Tests\TestCase;

class OeeUrdidoServiceTest extends TestCase
{
    private function julio(string $fecha, string $hora, float $minutos, float $kg = 100, ?string $defecto = null): array
    {
        return ['maquina' => 'MCCOY1', 'fecha' => $fecha, 'hora' => $hora, 'inicio' => strtotime("$fecha $hora"), 'minutos' => $minutos,
            'finalizado' => true, 'orden' => '1', 'julio' => '1', 'lmat' => null, 'config' => null, 'tamano' => null,
            'kg' => $kg, 'kg_buenos' => $defecto ? 0.0 : $kg, 'metros' => 0.0, 'defecto' => $defecto, 'penalizacion' => null, 'oficiales' => ''];
    }

    public function test_el_julio_manda_y_el_paro_se_prorratea_por_minutos(): void
    {
        // Julios 08:00–09:00 (60 min) y 10:00–12:00 (120 min). Paro 08:30–10:30: solo cuentan 09:00–10:00 = 60 min.
        $julios = [$this->julio('2026-10-07', '08:00', 60), $this->julio('2026-10-07', '10:00', 120, 300, 'PHC')];
        $paros = ['MCCOY1' => [[strtotime('2026-10-07 08:30'), strtotime('2026-10-07 10:30')]]];

        $r = OeeUrdidoService::armar(['2026-10'], $julios, $paros);
        $maq = $r['meses']['2026-10']['maquinas']['MCCOY1'];

        $this->assertEqualsWithDelta(20.0, $maq['julios'][0]['paro_oport'], 0.01);
        $this->assertEqualsWithDelta(40.0, $maq['julios'][1]['paro_oport'], 0.01);
        $this->assertEqualsWithDelta(60.0, $r['tot']['paro_oport'], 0.01);
        $this->assertSame(0.0, $r['tot']['paro_nec']);

        $i = OeeUrdidoService::indicadores($r['tot']);
        $this->assertEqualsWithDelta(180 / 240, $i['disp'], 0.0001);
        $this->assertEqualsWithDelta(0.25, $i['cal'], 0.0001); // 100 de 400 kg sin defecto
    }

    public function test_semana_que_cruza_el_mes_se_parte_y_paro_sin_julios_queda_en_el_subtotal(): void
    {
        $seg = OeeUrdidoService::segmentos('2026-10');
        $this->assertSame('2026-10-S40', array_key_first($seg)); // jue 1 – dom 4 oct
        $this->assertStringStartsWith('Sem 40 (1–4', $seg['2026-10-S40'][2]);

        $paros = ['KM1' => [[strtotime('2026-09-30 22:00'), strtotime('2026-10-01 02:00')]]];
        $r = OeeUrdidoService::armar(['2026-10'], [], $paros);
        $km = $r['meses']['2026-10']['maquinas']['KM1'];

        $this->assertSame([], $km['julios']);
        $this->assertEqualsWithDelta(120.0, $km['tot']['paro_oport'], 0.01); // recortado al 1 de octubre
    }

    public function test_paro_necesario_y_de_oportunidad_sin_contar_dos_veces_lo_encimado(): void
    {
        // Necesario 13:00–14:00; oportunidad 13:30–15:00. Lo encimado (13:30–14:00) cuenta como necesario.
        $paros = ['MCCOY1' => [
            [strtotime('2026-10-07 13:00'), strtotime('2026-10-07 14:00'), true],
            [strtotime('2026-10-07 13:30'), strtotime('2026-10-07 15:00'), false],
        ]];

        $r = OeeUrdidoService::armar(['2026-10'], [$this->julio('2026-10-07', '08:00', 60)], $paros);

        $this->assertEqualsWithDelta(60.0, $r['tot']['paro_nec'], 0.01);
        $this->assertEqualsWithDelta(60.0, $r['tot']['paro_oport'], 0.01);
    }
}
