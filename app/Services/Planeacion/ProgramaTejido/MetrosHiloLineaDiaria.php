<?php

namespace App\Services\Planeacion\ProgramaTejido;

use App\Models\Planeacion\ReqMatrizHilos;
use App\Models\Planeacion\ReqProgramaTejido;

/**
 * Metros de hilo de una línea diaria (MtsRizo, MtsPie) a partir de los kilos del día.
 * Extraído de ReqProgramaTejidoObserver; cálculo puro (la matriz de hilos llega ya leída).
 */
final class MetrosHiloLineaDiaria
{
    private const FACTOR_PESO = 1000.0;

    private const DENSIDAD_HILO = 0.59;

    private const FACTOR_RETORCIDO = 1.0162;

    /** ¿Hace falta la matriz de hilos para MtsRizo? (evita leerla si de todos modos daría null). */
    public function necesitaMatrizRizo(ReqProgramaTejido $programa): bool
    {
        return CampoPrograma::numero($programa, ['CuentaRizo']) > 0
            && ! empty(CampoPrograma::texto($programa, ['FibraRizo']));
    }

    public function hiloRizo(ReqProgramaTejido $programa): string
    {
        return CampoPrograma::texto($programa, ['FibraRizo']);
    }

    public function calcularMtsRizo(ReqProgramaTejido $programa, ?float $rizo, ?ReqMatrizHilos $matrizHilo): ?float
    {
        if (! $matrizHilo || ! ($rizo > 0) || ! $this->necesitaMatrizRizo($programa)) {
            return null;
        }

        [$n1, $n2] = $this->calibresHilo($matrizHilo);
        if (! ($n1 > 0) || ! ($n2 > 0)) {
            return null;
        }

        $cuentaRizo = CampoPrograma::numero($programa, ['CuentaRizo']);
        $valorRizo1 = (($n1 * ($rizo * self::FACTOR_PESO)) / self::DENSIDAD_HILO) / 2;
        $valorRizo2 = (($n2 * ($rizo * self::FACTOR_PESO)) / self::DENSIDAD_HILO) / 2;

        return self::positivoONull((($valorRizo1 + $valorRizo2) / $cuentaRizo) * self::FACTOR_RETORCIDO);
    }

    /**
     * N1/N2 del hilo; si faltan o no son positivos se usan Calibre/Calibre2.
     *
     * @return array{0: float|null, 1: float|null}
     */
    private function calibresHilo(ReqMatrizHilos $matrizHilo): array
    {
        $n1 = self::numero($matrizHilo->N1);
        $n2 = self::numero($matrizHilo->N2);

        if (! ($n1 > 0)) {
            $n1 = self::numero($matrizHilo->Calibre) ?? $n1;
        }
        if (! ($n2 > 0)) {
            $n2 = self::numero($matrizHilo->Calibre2) ?? $n2;
        }

        return [$n1, $n2];
    }

    private static function numero(mixed $valor): ?float
    {
        return is_numeric($valor) ? (float) $valor : null;
    }

    public function calcularMtsPie(ReqProgramaTejido $programa, ?float $pie): ?float
    {
        $calibrePie = CampoPrograma::numero($programa, ['CalibrePie2']);
        $cuentaPie = CampoPrograma::numero($programa, ['CuentaPie']);

        if (! ($pie > 0) || min($calibrePie, $cuentaPie) <= 0) {
            return null;
        }

        return self::positivoONull((((($calibrePie * ($pie * self::FACTOR_PESO)) / self::DENSIDAD_HILO) / $cuentaPie) * self::FACTOR_RETORCIDO));
    }

    private static function positivoONull(float $valor): ?float
    {
        return $valor > 0 ? $valor : null;
    }
}
