<?php

namespace App\Models\Sistema\Monitoreo;

use Illuminate\Database\Eloquent\Model;

/**
 * dbo.SYSMonDispositivo: un renglón por navegador (cookie towell_disp).
 *
 * @property int $Id
 * @property string $Uuid
 * @property string|null $Nombre
 * @property string $Tipo
 * @property string|null $Modelo
 * @property string|null $SO
 * @property string|null $Navegador
 * @property string $UaHash
 * @property string $UltimaIp
 * @property int|null $UltimoUsuarioId
 * @property int|null $UltimaSesionId
 * @property \Illuminate\Support\Carbon $PrimeraVez
 * @property \Illuminate\Support\Carbon $UltimaActividad
 * @property string|null $UltimaRuta
 * @property bool $Visible
 * @property int $InactivoSeg
 * @property string|null $VersionFront
 * @property string|null $Pantalla
 * @property \Illuminate\Support\Carbon|null $CierreSolicitadoEn
 * @property int|null $CierreSolicitadoPor
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
