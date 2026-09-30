<?php

declare(strict_types=1);

namespace App\Repositories\Ventas;

use App\Models\Ventas\TwHistPedidosModel;
use App\Models\Ventas\TwHistPronosModel;
use App\Models\Ventas\TwHistVtasModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

final class PvVsOcReportRepository
{
    /**
     * Dimensiones por las que filtra y agrupa la pestaña Compara. La semana y el código de color
     * quedan fuera a propósito: ninguna vista los usa y multiplicaban las filas (~262k líneas vs ~66k combos).
     *
     * @var list<string>
     */
    public const DIMENSIONES = [
        'TEXTIL', 'TIPOPEDIDO', 'CUSTACCOUNT', 'CUSTNAME', 'ITEMID', 'ITEMNAME',
        'LINEA', 'INVENTSIZEID', 'INVENTCOLORTXT', 'ANIO', 'MES',
    ];

    /** @var list<string> */
    public const MEDIDAS = ['QTY', 'PESO', 'AMOUNT', 'AMOUNTDES', 'AMOUNTNETO'];

    /**
     * Prefijo de la serie => tabla origen (Plan, Pedido/OC, Real).
     *
     * @var array<string, class-string>
     */
    public const SERIES = [
        'P' => TwHistPronosModel::class,
        'O' => TwHistPedidosModel::class,
        'R' => TwHistVtasModel::class,
    ];

    /**
     * Plan, Pedido y Real ya cruzados por combinación de dimensiones: una fila por combo con las
     * columnas P_QTY … R_AMOUNTNETO. Todos los años de una vez (el filtro de año vive en el navegador).
     * Las tres tablas son heaps sin índices en SQL Server 2008 R2; agrupar en SQL sigue siendo mucho
     * más barato que pasar cada línea a PHP.
     */
    public function combinado(): LazyCollection
    {
        $dimensiones = implode(', ', self::DIMENSIONES);

        // Cada tabla se agrupa por separado (menos filas que cruzar) y cada serie llena solo sus
        // columnas; el GROUP BY externo junta los tres resultados en una fila por combo.
        $fuentes = [];
        $sumas = [];
        foreach (self::SERIES as $serie => $modelo) {
            $columnas = [];
            foreach (self::SERIES as $otra => $_) {
                foreach (self::MEDIDAS as $medida) {
                    $columnas[] = ($otra === $serie ? "SUM({$medida})" : '0')." AS {$otra}_{$medida}";
                }
            }
            $fuentes[] = "SELECT {$dimensiones}, ".implode(', ', $columnas)
                .' FROM '.(new $modelo)->getTable()." GROUP BY {$dimensiones}";
            foreach (self::MEDIDAS as $medida) {
                $sumas[] = "SUM({$serie}_{$medida}) AS {$serie}_{$medida}";
            }
        }

        $sql = "SELECT {$dimensiones}, ".implode(', ', $sumas)
            .' FROM ('.implode(' UNION ALL ', $fuentes).') combos'
            ." GROUP BY {$dimensiones}";

        return LazyCollection::make(fn () => yield from DB::connection((new TwHistVtasModel)->getConnectionName())->cursor($sql));
    }
}
