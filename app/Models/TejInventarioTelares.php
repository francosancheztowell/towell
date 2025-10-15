<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TejInventarioTelares extends Model
{
    protected $table = 'tej_inventario_telares';

    protected $fillable = [
        'no_telar',
        'status',
        'tipo',
        'cuenta',
        'calibre',
        'fecha',
        'turno',
        'hilo',
        'metros',
        'no_julio',
        'no_orden',
        'tipo_atado',
        'salon'
    ];

    protected $casts = [
        'fecha' => 'date',
        'calibre' => 'decimal:2',
        'metros' => 'decimal:2',
        'turno' => 'integer'
    ];
}
