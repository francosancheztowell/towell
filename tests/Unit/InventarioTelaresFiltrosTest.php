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
    /**
     * El nombre de columna venia del querystring y entraba pelado en where().
     * Laravel escapa el identificador, asi que no habia inyeccion, pero si se
     * podia filtrar (y sondear) cualquier columna de la tabla.
     */
    public function test_solo_se_filtran_las_columnas_de_la_lista(): void
    {
        $query = $this->querySpy();

        (new InventarioTelaresService)->applyFiltros($query, [
            ['columna' => 'password', 'valor' => 'x'],
            ['columna' => 'status', 'valor' => 'Inactivo'],
            ['columna' => '; DROP TABLE tej_inventario_telares--', 'valor' => 'x'],
            ['columna' => 'no_telar', 'valor' => '401'],
        ]);

        $this->assertSame(
            [['no_telar', '=', '401']],
            array_values(array_map(
                fn ($l) => $l[1],
                array_filter($query->llamadas, fn ($l) => $l[0] === 'where')
            )),
            'Solo no_telar esta en COLS_TELARES; el resto no debe llegar a SQL.'
        );
    }

    private function querySpy(): object
    {
        return new class
        {
            public array $llamadas = [];

            public function __call(string $metodo, array $args): static
            {
                $this->llamadas[] = [$metodo, $args];

                return $this;
            }
        };
    }

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

    /**
     * Karl Mayer no teje rizo/pie: son cuatro barras y se guardan como '1'..'4',
     * el mismo canon de UrdProgramaUrdido.RizoPie y del alta de ordenes KM.
     */
    public function test_normalize_tipo_entiende_rizo_pie_y_las_barras_de_karl_mayer(): void
    {
        $service = new InventarioTelaresService;

        foreach ([
            'rizo' => 'Rizo',
            'PIE' => 'Pie',
            '3' => '3',
            'Barra 2' => '2',
            'B4' => '4',
            '5' => null,        // solo hay cuatro barras
            'barra' => null,
            '' => null,
        ] as $entrada => $esperado) {
            $this->assertSame($esperado, $service->normalizeTipo((string) $entrada), "tipo: {$entrada}");
        }
    }
}
