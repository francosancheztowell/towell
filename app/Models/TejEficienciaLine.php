<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TejEficienciaLine extends Model
{
    //
    protected $table = "TejEficienciaLine";

    protected $fillable = [
        "Folio",
        "Date",
        "Turno",
        "NoTelarId",
        "SalonTejidoId",
        "VelocidadSD",
        "EficienciaSTD", 
        "RpmR1",
        "EficienciaR1",
        "RpmR2",
        "EficienciaR2",
        "RpmR3",
        "EficienciaR3",
        "ObsR1",
        "ObsR2",
        "ObsR3",
        "StatusOB1",
        "StatusOB2",
        "StatusOB3",
    ];

    const CREATED_AT = "created_at";
    const UPDATED_AT = "upadted_at";

    protected $casts = [
        'Date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    
}
