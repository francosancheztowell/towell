<?php

namespace App\Services\Planeacion\ProgramaTejido;

use App\Models\Planeacion\ReqModelosCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Fórmulas de eficiencia del programa de tejido (StdToaHra, StdDia, HorasProd, PesoGRM2...).
 * Las usan el observer, Balancear, Duplicar, Dividir, Update y Revivir. Piezas/hora y horas en
 * {@see HorasProduccion}; entregas en {@see EntregasPrograma}; estándares de velocidad y
 * eficiencia en {@see EstandaresTelar}.
 */
final class FormulasEficiencia
{
    /** Balanceo: includeEntregaCte + includePTvsCte, sin fallback EntregaCte desde programa. */
    public const FORMULAS_CTX_BALANCEAR = 'balancear';

    /** Duplicar, dividir, update: mismos dos includes, con fallback EntregaCte desde programa. */
    public const FORMULAS_CTX_PEDIDO_INHERIT = 'pedido_inherit';

    /**
     * Con $stdToaHraAnterior y $checkVelocidadCambio (observer) preserva el StdToaHra guardado
     * salvo que la velocidad cambie de verdad. Un error deja las fórmulas calculadas hasta ahí.
     *
     * @return array<string, float|string>
     */
    public static function calcularFormulasEficiencia(
        ReqProgramaTejido $programa,
        array $modeloParams,
        bool $includeEntregaCte = false,
        bool $includePTvsCte = false,
        bool $fallbackEntregaCteFromProgram = false,
        ?float $stdToaHraAnterior = null,
        ?callable $checkVelocidadCambio = null
    ): array {
        $formulas = [];

        try {
            $vel = (float) ($programa->VelocidadSTD ?? 0);
            $efic = (float) ($programa->getAttribute('EficienciaSTD') ?? $programa->EficienciaSTD ?? 0);
            $cantidad = (float) ($programa->SaldoPedido ?? $programa->Produccion ?? $programa->TotalPedido ?? 0);
            $pesoCrudo = (float) ($programa->PesoCrudo ?? 0);

            if ($efic > 1) {
                $efic = $efic / 100;
            }

            $inicio = Carbon::parse($programa->FechaInicio);
            $fin = Carbon::parse($programa->FechaFinal);
            $diffDias = abs($fin->getTimestamp() - $inicio->getTimestamp()) / 86400;

            $stdToaHra = $stdToaHraAnterior !== null && $checkVelocidadCambio !== null
                ? self::stdToaHraConAnterior($formulas, $vel, $modeloParams, $stdToaHraAnterior, $checkVelocidadCambio)
                : self::stdToaHraNuevo($formulas, $vel, $modeloParams);
            $stdToaHraParaCalculos = $formulas['StdToaHra'] ?? $stdToaHra;

            // Karl Mayer: meta fija kg/dia sin eficiencia, reemplaza el estandar de pasadas.
            $stdKm = HorasProduccion::stdToaHraKarlMayer($programa);
            if ($stdKm !== null) {
                $stdToaHraParaCalculos = $stdKm;
                $formulas['StdToaHra'] = (float) round($stdKm, 2);
                $efic = 1.0;
            }

            self::formulasPesoYDias($formulas, $programa, $pesoCrudo, $diffDias);
            self::formulasRitmo($formulas, $stdToaHraParaCalculos, $efic, $cantidad, $pesoCrudo);
            self::formulasHorasEfectivas($formulas, $cantidad, $pesoCrudo, $diffDias);

            if ($includeEntregaCte || $includePTvsCte) {
                EntregasPrograma::agregarFormulas($formulas, $programa, $includeEntregaCte, $includePTvsCte, $fallbackEntregaCteFromProgram);
            }
        } catch (\Throwable $e) {
            Log::warning('TejidoHelpers: Error al calcular fórmulas de eficiencia', [
                'error' => $e->getMessage(),
                'programa_id' => $programa->Id ?? null,
            ]);
        }

        return $formulas;
    }

    /** Cálculo normal: StdToaHra siempre desde el modelo. */
    private static function stdToaHraNuevo(array &$formulas, float $vel, array $m): float
    {
        $stdToaHra = HorasProduccion::stdToaHraPorPasadas(
            (float) ($m['no_tiras'] ?? 0),
            (float) ($m['total'] ?? 0),
            (float) ($m['luchaje'] ?? 0),
            (float) ($m['repeticiones'] ?? 0),
            $vel
        );
        if ($stdToaHra > 0) {
            $formulas['StdToaHra'] = (float) round($stdToaHra, 2);
        }

        return $stdToaHra;
    }

    /** Observer: conserva el StdToaHra anterior si la velocidad no cambió de verdad. */
    private static function stdToaHraConAnterior(array &$formulas, float $vel, array $m, float $anterior, callable $checkVelocidadCambio): float
    {
        $velocidadInfo = $checkVelocidadCambio();
        $cambio = $velocidadInfo['cambio'] ?? false;
        $original = (float) ($velocidadInfo['original'] ?? 0);
        $nueva = (float) ($velocidadInfo['nueva'] ?? 0);

        if (self::debeRecalcularStd($anterior, (bool) $cambio, $original, $nueva)) {
            $repeticiones = (float) ($m['repeticiones'] ?? 0);
            $stdToaHra = HorasProduccion::stdToaHraPorPasadas(
                (float) ($m['no_tiras'] ?? 0),
                (float) ($m['total'] ?? 0),
                (float) ($m['luchaje'] ?? 0),
                $repeticiones > 0 ? $repeticiones : 1,
                $cambio && $nueva > 0 ? $nueva : $vel
            );
            if ($stdToaHra > 0) {
                $formulas['StdToaHra'] = (float) round($stdToaHra, 2);

                return $stdToaHra;
            }

            return $anterior;
        }

        if ($anterior > 0) {
            $formulas['StdToaHra'] = (float) round($anterior, 2);
        }

        return $anterior;
    }

    private static function debeRecalcularStd(float $anterior, bool $cambio, float $original, float $nueva): bool
    {
        return $anterior <= 0
            || ($original > 0 && $nueva > 0 && $original !== $nueva && ($cambio || abs($original - $nueva) > 0.1));
    }

    private static function formulasPesoYDias(array &$formulas, ReqProgramaTejido $programa, float $pesoCrudo, float $diffDias): void
    {
        $largoToalla = (float) ($programa->LargoToalla ?? 0);
        $anchoToalla = (float) ($programa->AnchoToalla ?? 0);
        if ($pesoCrudo > 0 && $largoToalla > 0 && $anchoToalla > 0) {
            $formulas['PesoGRM2'] = (float) round(($pesoCrudo * 10000) / ($largoToalla * $anchoToalla), 2);
        }

        if ($diffDias > 0) {
            $formulas['DiasEficiencia'] = (float) round($diffDias, 2);
        }
    }

    private static function formulasRitmo(array &$formulas, float $stdToaHra, float $efic, float $cantidad, float $pesoCrudo): void
    {
        if ($stdToaHra <= 0 || $efic <= 0) {
            return;
        }

        $stdDia = $stdToaHra * $efic * 24;
        $formulas['StdDia'] = (float) round($stdDia, 2);
        if ($pesoCrudo > 0) {
            $formulas['ProdKgDia'] = (float) round(($stdDia * $pesoCrudo) / 1000, 2);
        }

        $horasProd = $cantidad / ($stdToaHra * $efic);
        $formulas['HorasProd'] = (float) round($horasProd, 2);
        if ($horasProd > 0) {
            $formulas['DiasJornada'] = (float) round($horasProd / 24, 2);
        }
    }

    private static function formulasHorasEfectivas(array &$formulas, float $cantidad, float $pesoCrudo, float $diffDias): void
    {
        if ($diffDias <= 0) {
            return;
        }

        $stdHrsEfect = ($cantidad / $diffDias) / 24;
        $formulas['StdHrsEfect'] = (float) round($stdHrsEfect, 2);
        if ($pesoCrudo > 0) {
            $formulas['ProdKgDia2'] = (float) round((($pesoCrudo * $stdHrsEfect) * 24) / 1000, 2);
        }
    }

    /**
     * Fórmulas de eficiencia con flags unificados según contexto de negocio.
     *
     * @param  callable(string, ?string): (ReqModelosCodificados|null)|null  $obtenerModeloCallback
     */
    public static function calcularFormulasEficienciaPorContexto(
        ReqProgramaTejido $programa,
        string $contexto,
        ?callable $obtenerModeloCallback = null
    ): array {
        $balancear = $contexto === self::FORMULAS_CTX_BALANCEAR;
        try {
            $m = HorasProduccion::obtenerModeloParams($programa, $obtenerModeloCallback);
            if ($balancear) {
                return self::calcularFormulasEficiencia($programa, $m, true, true, false);
            }

            return self::calcularFormulasEficiencia($programa, $m, true, true, true);
        } catch (\InvalidArgumentException $e) {
            self::logErrorContexto($balancear, 'Parámetros inválidos para fórmulas', $contexto, $e, $programa);
        } catch (\Throwable $e) {
            self::logErrorContexto(false, 'Error al calcular fórmulas', $contexto, $e, $programa);
        }

        return [];
    }

    /** Balancear registra con su prefijo (y como error si los parámetros son inválidos). */
    private static function logErrorContexto(bool $comoError, string $mensaje, string $contexto, \Throwable $e, ReqProgramaTejido $programa): void
    {
        if ($contexto === self::FORMULAS_CTX_BALANCEAR) {
            $datos = ['error' => $e->getMessage(), 'programa_id' => $programa->Id ?? null];
            if ($comoError) {
                Log::error('BalancearTejido: '.$mensaje, $datos);

                return;
            }
            Log::warning('BalancearTejido: '.$mensaje, $datos);

            return;
        }

        Log::warning('TejidoHelpers: '.$mensaje, [
            'context' => $contexto,
            'error' => $e->getMessage(),
            'programa_id' => $programa->Id ?? null,
        ]);
    }
}
