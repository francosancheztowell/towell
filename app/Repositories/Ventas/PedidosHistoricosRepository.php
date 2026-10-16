<?php

declare(strict_types=1);

namespace App\Repositories\Ventas;

use App\Models\Ventas\TwHistPedidosModel;
use Illuminate\Support\Collection;

/**
 * Pedidos Históricos: líneas de dbo.TwHistoricosPedidos ubicadas por su fecha de creación
 * (YearCreado/MonthCreado), igual que el Pedido de Compara.
 */
final class PedidosHistoricosRepository
{
    /**
     * Jerarquía Empresa > Tipo de pedido > Cliente > OC > artículo, más los datos del artículo.
     * NAME es el nombre completo ("TOALLA FACIAL AM RESORTS VI"); ITEMNAME queda de respaldo.
     *
     * @var list<string>
     */
    public const DIMENSIONES = [
        TwHistPedidosModel::COLUMNA_MES, 'TEXTIL', 'TIPOPEDIDO', 'CUSTNAME', 'PURCHASEORDERID',
        'ITEMID', 'NAME', 'ITEMNAME', 'INVENTSIZEID', 'INVENTCOLORID', 'INVENTCOLORTXT', 'ITEMBARCODE',
        'PRICE', 'FECHACREADO', 'FECHAFACTURA',
    ];

    /** @return list<string> años de creación, del más reciente al más antiguo */
    public function anios(): array
    {
        $columna = TwHistPedidosModel::COLUMNA_ANIO;

        return TwHistPedidosModel::query()
            ->whereNotNull($columna)
            ->distinct()
            ->orderByDesc($columna)
            ->toBase()
            ->pluck($columna)
            ->map(static fn (mixed $anio): string => trim((string) $anio))
            ->filter(static fn (string $anio): bool => $anio !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Líneas de pedido de un año sumadas por todas las dimensiones (≈35k filas por año).
     */
    public function lineas(string $anio): Collection
    {
        return TwHistPedidosModel::query()
            ->where(TwHistPedidosModel::COLUMNA_ANIO, (int) $anio)
            ->select(self::DIMENSIONES)
            ->selectRaw('SUM(QTY) AS QTY, SUM(ENTREGADOQTY) AS ENTREGADOQTY, SUM(PENDIENTEQTY) AS PENDIENTEQTY, SUM(AMOUNT) AS AMOUNT')
            ->groupBy(self::DIMENSIONES)
            ->toBase()
            ->get();
    }
}
