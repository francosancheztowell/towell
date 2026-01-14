<?php

namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Model;

class InvSecuenciaCorteEf extends Model
{
    //
    protected $table = "InvSecuenciaCorteEf";

    protected $fillable = [
        "NoTelarId",
        "SalonTejidoId",
        "Orden",
    ];

    public $timestamps = false;

    
}
