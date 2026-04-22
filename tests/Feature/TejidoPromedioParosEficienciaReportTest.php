<?php

namespace Tests\Feature;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

class TejidoPromedioParosEficienciaReportTest extends TestCase
{
    use UsesSqlsrvSqlite;

    private int $initialOutputBufferLevel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->initialOutputBufferLevel = ob_get_level();
        $this->useSqlsrvSqlite();
        $this->createTejidoPromedioParosTables(includeAuthTable: true);
    }

    protected function tearDown(): void
    {
        while (ob_get_level() > $this->initialOutputBufferLevel) {
            ob_end_clean();
        }

        parent::tearDown();
    }

    public function test_tejido_report_index_lists_promedio_paros_card(): void
    {
        $this->actingAs($this->createUsuario(['area' => 'Tejido']));

        $response = $this->get(route('tejido.reportes.index'));

        $response->assertOk();
        $response->assertSee('Promedio Paros y Eficiencia');
        $response->assertSee(route('tejido.reportes.promedio-paros-eficiencia'), false);
    }

    public function test_report_view_loads_with_simple_filter_screen(): void
    {
        $this->actingAs($this->createUsuario(['area' => 'Tejido']));

        $response = $this->get(route('tejido.reportes.promedio-paros-eficiencia'));

        $response->assertOk();
        $response->assertSee('Promedio Paros y Eficiencia');
        $response->assertSee('Seleccione un rango de fechas para generar el reporte en Excel (incluye gráficas de líneas en JACQ, JACQ-SULZ, SMIT e ITEMA).');
    }

    public function test_excel_route_requires_complete_date_range(): void
    {
        $this->actingAs($this->createUsuario(['area' => 'Tejido']));

        $response = $this->get(route('tejido.reportes.promedio-paros-eficiencia.excel'));

        $response->assertRedirect(route('tejido.reportes.promedio-paros-eficiencia'));
        $response->assertSessionHas('error', 'Debe seleccionar fecha inicial y fecha final.');
    }

    public function test_excel_route_downloads_template_when_range_is_valid(): void
    {
        $this->actingAs($this->createUsuario(['area' => 'Tejido']));

        $response = $this->get(route('tejido.reportes.promedio-paros-eficiencia.excel', [
            'fecha_ini' => '2026-03-03',
            'fecha_fin' => '2026-03-04',
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $workbook = $this->loadWorkbookFromDownload($response);
        $this->assertSame(['SEMANA', 'JACQ', 'JACQ-SULZ', 'SMIT', 'ITEMA'], $workbook->getSheetNames());
    }

    private function loadWorkbookFromDownload(TestResponse $response): Spreadsheet
    {
        $baseResponse = $response->baseResponse;

        if (method_exists($baseResponse, 'getFile')) {
            return IOFactory::load($baseResponse->getFile()->getPathname());
        }

        $binary = method_exists($response, 'streamedContent')
            ? $response->streamedContent()
            : $response->getContent();

        $tempFile = tempnam(sys_get_temp_dir(), 'promedio-paros-feature-');
        file_put_contents($tempFile, $binary);

        $spreadsheet = IOFactory::load($tempFile);
        @unlink($tempFile);

        return $spreadsheet;
    }
}
