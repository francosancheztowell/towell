<?php

declare(strict_types=1);

namespace Tests\Unit\ProgramaUrdEng;

use App\Support\ProgramaUrdEng\CompatibilidadInventario as Regla;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Las reglas de compatibilidad telar <-> pieza vivian duplicadas en JS y en PHP,
 * y solo la copia JS decidia que veia el planeador. Aqui queda la unica version.
 */
class CompatibilidadInventarioTest extends TestCase
{
    /**
     * @return array<string, array{0: ?string, 1: ?string, 2: string}>
     */
    public static function seriales(): array
    {
        return [
            'prefijo antes del guion' => ['00061-744', null, '00061'],
            'conserva el lote si no hay guion' => ['00061', 'L-9', 'L-9'],
            'serial vacio cae al lote' => ['', 'L-9', 'L-9'],
            'serial nulo cae al lote' => [null, 'L-9', 'L-9'],
            'sin serial ni lote' => [null, null, ''],
            'recorta espacios' => ['  00043-455  ', null, '00043'],
            'guion inicial cae al lote' => ['-744', 'L-9', 'L-9'],
        ];
    }

    #[DataProvider('seriales')]
    public function test_el_lote_es_el_prefijo_del_numero_de_julio(?string $serial, ?string $lote, string $esperado): void
    {
        $this->assertSame($esperado, Regla::loteDerivado($serial, $lote));
    }

    public function test_un_telar_sin_orden_acepta_cualquier_lote(): void
    {
        $this->assertTrue(Regla::coincideLote(null, 'CUALQUIERA', '00099-1'));
        $this->assertTrue(Regla::coincideLote('  ', 'CUALQUIERA', '00099-1'));
    }

    public function test_con_orden_coincide_por_lote_o_por_prefijo_del_serial(): void
    {
        $this->assertTrue(Regla::coincideLote('00061', '00061', null), 'coincide el InventBatchId');
        $this->assertTrue(Regla::coincideLote('00061', 'OTRO', '00061-744'), 'coincide el prefijo del serial');
        $this->assertFalse(Regla::coincideLote('00061', 'OTRO', '00062-744'));
    }

    public function test_la_cuenta_del_telar_es_prefijo_del_tamano_de_la_pieza(): void
    {
        $this->assertTrue(Regla::coincideCuenta('3156', '3156'));
        $this->assertTrue(Regla::coincideCuenta('3156', '3156-X'));
        $this->assertTrue(Regla::coincideCuenta(' 31 56 ', '3156-X'), 'los espacios no cuentan');
        $this->assertFalse(Regla::coincideCuenta('3156', '315'), 'no basta con ser parecida');
        $this->assertFalse(Regla::coincideCuenta('3156', 'X-3156'), 'tiene que ser prefijo, no contener');
        $this->assertFalse(Regla::coincideCuenta('', '3156'), 'sin cuenta no hay match');
        $this->assertFalse(Regla::coincideCuenta(null, '3156'));
    }

    public function test_el_grupo_lo_definen_tipo_calibre_y_salon(): void
    {
        $base = ['tipo' => 'Rizo', 'calibre' => 10.0, 'salon' => 'Jacquard'];

        $this->assertTrue(Regla::mismoGrupo($base, ['tipo' => 'RIZO', 'calibre' => '10.00', 'salon' => 'jacquard']));
        $this->assertTrue(
            Regla::mismoGrupo($base, ['tipo' => 'Rizo', 'calibre' => 10.0000001, 'salon' => 'Jacquard']),
            'el calibre es float de SQL Server: se compara con tolerancia'
        );
        $this->assertFalse(Regla::mismoGrupo($base, ['tipo' => 'Pie', 'calibre' => 10.0, 'salon' => 'Jacquard']));
        $this->assertFalse(Regla::mismoGrupo($base, ['tipo' => 'Rizo', 'calibre' => 10.5, 'salon' => 'Jacquard']));
        $this->assertFalse(Regla::mismoGrupo($base, ['tipo' => 'Rizo', 'calibre' => 10.0, 'salon' => 'Itema']));
    }

    public function test_la_cuenta_puede_variar_dentro_del_grupo(): void
    {
        $a = ['tipo' => 'Rizo', 'calibre' => 10.0, 'salon' => 'Jacquard', 'cuenta' => '3156'];
        $b = ['tipo' => 'Rizo', 'calibre' => 10.0, 'salon' => 'Jacquard', 'cuenta' => '4020'];

        $this->assertTrue(Regla::mismoGrupo($a, $b));
    }
}
