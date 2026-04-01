<?php

namespace App\Models\Planeacion;

use Illuminate\Database\Eloquent\Model;

class ProgramaTejidoColumnPreset extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'ProgramaTejidoColumnPresets';

    protected $fillable = [
        'usuario_id',
        'tabla',
        'nombre',
        'columnas',
        'es_default',
    ];

    protected $casts = [
        'columnas'   => 'array',
        'es_default' => 'boolean',
    ];
}
