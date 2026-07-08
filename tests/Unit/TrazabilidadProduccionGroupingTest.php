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

    public function test_grouped_card_renders_one_compact_order_with_lower_left_loom_action(): void
    {
        $order = [
            'orden' => '36162',
            'estado' => 'terminado',
            'meses' => ['Feb'],
            'programadas' => 6200.0,
            'producidasTotal' => 4861.0,
            'pesoTotal' => 8161.8,
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
        $this->assertStringContainsString('prod-crudo-card__footer', $html);
        $this->assertStringContainsString('prod-crudo-toggle', $html);
        $this->assertStringContainsString('Ver telares', $html);
        $this->assertStringContainsString('aria-expanded="false"', $html);
        $this->assertSame(4, substr_count($html, 'data-loom-row'));
    }

    public function test_single_loom_card_preserves_program_production_as_its_visible_total(): void
    {
        $card = $this->card(telar: 'Telar 302', producidas: 500, kg: 225.5);
        $card['grupoKey'] = '36162_solo';
        $card['grupoMulti'] = false;
        $card['programa'] = ['produccion' => 450.0];
        $card['codificados'] = null;

        $method = new ReflectionMethod(TrazabilidadProduccionService::class, 'agruparCardsCrudo');
        $orders = $method->invoke(app(TrazabilidadProduccionService::class), [$card]);

        $this->assertFalse($orders[0]['esMultiTelar']);
        $this->assertSame(450.0, $orders[0]['producidasTotal']);
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
