<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TelBpmLineModel extends Model
{
    use HasFactory;

    // protected $connection = 'sqlsrv'; // o 'ProdTowel'

    protected $table = 'TelBPMLine';

    /**
     * Esta tabla NO tiene PK. Con Eloquent:
     * - Inserts: OK con create()/save()
     * - Updates/Deletes: usa query builder con condiciones (ver notas abajo).
     */
    protected $primaryKey = null;
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'Folio',
        'TurnoRecibe',
        'NoTelarId',
        'SalonTejidoId',
        'Orden',
        'Actividad',
        'Valor',
    ];

    protected $casts = [
        'Orden' => 'integer',
    ];

    /** Relaciones */
    public function header()
    {
        return $this->belongsTo(TelBpmModel::class, 'Folio', 'Folio');
    }

    /** Scopes útiles */
    public function scopeByFolio($q, string $folio)
    {
        return $q->where('Folio', $folio)->orderBy('Orden');
    }
}
