<?php

namespace App\Models\Engomado;

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
        'Id',
        'Folio',
        'EngProduccionFormulacionId',
        'ItemId',
        'ItemName',
        'ConfigId',
        'ConsumoUnit',
        'ConsumoTotal',
        'Unidad',
        'InventLocation',
    ];

    protected $casts = [
        'Id'                        => 'integer',
        'EngProduccionFormulacionId' => 'integer',
        'ConsumoUnit'               => 'float',
        'ConsumoTotal'              => 'float',
    ];

    /** Pertenece al encabezado por Id (nueva relación mejorada) */
    public function header()
    {
        return $this->belongsTo(EngProduccionFormulacionModel::class, 'EngProduccionFormulacionId', 'Id');
    }

    /** Relación alternativa: pertenece al encabezado por Folio (mantener compatibilidad) */
    public function headerByFolio()
    {
        return $this->belongsTo(EngProduccionFormulacionModel::class, 'Folio', 'Folio');
    }

    /** Scope útil para obtener líneas de un folio */
    public function scopeByFolio($q, string $folio)
    {
        return $q->where('Folio', $folio);
    }

    /** Scope útil para obtener líneas por Id del encabezado */
    public function scopeByFormulacionId($q, int $formulacionId)
    {
        return $q->where('EngProduccionFormulacionId', $formulacionId);
    }
}
