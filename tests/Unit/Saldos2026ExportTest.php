<?php

namespace Tests\Unit;

use App\Exports\Saldos2026Export;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use ReflectionClass;
use Tests\TestCase;

class Saldos2026ExportTest extends TestCase
{
    public function test_export_clears_template_sample_rows_and_keeps_cuenta_y_fibra_in_their_columns(): void
    {
        $sheet = $this->loadFilledSheet(collect([
            $this->makeRow([
                'NoProduccion' => '36403',
                'CalibreRizo2' => 16,
                'CuentaRizo' => '9999',
                'FibraRizo' => 'FIBRA-R1',
                'CalibrePie2' => 10,
                'CuentaPie' => '8888',
                'FibraPie' => 'PIE-R1',
            ]),
            $this->makeRow([
                'NoProduccion' => '36404',
                'CalibreRizo2' => 15.5,
                'CuentaRizo' => '7777',
                'FibraRizo' => 'FIBRA-R2',
                'CalibrePie2' => 20.2,
                'CuentaPie' => '6666',
                'FibraPie' => 'PIE-R2',
            ]),
        ]));

        $this->assertEquals(16.0, $sheet->getCell('AH4')->getValue());
        $this->assertEquals(9999.0, $sheet->getCell('AI4')->getValue());
        $this->assertSame('FIBRA-R1', $sheet->getCell('AJ4')->getValue());
        $this->assertSame('', $sheet->getCell('AK4')->getValue());
        $this->assertEquals(10.0, $sheet->getCell('AL4')->getValue());
        $this->assertEquals(8888.0, $sheet->getCell('AM4')->getValue());
        $this->assertSame('PIE-R1', $sheet->getCell('AN4')->getValue());
        $this->assertSame('', $sheet->getCell('AO4')->getValue());

        $this->assertEquals(15.5, $sheet->getCell('AH5')->getValue());
        $this->assertEquals(7777.0, $sheet->getCell('AI5')->getValue());
        $this->assertSame('FIBRA-R2', $sheet->getCell('AJ5')->getValue());
        $this->assertSame('', $sheet->getCell('AK5')->getValue());
        $this->assertEquals(20.2, $sheet->getCell('AL5')->getValue());
        $this->assertEquals(6666.0, $sheet->getCell('AM5')->getValue());
        $this->assertSame('PIE-R2', $sheet->getCell('AN5')->getValue());
        $this->assertSame('', $sheet->getCell('AO5')->getValue());

        $this->assertSame('', $sheet->getCell('AH6')->getValue());
        $this->assertSame('', $sheet->getCell('AI6')->getValue());
        $this->assertSame('', $sheet->getCell('AJ6')->getValue());
        $this->assertSame('', $sheet->getCell('AK6')->getValue());
        $this->assertSame('', $sheet->getCell('AL6')->getValue());
        $this->assertSame('', $sheet->getCell('AM6')->getValue());
        $this->assertSame('', $sheet->getCell('AN6')->getValue());
        $this->assertSame('', $sheet->getCell('AO6')->getValue());
    }

    private function loadFilledSheet(Collection $rows): Worksheet
    {
        $export = new Saldos2026Export($rows);
        $reflection = new ReflectionClass($export);

        $loadTemplateWorkbook = $reflection->getMethod('loadTemplateWorkbook');
        $loadTemplateWorkbook->setAccessible(true);

        $fillSaldosSheet = $reflection->getMethod('fillSaldosSheet');
        $fillSaldosSheet->setAccessible(true);

        $spreadsheet = $loadTemplateWorkbook->invoke($export);
        $sheet = $spreadsheet->getSheetByName('SALDOS 2026');

        $fillSaldosSheet->invoke($export, $sheet);

        return $sheet;
    }

    private function makeRow(array $overrides = []): object
    {
        return (object) array_merge([
            'NoTelarId' => '201',
            'NoExisteBase' => null,
            'FechaInicio' => null,
            'NoProduccion' => '36400',
            'FechaCreacion' => null,
            'EntregaCte' => null,
            'SalonTejidoId' => 'JACQUARD',
            'Prioridad' => 1,
            'NombreProducto' => 'MODELO TEST',
            'TamanoClave' => 'TK-01',
            'ItemId' => 'ITEM-01',
            'Tolerancia' => '',
            'CodigoDibujo' => '',
            'EntregaProduc' => null,
            'FlogsId' => 'FLOG-01',
            'Clave' => 'CLV',
            'TotalPedido' => 120,
            'Peine' => 0,
            'Ancho' => 0,
            'LargoCrudo' => 0,
            'PesoCrudo' => 0,
            'Luchaje' => 0,
            'CalibreTrama2' => 0,
            'FibraTrama' => '',
            'MedidaPlano' => '',
            'VelocidadSTD' => 0,
            'CuentaRizo' => '3156',
            'CalibreRizo2' => 12,
            'FibraRizo' => 'A12',
            'CuentaPie' => '4112',
            'CalibrePie2' => 10,
            'FibraPie' => 'OPEN',
            'Rasurado' => 'No',
            'NoTiras' => 1,
            'Repeticiones' => 1,
            'TotalRollos' => 1,
            'Produccion' => 50,
            'SaldoPedido' => 70,
            'Observaciones' => '',
            'ObsModelo' => '',
            'TipoRizo' => '',
            'AlturaRizo' => '',
            'C1' => null,
            'ObsC1' => '',
            'C2' => null,
            'ObsC2' => '',
            'C3' => null,
            'ObsC3' => '',
            'C4' => null,
            'ObsC4' => '',
            'MedidaCenefa' => null,
            'MedIniRizoCenefa' => null,
            '_esLider' => true,
            '_esGrupoVinculado' => false,
            '_sumTotalPedido' => 120,
            '_sumSaldoPedido' => 70,
            '_sumProduccion' => 50,
            '_sumTotalRollos' => 1,
        ], $overrides);
    }
}
