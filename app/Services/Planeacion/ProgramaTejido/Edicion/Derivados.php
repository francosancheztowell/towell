<?php

namespace App\Services\Planeacion\ProgramaTejido\Edicion;

use App\Models\Planeacion\ReqProgramaTejido;
use App\Services\Planeacion\ProgramaTejido\CalendarioProduccion;
use App\Services\Planeacion\ProgramaTejido\FormulasEficiencia;
use App\Services\Planeacion\ProgramaTejido\HorasProduccion;
use App\Support\Planeacion\NumeroPrograma;
use Carbon\Carbon;

/**
 * FechaFinal, fórmulas y PesoGRM2 de la edición inline (sin guardar).
 *
 * @internal Paso de EdicionProgramaTejido (aplicarCambios → recalcularDerivados → persistir).
 */
final class Derivados
{
    /**
     * FechaFinal, fórmulas y PesoGRM2 según las banderas de aplicarCambios() (sin guardar).
     * REGLA: cambiar calendario NO cambia duración; solo re-acomoda en líneas.
     *
     * @param  array<string, bool>  $flags
     */
    public static function recalcular(ReqProgramaTejido $registro, array $flags, float $horasProdAntes, float $cantidadAntes): void
    {
        $enProceso = (bool) $registro->EnProceso;

        if (! $flags['fechaFinalManual'] && ! empty($registro->FechaInicio) && ($flags['afectaCalendario'] || $flags['afectaDuracion'])) {
            self::recalcularFechaFinal($registro, $flags, $enProceso, $horasProdAntes, $cantidadAntes);
        }

        self::recalcularFormulas($registro, $flags, $enProceso);

        if ($flags['editoAncho']) {
            self::recalcularPesoGrm2($registro);
        }
    }

    /** EnProceso calcula desde now() sin tocar FechaInicio en BD; el snap al calendario solo si no está en proceso. */
    private static function recalcularFechaFinal(ReqProgramaTejido $registro, array $flags, bool $enProceso, float $horasProdAntes, float $cantidadAntes): void
    {
        $inicio = $enProceso ? Carbon::now() : Carbon::parse($registro->FechaInicio);

        if (! $enProceso && $flags['afectaCalendario'] && ! empty($registro->CalendarioId)) {
            $snap = CalendarioProduccion::snapInicioAlCalendario($registro->CalendarioId, $inicio);
            if ($snap && ! $snap->equalTo($inicio)) {
                $registro->FechaInicio = $snap->format('Y-m-d H:i:s');
                $inicio = $snap;
            }
        }

        $horas = self::horasNecesarias($registro, $flags['afectaDuracion'], $horasProdAntes, $cantidadAntes);
        $registro->FechaFinal = CalendarioProduccion::resolverFechaFinal($inicio, $horas, $registro->CalendarioId)->format('Y-m-d H:i:s');
    }

    /**
     * Si SOLO cambió calendario usa el HorasProd existente (evita drift 16:09->16:11); si
     * cambió pedido/modelo/etc. lo recalcula, con fallback proporcional a la cantidad.
     */
    private static function horasNecesarias(ReqProgramaTejido $registro, bool $afectaDuracion, float $horasProdAntes, float $cantidadAntes): float
    {
        if (! $afectaDuracion) {
            return $horasProdAntes > 0 ? $horasProdAntes : HorasProduccion::calcularHorasProd($registro);
        }

        $horas = HorasProduccion::calcularHorasProd($registro);
        if ($horas <= 0 && $horasProdAntes > 0) {
            $cantNew = NumeroPrograma::sanitizeNumber($registro->SaldoPedido ?? $registro->Produccion ?? $registro->TotalPedido ?? 0);
            if ($cantidadAntes > 0 && $cantNew > 0) {
                $horas = $horasProdAntes * ($cantNew / $cantidadAntes);
            }
        }

        return $horas;
    }

    /** Solo calendario: solo lo que depende de diffDias. Si no, fórmulas completas (EnProceso desde now()). */
    private static function recalcularFormulas(ReqProgramaTejido $registro, array $flags, bool $enProceso): void
    {
        if (self::esSoloCalendario($flags)) {
            self::recalcularSoloDiffDias($registro);

            return;
        }
        if (! self::afectaFormulas($flags)) {
            return;
        }

        $regParaFormulas = $registro;
        if ($enProceso && ! empty($registro->FechaFinal)) {
            $regParaFormulas = clone $registro;
            $regParaFormulas->FechaInicio = Carbon::now()->format('Y-m-d H:i:s');
        }

        $formulas = FormulasEficiencia::calcularFormulasEficienciaPorContexto($regParaFormulas, FormulasEficiencia::FORMULAS_CTX_PEDIDO_INHERIT);
        foreach ($formulas as $campo => $valor) {
            $registro->setAttribute($campo, $valor);
        }
    }

    private static function esSoloCalendario(array $flags): bool
    {
        return $flags['afectaCalendario'] && ! $flags['afectaDuracion'] && ! $flags['fechaFinalManual'] && ! $flags['afectaFormulas'];
    }

    private static function afectaFormulas(array $flags): bool
    {
        return $flags['afectaFormulas'] || $flags['afectaDuracion'] || $flags['afectaCalendario'] || $flags['fechaFinalManual'];
    }

    private static function recalcularSoloDiffDias(ReqProgramaTejido $p): void
    {
        if (empty($p->FechaFinal)) {
            return;
        }

        // EnProceso: usar now() como inicio efectivo (no FechaInicio)
        $inicio = (bool) $p->EnProceso ? Carbon::now() : (empty($p->FechaInicio) ? null : Carbon::parse($p->FechaInicio));
        if (! $inicio) {
            return;
        }

        $diffDias = abs(Carbon::parse($p->FechaFinal)->getTimestamp() - $inicio->getTimestamp()) / 86400;
        if ($diffDias <= 0) {
            return;
        }

        $cantidad = NumeroPrograma::sanitizeNumber($p->SaldoPedido ?? $p->Produccion ?? $p->TotalPedido ?? 0);
        $pesoCrudo = (float) ($p->PesoCrudo ?? 0);

        $p->DiasEficiencia = (float) round($diffDias, 2);

        $stdHrsEfect = ($cantidad / $diffDias) / 24;
        $p->StdHrsEfect = (float) round($stdHrsEfect, 2);

        if ($pesoCrudo > 0) {
            $p->ProdKgDia2 = (float) round((($pesoCrudo * $stdHrsEfect) * 24) / 1000, 2);
        }
    }

    /** Usa LargoCrudo si no hay LargoToalla. */
    private static function recalcularPesoGrm2(ReqProgramaTejido $registro): void
    {
        $pesoCrudo = (float) ($registro->PesoCrudo ?? 0);
        $largo = (float) ($registro->LargoToalla ?? $registro->LargoCrudo ?? 0);
        $anchoToalla = (float) ($registro->AnchoToalla ?? 0);
        if ($pesoCrudo > 0 && $largo > 0 && $anchoToalla > 0) {
            $registro->PesoGRM2 = (float) round(($pesoCrudo * 10000) / ($largo * $anchoToalla), 2);
        }
    }
}
