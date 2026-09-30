<?php

declare(strict_types=1);

namespace Tests\Feature\ProgramaUrdEng;

use Tests\Feature\ProgramaUrdEng\Concerns\InventarioUrdEngSqlite;
use Tests\TestCase;

/**
 * Reservar y programar (19-05, p1.1): la configuración va en data-pagina de #pu-pagina (antes una
 * isla <script type="application/json">), el filtro de columna es un x-ui.modal-base (antes
 * Swal.fire con input) y los botones se cablean con data-accion.
 */
class ReservarProgramarVistaTest extends TestCase
{
    use InventarioUrdEngSqlite;

    private const MODULO = 52; // Programa Urd / Eng

    private const VISTA = 'modulos/programa_urd_eng/reservar-programar.blade.php';

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararInventario();
        $this->withoutVite();
    }

    public function test_renderiza_con_config_en_data_pagina_y_sin_script_propio(): void
    {
        $this->telar(['no_telar' => '0401', 'tipo' => 'Rizo', 'cuenta' => "31'56", 'calibre' => 20.5, 'fecha' => '2026-09-01', 'salon' => 'Jacquard']);

        // La ruta mira el idrol 52; el controller (userCan) y los botones del navbar, el nombre.
        $todos = ['acceso', 'crear', 'modificar', 'eliminar'];
        $html = $this->actingAs($this->usuarioCon([self::MODULO => $todos, 'Programa Urd / Eng' => $todos], 'Urdido'))
            ->get(route('programa.urd.eng.index'))
            ->assertOk()
            ->getContent();

        $config = $this->jsonDeAtributo($html, 'data-pagina');
        $this->assertSame([
            'inventarioTelares' => route('programa.urd.eng.inventario.telares'),
            'inventarioDisponibleGet' => route('programa.urd.eng.inventario.disponible.get'),
            'programarRequerimientos' => route('programa.urd.eng.programacion.requerimientos'),
            'actualizarTelar' => route('programa.urd.eng.actualizar.telar'),
            'reservarInventario' => route('programa.urd.eng.reservar.inventario'),
            'liberarTelar' => route('programa.urd.eng.liberar.telar'),
        ], $config['api']);
        $this->assertSame(['modificar' => true, 'crear' => true, 'eliminar' => true], $config['can']);
        $this->assertCount(1, $config['telares']);
        // El apóstrofo de la cuenta no rompe el atributo (@json con una variable escapa ').
        $this->assertSame("31'56", $config['telares'][0]['cuenta']);

        $this->assertStringContainsString('id="pu-pagina"', $html);
        $this->assertStringNotContainsString('id="pu-config"', $html);
        $this->assertStringNotContainsString('id="puLoader"', $html);

        // Botones por data-accion (delegate en index.ts), menús y modal del filtro.
        foreach (['reservar', 'liberar', 'programar', 'seleccionar-lote', 'alternar-filtro-inventario'] as $accion) {
            $this->assertStringContainsString('data-accion="'.$accion.'"', $html, $accion);
        }
        $this->assertStringContainsString('id="tableContextMenu"', $html);
        $this->assertStringContainsString('id="puMenuFila"', $html);
        $this->assertStringContainsString('id="puModalFiltro"', $html);
        $this->assertStringContainsString('data-ui-modal-close-target="puModalFiltro"', $html);

        preg_match_all('/<script\b(?![^>]*\bsrc\s*=)[^>]*>(.*?)<\/script>/is', $html, $m);
        foreach ($m[1] as $js) {
            $this->assertDoesNotMatchRegularExpression('/telaresTable|inventarioTable|pu-config|puConfig/i', $js);
        }
        $this->assertStringNotContainsString('Swal.fire', $html);
        $this->assertStringNotContainsString('X-CSRF-TOKEN', $html);
        $this->assertSame(1, substr_count($html, '<h1'));
    }

    public function test_solo_lectura_manda_permisos_en_falso(): void
    {
        $html = $this->actingAs($this->usuarioCon([self::MODULO => ['acceso']], 'Urdido'))
            ->get(route('programa.urd.eng.reservar.programar'))
            ->assertOk()
            ->getContent();

        $config = $this->jsonDeAtributo($html, 'data-pagina');
        $this->assertSame(['modificar' => false, 'crear' => false, 'eliminar' => false], $config['can']);
        $this->assertSame([], $config['telares']);
    }

    public function test_fuente_sin_script_handlers_ni_csrf_y_con_bundle(): void
    {
        $fuente = (string) file_get_contents(resource_path('views/'.self::VISTA));

        $this->assertDoesNotMatchRegularExpression('/<script\b(?![^>]*\bsrc\s*=)[^>]*>/i', $fuente);
        $this->assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=\s*["\']/i', $fuente);
        $this->assertStringNotContainsString('csrf', $fuente);
        $this->assertDoesNotMatchRegularExpression('/text-\[(9|10|11)px\]/', $fuente);
        $this->assertDoesNotMatchRegularExpression('/font-size:\s*(\.[0-6]\d*rem|\.7[0-4]\d*rem|(9|10|11)px)/', $fuente, 'Texto >= 12 px (UX-18).');
        $this->assertStringContainsString("@vite('resources/js/modulos/programa-urd-eng/reservar-programar/index.ts')", $fuente);
        $this->assertStringContainsString("data-pagina='@json(\$puConfig)'", $fuente);
    }

    public function test_bundle_sin_fetch_swal_ni_innerhtml(): void
    {
        $dir = resource_path('js/modulos/programa-urd-eng/reservar-programar');
        foreach (glob($dir.'/*.ts') ?: [] as $archivo) {
            $ts = (string) file_get_contents($archivo);
            $nombre = basename($archivo);
            $this->assertDoesNotMatchRegularExpression('/\bfetch\(/', $ts, $nombre);
            $this->assertDoesNotMatchRegularExpression('/Swal\.fire\b/', $ts, $nombre);
            $this->assertDoesNotMatchRegularExpression('/onclick\s*=/i', $ts, $nombre);
            $this->assertDoesNotMatchRegularExpression('/\.innerHTML\s*\+?=(?!=)/', $ts, $nombre);
            $this->assertStringNotContainsStringIgnoringCase('X-CSRF-TOKEN', $ts, $nombre);
            $this->assertDoesNotMatchRegularExpression("/addEventListener\\(\\s*'contextmenu'/", $ts, $nombre.': usar accionesTactiles (UX-06).');
        }
    }

    public function test_sin_permiso_de_acceso_no_entra(): void
    {
        $this->actingAs($this->usuarioCon([], 'Urdido'))
            ->get(route('programa.urd.eng.index'))
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
