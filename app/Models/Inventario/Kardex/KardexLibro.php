<?php

namespace App\Models\Inventario\Kardex;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Base de las hojas Kdx del libro Kardex Prod Proc (solo lectura). Viven en ProdTowel del
 * servidor .24: se llega por la conexión de TOW_PRO con el nombre de tres partes, igual que
 * InvKardexMPConsol. Cada renglón es artículo + localidad (WMSLOCATIONID) de un mes; las
 * columnas de kg son numeric y llegan como string (App\Services\Kardex\KardexLibroBuilder las convierte).
 *
 * @property string $ITEMID
 * @property string $CONFIGID
 * @property string $INVENTSIZEID
 * @property string $INVENTCOLORID
 * @property string $WMSLOCATIONID máquina o telar
 * @property int $YEARDATE
 * @property int $MONTHDATE
 */
abstract class KardexLibro extends Model
{
    protected $connection = 'sqlsrv_tow_pro';

    public $timestamps = false;

    protected $casts = [
        'YEARDATE' => 'integer',
        'MONTHDATE' => 'integer',
    ];

    /** @param  Builder<static>  $query */
    public function scopePeriodo(Builder $query, int $anio, int $mes): void
    {
        $query->where('YEARDATE', $anio)->where('MONTHDATE', $mes);
    }
}
