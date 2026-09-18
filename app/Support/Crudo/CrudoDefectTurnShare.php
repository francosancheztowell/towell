<?php

declare(strict_types=1);

namespace App\Support\Crudo;

final class CrudoDefectTurnShare
{
    /**
     * Calidad por turno, misma regla que el gauge del modal:
     * 100 − (2das del turno / piezas del turno).
     *
     * Devuelve null cuando el turno no aporta información de falla: sin piezas
     * (el turno no corrió) o sin 2das (100%). El modal los pinta como “—” para
     * que el % solo señale a los turnos que sí fallaron.
     *
     * @param  list<array{turns?: array<string, float|int>}>  $defects
     * @param  array<string, float|int>  $piecesByTurn
     * @return array{1: int|null, 2: int|null, 3: int|null, 4: int|null}
     */
    public static function percents(array $defects, array $piecesByTurn = []): array
    {
        $seconds = [
            '1' => 0.0,
            '2' => 0.0,
            '3' => 0.0,
            '4' => 0.0,
        ];

        foreach ($defects as $defect) {
            $turns = is_array($defect['turns'] ?? null) ? $defect['turns'] : [];
            foreach ($seconds as $turn => $_) {
                $seconds[$turn] += is_numeric($turns[$turn] ?? null) ? (float) $turns[$turn] : 0.0;
            }
        }

        $percents = [];
        foreach ($seconds as $turn => $quantity) {
            $pieces = is_numeric($piecesByTurn[$turn] ?? null) ? (float) $piecesByTurn[$turn] : 0.0;
            $percents[$turn] = ($pieces > 0 && $quantity > 0)
                ? (int) round(max(0, 100 - (($quantity / $pieces) * 100)))
                : null;
        }

        return $percents;
    }

    /**
     * @param  list<array<string, mixed>>  $captures
     * @return array{1: float, 2: float, 3: float, 4: float}
     */
    public static function piecesFromCaptures(array $captures): array
    {
        $pieces = [
            '1' => 0.0,
            '2' => 0.0,
            '3' => 0.0,
            '4' => 0.0,
        ];

        foreach ($captures as $capture) {
            $pieces['1'] += (float) ($capture['piecesT1'] ?? 0);
            $pieces['2'] += (float) ($capture['piecesT2'] ?? 0);
            $pieces['3'] += (float) ($capture['piecesT3'] ?? 0);
            $pieces['4'] += (float) ($capture['piecesT4'] ?? 0);
        }

        return $pieces;
    }

    /**
     * Turnos de una captura con piezas &gt; 0, en orden 1–4.
     * Dos turnos en la misma fila salen "1,3".
     *
     * @param  array<string, mixed>  $capture
     */
    public static function captureTurnsLabel(array $capture): string
    {
        $turns = [];
        foreach (['1', '2', '3', '4'] as $turn) {
            if ((float) ($capture['piecesT'.$turn] ?? 0) > 0) {
                $turns[] = $turn;
            }
        }

        return implode(',', $turns);
    }
}
