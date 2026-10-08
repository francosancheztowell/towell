<?php

namespace App\Models\Engomado;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $Id
 * @property string $Folio
 * @property int|null $Orden
 * @property string|null $Actividad
 * @property string|null $Valor
 * @property string|null $TurnoRecibe
 * @property string|null $MaquinaId
 * @property string|null $Departamento
 */
class EngBpmLineModel extends Model
{
    use HasFactory;

    // protected $connection = 'sqlsrv';

    protected $table = 'EngBPMLine';

    protected $primaryKey = 'Id';   // En tu grid aparece Id

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'Folio',
        'TurnoRecibe',
        'MaquinaId',
        'Departamento',
        'Orden',
        'Actividad',
        'Valor',        // NULL | 'OK' | 'X' (o lo que uses)
    ];

    protected $casts = [
        'Id' => 'integer',
        'Orden' => 'integer',
    ];
}
