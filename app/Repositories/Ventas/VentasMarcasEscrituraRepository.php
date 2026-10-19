<?php

declare(strict_types=1);

namespace App\Repositories\Ventas;

use App\Models\Ventas\TwHistPedidosModel;
use App\Models\Ventas\TwHistPronosModel;
use App\Models\Ventas\TwHistVtasModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Cuándo se escribió por última vez cada tabla de Ventas (ReportesTowel), sin tocar las tablas ni
 * el proceso que las carga: SQL Server lo registra en sys.dm_db_index_usage_stats.
 *
 * La marca se pierde al reiniciar el servicio de SQL Server (vuelve a null) y no sabe de
 * transacciones; VentasDatosVersion lo compensa con un periodo de calma.
 *
 * No es final: los tests lo reemplazan porque SQLite no tiene la DMV.
 */
class VentasMarcasEscrituraRepository
{
    /** @var list<class-string<Model>> */
    private const MODELOS = [TwHistPronosModel::class, TwHistPedidosModel::class, TwHistVtasModel::class];

    /**
     * @return array<string, string|null> tabla => última escritura ('Y-m-d H:i:s.v') o null si no hay desde el último reinicio
     */
    public function marcas(): array
    {
        $tablas = array_map(static fn (string $modelo): string => (new $modelo)->getTable(), self::MODELOS);
        $marcas = array_fill_keys($tablas, null);

        // SQL crudo a propósito: es una vista de sistema (DMV) que el ORM no modela. Los nombres van
        // como parámetros a OBJECT_ID(); una tabla heap aparece con index_id 0.
        $filas = DB::connection((new TwHistVtasModel)->getConnectionName())->select(
            'SELECT o.tabla, MAX(s.last_user_update) AS ultima
               FROM (SELECT ? AS tabla UNION ALL SELECT ? UNION ALL SELECT ?) o
               LEFT JOIN sys.dm_db_index_usage_stats s
                 ON s.database_id = DB_ID() AND s.object_id = OBJECT_ID(o.tabla)
              GROUP BY o.tabla',
            $tablas,
        );

        foreach ($filas as $fila) {
            $marcas[(string) $fila->tabla] = $fila->ultima === null ? null : (string) $fila->ultima;
        }

        return $marcas;
    }
}
