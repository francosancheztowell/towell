<?php

namespace App\Services\Planeacion\ProgramaTejido;

use App\Models\Planeacion\ReqModelosCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Support\Planeacion\NumeroPrograma;
use App\Support\Planeacion\TelarSalonResolver;

/**
 * Piezas por hora (StdToaHra) y horas de producción de un programa de tejido, con los
 * parámetros del modelo codificado que las alimentan. Karl Mayer usa su meta fija de kg/día.
 */
final class HorasProduccion
{
    /**
     * Horas de producción del programa. Karl Mayer usa su meta fija de kg/día.
     *
     * @param  callable|null  $obtenerModeloCallback  (string $tamanoClave, ?string $salonTejidoId) => ReqModelosCodificados|null
     */
    public static function calcularHorasProd(ReqProgramaTejido $programa, ?callable $obtenerModeloCallback = null): float
    {
        $vel = (float) ($programa->VelocidadSTD ?? 0);
        $efic = (float) ($programa->EficienciaSTD ?? 0);
        $cantidad = NumeroPrograma::sanitizeNumber($programa->SaldoPedido ?? $programa->Produccion ?? $programa->TotalPedido ?? 0);

        $stdKm = self::stdToaHraKarlMayer($programa);
        if ($stdKm !== null) {
            return $cantidad > 0 ? $cantidad / $stdKm : 0.0;
        }

        $m = self::obtenerModeloParams($programa, $obtenerModeloCallback);

        return self::calcularHorasProdFromParams(
            $vel,
            $efic,
            $cantidad,
            $m['no_tiras'],
            $m['total'],
            $m['luchaje'],
            $m['repeticiones']
        );
    }

    public static function calcularHorasProdFromParams(
        float $vel,
        float $efic,
        float $cantidad,
        float $noTiras,
        float $total,
        float $luchaje,
        float $repeticiones
    ): float {
        if ($efic > 1) {
            $efic = $efic / 100;
        }

        $stdToaHra = self::stdToaHraPorPasadas($noTiras, $total, $luchaje, $repeticiones, $vel);

        if ($stdToaHra > 0 && $efic > 0 && $cantidad > 0) {
            return $cantidad / ($stdToaHra * $efic);
        }

        return 0.0;
    }

    /** Piezas/hora por pasadas y velocidad; 0.0 si falta algún parámetro. */
    public static function stdToaHraPorPasadas(float $noTiras, float $total, float $luchaje, float $repeticiones, float $vel): float
    {
        if ($noTiras > 0 && $total > 0 && $luchaje > 0 && $repeticiones > 0 && $vel > 0) {
            $parte1 = $total;
            $parte2 = (($luchaje * 0.5) / 0.0254) / $repeticiones;
            $den = ($parte1 + $parte2) / $vel;
            if ($den > 0) {
                return ($noTiras * 60) / $den;
            }
        }

        return 0.0;
    }

    /**
     * Karl Mayer no se estima por pasadas/velocidad: su estandar es la meta fija de kg/dia
     * por telar (crudo.fixed_daily_kilos, la misma del andon), sin eficiencia.
     * Piezas/hora = (kgDia * 1000 / PesoCrudo) / 24. Null si no es KM o falta PesoCrudo
     * (en ese caso sigue la formula de pasadas como JAC/SMIT).
     */
    public static function stdToaHraKarlMayer(ReqProgramaTejido $programa): ?float
    {
        $telar = trim((string) ($programa->NoTelarId ?? ''));
        if (! TelarSalonResolver::esKarlMayer($programa->SalonTejidoId ?? null, $telar)) {
            return null;
        }

        $kgDia = (float) (config('crudo.fixed_daily_kilos')[$telar] ?? 600.0);
        $pesoCrudo = (float) ($programa->PesoCrudo ?? 0);
        if ($kgDia <= 0 || $pesoCrudo <= 0) {
            return null;
        }

        return ($kgDia * 1000 / $pesoCrudo) / 24;
    }

    /**
     * Parámetros del modelo codificado (total, tiras, luchaje, repeticiones); lo capturado en
     * el programa gana sobre el modelo.
     *
     * @param  callable|null  $obtenerModeloCallback  Función para obtener el modelo (opcional, para usar obtenerModeloCodificadoPorSalon)
     * @return array{total: float, no_tiras: float, luchaje: float, repeticiones: float}
     */
    public static function obtenerModeloParams(ReqProgramaTejido $programa, ?callable $obtenerModeloCallback = null): array
    {
        $noTiras = (float) ($programa->NoTiras ?? 0);
        $luchaje = (float) ($programa->Luchaje ?? 0);
        $rep = (float) ($programa->Repeticiones ?? 0);
        $sinModelo = [
            'total' => 0.0,
            'no_tiras' => $noTiras,
            'luchaje' => $luchaje,
            'repeticiones' => $rep,
        ];

        $key = trim((string) ($programa->TamanoClave ?? ''));
        if ($key === '') {
            return $sinModelo;
        }

        $modelo = self::buscarModelo($programa, $key, $obtenerModeloCallback);
        if (! $modelo) {
            return $sinModelo;
        }

        return [
            'total' => (float) ($modelo->Total ?? 0),
            'no_tiras' => $noTiras > 0 ? $noTiras : (float) ($modelo->NoTiras ?? 0),
            'luchaje' => $luchaje > 0 ? $luchaje : (float) ($modelo->Luchaje ?? 0),
            'repeticiones' => $rep > 0 ? $rep : (float) ($modelo->Repeticiones ?? 0),
        ];
    }

    private static function buscarModelo(ReqProgramaTejido $programa, string $key, ?callable $obtenerModeloCallback): mixed
    {
        if ($obtenerModeloCallback === null) {
            // Fallback: buscar directamente sin salón
            return ReqModelosCodificados::where('TamanoClave', $key)->first();
        }

        $salonTejidoId = $programa->getAttribute('SalonTejidoId');
        $salonParaCallback = $salonTejidoId !== null && $salonTejidoId !== '' ? (string) $salonTejidoId : null;

        return $obtenerModeloCallback($key, $salonParaCallback);
    }
}
