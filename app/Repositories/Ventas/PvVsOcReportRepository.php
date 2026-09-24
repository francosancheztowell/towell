<?php

declare(strict_types=1);

namespace App\Repositories\Ventas;

use App\Models\Ventas\TwHistPedidosModel;
use App\Models\Ventas\TwHistPronosModel;
use App\Models\Ventas\TwHistVtasModel;
use Illuminate\Support\Collection;

final class PvVsOcReportRepository
{
    /**
     * Plan (Pronóstico), sin columnas de estatus: dbo.TwHistoricosPronostico.
     */
    public function pronostico(int $anio): Collection
    {
        return TwHistPronosModel::query()
            ->where('ANIO', $anio)
            ->select($this->columnasBase())
            ->get();
    }

    /**
     * Pedidos (OC): dbo.TwHistoricosPedidos, con las columnas extra para calcular status.
     */
    public function pedidos(int $anio): Collection
    {
        return TwHistPedidosModel::query()
            ->where('ANIO', $anio)
            ->select([...$this->columnasBase(), 'ENTREGADOQTY', 'PENDIENTEQTY'])
            ->get();
    }

    /**
     * Ventas reales: dbo.TwHistoricosVentas, sin columnas de estatus.
     */
    public function ventas(int $anio): Collection
    {
        return TwHistVtasModel::query()
            ->where('ANIO', $anio)
            ->select($this->columnasBase())
            ->get();
    }

    /**
     * @return list<string>
     */
    private function columnasBase(): array
    {
        return [
            'TEXTIL',
            'TIPOPEDIDO',
            'CUSTACCOUNT',
            'CUSTNAME',
            'ITEMID',
            'ITEMNAME',
            'LINEA',
            'INVENTSIZEID',
            'INVENTCOLORID',
            'INVENTCOLORTXT',
            'ANIO',
            'MES',
            'SEMANA',
            'QTY',
            'PESO',
            'AMOUNT',
            'AMOUNTDES',
            'AMOUNTNETO',
        ];
    }
}
