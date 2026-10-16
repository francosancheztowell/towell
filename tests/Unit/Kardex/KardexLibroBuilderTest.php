<?php

declare(strict_types=1);

namespace Tests\Unit\Kardex;

use App\Services\Kardex\KardexLibroBuilder;
use PHPUnit\Framework\TestCase;

final class KardexLibroBuilderTest extends TestCase
{
    /** @return array<string, mixed> */
    private function construir(array $filas = []): array
    {
        return (new KardexLibroBuilder)->construir(2026, 8, [
            'urdido' => [], 'julurd' => [], 'jultela' => [], 'tejido' => [], 'crudo' => [], ...$filas,
        ]);
    }

    /** @return array<string, mixed> */
    private function seccion(array $libro, string $clave): array
    {
        foreach ($libro['secciones'] as $s) {
            if ($s['clave'] === $clave) {
                return $s;
            }
        }
        $this->fail("Falta la sección {$clave}");
    }

    /** Valor de una columna por su encabezado (encabezados[0] es la etiqueta). */
    private function valor(array $seccion, array $valores, string $encabezado): float
    {
        $i = array_search($encabezado, $seccion['encabezados'], true);
        $this->assertIsInt($i, "Falta el encabezado {$encabezado}");

        return $valores[$i - 1];
    }

    public function test_urdido_agrupa_por_localidad_con_traspasos_netos_y_existencia_calculada(): void
    {
        $libro = $this->construir(['urdido' => [
            ['ITEMID' => '10/1', 'CONFIGID' => 'POL', 'INVENTSIZEID' => 'ENTERO', 'INVENTCOLORID' => '1000', 'WMSLOCATIONID' => 'MC2',
                'INICIALKG' => '100', 'ENTRADAMPKG' => '500', 'ENTMPBBKG' => '0', 'ENTRADASOTROSKG' => '0',
                'ENTTRASPASOSKG' => '50', 'SALTRASPASOSKG' => '80', 'EXISTENCIAKG' => '650',
                'SALPRODMC2KG' => '400', 'AJUSTESKG' => '-10', 'SALDOKG' => '180'],
            ['ITEMID' => '12/1', 'WMSLOCATIONID' => 'MC1', 'INICIALKG' => '10', 'ENTRADAMPKG' => '90', 'SALPRODMC1KG' => '100', 'SALDOKG' => '0'],
        ]]);

        $s = $this->seccion($libro, 'urdido');
        $this->assertSame('ALMACÉN PTE URDIDO', $s['titulo']);
        $this->assertSame(['MC1', 'MC2'], array_column($s['renglones'], 'etiqueta'));
        $this->assertSame('Existencia Kg.', $s['encabezados'][6], 'Existencia va antes de la primera salida');

        $mc2 = $s['renglones'][1]['valores'];
        $this->assertSame(-30.0, $this->valor($s, $mc2, 'Traspasos'));
        $this->assertSame(570.0, $this->valor($s, $mc2, 'Existencia Kg.'));
        $this->assertSame(180.0, $this->valor($s, $mc2, 'Saldo Kg.'));
        $this->assertSame(590.0, $this->valor($s, $s['total']['valores'], 'MPKg'));
        $this->assertSame('Total general', $s['total']['etiqueta']);
    }

    public function test_julios_tela_agrupa_por_articulo_y_netea_otros(): void
    {
        $s = $this->seccion($this->construir(['jultela' => [
            ['ITEMID' => 'JU-ENG-PI-C', 'INICIALKG' => '8012.76', 'ENTWP3KG' => '47709.41', 'SALOTROSKG' => '100', 'ENTRADASOTROSKG' => '1615.73', 'SALDOKG' => '13627.96'],
        ]]), 'jultela');

        $r = $s['renglones'][0];
        $this->assertSame('JU-ENG-PI-C', $r['etiqueta']);
        $this->assertSame('Articulo', $s['encabezados'][0]);
        $this->assertSame(-1515.73, $this->valor($s, $r['valores'], 'Otros Kg.'));
        $this->assertSame(55722.17, $this->valor($s, $r['valores'], 'Existencia Kg.'));
    }

    public function test_tejido_descuenta_los_traspasos_internos_de_otros(): void
    {
        $s = $this->seccion($this->construir(['tejido' => [
            ['WMSLOCATIONID' => '201', 'INICIALKG' => '938', 'ENTENGOMKG' => '4758', 'EXISTENCIAKG' => '6696', 'SALOTROSKG' => '1070.2', 'SALJACQKG' => '5478.72', 'SALDOKG' => '355.1'],
        ]]), 'tejido');

        $v = $s['renglones'][0]['valores'];
        $this->assertSame(70.2, $this->valor($s, $v, 'Otros Kg.'));
        $this->assertSame(5696.0, $this->valor($s, $v, 'Existencia Kg.'));
    }

    public function test_desglose_por_articulo_suma_entradas_y_salidas_y_omite_renglones_vacios(): void
    {
        $libro = $this->construir(['julurd' => [
            ['ITEMID' => 'A', 'CONFIGID' => 'C', 'INVENTSIZEID' => 'T', 'INVENTCOLORID' => '1000', 'WMSLOCATIONID' => 'MC1',
                'INICIALKG' => '5', 'ENTMC1KG' => '100', 'SALWP2' => '60', 'SALWP3' => '30', 'AJUSTESKG' => '1', 'SALDOKG' => '14'],
            ['ITEMID' => 'B', 'WMSLOCATIONID' => 'MC1'],
        ]]);

        $this->assertSame([[
            'a' => 'A', 'c' => 'C', 't' => 'T', 'o' => '1000', 'loc' => 'MC1', 'th' => '',
            'ini' => 5.0, 'ent' => 100.0, 'sal' => 90.0, 'aj' => 1.0, 'saldo' => 14.0,
        ]], $libro['articulos']['A-JUL/URD']);
    }

    public function test_kpis_flujo_y_crudo_salen_de_las_columnas_del_libro(): void
    {
        $libro = $this->construir([
            'urdido' => [['ENTRADAMPKG' => '137115.3', 'SALPRODMC1KG' => '1', 'SALPRODKMKG' => '2', 'INICIALKG' => '8211.78', 'SALDOKG' => '10479.45']],
            'julurd' => [['ENTMC1KG' => '10', 'ENTKM1KG' => '5', 'SALWP2' => '3', 'SALWP3' => '4', 'SALKM1KG' => '34554.8']],
            'jultela' => [['ENTWP2KG' => '7', 'JAQKG' => '1', 'ITEMAKG' => '2']],
            'tejido' => [['SALJACQKG' => '1', 'SALITEMAKG' => '2', 'SALSMITHKG' => '3', 'SALKMKG' => '4']],
            'crudo' => [
                ['ENTJACQUARDKG' => '91089.94', 'ENTJACQUARDPZA' => '310675', 'ENTITEMAKG' => '76951.7', 'ENTSMITHKG' => '76133', 'ENTKARLMAYERKG' => '34026.18', 'ENTRASURADOKG' => '25531.3'],
            ],
        ]);

        $this->assertSame(['anio' => 2026, 'mes' => 8, 'nombre' => 'Agosto 2026', 'corte' => '31/08/2026'], $libro['periodo']);
        $this->assertSame(278200.82, $libro['kpis']['crudoKg'], 'Sin rasurado, igual que KdxCrudo');
        $this->assertSame(310675.0, $libro['kpis']['crudoPz']);
        $this->assertSame(15.0, $libro['kpis']['julUrd']);
        $this->assertSame(7.0, $libro['kpis']['julTela']);
        $this->assertSame(91089.94, $libro['crudo']['referenciaKdx']['Ent Jacquard Kg']);
        $this->assertSame(['inicial' => 8211.78, 'final' => 10479.45], $libro['flujo']['nodos']['A-EP-URDID']);
        $this->assertSame(137115.3, $libro['flujo']['aristas']['mp-urdid']);
        $this->assertSame(3.0, $libro['flujo']['aristas']['urdid-julurd']);
        $this->assertSame(7.0, $libro['flujo']['aristas']['julurd-jultela']);
        $this->assertSame(34554.8, $libro['flujo']['aristas']['julurd-eptejid']);
        $this->assertSame(3.0, $libro['flujo']['aristas']['jultela-eptejid']);
        $this->assertSame(10.0, $libro['flujo']['aristas']['eptejid-crudo']);
    }

    public function test_mes_sin_renglones_deja_secciones_vacias_sin_total(): void
    {
        $libro = $this->construir();

        $this->assertCount(4, $libro['secciones']);
        foreach ($libro['secciones'] as $s) {
            $this->assertSame([], $s['renglones']);
            $this->assertNull($s['total']);
        }
        $this->assertSame(0.0, $libro['kpis']['crudoKg']);
    }
}
