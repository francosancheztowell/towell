<?php

namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Kardex consolidado de materia prima (solo lectura).
 * Vive en ProdTowel del servidor .24; se llega por la conexión de TOW_PRO
 * con el nombre de tres partes (la ProdTowel de `sqlsrv` es la del .28 y no la tiene).
 *
 * @property string $ITEMID
 * @property string $CONFIGID
 * @property string $INVENTCOLORID
 * @property float $MC1CU costo unitario Mc Coy 1
 * @property float $MC2CU costo unitario Mc Coy 2
 * @property float $MC3CU costo unitario Mc Coy 3
 * @property float $KMCU costo unitario Karl Mayer
 * @property float $EXISTENCIACU costo promedio del inventario del mes (respaldo si la máquina está en 0)
 * @property int $YEARDATE
 * @property int $MONTHDATE
 */
class InvKardexMPConsol extends Model
{
    protected $connection = 'sqlsrv_tow_pro';

    protected $table = 'ProdTowel.dbo.InvKardexMPConsol';

    public $timestamps = false;

    protected $casts = [
        'MC1CU' => 'float',
        'MC2CU' => 'float',
        'MC3CU' => 'float',
        'KMCU' => 'float',
        'EXISTENCIACU' => 'float',
        'YEARDATE' => 'integer',
        'MONTHDATE' => 'integer',
    ];

    public function scopeEntero(Builder $query): Builder
    {
        return $query->where('INVENTSIZEID', 'ENTERO');
    }
}
