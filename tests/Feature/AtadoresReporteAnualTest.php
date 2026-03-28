<?php

namespace Tests\Feature;

use App\Exports\Reporte00EAtadoresRangoExport;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

class AtadoresReporteAnualTest extends TestCase
{
    use UsesSqlsrvSqlite;

    private string $reportsRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqlsrvSqlite();
        $this->createAuthTable();

        $this->reportsRoot = storage_path('framework/testing/reports-atadores');

        if (! is_dir($this->reportsRoot)) {
            mkdir($this->reportsRoot, 0777, true);
        }

        foreach (glob($this->reportsRoot.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        config()->set('filesystems.disks.reports_atadores.root', $this->reportsRoot);
    }

    public function test_excel_route_guarda_archivo_anual_en_ruta_configurada(): void
    {
        $this->actingAs($this->createUsuario(['area' => 'Atadores']));

        Excel::shouldReceive('raw')
            ->once()
            ->withArgs(function ($export, $writerType) {
                $this->assertInstanceOf(Reporte00EAtadoresRangoExport::class, $export);
                $this->assertSame(ExcelFormat::XLSX, $writerType);

                $reflection = new \ReflectionClass($export);
                $weekStart = $reflection->getProperty('weekStart');
                $weekEnd = $reflection->getProperty('weekEnd');
                $weekStart->setAccessible(true);
                $weekEnd->setAccessible(true);

                $this->assertSame('2025-12-29', $weekStart->getValue($export)->toDateString());
                $this->assertSame('2026-12-28', $weekEnd->getValue($export)->toDateString());

                return true;
            })
            ->andReturn('excel-binary');

        $response = $this->get(route('atadores.reportes.atadores.excel', [
            'fecha_ini' => '2026-03-03',
            'fecha_fin' => '2026-03-04',
        ]));

        $response->assertRedirect(route('atadores.reportes.atadores', [
            'fecha_ini' => '2026-03-03',
            'fecha_fin' => '2026-03-04',
        ]));
        $response->assertSessionHas('success');

        $savedFile = $this->reportsRoot.DIRECTORY_SEPARATOR.'00E Atadores 2026.xlsx';

        $this->assertFileExists($savedFile);
        $this->assertSame('excel-binary', file_get_contents($savedFile));
    }

    public function test_excel_route_rechaza_rangos_de_distinto_anio_para_archivo_anual(): void
    {
        $this->actingAs($this->createUsuario(['area' => 'Atadores']));

        Excel::shouldReceive('raw')->never();

        $response = $this->get(route('atadores.reportes.atadores.excel', [
            'fecha_ini' => '2026-12-31',
            'fecha_fin' => '2027-01-01',
        ]));

        $response->assertRedirect(route('atadores.reportes.atadores', [
            'fecha_ini' => '2026-12-31',
            'fecha_fin' => '2027-01-01',
        ]));
        $response->assertSessionHas('error', 'Para guardar el archivo anual del 00E Atadores, selecciona un rango dentro del mismo año.');
    }
}
