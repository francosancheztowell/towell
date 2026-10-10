<?php

declare(strict_types=1);

namespace Tests\Unit\Programas;

use App\Support\Programas\OperadorPorMetros;
use PHPUnit\Framework\TestCase;

/** Misma regla que operadorDelJulio en calificar julios, más la clave cuando no hay nombre. */
class OperadorPorMetrosTest extends TestCase
{
    public function test_mostrar_prefiere_nombre_y_si_no_la_clave(): void
    {
        $this->assertSame('Ana', OperadorPorMetros::mostrar(' Ana ', '100'));
        $this->assertSame('100', OperadorPorMetros::mostrar('  ', ' 100 '));
        $this->assertSame('', OperadorPorMetros::mostrar(null, null));
    }

    public function test_mayor_metraje_gana_y_recorta_el_nombre(): void
    {
        $row = (object) [
            'Metros1' => 10, 'NomEmpl1' => 'Ana',
            'Metros2' => '30', 'NomEmpl2' => ' Beto ',
            'Metros3' => 5, 'NomEmpl3' => 'Caro',
        ];

        $this->assertSame('Beto', OperadorPorMetros::mayorMetros($row));
    }

    public function test_empate_en_el_maximo_salta_el_nombre_vacio(): void
    {
        $row = (object) [
            'Metros1' => 30, 'NomEmpl1' => '',
            'Metros2' => 30, 'NomEmpl2' => 'Beto',
        ];

        $this->assertSame('Beto', OperadorPorMetros::mayorMetros($row));
    }

    public function test_sin_metros_toma_el_primer_nombre_o_la_clave(): void
    {
        $this->assertSame('Dani', OperadorPorMetros::mayorMetros((object) ['NomEmpl2' => 'Dani']));
        $this->assertSame('55', OperadorPorMetros::mayorMetros((object) ['CveEmpl1' => '55', 'Metros1' => 0]));
        $this->assertSame('', OperadorPorMetros::mayorMetros((object) []));
    }

    public function test_con_metros_omite_ceros_y_rotula_el_turno_sin_nombre(): void
    {
        $ops = OperadorPorMetros::conMetros((object) [
            'Metros1' => 0, 'NomEmpl1' => 'Ana',
            'Metros2' => 12.4, 'CveEmpl2' => '20',
            'Metros3' => 3, 'NomEmpl3' => '', 'CveEmpl3' => '',
        ]);

        $this->assertSame([
            ['nombre' => '20', 'metros' => 12.0],
            ['nombre' => 'Turno 3', 'metros' => 3.0],
        ], $ops);
    }
}
