<?php

namespace App\Services\Planeacion\ProgramaTejido\Helpers;

use App\Models\Planeacion\ReqProgramaTejido;
use App\Support\ActualizacionPorId;
use App\Support\ColumnasDeTabla;

/**
 * Pasos comunes tras DateHelpers::recalcularFechasSecuencia (updates masivos + líneas diarias).
 */
final class ProgramaTejidoSecuenciaHelper
{
    /**
     * @param  array<int|string, array<string, mixed>>  $updates  mapa Id => columnas
     */
    public static function aplicarUpdatesDesdeRecalculo(array $updates): void
    {
        // Un UPDATE … CASE por lote en vez de uno por fila (un telar de 40 programas eran 40 idas a SQL Server).
        // ActualizacionPorId exige las mismas columnas en todas las filas: se agrupa por conjunto de columnas.
        $tabla = ReqProgramaTejido::tableName();
        $grupos = [];
        foreach ($updates as $id => $data) {
            $grupos[implode('|', array_keys($data))][(int) $id] = $data;
        }
        foreach ($grupos as $filas) {
            ActualizacionPorId::ejecutar($tabla, $filas, ColumnasDeTabla::de($tabla));
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $detalles  filas con clave 'Id'
     */
    public static function regenerarLineasDesdeDetalles(array $detalles): void
    {
        $ids = array_column($detalles, 'Id');
        if ($ids === []) {
            return;
        }
        ReqProgramaTejido::regenerarLineas(
            ReqProgramaTejido::findMany($ids)
        );
    }
}
