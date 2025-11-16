<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TejMarcasLine extends Model
{
    protected $table = 'TejMarcasLine';
    public $timestamps = false;

    protected $fillable = [
        'Folio',
        'Date',
        'Turno',
        'SalonTejidoId',
        'NoTelarId',
        'Eficiencia',
        'Marcas',
        'Trama',
        'Pie',
        'Rizo',
        'Otros',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'Date' => 'date',
        'Turno' => 'integer',
        'Eficiencia' => 'float',
        'Marcas' => 'integer',
        'Trama' => 'integer',
        'Pie' => 'integer',
        'Rizo' => 'integer',
        'Otros' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relación con la marca principal
    public function tejMarcas()
    {
        return $this->belongsTo(TejMarcas::class, 'Folio', 'Folio');
    }
}





















































