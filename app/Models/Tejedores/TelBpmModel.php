<?php

namespace App\Models\Tejedores;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
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
 * @property string|null $Comentarios
 */
class TelBpmModel extends Model
{
    use HasFactory;

    // Si usas otra conexión (SQL Server), descomenta y ajusta:
    // protected $connection = 'sqlsrv'; // o 'ProdTowel'

    protected $table = 'TelBPM';

    protected $primaryKey = 'Folio';

    public $incrementing = false;      // PK string

    protected $keyType = 'string';

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
        'NomEmplAutoriza',
        'Status',
        'Comentarios',
    ];

    protected $casts = [
        'Fecha' => 'datetime',
    ];
}
