<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TejTramaConsumos extends Model
{
    protected $table = 'TejTramaConsumos';
    public $timestamps = false;

    protected $fillable = [
        'Folio',
        'NoTelarId',
        'SalonTejidoId',
        'NoProduccion',
        'CalibreTrama',
        'NombreProducto',
        'FibraTrama',
        'CodColorTrama',
        'ColorTrama',
        'Cantidad',
    ];

    protected $casts = [
        'CalibreTrama' => 'decimal:2',
        'Cantidad' => 'decimal:2',
    ];

    public function tejTrama()
    {
        return $this->belongsTo(TejTrama::class, 'Folio', 'Folio');
    }
}





















