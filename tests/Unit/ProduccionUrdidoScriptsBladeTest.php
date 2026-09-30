<?php

namespace Tests\Unit;

use App\Models\Urdido\UrdProgramaUrdido;
use Tests\TestCase;

/**
 * produccion/_scripts ya no emite JS (19-01): pasa la config por data-produccion-urdido y el
 * bundle resources/js/modulos/urdido/produccion/index.ts la lee. Se conservan las dos
 * intenciones del test original: un $orden string no revienta ni produce un ordenId, y con
 * modelo el ordenId sale del Id.
 */
class ProduccionUrdidoScriptsBladeTest extends TestCase
{
    public function test_scripts_do_not_read_id_when_orden_is_a_string(): void
    {
        $html = $this->withoutVite()->renderizar('folio-no-es-objeto');

        $config = $this->config($html);
        $this->assertArrayHasKey('ordenId', $config);
        $this->assertNull($config['ordenId']);
        $this->assertNull($config['rutas']['pdf']);

        // Sin ordenId, finalizar avisa en vez de mandar la petición.
        $finalizar = (string) file_get_contents(resource_path('js/modulos/urdido/produccion/finalizar.ts'));
        $this->assertStringContainsString("SIN_ORDEN = 'No hay orden seleccionada'", $finalizar);
        $this->assertMatchesRegularExpression('/ordenId === null\)\s*\{\s*alerta\([^)]*SIN_ORDEN\);\s*return;/', $finalizar);
    }

    public function test_scripts_emit_orden_id_from_model(): void
    {
        $orden = new UrdProgramaUrdido;
        $orden->Id = 42;

        $config = $this->config($this->withoutVite()->renderizar($orden));

        $this->assertSame(42, $config['ordenId']);
        $this->assertStringContainsString('orden_id=42', $config['rutas']['pdf']);
        $this->assertSame(700, $config['maxKgNeto']);
    }

    // La directiva json sobre una variable conserva los JSON_HEX_*: un ' o un < en los datos no rompe data-*='…'.
    public function test_la_config_escapa_apostrofos_y_etiquetas(): void
    {
        $orden = new UrdProgramaUrdido;
        $orden->Id = 7;

        $html = $this->withoutVite()->renderizar($orden, "7'0<b>0");

        $this->assertStringNotContainsString('<b>', $html);
        $this->assertStringContainsString('\u0026tipo=urdido', $html);
        $this->assertSame("7'0<b>0", $this->config($html)['maxKgNeto']);
    }

    private function renderizar(mixed $orden, mixed $maxKgNeto = 700): string
    {
        return view('modulos.urdido.produccion._scripts', [
            'orden' => $orden,
            'maxKgNeto' => $maxKgNeto,
            'isKarlMayer' => false,
        ])->render();
    }

    /** @return array<string, mixed> */
    private function config(string $html): array
    {
        $this->assertStringNotContainsString('<script', $html);
        $this->assertSame(1, preg_match("/data-produccion-urdido='([^']*)'/", $html, $m));

        return json_decode(html_entity_decode($m[1], ENT_QUOTES), true, 512, JSON_THROW_ON_ERROR);
    }
}
