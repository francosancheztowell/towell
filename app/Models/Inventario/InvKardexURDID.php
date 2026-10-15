<?php

namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Model;

/**
 * Kardex del almacén de urdido A-EP-URDID (solo lectura): el hilo que está en cada máquina.
 * Vive en ProdTowel del servidor .24, como InvKardexMPConsol. Un renglón por material, localidad
 * (WMSLOCATIONID = MC1, MC2, MC3, KM1) y mes. Las entradas de MP (ENTRADAMPKG + ENTMPBBKG) son las
 * columnas MC1KG… de InvKardexMPConsol; SALPROD*KG es lo que consumieron las órdenes de urdido, y debe
 * ser igual a los kg de los julios.
 *
 * @property string $WMSLOCATIONID
 * @property float $SALPRODMC1KG
 * @property float $SALPRODMC2KG
 * @property float $SALPRODMC3KG
 * @property float $SALPRODKMKG
 * @property int $YEARDATE
 * @property int $MONTHDATE
 */
class InvKardexURDID extends Model
{
    protected $connection = 'sqlsrv_tow_pro';

    protected $table = 'ProdTowel.dbo.InvKardexURDID';

    public $timestamps = false;

    protected $casts = [
        'SALPRODMC1KG' => 'float',
        'SALPRODMC2KG' => 'float',
        'SALPRODMC3KG' => 'float',
        'SALPRODKMKG' => 'float',
        'YEARDATE' => 'integer',
        'MONTHDATE' => 'integer',
    ];
}
