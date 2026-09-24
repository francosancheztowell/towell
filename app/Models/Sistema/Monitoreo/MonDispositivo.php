<?php

namespace App\Models\Sistema\Monitoreo;

use Illuminate\Database\Eloquent\Model;

/**
 * dbo.SYSMonDispositivo: un renglón por navegador (cookie towell_disp).
 */
class MonDispositivo extends Model
{
    protected $connection = 'sqlsrv';

    protected $table = 'SYSMonDispositivo';

    protected $primaryKey = 'Id';

    public $timestamps = false;

    protected $fillable = [
        'Uuid', 'Nombre', 'Tipo', 'Modelo', 'SO', 'Navegador', 'UaHash', 'UltimaIp',
        'UltimoUsuarioId', 'UltimaSesionId', 'PrimeraVez', 'UltimaActividad', 'UltimaRuta',
        'Visible', 'InactivoSeg', 'VersionFront', 'Pantalla', 'CierreSolicitadoEn', 'CierreSolicitadoPor',
    ];

    protected $casts = [
        'Id' => 'integer',
        'UltimoUsuarioId' => 'integer',
        'UltimaSesionId' => 'integer',
        'PrimeraVez' => 'datetime',
        'UltimaActividad' => 'datetime',
        'Visible' => 'boolean',
        'InactivoSeg' => 'integer',
        'CierreSolicitadoEn' => 'datetime',
        'CierreSolicitadoPor' => 'integer',
    ];
}
