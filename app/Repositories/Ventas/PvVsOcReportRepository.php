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
     * La cuenta del cliente (CUSTACCOUNT) también: un mismo cliente tiene varias cuentas y el
     * reporte debe sumarlo en una sola fila por nombre.
     *
     * @var list<string>
     */
    public const DIMENSIONES = [
        'TEXTIL', 'TIPOPEDIDO', 'CUSTNAME', 'ITEMID', 'ITEMNAME',
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
     * Dimensiones que una serie toma de otra columna. El Pedido se ubica en el año/mes en que se creó
     * (YearCreado/MonthCreado); Plan y Real siguen con ANIO/MES.
     *
     * @var array<string, array<string, string>>
     */
    private const COLUMNAS_POR_SERIE = [
        'O' => ['ANIO' => TwHistPedidosModel::COLUMNA_ANIO, 'MES' => TwHistPedidosModel::COLUMNA_MES],
    ];

    /**
     * Plan, Pedido y Real ya cruzados por combinación de dimensiones: una fila por combo con las
     * columnas P_QTY … R_AMOUNTNETO. Con $anio solo ese año (el navegador pide el más reciente primero
     * y los demás en segundo plano); sin él, todos. Las tres tablas son heaps sin índices en SQL Server
     * 2008 R2; agrupar en SQL sigue siendo mucho más barato que pasar cada línea a PHP.
     *
     * SQL crudo porque el ORM no expresa el UNION ALL de tres tablas con GROUP BY externo; el año
     * va como binding y se filtra en cada SELECT interno, antes de agrupar.
     */
    public function combinado(?string $anio = null): LazyCollection
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
            $origen = array_map(fn (string $dimension): string => $this->columna($serie, $dimension), self::DIMENSIONES);
            $select = array_map(
                static fn (string $columna, string $dimension): string => $columna === $dimension ? $columna : "{$columna} AS {$dimension}",
                $origen,
                self::DIMENSIONES,
            );
            $where = $anio === null ? '' : ' WHERE '.$this->columna($serie, 'ANIO').' = ?';
            $fuentes[] = 'SELECT '.implode(', ', $select).', '.implode(', ', $columnas)
                .' FROM '.(new $modelo)->getTable().$where.' GROUP BY '.implode(', ', $origen);
            foreach (self::MEDIDAS as $medida) {
                $sumas[] = "SUM({$serie}_{$medida}) AS {$serie}_{$medida}";
            }
        }

        $sql = "SELECT {$dimensiones}, ".implode(', ', $sumas)
            .' FROM ('.implode(' UNION ALL ', $fuentes).') combos'
            ." GROUP BY {$dimensiones}";
        $bindings = $anio === null ? [] : array_fill(0, count(self::SERIES), $anio);

        return LazyCollection::make(fn () => yield from DB::connection((new TwHistVtasModel)->getConnectionName())->cursor($sql, $bindings));
    }

    /**
     * Años con datos en alguna de las tres series, del más reciente al más antiguo, como texto.
     * SQL crudo por el mismo motivo que combinado(): UNION de tres tablas sin relación en el ORM.
     * UNION (sin ALL) ya quita duplicados entre tablas.
     *
     * @return list<string>
     */
    public function anios(): array
    {
        $fuentes = [];
        foreach (self::SERIES as $serie => $modelo) {
            $columna = $this->columna($serie, 'ANIO');
            $fuentes[] = 'SELECT DISTINCT '.($columna === 'ANIO' ? $columna : "{$columna} AS ANIO").' FROM '.(new $modelo)->getTable();
        }
        $sql = implode(' UNION ', $fuentes).' ORDER BY ANIO DESC';

        $anios = array_map(
            static fn (object $fila): string => trim((string) $fila->ANIO),
            DB::connection((new TwHistVtasModel)->getConnectionName())->select($sql),
        );

        return array_values(array_unique(array_filter($anios, static fn (string $anio): bool => $anio !== '')));
    }

    /** Columna de la tabla de la serie que alimenta una dimensión (el Pedido usa su fecha de creación). */
    private function columna(string $serie, string $dimension): string
    {
        return self::COLUMNAS_POR_SERIE[$serie][$dimension] ?? $dimension;
    }
}
