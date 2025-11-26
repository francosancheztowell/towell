<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EngFormulacionLineModel extends Model
{
    use HasFactory;

    // protected $connection = 'sqlsrv';

    protected $table = 'EngFormulacionLine';
    protected $primaryKey = 'Id';  // en tu grid aparece Id
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'Folio',
        'ItemId',
        'ItemName',
        'ConfigId',
        'ConsumoUnit',
        'ConsumoTotal',
        'Unidad',
        'InventLocation', 
    ];

    protected $casts = [
        'Id'           => 'integer',
        'ConsumoUnit'  => 'float',
        'ConsumoTotal' => 'float',
    ];

    /** Pertenece al encabezado por Folio */
    public function header()
    {
        return $this->belongsTo(EngProduccionFormulacionModel::class, 'Folio', 'Folio');
    }

    /** Scope útil para obtener líneas de un folio */
    public function scopeByFolio($q, string $folio)
    {
        return $q->where('Folio', $folio);
    }
}
