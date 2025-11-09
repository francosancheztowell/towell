<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
    ];

    protected $casts = [
        'Fecha' => 'datetime',   // DATETIME -> Carbon
    ];

    /** Relaciones */
    public function lines()
    {
        return $this->hasMany(TelBpmLineModel::class, 'Folio', 'Folio')
                    ->orderBy('Orden'); // si Orden viene nulo, las coloca al final
    }
}
