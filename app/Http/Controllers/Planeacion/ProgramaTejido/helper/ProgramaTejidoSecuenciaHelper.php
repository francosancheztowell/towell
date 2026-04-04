<?php

namespace App\Http\Controllers\Planeacion\ProgramaTejido\helper;

use App\Models\Planeacion\ReqProgramaTejido;
use Illuminate\Support\Facades\DB;

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
        foreach ($updates as $idU => $data) {
            DB::table(ReqProgramaTejido::tableName())->where('Id', $idU)->update($data);
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
