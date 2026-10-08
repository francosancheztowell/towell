<?php

namespace App\Services\Planeacion\ProgramaTejido;

use App\Models\Planeacion\ReqProgramaTejido;

/**
 * Posición consecutiva de cada registro dentro de su telar (1, 2, 3...). Las lecturas para
 * asignar posición toman UPDLOCK para que dos inserts no se queden con la misma.
 */
final class PosicionesTelar
{
    /** Primer hueco de la secuencia del telar (o la última + 1). */
    public static function siguienteDisponible(string $salonTejidoId, string $noTelarId): int
    {
        $posicionesExistentes = ReqProgramaTejido::query()
            ->where('SalonTejidoId', $salonTejidoId)
            ->where('NoTelarId', $noTelarId)
            ->whereNotNull('Posicion')
            ->orderBy('Posicion', 'asc')
            // ponytail: UPDLOCK sobre las filas del telar serializa a dos inserts que
            // pelean por la misma posicion. Un telar vacio no deja rango que bloquear;
            // si eso llega a chocar, hace falta un lock por telar (tabla o app lock).
            ->lockForUpdate()
            ->pluck('Posicion')
            ->toArray();

        $posicionEsperada = 1;
        foreach ($posicionesExistentes as $posicionExistente) {
            if ($posicionExistente != $posicionEsperada) {
                return $posicionEsperada;
            }
            $posicionEsperada++;
        }

        return $posicionEsperada;
    }

    /**
     * PT-PERF-02: lo mismo que llamar siguienteDisponible() una vez por fila nueva, pero con
     * UNA consulta (mismo filtro exacto y mismo UPDLOCK) por salón. Devuelve un reservador:
     * cada llamada da el primer hueco del telar y lo marca ocupado, igual que hacía la
     * consulta al ver la fila recién guardada.
     *
     * @param  list<array{0: string, 1: string}>  $telares  pares [salón, telar] destino
     * @return \Closure(string, string): int
     */
    public static function reservador(array $telares): \Closure
    {
        // SQL Server compara sin mayúsculas ni espacios finales: la llave del mapa también.
        $llave = fn ($salon, $telar) => mb_strtoupper(rtrim((string) $salon)).'|'.mb_strtoupper(rtrim((string) $telar));

        $porSalon = [];
        foreach ($telares as [$salon, $telar]) {
            $porSalon[(string) $salon][(string) $telar] = true;
        }

        $ocupadas = [];
        foreach ($porSalon as $salon => $telaresSalon) {
            $filas = ReqProgramaTejido::query()
                ->where('SalonTejidoId', $salon)
                ->whereIn('NoTelarId', array_map('strval', array_keys($telaresSalon)))
                ->whereNotNull('Posicion')
                ->lockForUpdate()
                ->get(['NoTelarId', 'Posicion']);
            foreach ($filas as $fila) {
                $ocupadas[$llave($salon, $fila->NoTelarId)][(int) $fila->Posicion] = true;
            }
        }

        return function (string $salon, string $telar) use (&$ocupadas, $llave): int {
            $k = $llave($salon, $telar);
            $posicion = 1;
            while (isset($ocupadas[$k][$posicion])) {
                $posicion++;
            }
            $ocupadas[$k][$posicion] = true;

            return $posicion;
        };
    }

    /**
     * Reasigna 1, 2, 3... a los registros del telar en su orden de Posicion actual; los que no
     * tienen Posicion van al final por FechaInicio. Guarda sin disparar el observer.
     */
    public static function recalcular(string $salonTejidoId, string $noTelarId): void
    {
        $registros = ReqProgramaTejido::query()
            ->salon($salonTejidoId)
            ->telar($noTelarId)
            ->whereNotNull('Posicion')
            ->orderBy('Posicion', 'asc')
            ->orderBy('FechaInicio', 'asc')
            ->get();

        if ($registros->isEmpty()) {
            return;
        }

        $nuevaPosicion = 1;
        foreach ($registros as $registro) {
            if ($registro->Posicion != $nuevaPosicion) {
                $registro->Posicion = $nuevaPosicion;
                $registro->saveQuietly();
            }
            $nuevaPosicion++;
        }

        $registrosSinPosicion = ReqProgramaTejido::query()
            ->salon($salonTejidoId)
            ->telar($noTelarId)
            ->whereNull('Posicion')
            ->orderBy('FechaInicio', 'asc')
            ->get();

        foreach ($registrosSinPosicion as $registro) {
            $registro->Posicion = $nuevaPosicion;
            $registro->saveQuietly();
            $nuevaPosicion++;
        }
    }
}
