<?php

namespace App\Services\Planeacion\ProgramaTejido;

use App\Models\Planeacion\ReqModelosCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Support\Planeacion\NumeroPrograma;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Métricas y fórmulas de eficiencia que acompañan a {@see SecuenciaFechasTelar}: horas de
 * producción (sin depender de fechas) y fórmulas con diffDias = FechaFinal - FechaInicio.
 * Implementación propia de la cadena de fechas (redondeo a 2 decimales), distinta de
 * {@see FormulasEficiencia}.
 */
final class FormulasSecuenciaTelar
{
    /** Cache simple para no pegarle a ReqModelosCodificados por cada cálculo */
    private static array $totalModeloCache = [];

    public static function saldo(ReqProgramaTejido $p): float
    {
        return NumeroPrograma::sanitizeNumber($p->SaldoPedido ?? $p->Produccion ?? $p->TotalPedido ?? 0);
    }

    /**
     * Calcula StdToaHra y HorasProdRaw sin depender de fechas.
     * (Esto es lo que debe gobernar la duración real).
     */
    public static function metricasBase(ReqProgramaTejido $p): array
    {
        $efic = (float) ($p->EficienciaSTD ?? 0);
        $cant = self::saldo($p);

        if ($efic > 1) {
            $efic = $efic / 100;
        }

        $total = self::obtenerTotalModelo($p->TamanoClave ?? null);
        $stdToaHra = self::stdToaHraPorPasadas($p, $total);

        // Karl Mayer: meta fija kg/dia sin eficiencia.
        $stdKm = HorasProduccion::stdToaHraKarlMayer($p);
        if ($stdKm !== null) {
            $stdToaHra = $stdKm;
            $efic = 1.0;
        }

        $horasProdRaw = 0.0;
        if ($stdToaHra > 0 && $efic > 0 && $cant > 0) {
            $horasProdRaw = $cant / ($stdToaHra * $efic);
        }

        return [
            'StdToaHra' => $stdToaHra,
            'HorasProdRaw' => $horasProdRaw,
            'Efic' => $efic,
            'Cant' => $cant,
            'TotalModelo' => $total,
        ];
    }

    private static function stdToaHraPorPasadas(ReqProgramaTejido $p, float $total): float
    {
        $vel = (float) ($p->VelocidadSTD ?? 0);
        $noTiras = (float) ($p->NoTiras ?? 0);
        $luchaje = (float) ($p->Luchaje ?? 0);
        $rep = (float) ($p->Repeticiones ?? 0);

        if (! ($noTiras > 0 && $total > 0 && $luchaje > 0 && $rep > 0 && $vel > 0)) {
            return 0.0;
        }

        $parte2 = (($luchaje * 0.5) / 0.0254) / $rep;
        $den = ($total + $parte2) / $vel;

        return $den > 0 ? ($noTiras * 60) / $den : 0.0;
    }

    /**
     * Fórmulas: diffDias = (FechaFinal - FechaInicio).
     * Puedes pasar $metricasBase para evitar recálculo.
     */
    public static function formulas(ReqProgramaTejido $programa, ?array $metricasBase = null): array
    {
        $formulas = [];

        try {
            $metricasBase = $metricasBase ?: self::metricasBase($programa);

            $stdToaHra = (float) ($metricasBase['StdToaHra'] ?? 0);
            $efic = (float) ($metricasBase['Efic'] ?? 0);
            $cantidad = (float) ($metricasBase['Cant'] ?? self::saldo($programa));
            $pesoCrudo = (float) ($programa->PesoCrudo ?? 0);

            $inicio = Carbon::parse($programa->FechaInicio);
            $fin = Carbon::parse($programa->FechaFinal);
            $diffDias = abs($fin->getTimestamp() - $inicio->getTimestamp()) / 86400;

            if ($stdToaHra > 0) {
                $formulas['StdToaHra'] = (float) round($stdToaHra, 2);
            }

            self::formulaPesoGrm2($formulas, $programa, $pesoCrudo);

            if ($diffDias > 0) {
                $formulas['DiasEficiencia'] = (float) round($diffDias, 2);
            }

            self::formulasStdDia($formulas, $stdToaHra, $efic, $pesoCrudo);
            self::formulasEfectivas($formulas, $cantidad, $diffDias, $pesoCrudo);
            self::formulasHoras($formulas, (float) ($metricasBase['HorasProdRaw'] ?? 0));

            // EntregaCte = FechaFinal + 12/16 dias; EntregaPT, EntregaProduc y PTvsCte
            EntregasPrograma::agregarFormulas($formulas, $programa, true, true, true);
        } catch (\Throwable $e) {
            Log::warning('FormulasSecuenciaTelar: Error al calcular fórmulas', [
                'error' => $e->getMessage(),
                'programa_id' => $programa->Id ?? null,
            ]);
        }

        return $formulas;
    }

    private static function formulaPesoGrm2(array &$formulas, ReqProgramaTejido $programa, float $pesoCrudo): void
    {
        $largoToalla = (float) ($programa->LargoToalla ?? 0);
        $anchoToalla = (float) ($programa->AnchoToalla ?? 0);
        if ($pesoCrudo > 0 && $largoToalla > 0 && $anchoToalla > 0) {
            $formulas['PesoGRM2'] = (float) round(($pesoCrudo * 10000) / ($largoToalla * $anchoToalla), 2);
        }
    }

    /** StdDia / ProdKgDia */
    private static function formulasStdDia(array &$formulas, float $stdToaHra, float $efic, float $pesoCrudo): void
    {
        if ($stdToaHra <= 0 || $efic <= 0) {
            return;
        }

        $stdDia = $stdToaHra * $efic * 24;
        $formulas['StdDia'] = (float) round($stdDia, 2);

        if ($pesoCrudo > 0) {
            $formulas['ProdKgDia'] = (float) round(($stdDia * $pesoCrudo) / 1000, 2);
        }
    }

    /** StdHrsEfect / ProdKgDia2 */
    private static function formulasEfectivas(array &$formulas, float $cantidad, float $diffDias, float $pesoCrudo): void
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

    /** HorasProd / DiasJornada (usa horasProdRaw) */
    private static function formulasHoras(array &$formulas, float $horasProdRaw): void
    {
        if ($horasProdRaw > 0) {
            $formulas['HorasProd'] = (float) round($horasProdRaw, 2);
            $formulas['DiasJornada'] = (float) round($horasProdRaw / 24, 2);
        }
    }

    private static function obtenerTotalModelo(?string $tamanoClave): float
    {
        $key = trim((string) $tamanoClave);
        if ($key === '') {
            return 0.0;
        }

        if (isset(self::$totalModeloCache[$key])) {
            return self::$totalModeloCache[$key];
        }

        $modelo = ReqModelosCodificados::where('TamanoClave', $key)->first();
        $total = $modelo ? (float) ($modelo->Total ?? 0) : 0.0;

        self::$totalModeloCache[$key] = $total;

        return $total;
    }
}
