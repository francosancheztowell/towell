<?php

namespace App\Models\Atadores;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $Id
 * @property string|null $NoJulio
 * @property string|null $NoProduccion
 * @property string|null $ActividadId
 * @property float|null $Porcentaje
 * @property int|null $Estado
 * @property string|null $CveEmpl
 * @property string|null $NomEmpl
 * @property string|null $Turno
 */
class AtaMontadoActividadesModel extends Model
{
    //
    protected $table = 'AtaMontadoActividades';

    protected $connection = 'sqlsrv';

    public $timestamps = false;

    protected $primaryKey = 'Id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'NoJulio',
        'NoProduccion',
        'ActividadId',
        'Porcentaje',
        'Estado',
        'CveEmpl',
        'NomEmpl',
        'Turno',
    ];
}
