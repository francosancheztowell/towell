<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ManFallasParos extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'ManFallasParos';

    protected $primaryKey = 'Id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'Id',
        'Folio',
        'Estatus',
        'Fecha',
        'Hora',
        'Depto',
        'MaquinaId',
        'TipoFallaId',
        'Falla',
        'Descripcion',
        'HoraFin',
        'CveEmpl',
        'NomEmpl',
        'Turno',
        'CveAtendio',
        'NomAtendio',
        'TurnoAtendio',
        'Obs',
        'OrdenTrabajo',
    ];

    protected $casts = [
        'Id' => 'integer',
        'Fecha' => 'date',
        'Hora' => 'string',
        'HoraFin' => 'string',
        'Turno' => 'integer',
        'TurnoAtendio' => 'integer',
    ];

    /**
     * Relación con CatTipoFalla
     */
    public function tipoFalla()
    {
        return $this->belongsTo(CatTipoFalla::class, 'TipoFallaId', 'TipoFallaId');
    }
}

