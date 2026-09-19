<?php

declare(strict_types=1);

namespace App\Services\Planeacion\Liberar;

use App\Models\Planeacion\ReqProgramaTejido;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Prioridad anterior y fecha programada (fórmula INN) al listar/liberar.
 *
 * Extraído de LiberarOrdenesController. Misma entrada → misma salida:
 * Prioridad = "SALDAR + NombreProducto" del renglón anterior del mismo
 * salón+telar; Programado = HOY si FechaInicio ≤ (HOY + días).
 */
final class LiberarProgramaScheduling
{
    /**
     * Candidatos a "registro anterior" de todo el lote, en UNA consulta, agrupados por
     * salón|telar. Antes se consultaba uno por renglón. Se traen todas las órdenes de esos
     * salones y telares (incluidas las ya liberadas: el anterior puede ser cualquiera), y el
     * par exacto se filtra al agrupar, porque whereIn sobre dos columnas es un superset.
     *
     * @param  Collection<int, ReqProgramaTejido>|iterable<int, ReqProgramaTejido>  $registros
     * @return array<string, array<int, object>>
     */
    public function candidatosPrioridadAnterior($registros): array
    {
        $lote = collect($registros);
        $telares = $lote->map(fn ($r) => trim((string) ($r->NoTelarId ?? '')))->filter()->unique()->values()->all();

        if ($telares === []) {
            return [];
        }

        $salones = $lote->map(fn ($r) => (string) ($r->SalonTejidoId ?? ''))->unique()->values()->all();

        $filas = ReqProgramaTejido::query()
            ->select(['Id', 'NombreProducto', 'SalonTejidoId', 'NoTelarId', 'FechaInicio'])
            ->whereIn('NoTelarId', $telares)
            ->whereIn('SalonTejidoId', $salones)
            ->orderBy('FechaInicio')
            ->orderBy('Id')
            ->get();

        $porGrupo = [];
        foreach ($filas as $fila) {
            $porGrupo[self::clavePrioridad($fila->SalonTejidoId ?? '', $fila->NoTelarId ?? '')][] = $fila;
        }

        return $porGrupo;
    }

    public static function clavePrioridad(?string $salon, ?string $telar): string
    {
        return trim((string) $salon).'|'.trim((string) $telar);
    }

    /**
     * El registro inmediatamente anterior del mismo salón+telar: FechaInicio menor, o la misma
     * fecha con Id menor. Mismo criterio que la consulta que había por renglón.
     *
     * ponytail: recorrido lineal sobre el grupo (mismo salón+telar, decenas de filas); si un
     * telar llegara a tener miles de órdenes, indexar el grupo por fecha.
     *
     * @param  array<string, array<int, object>>  $candidatos
     */
    public function prioridadAnterior(ReqProgramaTejido $registro, array $candidatos): string
    {
        $telar = trim((string) ($registro->NoTelarId ?? ''));
        $idActual = $registro->Id ?? null;

        if ($telar === '' || ! $idActual) {
            return '';
        }

        $grupo = $candidatos[self::clavePrioridad($registro->SalonTejidoId ?? '', $telar)] ?? [];
        $fechaInicio = $registro->FechaInicio ?? null;
        $fechaActual = $fechaInicio ? Carbon::parse($fechaInicio)->format('Y-m-d H:i:s') : null;

        $anterior = null;
        foreach ($grupo as $fila) {
            if ((int) $fila->Id === (int) $idActual) {
                continue;
            }

            $fechaFila = $fila->FechaInicio ? Carbon::parse($fila->FechaInicio)->format('Y-m-d H:i:s') : null;

            $esAnterior = $fechaActual !== null
                ? ($fechaFila !== null && ($fechaFila < $fechaActual || ($fechaFila === $fechaActual && (int) $fila->Id < (int) $idActual)))
                : (int) $fila->Id < (int) $idActual;

            if (! $esAnterior) {
                continue;
            }

            // El grupo viene ordenado ascendente, así que el último que cumple es el más cercano.
            $anterior = $fila;
        }

        return $anterior !== null && ! empty($anterior->NombreProducto)
            ? 'SALDAR '.$anterior->NombreProducto
            : '';
    }

    /**
     * Asigna PrioridadAnterior a cada registro del lote (una consulta).
     *
     * @param  Collection<int, ReqProgramaTejido>|iterable<int, ReqProgramaTejido>  $registros
     */
    public function aplicarPrioridadAnterior($registros): void
    {
        $lote = collect($registros);
        $candidatos = $this->candidatosPrioridadAnterior($lote);
        $lote->each(function ($registro) use ($candidatos) {
            $registro->PrioridadAnterior = $this->prioridadAnterior($registro, $candidatos);
        });
    }

    /**
     * Calcula la fecha programada basada en la fórmula INN:
     * =SI(FechaInicio <= (HOY+días), HOY, "")
     */
    public function calcularFechaProgramada(ReqProgramaTejido $registro, Carbon $hoy, Carbon $fechaFormula): ?Carbon
    {
        if (! $registro->FechaInicio) {
            return null;
        }

        $fechaInicio = $registro->FechaInicio instanceof Carbon
            ? $registro->FechaInicio->copy()->startOfDay()
            : Carbon::parse($registro->FechaInicio)->startOfDay();

        return $fechaInicio->lte($fechaFormula) ? $hoy->copy() : null;
    }

    /**
     * Asigna ProgramadoCalculado con el mismo try/catch que index():
     * parseo inválido → log + null, no tumba el lote.
     *
     * @param  Collection<int, ReqProgramaTejido>|iterable<int, ReqProgramaTejido>  $registros
     */
    public function aplicarProgramadoCalculado($registros, Carbon $hoy, Carbon $fechaFormula): void
    {
        collect($registros)->each(function ($registro) use ($hoy, $fechaFormula) {
            try {
                $registro->ProgramadoCalculado = $this->calcularFechaProgramada($registro, $hoy, $fechaFormula);
            } catch (\Exception $e) {
                Log::error('Error al procesar fecha', [
                    'registro_id' => $registro->Id,
                    'error' => $e->getMessage(),
                ]);
                $registro->ProgramadoCalculado = null;
            }
        });
    }
}
