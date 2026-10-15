<?php

namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Model;

/**
 * Kardex del almacén de julios urdidos A-JUL/URD (solo lectura). Vive en ProdTowel del servidor .24, como
 * InvKardexMPConsol. Un renglón por julio-artículo, localidad y mes. ENT{MC1,MC2,MC3,KM1}KG son los julios
 * que AX recibió de cada máquina en el mes: deben ser los kg de UrdProduccionUrdido.
 *
 * @property float $ENTMC1KG
 * @property float $ENTMC2KG
 * @property float $ENTMC3KG
 * @property float $ENTKM1KG
 * @property int $YEARDATE
 * @property int $MONTHDATE
 */
class InvKardexAJULURD extends Model
{
    protected $connection = 'sqlsrv_tow_pro';

    protected $table = 'ProdTowel.dbo.InvKardexAJULURD';

    public $timestamps = false;

    protected $casts = [
        'ENTMC1KG' => 'float',
        'ENTMC2KG' => 'float',
        'ENTMC3KG' => 'float',
        'ENTKM1KG' => 'float',
        'YEARDATE' => 'integer',
        'MONTHDATE' => 'integer',
    ];
}
