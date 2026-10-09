<?php

namespace Tests\Unit;

use App\Services\Trazabilidad\TrazabilidadProduccionService;
use ReflectionMethod;
use Tests\TestCase;

class TrazabilidadProduccionGroupingTest extends TestCase
{
    public function test_it_groups_all_looms_of_an_order_into_one_card_with_consolidated_totals(): void
    {
        $cards = [
            $this->card(telar: 'Telar 302', producidas: 0, kg: 5951.80),
            $this->card(telar: 'Telar 301', producidas: 1356, kg: 609.80, otroTelar: true),
            $this->card(telar: 'Telar 303', producidas: 2616, kg: 1194.80, otroTelar: true),
            $this->card(telar: 'Telar 320', producidas: 889, kg: 405.40, otroTelar: true),
        ];

        $method = new ReflectionMethod(TrazabilidadProduccionService::class, 'agruparCardsCrudo');
        $orders = $method->invoke(app(TrazabilidadProduccionService::class), $cards);

        $this->assertCount(1, $orders);
        $this->assertSame('36162', $orders[0]['orden']);
        $this->assertSame('terminado', $orders[0]['estado']);
        $this->assertTrue($orders[0]['esMultiTelar']);
        $this->assertSame(4, $orders[0]['cantidadTelares']);
        $this->assertSame(4861.0, $orders[0]['producidasTotal']);
        $this->assertSame(8161.8, $orders[0]['pesoTotal']);
        $this->assertSame(['302', '301', '303', '320'], array_column($orders[0]['telares'], 'telarNumero'));
        $this->assertSame('programa', $orders[0]['telares'][0]['origen']);
        $this->assertSame('trazabilidad', $orders[0]['telares'][1]['origen']);
    }

    public function test_grouped_card_renders_looms_as_columns_without_dropdown_and_progress_at_the_bottom(): void
    {
        $order = [
            'orden' => '36162',
            'estado' => 'terminado',
            'meses' => ['Feb'],
            'programadas' => 6200.0,
            'producidasTotal' => 4861.0,
            'pesoTotal' => 8161.8,
            'pzasDia' => 780.0,
            'prodKgDia' => 355.5,
            'avance' => 78.4,
            'cantidadTelares' => 4,
            'esMultiTelar' => true,
            'telaresResumen' => '302, 301, 303, 320',
            'telares' => [
                ['telarNumero' => '302', 'origen' => 'programa', 'producidas' => 0.0, 'kg' => 5951.8],
                ['telarNumero' => '301', 'origen' => 'trazabilidad', 'producidas' => 1356.0, 'kg' => 609.8],
                ['telarNumero' => '303', 'origen' => 'trazabilidad', 'producidas' => 2616.0, 'kg' => 1194.8],
                ['telarNumero' => '320', 'origen' => 'trazabilidad', 'producidas' => 889.0, 'kg' => 405.4],
            ],
        ];

        $html = view('modulos.trazabilidad._produccion_crudo_card', ['o' => $order])->render();

        $this->assertSame(1, substr_count($html, 'Orden 36162'));
        $this->assertStringNotContainsString('prod-crudo-toggle', $html);
        $this->assertStringNotContainsString('Ver telares', $html);
        $this->assertStringNotContainsString('Origen', $html);
        $this->assertStringContainsString('prod-crudo-card__loom-matrix', $html);
        $this->assertSame(4, substr_count($html, 'data-loom-column'));
        $this->assertStringContainsString('>Pzas<', $html);
        $this->assertStringContainsString('>Kg<', $html);
        $this->assertStringContainsString('Pzas/día', $html);
        $this->assertStringContainsString('Kg/día', $html);
        $this->assertStringContainsString('78.4%', $html);
        $this->assertStringContainsString('prod-crudo-card__progress-bar', $html);
        $this->assertGreaterThan(
            strpos($html, 'prod-crudo-card__loom-matrix'),
            strpos($html, 'prod-crudo-card__progress')
        );
    }

    public function test_order_out_of_programa_tejido_gets_its_real_daily_rate(): void
    {
        $card = $this->card(telar: 'Telar 202', producidas: 3000, kg: 1500);
        $card['codificados'] = ['diasProduccion' => 10.0, 'fechaInicio' => '01/05/26', 'fechaFinal' => '11/05/26'];
        $sinFechas = $this->card(telar: 'Telar 203', producidas: 3000, kg: 1500);
        $sinFechas['grupoKey'] = '36163';
        $sinFechas['codificados'] = ['diasProduccion' => null];

        $method = new ReflectionMethod(TrazabilidadProduccionService::class, 'agruparCardsCrudo');
        [$conFechas, $sinDias] = $method->invoke(app(TrazabilidadProduccionService::class), [$card, $sinFechas]);

        $this->assertTrue($conFechas['ritmoReal']);
        $this->assertSame(300.0, $conFechas['pzasDia']);
        $this->assertSame(150.0, $conFechas['prodKgDia']);
        $this->assertFalse($sinDias['ritmoReal']);
        $this->assertNull($sinDias['pzasDia']);

        $dias = new ReflectionMethod(TrazabilidadProduccionService::class, 'diasEntre');
        $this->assertSame(2.5, $dias->invoke(app(TrazabilidadProduccionService::class), '2026-05-01 00:00:00', '2026-05-03 12:00:00'));
        $this->assertNull($dias->invoke(app(TrazabilidadProduccionService::class), null, '2026-05-03'));
    }

    public function test_single_loom_card_uses_trace_production_as_its_visible_total(): void
    {
        $card = $this->card(telar: 'Telar 302', producidas: 500, kg: 225.5);
        $card['grupoKey'] = '36162_solo';
        $card['grupoMulti'] = false;
        $card['programa'] = ['produccion' => 450.0];
        $card['codificados'] = null;

        $method = new ReflectionMethod(TrazabilidadProduccionService::class, 'agruparCardsCrudo');
        $orders = $method->invoke(app(TrazabilidadProduccionService::class), [$card]);

        $this->assertFalse($orders[0]['esMultiTelar']);
        $this->assertSame(500.0, $orders[0]['producidasTotal']);
    }

    public function test_crudo_filters_are_toggle_buttons(): void
    {
        $view = file_get_contents(resource_path('views/modulos/trazabilidad/_produccion.blade.php'));
        $script = file_get_contents(resource_path('js/trazabilidad/production-detail.ts'));

        $this->assertStringContainsString('data-prod-filtro=', $view);
        $this->assertStringContainsString('aria-pressed=', $view);
        $this->assertStringContainsString("setAttribute('aria-pressed'", $script);
    }

    /**
     * @return array<string, mixed>
     */
    private function card(string $telar, float $producidas, float $kg, bool $otroTelar = false): array
    {
        return [
            'orden' => '36162',
            'fuente' => 'codificados',
            'estado' => 'terminado',
            'meses' => ['Feb'],
            'programadas' => 6200.0,
            'pzasDia' => null,
            'programa' => null,
            'codificados' => [
                'pedido' => 6200.0,
                'produccion' => 0.0,
            ],
            'grupoKey' => '36162',
            'grupoMulti' => true,
            'telarSort' => 302,
            'esOtroTelar' => $otroTelar,
            'telar' => $telar,
            'localidad' => str_replace('Telar ', '', $telar),
            'enProceso' => false,
            'producidas' => $producidas,
            'kg' => $kg,
            'avance' => 0.0,
            'usarTrazaEnProducido' => $otroTelar,
        ];
    }
}
