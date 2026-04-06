<?php

namespace Tests\Unit;

use App\Services\Planeacion\CatCodificados\Excel\CatCodificadosExcelRowMapper;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Tests\TestCase;

class CatCodificadosExcelRowMapperTest extends TestCase
{
    public function test_maps_dates_numerics_booleans_and_derived_fields(): void
    {
        $mapper = new CatCodificadosExcelRowMapper();
        $columnMap = [
            0 => 'OrdenTejido',
            1 => 'FechaTejido',
            2 => 'Produccion',
            3 => 'FibraId',
            4 => 'FibraComb1',
            5 => 'OrdCompartidaLider',
            6 => 'HrInicio',
            7 => 'InventSizeId',
        ];

        $row = [
            'OT-100',
            ExcelDate::dateTimeToExcel(Carbon::create(2026, 4, 6)),
            '1,245.50',
            ' AZUL  ',
            ' BLANCO ',
            'si',
            ExcelDate::dateTimeToExcel(Carbon::create(2026, 4, 6, 8, 30, 0)),
            4942.0,
        ];

        $result = $mapper->map($row, $columnMap);

        $this->assertSame('OT-100', $result['OrdenTejido']);
        $this->assertSame('2026-04-06', $result['FechaTejido']);
        $this->assertSame(1245.5, $result['Produccion']);
        $this->assertSame('AZUL', $result['FibraId']);
        $this->assertSame('AZUL', $result['ColorTrama']);
        $this->assertSame('BLANCO', $result['FibraComb1']);
        $this->assertSame('BLANCO', $result['NomColorC1']);
        $this->assertSame(1, $result['OrdCompartidaLider']);
        $this->assertSame('08:30:00', $result['HrInicio']);
        $this->assertSame('4942', $result['InventSizeId']);
    }

    public function test_rounds_calibre_rizo_and_calibre_pie2_to_one_decimal(): void
    {
        $mapper = new CatCodificadosExcelRowMapper();

        $result = $mapper->map(
            ['12.44', '18.96'],
            [
                0 => 'CalibreRizo',
                1 => 'CalibrePie2',
            ]
        );

        $this->assertSame(12.4, $result['CalibreRizo']);
        $this->assertSame(19.0, $result['CalibrePie2']);
    }

    public function test_returns_empty_payload_for_blank_values(): void
    {
        $mapper = new CatCodificadosExcelRowMapper();

        $result = $mapper->map(
            ['', null, '   '],
            [
                0 => 'OrdenTejido',
                1 => 'FechaTejido',
                2 => 'FibraId',
            ]
        );

        $this->assertSame([], $result);
    }
}
