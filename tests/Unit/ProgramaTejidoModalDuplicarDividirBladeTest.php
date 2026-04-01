<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Regresión ligera: el modal duplicar/dividir evitó cargas redundantes al abrir con OrdCompartida.
 */
class ProgramaTejidoModalDuplicarDividirBladeTest extends TestCase
{
    public function test_dividir_blade_skips_per_row_calcular_saldo_for_ord_compartida_load(): void
    {
        $path = resource_path('views/modulos/programa-tejido/modal/_dividir.blade.php');
        $this->assertFileExists($path);
        $content = file_get_contents($path);
        $this->assertStringContainsString('No llamar calcularSaldoTotal', $content);
        $this->assertGreaterThanOrEqual(1, substr_count($content, 'calcularSaldoTotal(newRow)'), 'agregarFilaDividir sigue recalculando saldo en filas nuevas');
    }

    public function test_duplicar_dividir_blade_defers_heavy_init_when_ord_compartida(): void
    {
        $path = resource_path('views/modulos/programa-tejido/modal/duplicar-dividir.blade.php');
        $this->assertFileExists($path);
        $content = file_get_contents($path);
        $this->assertStringContainsString('!tieneOrdCompartida && claveModeloInicial && salonInicial', $content);
        $this->assertStringContainsString('!tieneOrdCompartida && claveModeloInicial && salonesDisponibles', $content);
        $this->assertStringContainsString('carga perezosa', $content);
        $this->assertSame(2, substr_count($content, 'ensureFlogsListaLoaded()'), 'Solo carga bajo demanda (autocompletar), no en el array inicial del modal');
    }
}
