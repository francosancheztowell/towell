<?php

namespace Tests\Unit\Planeacion;

use App\Http\Controllers\Planeacion\ProgramaTejido\helper\TejidoHelpers;
use App\Models\Planeacion\ReqProgramaTejido;
use Tests\TestCase;

/** Karl Mayer se programa a 600 kg/dia por telar (meta del andon), sin eficiencia; JAC/SMIT no cambian. */
class KarlMayerMetaKgDiaTest extends TestCase
{
    private const MODELO = ['no_tiras' => 4, 'total' => 120, 'luchaje' => 30, 'repeticiones' => 7];

    private function programa(string $salon, string $telar): ReqProgramaTejido
    {
        $p = new ReqProgramaTejido;
        $p->setRawAttributes([
            'Id' => 1, 'SalonTejidoId' => $salon, 'NoTelarId' => $telar,
            'VelocidadSTD' => 600, 'EficienciaSTD' => 0.8, 'SaldoPedido' => 11500, 'PesoCrudo' => 967,
            'FechaInicio' => '2026-09-23 10:08:51', 'FechaFinal' => '2026-10-04 01:09:27',
        ]);

        return $p;
    }

    public function test_karl_mayer_usa_600_kg_dia_sin_eficiencia(): void
    {
        foreach (['KARL MAYER', 'KM'] as $salon) {
            $p = $this->programa($salon, '402');
            $f = TejidoHelpers::calcularFormulasEficiencia($p, self::MODELO);

            // 11500 pz * 0.967 kg = 11120.5 kg / 600 kg/dia = 18.53 dias = 444.82 h
            $this->assertSame(600.0, $f['ProdKgDia']);
            $this->assertSame(444.82, $f['HorasProd']);
            $this->assertSame(18.53, $f['DiasJornada']);
            $this->assertEqualsWithDelta(444.82, TejidoHelpers::calcularHorasProd($p), 0.01);
        }
    }

    public function test_jacquard_y_smit_siguen_con_la_formula_de_pasadas(): void
    {
        $sinSalon = TejidoHelpers::calcularFormulasEficiencia($this->programa('', ''), self::MODELO);

        foreach ([['JACQUARD', '205'], ['SMIT', '305']] as [$salon, $telar]) {
            $this->assertSame($sinSalon, TejidoHelpers::calcularFormulasEficiencia($this->programa($salon, $telar), self::MODELO));
            $this->assertNull(TejidoHelpers::stdToaHraKarlMayer($this->programa($salon, $telar)));
        }
    }
}
