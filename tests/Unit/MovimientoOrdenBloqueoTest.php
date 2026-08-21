<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * El antideadlock depende de una sola propiedad: que dos movimientos opuestos
 * (A->B y B->A) pidan los bloqueos en el MISMO orden. Si el criterio no fuera
 * simetrico, el orden canonico no serviria de nada.
 */
class MovimientoOrdenBloqueoTest extends TestCase
{
    /**
     * Reproduce el criterio de bloquearTelaresEnOrdenCanonico().
     *
     * @param  array{0: string, 1: string}  $origen
     * @param  array{0: string, 1: string}  $destino
     * @return array<int, array{0: string, 1: string}>
     */
    private function ordenar(array $origen, array $destino): array
    {
        $telares = [$origen, $destino];
        usort($telares, fn (array $a, array $b): int => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        return $telares;
    }

    public function test_dos_movimientos_opuestos_bloquean_en_el_mismo_orden(): void
    {
        $a = ['SALON1', '101'];
        $b = ['SALON1', '205'];

        $this->assertSame(
            $this->ordenar($a, $b),
            $this->ordenar($b, $a),
            'A->B y B->A deben producir la misma secuencia de bloqueo.'
        );
    }

    public function test_desempata_por_salon_antes_que_por_telar(): void
    {
        // Telar bajo en salon alto no debe adelantarse a un telar alto en salon bajo.
        $orden = $this->ordenar(['SALON2', '101'], ['SALON1', '999']);

        $this->assertSame(['SALON1', '999'], $orden[0]);
        $this->assertSame(['SALON2', '101'], $orden[1]);
    }

    public function test_el_orden_es_estable_para_cualquier_par(): void
    {
        $pares = [
            [['S1', '1'], ['S1', '2']],
            [['S2', '50'], ['S1', '50']],
            [['SA', '10'], ['SB', '10']],
            [['S1', '101'], ['S1', '101']],
        ];

        foreach ($pares as [$x, $y]) {
            $this->assertSame(
                $this->ordenar($x, $y),
                $this->ordenar($y, $x),
                'El orden debe ser independiente de quien sea origen y quien destino.'
            );
        }
    }
}
