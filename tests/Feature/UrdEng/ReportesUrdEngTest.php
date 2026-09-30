<?php

namespace Tests\Feature\UrdEng;

use Illuminate\Support\Carbon;
use Tests\Feature\UrdEng\Concerns\ModuloUrdEng;
use Tests\TestCase;

/**
 * Reportes de Urdido/Engomado (19-01): modal de rango compartido, resumen parametrizado,
 * panel de control y popup de reimpresión sin JS inline.
 */
class ReportesUrdEngTest extends TestCase
{
    use ModuloUrdEng;

    /** Vistas de la unidad: fuente Blade sin handlers ni <script> inline. */
    private const VISTAS = [
        'modulos/urdido/reportes-urdido',
        'modulos/urdido/reportes-roturas',
        'modulos/urdido/reportes-kaizen',
        'modulos/urdido/reportes-bpm-urdido',
        'modulos/urdido/reportes-resumen-urdido',
        'modulos/urdido/reportes-panel-control',
        'modulos/urdido/reimpresion-urdido-popup',
        'modulos/urdido/comun/reporte-rango',
        'modulos/urdido/comun/reporte-resumen',
        'modulos/engomado/reportes-bpm-engomado',
        'modulos/engomado/reportes-control-merma',
        'modulos/engomado/reporte-resumen-engomado',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararSqlite();
        $this->withoutVite();
        Carbon::setTestNow(Carbon::parse('2026-09-26 21:30:00', 'America/Mexico_City'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return array<string, array{string, string, string|null}> */
    public static function reportesConRango(): array
    {
        return [
            'oee' => ['/urdido/reportesurdido/03-oee-urd-eng', 'urdido.reportes.urdido.03-oee', 'Solo finalizados'],
            'roturas' => ['/urdido/reportesurdido/roturas-millon', 'urdido.reportes.urdido.roturas', 'Solo finalizados'],
            'kaizen' => ['/urdido/reportesurdido/kaizen', 'urdido.reportes.urdido.kaizen', 'Solo finalizados'],
            'bpm-urdido' => ['/urdido/reportesurdido/bpm-urdido', 'urdido.reportes.urdido.bpm', 'Solo terminados/autorizados'],
            'bpm-engomado' => ['/engomado/reportesengomado/bpm-engomado', 'engomado.reportes.bpm', 'Solo terminados/autorizados'],
            'control-merma' => ['/engomado/reportesengomado/control-merma', 'engomado.reportes.control-merma', null],
        ];
    }

    /** @dataProvider reportesConRango */
    public function test_sin_fechas_pinta_el_modal_de_rango_con_hoy(string $url, string $ruta, ?string $checkbox): void
    {
        $html = $this->actingAs($this->usuarioCon([], 'Urdido'))->get($url)->assertOk()->getContent();

        $this->assertStringContainsString('id="modalReporteRango"', $html);
        $this->assertStringContainsString('data-ui-modal-open="modalReporteRango"', $html);
        $this->assertStringNotContainsString('mostrarModalConsultar', $html);
        $this->assertStringNotContainsString('Swal.fire', $html);

        $config = $this->configRango($html);
        $this->assertSame(route($ruta), $config['ruta']);
        $this->assertSame($checkbox !== null, $config['conCheckbox']);
        $this->assertSame('', $config['fechaIni']);

        // Hoy en la zona de la app (antes: toISOString() = día UTC, ya "mañana" a las 21:30 en CDMX).
        $this->assertStringContainsString('value="2026-09-26"', $html);
        if ($checkbox !== null) {
            $this->assertStringContainsString($checkbox, $html);
            $this->assertMatchesRegularExpression('/id="rr_solo_finalizados"[^>]*checked/', $html);
        } else {
            $this->assertStringNotContainsString('rr_solo_finalizados', $html);
        }
    }

    public function test_con_fechas_precarga_los_valores_actuales(): void
    {
        $this->actingAs($this->usuarioCon([], 'Urdido'));
        $html = view('modulos.urdido.comun.reporte-rango', [
            'ruta' => route('urdido.reportes.urdido.kaizen'),
            'checkbox' => 'Solo finalizados',
            'fechaIni' => '2026-09-01',
            'fechaFin' => '2026-09-30',
            'soloFinalizados' => false,
        ])->render();

        $config = $this->configRango($html);
        $this->assertSame(['2026-09-01', '2026-09-30'], [$config['fechaIni'], $config['fechaFin']]);
        $this->assertStringContainsString('value="2026-09-01"', $html);
        $this->assertStringContainsString('value="2026-09-30"', $html);
        $this->assertDoesNotMatchRegularExpression('/id="rr_solo_finalizados"[^>]*checked/', $html);
        $this->assertStringContainsString('form="formReporteRango"', $html);
    }

    /** @return array<string, array{string, string, string, string}> */
    public static function resumenes(): array
    {
        return [
            'urdido' => ['/urdido/reportesurdido/resumen', 'modalConsultarResumenUrdido', 'resumenChartUrdido', 'RESUMEN SEMANAL URDIDO'],
            'engomado' => ['/engomado/reportesengomado/resumen-engomado', 'modalConsultarResumenEngomado', 'resumenChart', 'RESUMEN SEMANAL ENGOMADO'],
        ];
    }

    /** @dataProvider resumenes */
    public function test_resumen_sin_fechas_tiene_modal_y_no_graficas(string $url, string $modal, string $canvas, string $titulo): void
    {
        $html = $this->actingAs($this->usuarioCon([], 'Urdido'))->get($url)->assertOk()->getContent();

        $this->assertStringContainsString('id="'.$modal.'"', $html);
        $this->assertStringContainsString('data-accion-resumen="abrir"', $html);
        $this->assertStringContainsString($titulo, $html);
        $this->assertStringNotContainsString('id="'.$canvas.'"', $html);
        $this->assertSame(['datos' => []], $this->jsonDeAtributo($html, 'data-reporte-resumen'));
    }

    /** @dataProvider resumenes */
    public function test_resumen_con_datos_pasa_las_semanas_al_bundle(string $url, string $modal, string $canvas): void
    {
        $this->actingAs($this->usuarioCon([], 'Urdido'));
        $variante = str_contains($url, 'engomado') ? 'engomado' : 'urdido';
        $semana = ['semana_label' => 'SEM-36-2026', 'total_ordenes' => 2, 'total_julios' => 4, 'total_kg' => 10, 'total_metros' => 100,
            'peso_promedio' => 2.5, 'metros_promedio' => 25, 'cuenta_promedio' => 3000, 'eficiencia' => 80];
        $vista = $variante === 'urdido' ? 'modulos.urdido.reportes-resumen-urdido' : 'modulos.engomado.reporte-resumen-engomado';
        $html = view($vista, ['datosSemanales' => [$semana], 'fechaIni' => '2026-09-01', 'fechaFin' => '2026-09-30'])->render();

        $this->assertStringContainsString('id="'.$canvas.'" data-grafica-resumen="promedios"', $html);
        $this->assertStringContainsString('data-grafica-resumen="eficiencia"', $html);
        $this->assertSame('SEM-36-2026', $this->jsonDeAtributo($html, 'data-reporte-resumen')['datos'][0]['semana_label']);
        $this->assertStringContainsString($variante === 'urdido' ? '/reportesurdido/resumen/excel?' : '/resumen-engomado/excel?', $html);
        // Única diferencia de marcado entre variantes: el encabezado de eficiencia en negro solo en urdido.
        $this->assertSame($variante === 'urdido', str_contains($html, 'bg-yellow-400 text-black'));
    }

    public function test_panel_control_pasa_semanas_y_categorias_al_bundle(): void
    {
        $this->actingAs($this->usuarioCon([], 'Urdido'));
        $html = view('modulos.urdido.reportes-panel-control', [
            'telar' => 'ambos', 'anio' => 2026, 'desde' => null, 'hasta' => null,
            'kpis' => ['semanas' => 1, 'eventos' => 2],
            'semanas_detalle' => [['semana' => 36, 'efic' => 80.0, 'est' => 90.0, 'rpm' => 400, 'rpm_est' => 420, 'dif' => -10.0, 'dias' => 5, 'eventos' => 2, 'estado' => 'Crítico']],
            'categorias' => [['categoria' => "Paro <b> d'ur", 'menciones' => 2, 'porcentaje' => 1.0]],
            'hallazgos' => [],
        ])->render();

        $datos = $this->jsonDeAtributo($html, 'data-panel-control');
        $this->assertSame(36, $datos['semanas'][0]['semana']);
        // Con ' y <: el JSON va con JSON_HEX_* y no rompe el atributo data-*='...'.
        $this->assertSame("Paro <b> d'ur", $datos['categorias'][0]['categoria']);
        $this->assertStringNotContainsString('Paro <b>', $html);
        $this->assertStringContainsString('id="panelKmEficienciaChart"', $html);
    }

    public function test_popup_de_reimpresion_sin_script_inline(): void
    {
        $html = view('modulos.urdido.reimpresion-urdido-popup', ['pdfUrl' => '/pdf?x=1', 'ordenId' => 7])->render();

        $this->assertStringContainsString('id="pdf-frame"', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('window.print', $html);
    }

    public function test_fuentes_blade_sin_js_inline(): void
    {
        foreach (self::VISTAS as $vista) {
            $fuente = (string) file_get_contents(resource_path('views/'.$vista.'.blade.php'));
            $this->assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=/i', $fuente, "$vista trae un on*= inline");
            $this->assertDoesNotMatchRegularExpression('/<script(?![^>]*\bsrc=)/i', $fuente, "$vista trae <script> inline");
            $this->assertStringNotContainsString('Swal', $fuente, $vista);
            $this->assertStringNotContainsString('resources/js/charts.js', $fuente, $vista);
        }
    }

    /** @return array<string, mixed> */
    private function configRango(string $html): array
    {
        return $this->jsonDeAtributo($html, 'data-reporte-rango');
    }

    /** @return array<string, mixed> */
    private function jsonDeAtributo(string $html, string $atributo): array
    {
        $this->assertMatchesRegularExpression("/{$atributo}='([^']*)'/", $html);
        preg_match("/{$atributo}='([^']*)'/", $html, $m);

        return json_decode(html_entity_decode($m[1], ENT_QUOTES), true, 512, JSON_THROW_ON_ERROR);
    }
}
