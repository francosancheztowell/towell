<?php

namespace Tests\Unit;

use App\Services\ProgramaUrdEng\InventarioTelaresService;
use Tests\TestCase;

/**
 * El endpoint /inventario-telares recibe ?filtros=[{columna,valor}] desde la vista
 * de inventario de telas. Durante mucho tiempo se ignoraron y el filtrado se hacia
 * en el navegador; este test falla si alguien vuelve a desconectarlos o cambia
 * no_telar por un LIKE (401 traeria tambien 1401).
 */
class InventarioTelaresFiltrosTest extends TestCase
{
    public function test_los_filtros_se_traducen_a_condiciones_sql(): void
    {
        $query = new class
        {
            public array $llamadas = [];

            public function __call(string $metodo, array $args): static
            {
                $this->llamadas[] = [$metodo, $args];

                return $this;
            }
        };

        (new InventarioTelaresService)->applyFiltros($query, [
            ['columna' => 'no_telar', 'valor' => '401'],
            ['columna' => 'hilo', 'valor' => ' ALG-OPEN '],
            ['columna' => 'salon', 'valor' => 'Karl Mayer'],
            ['columna' => 'tipo', 'valor' => ''],          // vacio: se ignora
        ]);

        $this->assertSame(
            [['no_telar', '=', '401'], ['hilo', '!=', ''], ['salon', 'like', '%Karl Mayer%']],
            array_values(array_map(
                fn ($l) => $l[1],
                array_filter($query->llamadas, fn ($l) => $l[0] === 'where')
            )),
            'no_telar debe comparar exacto y el resto con LIKE; los valores vacios no filtran.'
        );

        $whereRaw = array_values(array_filter($query->llamadas, fn ($l) => $l[0] === 'whereRaw'));
        $this->assertCount(1, $whereRaw, 'hilo se compara sin distinguir mayusculas ni espacios.');
        $this->assertSame(['ALG-OPEN'], $whereRaw[0][1][1]);
    }
}
