<?php

namespace App\Models\Atadores;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $Id
 * @property string|null $NoJulio
 * @property string|null $NoProduccion
 * @property string|null $MaquinaId
 * @property int|null $Estado
 */
class AtaMontadoMaquinasModel extends Model
{
    //
    protected $table = 'AtaMontadoMaquinas';

    protected $connection = 'sqlsrv';

    public $timestamps = false;

    protected $primaryKey = 'Id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'NoJulio',
        'NoProduccion',
        'MaquinaId',
        'Estado',
        'CveEmpl',
        'NomEmpleado',
        // 'NomEmpl',
    ];
}
