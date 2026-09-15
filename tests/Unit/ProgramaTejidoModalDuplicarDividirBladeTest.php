<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Regresión ligera sobre el bundle (resources/js/programa-tejido/index.js): el modal
 * duplicar/dividir evitó cargas redundantes al abrir con OrdCompartida.
 */
class ProgramaTejidoModalDuplicarDividirBladeTest extends TestCase
{
    public function test_dividir_blade_skips_per_row_calcular_saldo_for_ord_compartida_load(): void
    {
        $path = base_path('resources/js/programa-tejido/index.js');
        $this->assertFileExists($path);
        $content = file_get_contents($path);
        $this->assertStringContainsString('No llamar calcularSaldoTotal', $content);
        $this->assertGreaterThanOrEqual(1, substr_count($content, 'calcularSaldoTotal(newRow)'), 'agregarFilaDividir sigue recalculando saldo en filas nuevas');
    }

    public function test_duplicar_dividir_blade_defers_heavy_init_when_ord_compartida(): void
    {
        $path = base_path('resources/js/programa-tejido/index.js');
        $this->assertFileExists($path);
        $content = file_get_contents($path);

        // La guarda se llamaba 'tieneOrdCompartida'; deb1962b la renombro a
        // 'tieneGrupoOrdCompartida' porque solo cuenta un grupo real (2+ registros),
        // no un OrdCompartida huerfano en una fila. El comportamiento es el mismo.
        $this->assertStringContainsString('!tieneGrupoOrdCompartida && claveModeloInicial && salonInicial', $content);
        $this->assertStringContainsString('!tieneGrupoOrdCompartida && claveModeloInicial && salonesDisponibles', $content);
        // Se cuentan las llamadas, no las apariciones: en el bundle tambien esta la
        // definicion de la funcion, que antes vivia en otro archivo.
        $this->assertSame(2, substr_count($content, 'await window.ensureFlogsListaLoaded()'), 'Solo carga bajo demanda (autocompletar), no en el array inicial del modal');
    }

    public function test_el_modal_abre_al_clic_cuando_nada_puede_cambiar_de_modo(): void
    {
        $content = file_get_contents(base_path('resources/js/programa-tejido/index.js'));

        // Sin OrdCompartida no hay nada que pueda cambiar el modo: se abre ya y el
        // detalle rellena los importes en didOpen.
        $this->assertStringContainsString('const puedeCambiarDeModo = ordCompartidaDom !== ', $content);
        $this->assertStringContainsString('if (puedeCambiarDeModo) return;', $content);
        $this->assertStringContainsString('detallePendiente.then((detalle) => {', $content);

        // Con OrdCompartida si se espera, pero los dos requests van en paralelo.
        $this->assertStringContainsString('const [detalle, grupo] = await Promise.all([', $content);

        // Y no se le escribe encima de lo que el usuario este teclando.
        $this->assertStringContainsString('limpiarFormatoMiles(input.value) === limpiarFormatoMiles(renderizado.pedido)', $content);

        // Dentro de duplicarTelar, antes de abrir, el unico await que no es el
        // Promise.all debe ser el re-resolve condicional del grupo.
        $inicio = strpos($content, 'async function duplicarTelar(row)');
        $cuerpo = substr($content, $inicio, strpos($content, 'const resultado = await Swal.fire(') - $inicio);
        $awaits = preg_match_all('/await (?!Promise\.all)/', $cuerpo);
        $this->assertSame(1, $awaits, 'volvio a haber requests en serie antes de abrir el modal');
    }

    public function test_el_popup_no_nace_al_ancho_de_la_ventana(): void
    {
        $path = base_path('resources/js/programa-tejido/index.js');
        $content = file_get_contents($path);

        // Antes: width 100% y el tope de 1600px aplicado en didOpen, un paso despues.
        $this->assertStringContainsString("width: 'min(100%, 1600px)'", $content);
        $this->assertStringNotContainsString("popup.style.maxWidth = '1600px'", $content);
    }
}
