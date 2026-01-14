<?php

namespace App\Models\Urdido;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'Id'    => 'integer',
        'Fecha' => 'datetime',
    ];

    /** Header tiene muchas líneas por Folio (FK en UrdBPMLine) */
    public function lines()
    {
        // FK en lines: Folio ; Local key en header: Folio (no la PK Id)
        return $this->hasMany(UrdBpmLineModel::class, 'Folio', 'Folio')
                    ->orderBy('Orden');
    }

    /** Relación con la máquina */
    public function maquina()
    {
        return $this->belongsTo(URDCatalogoMaquina::class, 'MaquinaId', 'MaquinaId');
    }

    /** Scope útil: por status */
    public function scopeStatus($q, ?string $status)
    {
        return $status ? $q->where('Status', $status) : $q;
    }
}
