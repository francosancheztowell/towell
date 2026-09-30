<?php

namespace App\Models\Atadores;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $Id
 * @property string|null $ActividadId
 * @property float|null $Porcentaje
 */
class AtaActividadesModel extends Model
{
    protected $table = 'AtaActividades';

    protected $connection = 'sqlsrv';

    protected $primaryKey = 'Id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'ActividadId',
        'Porcentaje',
    ];
}
