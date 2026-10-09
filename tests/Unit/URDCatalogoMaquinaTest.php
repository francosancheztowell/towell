<?php

namespace Tests\Unit;

use App\Models\Urdido\URDCatalogoMaquina;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Los telares de URDCatalogoMaquinas se presentan como los daban ReqTelares e InvSecuenciaTelares. */
class URDCatalogoMaquinaTest extends TestCase
{
    /** @return array<string, array{string, string, string, string, string}> Departamento, MaquinaId => salón, nombre, tipo */
    public static function telares(): array
    {
        return [
            'jacquard' => ['Jacquard', '201', 'Jacquard', 'JAC 201', 'JACQUARD'],
            'itema es salón Smith' => ['Itema', '300', 'Smith', 'Smith 300', 'ITEMA'],
            'smith' => ['Smith', '305', 'Smith', 'Smith 305', 'SMIT'],
            'karl mayer es KM' => ['Karl Mayer', '401', 'KM', 'KM 401', 'KARL MAYER'],
        ];
    }

    #[DataProvider('telares')]
    public function test_salon_nombre_y_tipo_como_las_tablas_viejas(string $departamento, string $id, string $salon, string $nombre, string $tipo): void
    {
        $m = new URDCatalogoMaquina(['MaquinaId' => $id, 'Departamento' => $departamento]);

        $this->assertSame($salon, $m->salon());
        $this->assertSame($nombre, $m->nombreTelar());
        $this->assertSame($tipo, $m->tipoTelar());
    }

    public function test_espacios_y_mayusculas_en_el_departamento(): void
    {
        $this->assertSame('Smith', URDCatalogoMaquina::salonDe(' ITEMA '));
        $this->assertSame('KM', URDCatalogoMaquina::salonDe('karl mayer'));
        $this->assertSame('', URDCatalogoMaquina::salonDe(null));
    }
}
