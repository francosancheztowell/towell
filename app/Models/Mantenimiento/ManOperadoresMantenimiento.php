<?php

namespace App\Models\Mantenimiento;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $Id
 * @property string $CveEmpl
 * @property string $NomEmpl
 * @property int|null $Turno
 * @property string|null $Depto
 * @property string|null $Telefono
 */
class ManOperadoresMantenimiento extends Model
{
    protected $connection = 'sqlsrv';

    protected $table = 'dbo.ManOperadoresMantenimiento';

    protected $primaryKey = 'Id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'CveEmpl',
        'NomEmpl',
        'Turno',
        'Depto',
        'Telefono',
    ];

    protected $casts = [
        'Id' => 'integer',
        'Turno' => 'integer',
    ];
}
