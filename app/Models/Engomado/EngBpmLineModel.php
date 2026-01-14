<?php

namespace App\Models\Engomado;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'Id'    => 'integer',
        'Orden' => 'integer',
    ];

    /** Relaciones */
    public function header()
    {
        return $this->belongsTo(EngBpmModel::class, 'Folio', 'Folio');
    }

    /** Scope: todas las líneas de un folio */
    public function scopeByFolio($q, string $folio)
    {
        return $q->where('Folio', $folio)->orderBy('Orden');
    }
}
