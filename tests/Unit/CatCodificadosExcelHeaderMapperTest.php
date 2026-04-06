<?php

namespace Tests\Unit;

use App\Services\Planeacion\CatCodificados\Excel\CatCodificadosExcelHeaderMapper;
use Tests\TestCase;

class CatCodificadosExcelHeaderMapperTest extends TestCase
{
    public function test_maps_the_base_template_headers_without_errors(): void
    {
        $mapper = new CatCodificadosExcelHeaderMapper();

        $result = $mapper->map($mapper->expectedHeaders());

        $this->assertSame([], $result['errors']);
        $this->assertSame('OrdenTejido', $result['columnMap'][0]);
        $this->assertSame('CalibreTrama2', $result['columnMap'][23]);
        $this->assertSame('CalibreRizo2', $result['columnMap'][32]);
        $this->assertSame('CalibrePie2', $result['columnMap'][36]);
        $this->assertSame('CategoriaCalidad', $result['columnMap'][55]);
        $this->assertArrayNotHasKey(90, $result['columnMap']);
    }

    public function test_template_covers_columns_from_a_to_cq(): void
    {
        $mapper = new CatCodificadosExcelHeaderMapper();
        $columns = $mapper->expectedColumns();

        $this->assertCount(95, $columns);
        $this->assertSame('A', $columns[0]);
        $this->assertSame('CQ', $columns[94]);
    }

    public function test_expected_headers_match_the_fixed_codi_plantilla_order(): void
    {
        $mapper = new CatCodificadosExcelHeaderMapper();
        $headers = [
            'Num de Orden', 'Fecha  Orden', 'Fecha   Cumplimiento.', 'Departamento', 'Telar Actual', 'Prioridad',
            'Modelo', 'CLAVE MODELO', 'CLAVE  AX', 'Tamaño', 'TOLERANCIA', 'CODIGO DE DIBUJO',
            'Fecha Compromiso', 'Flogs', 'Nombre de Formato Logístico', 'Clave', 'Cantidad a Producir',
            'Peine', 'Ancho', 'Largo', 'P_crudo', 'Luchaje', 'Tra', 'Hilo', 'OBS.', 'Tipo plano',
            'Med plano', 'TIPO DE RIZO', 'ALTURA DE RIZO', 'OBS', 'Veloc.    Mínima', 'Rizo', 'Hilo',
            'CUENTA', 'OBS.', 'Pie', 'Hilo', 'CUENTA', 'OBS.', 'C1', 'OBS', 'C2', 'OBS', 'C3', 'OBS',
            'C4', 'OBS', 'Med. de Cenefa', 'Med de inicio de rizo a cenefa', 'RAZURADA', 'TIRAS',
            'Repeticiones p/corte', 'No. De Marbetes', 'Cambio de repaso', 'Vendedor', 'CategoriaCalidad',
            'Observaciones', 'TRAMA (Ancho Peine)', 'LOG. DE LUCHA TOTAL', 'C1  Trama de Fondo', 'Hilo',
            'OBS.', 'PASADAS', 'C1', 'Hilo', 'OBS.', 'PASADAS', 'C2', 'Hilo', 'OBS.', 'PASADAS', 'C3',
            'Hilo', 'OBS.', 'PASADAS', 'C4', 'Hilo', 'OBS.', 'PASADAS', 'C5', 'Hilo', 'OBS.', 'PASADAS',
            'TOTAL', 'RESPONSABLE DE INICIO', 'Hr DE INICIO', 'Hr DE TERMINO', 'MINUTOS DEL CAMBIO=',
            'PESO MUESTRA', 'REGISTRO DE ALINEACION', '', 'OBSERVACIONES PARA PROGRAMACION DE PROD.',
            'Cantidad a Producir', 'Tejidas', 'pza. x rollo',
        ];

        $this->assertSame($headers, $mapper->expectedHeaders());
        $this->assertSame([], $mapper->map($headers)['errors']);
    }

    public function test_rejects_legacy_header_variants_outside_the_fixed_template(): void
    {
        $mapper = new CatCodificadosExcelHeaderMapper();
        $headers = $mapper->expectedHeaders();
        $headers[0] = 'OrdenTejido';
        $headers[4] = 'TelarId';
        $headers[55] = 'Categoria Calidad';

        $result = $mapper->map($headers);

        $this->assertCount(3, $result['errors']);
        $this->assertSame(1, $result['errors'][0]['column']);
        $this->assertSame('Num de Orden', $result['errors'][0]['expected']);
        $this->assertSame(5, $result['errors'][1]['column']);
        $this->assertSame('Telar Actual', $result['errors'][1]['expected']);
        $this->assertSame(56, $result['errors'][2]['column']);
        $this->assertSame('CategoriaCalidad', $result['errors'][2]['expected']);
    }

    public function test_reports_invalid_headers_with_column_context(): void
    {
        $mapper = new CatCodificadosExcelHeaderMapper();
        $headers = $mapper->expectedHeaders();
        $headers[0] = 'OrdenX';
        $headers[17] = 'Fecha rara';

        $result = $mapper->map($headers);

        $this->assertCount(2, $result['errors']);
        $this->assertSame(1, $result['errors'][0]['column']);
        $this->assertSame('A', $result['errors'][0]['column_letter']);
        $this->assertSame('Num de Orden', $result['errors'][0]['expected']);
        $this->assertSame('OrdenX', $result['errors'][0]['actual']);
    }
}
