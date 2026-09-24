<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Solo lectura: dbo.TwHistoricosPedidos (base ReportesTowel) — Pedido (OC).
 * Tabla plana de líneas de pedido, sin primary key. Trae además
 * ENTREGADOQTY/PENDIENTEQTY/ENTREGADOKG/PENDIENTEKG para calcular estatus.
 *
 * Varias personas consultan estos datos en simultáneo (dashboards/reportes);
 * el modelo bloquea save()/delete() para que nadie escriba por accidente
 * sobre la fuente de reportes.
 */
class TwHistPedidosModel extends Model
{
    protected $connection = 'sqlsrv_Reportes_Towell';

    protected $table = 'dbo.TwHistoricosPedidos';

    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = null;

    protected $guarded = ['*'];

    public function save(array $options = []): bool
    {
        throw new RuntimeException('TwHistPedidosModel es de solo lectura: dbo.TwHistoricosPedidos no admite escrituras.');
    }

    public function delete(): ?bool
    {
        throw new RuntimeException('TwHistPedidosModel es de solo lectura: dbo.TwHistoricosPedidos no admite escrituras.');
    }
}
