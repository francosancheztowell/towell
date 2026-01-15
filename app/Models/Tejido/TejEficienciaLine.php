<?php

namespace App\Models\Tejido;

use Illuminate\Database\Eloquent\Model;

class TejEficienciaLine extends Model
{
    protected $table = "TejEficienciaLine";

    protected $fillable = [
        "Folio",
        "Date",
        "Turno",
        "NoTelarId",
        "SalonTejidoId",
        "RpmStd",
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
    const UPDATED_AT = "updated_at";

    protected $casts = [
        'Date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relación con el encabezado de eficiencia
     */
    public function tejEficiencia()
    {
        return $this->belongsTo(TejEficiencia::class, 'Folio', 'Folio');
    }

    /**
     * Relación con el telar
     */
    public function telar()
    {
        return $this->belongsTo(\App\Models\Planeacion\ReqTelares::class, 'NoTelarId', 'NoTelarId');
    }
}
