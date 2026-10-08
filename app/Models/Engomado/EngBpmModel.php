<?php

namespace App\Models\Engomado;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $Id
 * @property string $Folio
 * @property Carbon|null $Fecha
 * @property string|null $CveEmplRec
 * @property string|null $NombreEmplRec
 * @property string|null $TurnoRecibe
 * @property string|null $CveEmplEnt
 * @property string|null $NombreEmplEnt
 * @property string|null $TurnoEntrega
 * @property string|null $CveEmplAutoriza
 * @property string|null $Status
 * @property string|null $NomEmplAutoriza
 */
class EngBpmModel extends Model
{
    use HasFactory;

    // protected $connection = 'sqlsrv'; // o 'ProdTowel'

    protected $table = 'EngBPM';

    protected $primaryKey = 'Id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'Folio',
        'Fecha',
        'CveEmplRec',
        'NombreEmplRec',
        'TurnoRecibe',
        'CveEmplEnt',
        'NombreEmplEnt',
        'TurnoEntrega',
        'CveEmplAutoriza',
        'NomEmplAutoriza',   // según tu encabezado
        'Status',
    ];

    protected $casts = [
        'Id' => 'integer',
        'Fecha' => 'datetime',
    ];
}
