<?php

namespace App\Services\Planeacion;

use App\Models\Planeacion\Catalogos\CatCodificados;

class NoProduccionCierreAxService
{
    /**
     * Indica si la orden ya fue cerrada en AX.
     *
     * La comparacion directa por OrdenTejido conserva el uso del indice
     * IX_CatCodificados_OrdenTejido en SQL Server.
     */
    public function estaCerrada(?string $noProduccion): bool
    {
        $ordenTejido = trim((string) $noProduccion);

        if ($ordenTejido === '') {
            return false;
        }

        return CatCodificados::query()
            ->where('OrdenTejido', $ordenTejido)
            ->where('cierre_ax', 1)
            ->exists();
    }
}
