<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ManFallasParos extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'dbo.ManFallasParos';

    protected $primaryKey = 'Id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
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
        'Enviado',
        'ObsCierre',
        'Calidad',
        'FechaFin',
    ];

    protected $casts = [
        'Id' => 'integer',
        'Fecha' => 'date',
        'Turno' => 'integer',
        'TurnoAtendio' => 'integer',
        'Enviado' => 'boolean',
        'Calidad' => 'integer',
        'FechaFin' => 'date',
    ];

    public function tipoFalla()
    {
        return $this->belongsTo(CatTipoFalla::class, 'TipoFallaId', 'TipoFallaId');
    }
}

