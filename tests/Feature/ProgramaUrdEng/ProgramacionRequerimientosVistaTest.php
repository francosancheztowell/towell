<?php

namespace Tests\Feature\ProgramaUrdEng;

use App\Models\Urdido\URDCatalogoMaquina;
use Illuminate\Support\Facades\DB;
use Tests\Feature\UrdEng\Concerns\ModuloUrdEng;
use Tests\TestCase;

/**
 * Programación de Requerimientos (19-05, p1.2): la vista ya no trae JS inline; rutas, telares
 * seleccionados y máquinas de urdido van en data-pagina y las filas salen de <template>.
 */
class ProgramacionRequerimientosVistaTest extends TestCase
{
    use ModuloUrdEng;

    private const MODULO = 52; // Programa Urd / Eng

    private const VISTA = 'modulos/programa_urd_eng/programacion-requerimientos.blade.php';

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararSqlite();
        $this->tablaDe(URDCatalogoMaquina::class);
        $this->withoutVite();
    }

    public function test_renderiza_con_config_en_data_pagina_y_sin_script_propio(): void
    {
        $maquina = new URDCatalogoMaquina;
        DB::connection('sqlsrv')->table($maquina->getTable())->insert([
            ['Nombre' => 'MC2', 'Departamento' => 'Urdido'],
            ['Nombre' => 'KM1', 'Departamento' => 'Urdido'],
            ['Nombre' => 'MC1', 'Departamento' => 'Urdido'],
            ['Nombre' => 'W1', 'Departamento' => 'Engomado'],
        ]);

        // Con id no se consulta el inventario; el apóstrofo prueba el escape del atributo.
        $telares = [['id' => 7, 'no_telar' => '201', 'tipo' => 'RIZO', 'cuenta' => "41'12", 'calibre' => '12', 'fecha' => '2026-09-28', 'turno' => '1']];

        $html = $this->actingAs($this->usuarioCon([self::MODULO => ['acceso']], 'Urdido'))
            ->get(route('programa.urd.eng.programacion.requerimientos', ['telares' => json_encode($telares)]))
            ->assertOk()
            ->getContent();

        $config = $this->jsonDeAtributo($html, 'data-pagina');
        $this->assertSame([
            'resumen' => route('programa.urd.eng.programacion.resumen.semanas'),
            'actualizarTelar' => route('programa.urd.eng.actualizar.telar'),
            'hilos' => route('programa.urd.eng.hilos'),
            'tamanos' => route('programa.urd.eng.tamanos'),
            'creacionOrdenes' => route('programa.urd.eng.creacion.ordenes'),
        ], $config['rutas']);
        $this->assertSame(['MC1', 'MC2'], $config['opcionesUrdido']);
        $this->assertSame('201', $config['telares'][0]['no_telar']);
        $this->assertSame("41'12", $config['telares'][0]['cuenta']);

        foreach (['tpl-fila-requerimiento', 'tpl-requerimientos-vacio', 'tpl-requerimientos-error', 'tpl-resumen-mensaje', 'tpl-resumen-cargando'] as $plantilla) {
            $this->assertStringContainsString('<template id="'.$plantilla.'">', $html);
        }

        // Ningún <script> inline del HTML es de esta pantalla (los del layout no mencionan sus ids).
        preg_match_all('/<script\b(?![^>]*\bsrc\s*=)[^>]*>(.*?)<\/script>/is', $html, $m);
        foreach ($m[1] as $js) {
            $this->assertDoesNotMatchRegularExpression('/tbodyRequerimientos|tbodyResumen|btnSiguiente|RUTA_RESUMEN/', $js);
        }
        $this->assertStringNotContainsString('Swal.fire', $html);
        $this->assertStringNotContainsString('X-CSRF-TOKEN', $html);

        // UX-18: un solo <h1> (el del navbar) y botón de ícono con nombre accesible.
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertMatchesRegularExpression('/id="btnSiguiente"[^>]*aria-label="[^"]+"/', $html);
    }

    public function test_sin_telares_en_la_query_renderiza_igual(): void
    {
        $html = $this->actingAs($this->usuarioCon([self::MODULO => ['acceso']], 'Urdido'))
            ->get(route('programa.urd.eng.programacion.requerimientos'))
            ->assertOk()
            ->getContent();

        $this->assertSame([], $this->jsonDeAtributo($html, 'data-pagina')['telares']);
    }

    public function test_fuente_sin_script_handlers_ni_csrf_y_con_bundle(): void
    {
        $fuente = (string) file_get_contents(resource_path('views/'.self::VISTA));

        $this->assertDoesNotMatchRegularExpression('/<script\b(?![^>]*\bsrc\s*=)[^>]*>/i', $fuente);
        $this->assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=\s*["\']/i', $fuente);
        $this->assertStringNotContainsString('csrf', $fuente);
        $this->assertDoesNotMatchRegularExpression('/text-\[(9|10|11)px\]/', $fuente);
        $this->assertStringContainsString("@vite('resources/js/modulos/programa-urd-eng/programacion-requerimientos/index.ts')", $fuente);
    }

    public function test_sin_permiso_de_acceso_no_entra(): void
    {
        $this->actingAs($this->usuarioCon([], 'Urdido'))
            ->get(route('programa.urd.eng.programacion.requerimientos'))
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
