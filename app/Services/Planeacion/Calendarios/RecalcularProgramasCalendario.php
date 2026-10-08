<?php

declare(strict_types=1);

namespace App\Services\Planeacion\Calendarios;

use App\Models\Planeacion\ReqProgramaTejido;
use App\Services\Planeacion\ProgramaTejido\CalendarioProduccion;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Recalcula las fechas de los programas de tejido de un calendario (antes
 * CalendarioController::recalcularProgramasPorCalendario, 212 líneas).
 *
 * Por cada telar afectado, en orden (FechaInicio, Id): el primero conserva su inicio; los demás
 * arrancan donde terminó el anterior y se ajustan al primer instante hábil del calendario. La
 * fecha final sale de las horas de producción sobre el calendario (CalendarioProduccion) y solo se
 * recalculan las fórmulas que dependen de las fechas. Se guarda sin eventos
 * (ReqProgramaTejido::suppressObservers, HANDOFF PT-05 B1) y se restauran al final.
 */
final class RecalcularProgramasCalendario
{
    /** Columnas que usa el recálculo (no las ~170 del programa). */
    private const COLUMNAS = [
        'Id', 'CalendarioId', 'SalonTejidoId', 'NoTelarId', 'FechaInicio', 'FechaFinal', 'HorasProd', 'SaldoPedido',
        'Produccion', 'TotalPedido', 'PesoCrudo', 'EntregaPT', 'EntregaCte', 'DiasEficiencia', 'StdHrsEfect',
        'ProdKgDia2', 'DiasJornada', 'EnProceso',
    ];

    public function __construct(private readonly FormulasCalendario $formulas) {}

    /** @return array{procesados: int, actualizados: int, errores: int, segundos: float} */
    public function recalcular(string $calendarioId, ?Carbon $rangoIni = null, ?Carbon $rangoFin = null, bool $regenerarLineas = false): array
    {
        $t0 = microtime(true);
        $stats = ['procesados' => 0, 'actualizados' => 0, 'errores' => 0];
        // Calendarios grandes: el query log (debugbar en local) no debe crecer con cada programa.
        DB::connection()->disableQueryLog();
        $dispatcher = ReqProgramaTejido::suppressObservers();

        try {
            $lineas = $this->formulas->lineas($calendarioId);
            foreach ($this->telaresAfectados($calendarioId, $rangoIni, $rangoFin) as [$salon, $telar]) {
                $parcial = $this->recalcularTelar($calendarioId, $this->programasDelTelar($calendarioId, $salon, $telar), $lineas, $regenerarLineas);
                foreach ($parcial as $clave => $n) {
                    $stats[$clave] += $n;
                }
            }
        } finally {
            ReqProgramaTejido::restoreObservers($dispatcher);
        }

        return $stats + ['segundos' => round(microtime(true) - $t0, 2)];
    }

    /**
     * Telares con programas del calendario que se cruzan con el rango (o todos, sin rango).
     * Un programa se cruza si empieza antes del fin del rango y termina —por FechaFinal o por
     * FechaInicio + HorasProd— después de su inicio. La segunda condición antes iba en SQL con
     * DATEADD/ISNULL (solo SQL Server); ahora se evalúa aquí sobre pocas columnas.
     *
     * @return list<array{0: string|null, 1: string|null}>
     */
    public function telaresAfectados(string $calendarioId, ?Carbon $rangoIni, ?Carbon $rangoFin): array
    {
        $query = ReqProgramaTejido::query()->where('CalendarioId', $calendarioId)->whereNotNull('FechaInicio');
        if (! $rangoIni || ! $rangoFin) {
            return $query->select(['SalonTejidoId', 'NoTelarId'])->distinct()->get()
                ->map(fn (ReqProgramaTejido $p) => [$p->SalonTejidoId, $p->NoTelarId])->all();
        }

        return $query->where('FechaInicio', '<', $rangoFin->format('Y-m-d H:i:s'))
            ->get(['SalonTejidoId', 'NoTelarId', 'FechaInicio', 'FechaFinal', 'HorasProd'])
            ->filter(fn (ReqProgramaTejido $p) => $this->terminaDespuesDe($p, $rangoIni))
            ->map(fn (ReqProgramaTejido $p) => [$p->SalonTejidoId, $p->NoTelarId])
            ->unique(fn (array $t) => $t[0].'|'.$t[1])
            ->values()->all();
    }

    private function terminaDespuesDe(ReqProgramaTejido $p, Carbon $momento): bool
    {
        $fin = $this->formulas->fecha($p->FechaFinal);
        if ($fin !== null && $fin->gt($momento)) {
            return true;
        }
        $inicio = $this->formulas->fecha($p->FechaInicio);

        return $inicio !== null && $inicio->copy()->addSeconds((int) ((float) ($p->HorasProd ?? 0) * 3600))->gt($momento);
    }

    /** @return Collection<int, ReqProgramaTejido> */
    private function programasDelTelar(string $calendarioId, ?string $salon, ?string $telar): Collection
    {
        return ReqProgramaTejido::where('CalendarioId', $calendarioId)
            ->where('SalonTejidoId', $salon)
            ->where('NoTelarId', $telar)
            ->whereNotNull('FechaInicio')
            ->orderBy('FechaInicio')
            ->orderBy('Id')
            ->get(self::COLUMNAS);
    }

    /**
     * @param  Collection<int, ReqProgramaTejido>  $programas
     * @param  list<array{ini: Carbon, fin: Carbon, fin_ts: int}>  $lineas
     * @return array{procesados: int, actualizados: int, errores: int}
     */
    private function recalcularTelar(string $calendarioId, Collection $programas, array $lineas, bool $regenerarLineas): array
    {
        $stats = ['procesados' => 0, 'actualizados' => 0, 'errores' => 0];
        $prevFin = null;
        $primero = true;

        foreach ($programas as $p) {
            try {
                $esPrimero = $primero;
                $primero = false;
                $resultado = $this->recalcularPrograma($calendarioId, $p, $esPrimero, $prevFin, $lineas);
                if ($resultado === null) {
                    $stats['errores']++;

                    continue;
                }
                [$fin, $cambio] = $resultado;
                $stats['procesados']++;
                $stats['actualizados'] += $cambio ? 1 : 0;
                if ($regenerarLineas) {
                    // regenerarLineas() no pasa por el guard shouldRegenerateLines() del observer.
                    ReqProgramaTejido::regenerarLineas([$p]);
                }
                $prevFin = $fin;
            } catch (\Throwable $e) {
                report($e);
                $stats['errores']++;
            }
        }

        return $stats;
    }

    /**
     * Nuevas fechas de un programa. El primero del telar conserva su inicio; los demás arrancan
     * donde terminó el anterior (si lo hubo) y se ajustan al calendario.
     *
     * @param  list<array{ini: Carbon, fin: Carbon, fin_ts: int}>  $lineas
     * @return array{0: Carbon, 1: bool}|null fin y si cambió; null si no se puede calcular
     */
    private function recalcularPrograma(string $calendarioId, ReqProgramaTejido $p, bool $esPrimero, ?Carbon $prevFin, array $lineas): ?array
    {
        $inicioOriginal = $this->formulas->fecha($p->FechaInicio);
        if ($inicioOriginal === null) {
            return null;
        }
        $inicio = $inicioOriginal->copy();
        if (! $esPrimero) {
            $inicio = $prevFin?->copy() ?? $inicio;
            $inicio = $this->formulas->snapInicio($calendarioId, $inicio, $lineas) ?? $inicio;
        }

        $horas = (float) ($p->HorasProd ?? 0);
        if ($horas <= 0) {
            $horas = $this->formulas->horasProd($p);
            if ($horas > 0) {
                $p->HorasProd = $horas;
            }
        }
        if ($horas <= 0) {
            return null;
        }

        $fin = CalendarioProduccion::calcularFechaFinalDesdeInicio($calendarioId, $inicio, $horas)
            ?? $inicio->copy()->addSeconds((int) round($horas * 3600));
        if ($fin->lt($inicio)) {
            $fin = $inicio->copy();
        }

        $inicioStr = $inicio->format('Y-m-d H:i:s');
        $finStr = $fin->format('Y-m-d H:i:s');
        $cambio = $inicioOriginal->format('Y-m-d H:i:s') !== $inicioStr
            || $this->formulas->fecha($p->FechaFinal)?->format('Y-m-d H:i:s') !== $finStr;

        $p->FechaInicio = $inicioStr;
        $p->FechaFinal = $finStr;
        foreach ($this->formulas->dependientesDeFechas($p, $inicio, $fin, $horas) as $campo => $valor) {
            $p->{$campo} = $valor;
        }
        $p->saveQuietly();

        return [$fin, $cambio];
    }
}
