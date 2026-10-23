<?php

namespace Tests\Feature\Planeacion;

use Illuminate\Support\Facades\DB;
use Tests\Feature\Planeacion\Concerns\ProgramaTejidoFixtures;
use Tests\TestCase;

/**
 * programa-tejido:recalcular-peso-grm2 con los casos medidos en ProdTowel (oct-2026): vacío con
 * los tres datos, el peso copiado de otra orden (676.44 en 13 modelos distintos), ya correcto, y
 * un valor viejo en una fila sin largo.
 */
class RecalcularPesoGrm2CommandTest extends TestCase
{
    use ProgramaTejidoFixtures;

    private const COMANDO = 'programa-tejido:recalcular-peso-grm2';

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararSuperficies();
        foreach (['ReqProgramaTejido', 'MuestrasPrograma'] as $tabla) {
            DB::table($tabla)->insert([
                // 990 × 10000 / (176 × 90) = 625.00
                ['Id' => 1, 'PesoCrudo' => 990, 'LargoCrudo' => 176, 'AnchoToalla' => 90, 'PesoGRM2' => null],
                // Copiado de otra orden: 314 × 10000 / (133 × 70) = 337.27
                ['Id' => 2, 'PesoCrudo' => 314, 'LargoCrudo' => 133, 'AnchoToalla' => 70, 'PesoGRM2' => 676.44000000000005],
                // Ya correcto (el float de SQL Server con su cola de decimales)
                ['Id' => 3, 'PesoCrudo' => 337, 'LargoCrudo' => 133, 'AnchoToalla' => 75, 'PesoGRM2' => 337.83999999999997],
                // Sin largo: no se puede calcular y el valor viejo no es de esta orden
                ['Id' => 4, 'PesoCrudo' => 400, 'LargoCrudo' => null, 'AnchoToalla' => 70, 'PesoGRM2' => 521.9],
            ]);
        }
    }

    /** @return array<int, float|null> */
    private function pesos(string $tabla = 'ReqProgramaTejido'): array
    {
        return DB::table($tabla)->orderBy('Id')->pluck('PesoGRM2', 'Id')->map(fn ($v) => $v === null ? null : round((float) $v, 2))->all();
    }

    public function test_sin_aplicar_solo_muestra_lo_que_cambiaria(): void
    {
        $this->artisan(self::COMANDO)
            ->expectsOutputToContain('3 registro(s) de ReqProgramaTejido cambiarían')
            ->assertSuccessful();

        $this->assertSame([1 => null, 2 => 676.44, 3 => 337.84, 4 => 521.9], $this->pesos());
    }

    public function test_aplicar_recalcula_y_deja_vacio_lo_que_no_se_puede_calcular(): void
    {
        $this->artisan(self::COMANDO, ['--aplicar' => true])
            ->expectsOutputToContain('Se actualizaron 3 registro(s) de ReqProgramaTejido')
            ->assertSuccessful();

        $this->assertSame([1 => 625.0, 2 => 337.27, 3 => 337.84, 4 => null], $this->pesos());
        $this->assertSame([1 => null, 2 => 676.44, 3 => 337.84, 4 => 521.9], $this->pesos('MuestrasPrograma'), 'Muestras no se toca');

        // Segunda corrida: ya no hay nada que cambiar.
        $this->artisan(self::COMANDO)->expectsOutputToContain('ya cuadra en todos')->assertSuccessful();
    }

    public function test_muestras_recalcula_su_propia_tabla(): void
    {
        $this->artisan(self::COMANDO, ['--muestras' => true, '--aplicar' => true])
            ->expectsOutputToContain('Se actualizaron 3 registro(s) de MuestrasPrograma')
            ->assertSuccessful();

        $this->assertSame([1 => 625.0, 2 => 337.27, 3 => 337.84, 4 => null], $this->pesos('MuestrasPrograma'));
        $this->assertSame([1 => null, 2 => 676.44, 3 => 337.84, 4 => 521.9], $this->pesos());
    }
}
