<?php

namespace App\Models\Tejedores;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TelBpmLineModel extends Model
{
    use HasFactory;

    // protected $connection = 'sqlsrv'; // o 'ProdTowel'

    protected $table = 'TelBPMLine';

    protected $primaryKey = 'Id';
    public $incrementing = true;
    protected $keyType = 'integer';
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
