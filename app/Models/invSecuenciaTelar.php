<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class invSecuenciaTelar extends Model
{
    // Modelo para la base de datos del Inventario de secuencia de telares

    protected $table = "InvSecuenciaTelares";

    protected $primaryKey = 'Id';

    protected $fillable = [
        "NoTelar",
        "TipoTelar",
        "Secuencia",
        "Observaciones",
    ];

    const CREATED_AT = "Created_At";
    const UPDATED_AT = "Updated_At";

    protected $casts = [
        "NoTelar"=> "integer",
        "Secuencia"=> "integer",
        "Created_At"=> "datetime",
        "Updated_At"=> "datetime",
    ];

    protected $nullable = [
        "Observaciones",
        "Updated_At"
    ];
}
