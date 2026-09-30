<?php

namespace Tests\Feature\ProgramaUrdEng;

use Tests\Feature\UrdEng\Concerns\ModuloUrdEng;
use Tests\TestCase;

/**
 * Programación Karl Mayer (19-05, p2.1): la vista ya no trae JS inline; rutas van en
 * data-pagina y el formulario de fecha de requerimiento es un x-ui.modal-base (antes Swal.fire con html).
 */
class KarlMayerVistaTest extends TestCase
{
    use ModuloUrdEng;

    private const MODULO = 52; // Programa Urd / Eng

    private const VISTA = 'modulos/programa_urd_eng/karl-mayer/crear-karl-mayer.blade.php';

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararSqlite();
        $this->withoutVite();
    }

    public function test_renderiza_con_config_en_data_pagina_y_sin_script_propio(): void
    {
        $html = $this->actingAs($this->usuarioCon([self::MODULO => ['acceso', 'crear']], 'Urdido'))
            ->get(route('programa.urd.eng.karl.mayer'))
            ->assertOk()
            ->getContent();

        $config = $this->jsonDeAtributo($html, 'data-pagina');
        $this->assertSame([
            'buscarBomUrdido' => route('programa.urd.eng.buscar.bom.urdido'),
            'materialesCompleto' => route('programa.urd.eng.materiales.urdido.completo'),
            'hilos' => route('programa.urd.eng.hilos', ['tipo' => 'Urdido']),
            'tamanos' => route('programa.urd.eng.tamanos', ['tipo' => 'Urdido']),
            'crearOrden' => route('programa.urd.eng.crear.orden.karl.mayer'),
            'index' => route('programa.urd.eng.index'),
        ], $config['rutas']);

        // Modal de fecha de requerimiento en Blade (antes: Swal.fire({html: '<input type="datetime-local">'})).
        $this->assertStringContainsString('id="modal-fecha-req-km"', $html);
        $this->assertStringContainsString('id="modal-fecha-req-km-valor"', $html);
        $this->assertStringContainsString('data-ui-modal-close-target="modal-fecha-req-km"', $html);

        // Ningún <script> inline del HTML es de esta pantalla (los del layout no mencionan sus ids).
        preg_match_all('/<script\b(?![^>]*\bsrc\s*=)[^>]*>(.*?)<\/script>/is', $html, $m);
        foreach ($m[1] as $js) {
            $this->assertDoesNotMatchRegularExpression('/karl|lmat|tamano|crearOrden/i', $js);
        }
        $this->assertStringNotContainsString('Swal.fire', $html);
        $this->assertStringNotContainsString('X-CSRF-TOKEN', $html);

        // UX-18: un solo <h1> (el del navbar) e íconos de orden decorativos.
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringNotContainsString('sort-icon ml-1"></i>', $html);
    }

    public function test_fuente_sin_script_handlers_ni_csrf_y_con_bundle(): void
    {
        $fuente = (string) file_get_contents(resource_path('views/'.self::VISTA));

        $this->assertDoesNotMatchRegularExpression('/<script\b(?![^>]*\bsrc\s*=)[^>]*>/i', $fuente);
        $this->assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=\s*["\']/i', $fuente);
        $this->assertStringNotContainsString('csrf', $fuente);
        $this->assertDoesNotMatchRegularExpression('/text-\[(9|10|11)px\]/', $fuente);
        $this->assertStringContainsString("@vite('resources/js/modulos/programa-urd-eng/karl-mayer/index.ts')", $fuente);
    }

    public function test_sin_permiso_de_acceso_no_entra(): void
    {
        $this->actingAs($this->usuarioCon([], 'Urdido'))
            ->get(route('programa.urd.eng.karl.mayer'))
            ->assertStatus(403);
    }

    /** @return array<string, mixed> */
    private function jsonDeAtributo(string $html, string $atributo): array
    {
        $this->assertMatchesRegularExpression("/{$atributo}='([^']*)'/", $html);
        preg_match("/{$atributo}='([^']*)'/", $html, $m);

        return json_decode(html_entity_decode($m[1], ENT_QUOTES), true, 512, JSON_THROW_ON_ERROR);
    }
}
