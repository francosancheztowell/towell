<?php

declare(strict_types=1);

namespace App\Support\Crudo;

final class CrudoDefectRanking
{
    /**
     * Índice del defecto con más piezas; en empate, el primero capturado.
     *
     * @param  list<array{piezas: int}>  $defects
     */
    public static function principalIndex(array $defects): ?int
    {
        if ($defects === []) {
            return null;
        }

        $principalIndex = 0;
        foreach ($defects as $index => $defect) {
            if ($defect['piezas'] > $defects[$principalIndex]['piezas']) {
                $principalIndex = $index;
            }
        }

        return $principalIndex;
    }
}
