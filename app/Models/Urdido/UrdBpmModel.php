<?php

namespace App\Models\Urdido;

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
 * @property string|null $NombreEmplAutoriza
 */
class UrdBpmModel extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv'; // o 'ProdTowel'

    protected $table = 'UrdBPM';

    protected $primaryKey = 'Id';     // En tu grid aparece Id

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
        'NombreEmplAutoriza',
        'Status',
    ];

    protected $casts = [
        'Id' => 'integer',
        'Fecha' => 'datetime',
    ];
}
