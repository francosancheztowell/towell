<?php

namespace App\Models\Urdido;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UrdBpmLineModel extends Model
{
    use HasFactory;

    // protected $connection = 'sqlsrv'; // o 'ProdTowel'

    protected $table = 'UrdBPMLine';
    protected $primaryKey = 'Id';  // En tu grid aparece Id
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'Folio',
        'TurnoRecibe',
        'MaquinaId',      // visto en tu captura
        'Departamento',   // visto en tu captura
        'Orden',
        'Actividad',
        'Valor',          // NULL | 'OK' | 'X' (o lo que uses)
    ];

    protected $casts = [
        'Id'    => 'integer',
        'Orden' => 'integer',
    ];

    /** Pertenece al encabezado por Folio */
    public function header()
    {
        return $this->belongsTo(UrdBpmModel::class, 'Folio', 'Folio');
    }

    /** Scope: todas las líneas de un folio */
    public function scopeByFolio($q, string $folio)
    {
        return $q->where('Folio', $folio)->orderBy('Orden');
    }
}
