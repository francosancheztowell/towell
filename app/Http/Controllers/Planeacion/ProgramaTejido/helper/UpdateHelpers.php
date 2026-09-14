<?php

namespace App\Http\Controllers\Planeacion\ProgramaTejido\helper;

use App\Models\Planeacion\ReqProgramaTejido;

class UpdateHelpers
{
    public static function applyFlogYTipoPedido(ReqProgramaTejido $r, ?string $flog): void
    {
        $r->FlogsId = $flog ?: null;

        // Si se limpia el Flog, también limpiar TipoPedido; si no, derivarlo
        if ($r->FlogsId && strlen($r->FlogsId) >= 2) {
            $pref = strtoupper(substr($r->FlogsId, 0, 2));
            $r->TipoPedido = $pref;
        } else {
            $r->TipoPedido = null;
        }
    }
}
