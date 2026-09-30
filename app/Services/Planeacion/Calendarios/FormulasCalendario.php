<?php

declare(strict_types=1);

namespace App\Services\Planeacion\Calendarios;

use App\Http\Controllers\Planeacion\ProgramaTejido\helper\TejidoHelpers;
use App\Models\Planeacion\ReqCalendarioLine;
use App\Models\Planeacion\ReqModelosCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use Carbon\Carbon;

/**
 * Fórmulas de un programa de tejido que dependen del calendario (antes, métodos públicos de
 * CalendarioController que también usa CambiarCalendario de Programa Tejido).
 */
final class FormulasCalendario
{
    /** @var array<string, array{total: float, no_tiras: float, luchaje: float, repeticiones: float}> */
    private array $modelos = [];

    /**
     * Líneas del calendario en el formato de TejidoHelpers::snapInicioAlCalendario (ordenadas por
     * inicio): se leen una vez por recálculo en lugar de una consulta por programa.
     *
     * @return list<array{ini: Carbon, fin: Carbon, fin_ts: int}>
     */
    public function lineas(string $calendarioId): array
    {
        return ReqCalendarioLine::where('CalendarioId', $calendarioId)
            ->orderBy('FechaInicio')
            ->get(['FechaInicio', 'FechaFin'])
            ->map(function (ReqCalendarioLine $l): array {
                $fin = Carbon::parse($l->FechaFin);

                return ['ini' => Carbon::parse($l->FechaInicio), 'fin' => $fin, 'fin_ts' => $fin->getTimestamp()];
            })
            ->all();
    }

    /**
     * Primer instante hábil desde $fechaInicio (o ella misma si cae dentro de una línea).
     *
     * @param  list<array{ini: Carbon, fin: Carbon, fin_ts: int}>|null  $lineas
     */
    public function snapInicio(string $calendarioId, Carbon $fechaInicio, ?array $lineas = null): ?Carbon
    {
        return TejidoHelpers::snapInicioAlCalendario($calendarioId, $fechaInicio, $lineas);
    }

    public function horasProd(ReqProgramaTejido $p): float
    {
        $cantidad = TejidoHelpers::sanitizeNumber($p->SaldoPedido ?? $p->Produccion ?? $p->TotalPedido ?? 0);

        $stdKm = TejidoHelpers::stdToaHraKarlMayer($p);
        if ($stdKm !== null) {
            return $cantidad > 0 ? $cantidad / $stdKm : 0.0;
        }

        $m = $this->parametrosModelo($p);

        return TejidoHelpers::calcularHorasProdFromParams(
            (float) ($p->VelocidadSTD ?? 0),
            (float) ($p->EficienciaSTD ?? 0),
            $cantidad,
            $m['no_tiras'],
            $m['total'],
            $m['luchaje'],
            $m['repeticiones'],
        );
    }

    /**
     * Solo campos que dependen de FechaInicio/FechaFinal (por cambios de calendario).
     * NO toca StdToaHra/StdDia/HorasProd.
     *
     * @return array<string, float|string>
     */
    public function dependientesDeFechas(ReqProgramaTejido $p, Carbon $inicio, Carbon $fin, float $horasProd): array
    {
        $out = [];
        $diffDias = abs($fin->getTimestamp() - $inicio->getTimestamp()) / 86400;
        if ($diffDias > 0) {
            $out['DiasEficiencia'] = (float) round($diffDias, 4);
        }

        $cantidad = TejidoHelpers::sanitizeNumber($p->SaldoPedido ?? $p->Produccion ?? $p->TotalPedido ?? 0);
        if ($diffDias > 0 && $cantidad > 0) {
            $stdHrsEfect = ($cantidad / $diffDias) / 24;
            $out['StdHrsEfect'] = (float) round($stdHrsEfect, 4);
            $pesoCrudo = (float) ($p->PesoCrudo ?? 0);
            if ($pesoCrudo > 0) {
                $out['ProdKgDia2'] = (float) round((($pesoCrudo * $stdHrsEfect) * 24) / 1000, 4);
            }
        }

        if ($horasProd > 0) {
            $out['DiasJornada'] = (float) round($horasProd / 24, 4);
        }

        $entregaCte = $fin->copy()->addDays(12);
        $out['EntregaCte'] = $entregaCte->format('Y-m-d H:i:s');
        $entregaPT = $this->fecha($p->EntregaPT ?? null);
        if ($entregaPT !== null) {
            $out['PTvsCte'] = (float) round($entregaCte->diffInDays($entregaPT, false), 2);
        }

        return $out;
    }

    /** Fecha o null si no se puede leer (antes: catch vacío). */
    public function fecha(mixed $valor): ?Carbon
    {
        if (empty($valor)) {
            return null;
        }
        try {
            return Carbon::parse($valor);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /** @return array{total: float, no_tiras: float, luchaje: float, repeticiones: float} */
    private function parametrosModelo(ReqProgramaTejido $p): array
    {
        $noTiras = (float) ($p->NoTiras ?? 0);
        $luchaje = (float) ($p->Luchaje ?? 0);
        $rep = (float) ($p->Repeticiones ?? 0);
        $clave = trim((string) ($p->TamanoClave ?? ''));
        $base = $clave === '' ? ['total' => 0.0, 'no_tiras' => $noTiras, 'luchaje' => $luchaje, 'repeticiones' => $rep] : $this->modelo($clave);

        return [
            'total' => $base['total'],
            'no_tiras' => $noTiras > 0 ? $noTiras : $base['no_tiras'],
            'luchaje' => $luchaje > 0 ? $luchaje : $base['luchaje'],
            'repeticiones' => $rep > 0 ? $rep : $base['repeticiones'],
        ];
    }

    /** @return array{total: float, no_tiras: float, luchaje: float, repeticiones: float} */
    private function modelo(string $tamanoClave): array
    {
        return $this->modelos[$tamanoClave] ??= (function () use ($tamanoClave): array {
            $m = ReqModelosCodificados::where('TamanoClave', $tamanoClave)->first();

            $valor = fn (string $campo): float => (float) ($m?->getAttribute($campo) ?? 0);

            return ['total' => $valor('Total'), 'no_tiras' => $valor('NoTiras'), 'luchaje' => $valor('Luchaje'), 'repeticiones' => $valor('Repeticiones')];
        })();
    }
}
