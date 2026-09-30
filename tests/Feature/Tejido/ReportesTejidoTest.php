<?php

namespace Tests\Feature\Tejido;

use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Tejido\Concerns\ModuloTejido;
use Tests\TestCase;

/**
 * Reportes de Tejido (19-02 p-d): las 6 vistas sin JS inline ni on*=, un solo <h1> (el del
 * navbar), modal de fechas en x-ui.modal-base y Saldos 2026 con datos falsos (su SQL usa
 * TOP, que sqlite no entiende: se renderiza la vista directamente).
 */
class ReportesTejidoTest extends TestCase
{
    use ModuloTejido;

    private const DIR = 'resources/views/modulos/tejido/reportes/';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->prepararSqlite();
    }

    /** @return array<string, array{string}> */
    public static function vistas(): array
    {
        return [
            'index' => ['index.blade.php'],
            'inv-telas' => ['inv-telas.blade.php'],
            'promedio' => ['promedio-paros-eficiencia.blade.php'],
            'marcas' => ['reporte-marcas-finales.blade.php'],
            'rpm' => ['rpm-semanal.blade.php'],
            'saldos' => ['saldos-2026.blade.php'],
            'modal-fechas' => ['partials/rango-fechas.blade.php'],
        ];
    }

    #[DataProvider('vistas')]
    public function test_fuente_sin_js_inline_ni_h1(string $archivo): void
    {
        $fuente = (string) file_get_contents(base_path(self::DIR.$archivo));

        $this->assertDoesNotMatchRegularExpression('/<script(?![^>]*\bsrc=)[^>]*>/i', $fuente, 'script inline');
        $this->assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=\s*"/i', $fuente, 'atributo on*=');
        $this->assertStringNotContainsString('csrf_token()', $fuente);
        $this->assertStringNotContainsString('Swal.', $fuente);
        $this->assertStringNotContainsString('<h1', $fuente, 'el <h1> es el del navbar (HANDOFF 17-02 C2)');
        $this->assertDoesNotMatchRegularExpression('/text-\[(\d|1[01])px\]|text-\[0\.\d+rem\]/', $fuente, 'texto < 12 px en clases');
    }

    private function unH1(string $html): void
    {
        $this->assertSame(1, substr_count(strtolower($html), '<h1'), 'un solo <h1> en la página');
    }

    /** @return array<string, array{string, string, string}> */
    public static function pantallasConModal(): array
    {
        return [
            'inv-telas' => ['/tejido/reportes/inv-telas', 'Consultar en rango', '"maxDias":5'],
            'promedio' => ['/tejido/reportes/promedio-paros-eficiencia', 'Consultar rango', '"maxDias":null'],
            'marcas' => ['/tejido/reportes/marcas-finales', 'Consultar rango', '"maxDias":null'],
            'rpm' => ['/tejido/reportes/rpm-semanal', 'Semana a consultar', '"modo":"semana"'],
        ];
    }

    #[DataProvider('pantallasConModal')]
    public function test_sin_fechas_el_modal_abre_solo(string $url, string $titulo, string $config): void
    {
        $html = $this->actingAs($this->usuarioCon([], 'Tejido'))->get($url)->assertOk()->getContent();

        $this->unH1($html);
        $this->assertStringContainsString('id="modalRangoTejido"', $html);
        $this->assertStringContainsString($titulo, $html);
        $this->assertStringContainsString('data-ui-modal-open="modalRangoTejido"', $html);
        $this->assertStringContainsString('data-rango-tejido=', $html);
        $this->assertStringContainsString('"abrirAlCargar":true', $html);
        $this->assertStringContainsString($config, $html);
        $this->assertStringNotContainsString('onclick="mostrarModal', $html);
        $this->assertStringNotContainsString('swal_fecha', $html);
        // La fecha por defecto es la de hoy en México (antes: toISOString() = UTC, el día siguiente después de las 18:00).
        $this->assertStringContainsString('value="'.now()->toDateString().'"', $html);
    }

    public function test_index_de_reportes(): void
    {
        $html = $this->actingAs($this->usuarioCon([], 'Tejido'))->get('/tejido/reportes')->assertOk()->getContent();

        $this->unH1($html);
        $this->assertStringContainsString('<h2 class="text-xl font-bold text-white">Reportes</h2>', $html);
        $this->assertStringContainsString(route('tejido.reportes.saldos-2026'), $html);
        $this->assertStringContainsString('<title>Reportes · Towell</title>', $html);
    }

    public function test_inv_telas_con_datos_no_abre_el_modal_y_trae_el_indice(): void
    {
        $this->actingAs($this->usuarioCon([], 'Tejido'));
        $html = $this->view('modulos.tejido.reportes.inv-telas', [
            'fechaIni' => '2026-09-01',
            'fechaFin' => '2026-09-03',
            'secciones' => [['nombre' => 'JACQUARD', 'filas' => [[
                'no_telar' => 201, 'fibra' => 'ALG', 'calibre' => '12', 'cuenta_rizo' => '10', 'cuenta_pie' => '',
                'por_dia' => ['2026-09-01' => ['turnos' => [1 => ['texto' => 'J-1', 'color' => 'blue']]]],
            ]]]],
            'dias' => [['fecha' => '2026-09-01', 'label' => 'Lun 01']],
            'leyendaColores' => [],
        ]);

        $html->assertSee('"abrirAlCargar":false', false);
        $html->assertSee('value="2026-09-01"', false);
        $html->assertSee('data-ruta-indice="'.route('tejido.reportes.index').'"', false);
        $html->assertSee('R: 10');
        $this->unH1((string) $html);
    }

    public function test_rpm_con_semana_precarga_el_lunes_y_sin_chart_js(): void
    {
        $this->actingAs($this->usuarioCon([], 'Tejido'));
        $html = (string) $this->view('modulos.tejido.reportes.rpm-semanal', [
            'secciones' => [],
            'filasOrdenTelar' => [['grupo' => 'JACQ', 'no_telar' => 201, 'rpm_real' => 320, 'rpm_ideal' => 350]],
            'totalGeneral' => ['grupo' => 'Total', 'rpm_real' => 320, 'rpm_ideal' => 350],
            'lunes' => '2026-09-28',
            'domingo' => '2026-10-04',
            'semanaParam' => '2026-09-28',
        ]);

        $this->assertStringContainsString('"abrirAlCargar":false', $html);
        $this->assertStringContainsString('value="2026-09-28"', $html);
        $this->assertStringContainsString('data-semana-texto', $html);
        $this->assertStringNotContainsString('charts.js', $html);
        $this->unH1($html);
    }

    /** Fila de Saldos con todas las propiedades que lee la vista. */
    private function filaSaldos(array $datos): object
    {
        $base = array_fill_keys(explode(' ', 'AlturaRizo Ancho C1 C2 C3 C4 CalibrePie2 CalibreRizo2 CalibreTrama2 Clave CodigoDibujo CuentaPie CuentaRizo EnProceso EntregaCte EntregaProduc FechaCreacion FechaInicio FibraPie FibraRizo FibraTrama FlogsId InventSizeId ItemId LargoCrudo Luchaje MedIniRizoCenefa MedidaCenefa MedidaPlano NoExisteBase NoMarbete NoProduccion NoTelarId NoTiras NombreProducto ObsC1 ObsC2 ObsC3 ObsC4 ObsModelo Observaciones OrdCompartida OrdenLider Peine PesoCrudo Prioridad Produccion Rasurado Repeticiones SaldoPedido SalonTejidoId TamanoClave TipoRizo Tolerancia TotalPedido TotalRollos VelocidadSTD _ordenLider _sumProduccion _sumRollosPorTejer _sumSaldoPedido _sumTotalPedido _sumTotalRollos'), null);

        return (object) array_merge($base, ['_esGrupoVinculado' => false, '_esLider' => true, '_finalizado' => false], $datos);
    }

    /** @return Collection<int, object> */
    public function registrosSaldos(): Collection
    {
        return collect([
            $this->filaSaldos(['Id' => 1, 'NoTelarId' => '201', 'NoProduccion' => 'OP-1', 'NombreProducto' => "Toalla <b>O'Neil</b>", 'SalonTejidoId' => 'JACQUARD', 'TotalPedido' => 1000, 'Produccion' => 400, 'SaldoPedido' => 600, 'Prioridad' => 1, 'NoTiras' => 2, 'Repeticiones' => 10, 'NoMarbete' => 5]),
            $this->filaSaldos(['Id' => 2, 'NoTelarId' => '202', 'NoProduccion' => 'OP-2', 'OrdCompartida' => '77', '_esGrupoVinculado' => true, 'SalonTejidoId' => 'SMIT', '_sumTotalPedido' => 900]),
            $this->filaSaldos(['Id' => 3, 'NoTelarId' => '305', 'NoProduccion' => 'OP-3', 'OrdCompartida' => '77', '_esGrupoVinculado' => true, '_esLider' => false, '_finalizado' => true]),
        ]);
    }

    public function test_saldos_con_datos_falsos_sin_js_inline_y_menu_tactil(): void
    {
        $this->actingAs($this->usuarioCon([], 'Tejido'));
        $html = (string) $this->view('modulos.tejido.reportes.saldos-2026', ['registros' => $this->registrosSaldos()]);

        $this->unH1($html);
        $this->assertStringContainsString('id="saldos-table"', $html);
        $this->assertSame(3, substr_count($html, 'class="saldos-row '));
        // Menú de columna: botones de tipo button y el hueco del "⋮" (botonAcciones).
        $this->assertStringContainsString('<button type="button" role="menuitem" class="ctx-btn" data-action="freeze">', $html);
        $this->assertStringContainsString('data-saldos-acciones', $html);
        // Filtro por valores en x-ui.modal-base con <template> (antes: Swal con HTML armado).
        $this->assertStringContainsString('id="modalSaldosFiltro"', $html);
        $this->assertStringContainsString('<template id="saldos-filtro-opcion">', $html);
        // Datos del usuario escapados.
        $this->assertStringContainsString('Toalla &lt;b&gt;O&#039;Neil&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<b>O\'Neil', $html);
        // Sin el atributo data-search muerto (no había buscador global).
        $this->assertStringNotContainsString('data-search=', $html);
        // Separador entre telares (201 → 202; 202 y 305 son la misma orden compartida).
        $this->assertSame(1, substr_count($html, '<tr class="saldos-telar-sep"'));
        $this->assertStringNotContainsString('text-[10px]', $html);
    }

    public function test_saldos_vacio_muestra_estado_vacio(): void
    {
        $this->actingAs($this->usuarioCon([], 'Tejido'));
        $html = (string) $this->view('modulos.tejido.reportes.saldos-2026', ['registros' => collect()]);

        $this->assertStringContainsString('Sin registros con orden de producción', $html);
        $this->assertStringNotContainsString('id="saldos-table"', $html);
        $this->unH1($html);
    }
}
