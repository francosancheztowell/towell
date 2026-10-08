<?php

namespace App\Models\Tejedores;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $Id
 * @property string|null $numero_empleado
 * @property string|null $nombreEmpl
 * @property string|null $NoTelarId
 * @property string|null $Turno
 * @property string|null $SalonTejidoId
 * @property int|null $Supervisor
 */
class TelTelaresOperador extends Model
{
    protected $connection = 'sqlsrv';

    protected $table = 'TelTelaresOperador';

    // Usar Id como clave primaria (IDENTITY)
    protected $primaryKey = 'Id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'Id',
        'numero_empleado',
        'nombreEmpl',
        'NoTelarId',
        'Turno',
        'SalonTejidoId',
        'Supervisor',
    ];

    protected $casts = [
        'Supervisor' => 'boolean',
    ];
}
