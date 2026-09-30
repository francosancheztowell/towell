<?php

declare(strict_types=1);

namespace App\Repositories\Ventas;

use App\Models\Ventas\TwHistPedidosModel;
use App\Models\Ventas\TwHistPronosModel;
use App\Models\Ventas\TwHistVtasModel;
use Illuminate\Support\LazyCollection;

final class PvVsOcReportRepository
{
    /*
     * Todos los años de una vez (el filtro de año vive en el navegador). Son ~260k filas entre las
     * tres tablas: cursor() las recorre una a una en vez de cargarlas todas en memoria.
     */

    /**
     * Plan (Pronóstico), sin columnas de estatus: dbo.TwHistoricosPronostico.
     */
    public function pronostico(): LazyCollection
    {
        return TwHistPronosModel::query()
            ->select($this->columnasBase())
            ->toBase()
            ->cursor();
    }

    /**
     * Pedidos (OC): dbo.TwHistoricosPedidos, con las columnas extra para calcular status.
     */
    public function pedidos(): LazyCollection
    {
        return TwHistPedidosModel::query()
            ->select([...$this->columnasBase(), 'ENTREGADOQTY', 'PENDIENTEQTY'])
            ->toBase()
            ->cursor();
    }

    /**
     * Ventas reales: dbo.TwHistoricosVentas, sin columnas de estatus.
     */
    public function ventas(): LazyCollection
    {
        return TwHistVtasModel::query()
            ->select($this->columnasBase())
            ->toBase()
            ->cursor();
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
