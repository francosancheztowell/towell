<?php

declare(strict_types=1);

namespace App\Repositories\Ventas;

use App\Models\Ventas\TwHistVtasModel;
use Illuminate\Support\Collection;

final class VentasHistoricasRepository
{
    /**
     * Columnas por las que filtra la pestaña Ventas históricas. Agrupar en SQL reduce ~89k
     * facturas a ~2k filas: el navegador solo necesita los totales por combinación.
     *
     * @var list<string>
     */
    public const DIMENSIONES = [
        'TEXTIL', 'ANIO', 'MES', 'SEMESTRE', 'TIPOPEDIDO', 'TIPOMATERIAL', 'CALIDAD', 'CUSTNAME', 'NOMBREAGENTE',
    ];

    /**
     * Ventas reales (dbo.TwHistoricosVentas) sumadas por todas las dimensiones de filtro.
     */
    public function ventasAgrupadas(): Collection
    {
        return TwHistVtasModel::query()
            ->select(self::DIMENSIONES)
            ->selectRaw('SUM(QTY) AS QTY, SUM(PESO) AS PESO, SUM(AMOUNT) AS AMOUNT, SUM(AMOUNTDES) AS AMOUNTDES')
            ->groupBy(self::DIMENSIONES)
            ->toBase()
            ->get();
    }
}
