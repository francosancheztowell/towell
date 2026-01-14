<?php

namespace App\Models\Engomado;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'Id'    => 'integer',
        'Fecha' => 'datetime',
    ];

    /** Relaciones */
    public function lines()
    {
        // Relación por Folio (FK en EngBPMLine)
        return $this->hasMany(EngBpmLineModel::class, 'Folio', 'Folio')
                    ->orderBy('Orden');
    }

    /** Scopes útiles */
    public function scopeStatus($q, ?string $status)
    {
        return $status ? $q->where('Status', $status) : $q;
    }
}
