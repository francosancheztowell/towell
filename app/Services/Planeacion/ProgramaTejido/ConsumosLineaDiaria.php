<?php

namespace App\Services\Planeacion\ProgramaTejido;

use App\Models\Planeacion\ReqMatrizHilos;
use App\Models\Planeacion\ReqProgramaTejido;

/**
 * Consumos de hilo de una línea diaria del programa de tejido: Trama, Combinaciones 1-5, Pie,
 * Rizo (lo que resta de los kilos del día), MtsRizo, MtsPie y Aplicación. Extraído de
 * ReqProgramaTejidoObserver; cálculo puro (la matriz de hilos y el factor de aplicación
 * llegan ya leídos). Los metros de hilo están en {@see MetrosHiloLineaDiaria}.
 */
final class ConsumosLineaDiaria
{
    public function __construct(private readonly MetrosHiloLineaDiaria $metros = new MetrosHiloLineaDiaria) {}

    /**
     * Cantidad, kilos y consumos de hilo de un día con $horasDia horas de producción.
     *
     * @param  array{stdHrEfectivo: float, prodKgDia: float, stdHrsEfect: float, prodKgDia2: float}  $ritmo  {@see FormulasProgramaTejido::ritmo()}
     * @return array<string, float|null>
     */
    public function delDia(ReqProgramaTejido $programa, float $horasDia, array $ritmo, ?float $factorAplicacion, ?ReqMatrizHilos $matrizRizo): array
    {
        $pzasDia = $ritmo['stdHrEfectivo'] * $horasDia;
        $kilosBase = ($ritmo['prodKgDia2'] > 0 && $ritmo['stdHrsEfect'] > 0)
            ? (($pzasDia * $ritmo['prodKgDia2']) / ($ritmo['stdHrsEfect'] * 24))
            : (($ritmo['prodKgDia'] > 0) ? ($ritmo['prodKgDia'] / 24) * $horasDia : 0);

        $trama = $this->calcularTrama($programa, $pzasDia);
        $comb = [];
        for ($n = 1; $n <= 5; $n++) {
            $comb[$n] = $this->calcularCombinacion($programa, $n, $pzasDia);
        }
        $pie = $this->calcularPie($programa, $pzasDia);

        // El orden de la suma se conserva: cambia el redondeo de punto flotante.
        $componentesParaRizo = ($pie ?? 0)
            + ($comb[3] ?? 0)
            + ($comb[2] ?? 0)
            + ($comb[1] ?? 0)
            + ($trama ?? 0)
            + ($comb[4] ?? 0);

        $rizo = max(0.0, $kilosBase - $componentesParaRizo);
        $kilosDia = $rizo + $componentesParaRizo;

        $aplicacionValor = ($factorAplicacion !== null && $kilosDia > 0) ? $factorAplicacion * $kilosDia : null;

        return [
            'Cantidad' => round($pzasDia, 6),
            'Kilos' => round($kilosDia, 6),
            'Aplicacion' => self::redondear($aplicacionValor),
            'Trama' => self::redondear($trama),
            'Combina1' => self::redondear($comb[1]),
            'Combina2' => self::redondear($comb[2]),
            'Combina3' => self::redondear($comb[3]),
            'Combina4' => self::redondear($comb[4]),
            'Combina5' => self::redondear($comb[5]),
            'Pie' => self::redondear($pie),
            'Rizo' => round($rizo, 6),
            'MtsRizo' => self::redondear($this->metros->calcularMtsRizo($programa, $rizo, $matrizRizo)),
            'MtsPie' => self::redondear($this->metros->calcularMtsPie($programa, $pie)),
        ];
    }

    private static function redondear(?float $valor): ?float
    {
        return $valor !== null ? round($valor, 6) : null;
    }

    public function calcularTrama(ReqProgramaTejido $programa, float $pzasDia): ?float
    {
        return self::kilosPorPasadas(
            CampoPrograma::numero($programa, ['PasadasTrama']),
            CampoPrograma::numero($programa, ['CalibreTrama2']),
            CampoPrograma::numero($programa, ['AnchoToalla']),
            $pzasDia
        );
    }

    public function calcularCombinacion(ReqProgramaTejido $programa, int $numero, float $pzasDia): ?float
    {
        return self::kilosPorPasadas(
            CampoPrograma::numero($programa, ["PasadasComb{$numero}", "Pasadas_C{$numero}", "PASADAS_C{$numero}"]),
            CampoPrograma::numero($programa, ["CalibreComb{$numero}2", "CalibreComb{$numero}"]),
            CampoPrograma::numero($programa, ['AnchoToalla', 'Ancho']),
            $pzasDia
        );
    }

    /** Kilos de trama o de una combinación: misma fórmula, distintos campos. */
    private static function kilosPorPasadas(float $pasadas, float $calibre, float $anchoToalla, float $pzasDia): ?float
    {
        if (min($pasadas, $calibre, $anchoToalla) <= 0) {
            return null;
        }

        return self::positivoONull((((0.59 * ((($pasadas * 1.001) * $anchoToalla) / 100.0)) / $calibre) * $pzasDia) / 1000.0);
    }

    public function calcularPie(ReqProgramaTejido $programa, float $pzasDia): ?float
    {
        $largo = CampoPrograma::numero($programa, ['LargoCrudo']);
        $medidaPlano = CampoPrograma::numero($programa, ['MedidaPlano']);
        $calibrePie = CampoPrograma::numero($programa, ['CalibrePie2']);
        $cuentaPie = CampoPrograma::numero($programa, ['CuentaPie']);
        $noTiras = CampoPrograma::numero($programa, ['NoTiras']);

        if (min($largo, $noTiras, $calibrePie, $cuentaPie) <= 0) {
            return null;
        }

        $baseLongitud = ($largo + $medidaPlano) / 100.0;
        $ajuste = $baseLongitud * 1.055;
        $numerador = $ajuste * 0.00059;
        // Con $calibrePie > 0 el divisor nunca es 0 (antes había una guarda inalcanzable).
        $divisor = (0.00059 * 1.0) / (0.00059 / $calibrePie);
        $fraccionCuenta = ($cuentaPie - 32.0) / $noTiras;

        return self::positivoONull(($numerador / $divisor) * $fraccionCuenta * $pzasDia);
    }

    private static function positivoONull(float $valor): ?float
    {
        return $valor > 0 ? $valor : null;
    }
}
