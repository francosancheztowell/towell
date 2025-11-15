<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EngProduccionEngomado extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'EngProduccionEngomado';

    protected $primaryKey = 'Id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'Folio',
        'Fecha',
        'HoraInicial',
        'HoraFinal',
        'Tiempo',
        'NoJulio',
        'KgBruto',
        'Tara',
        'KgNeto',
        'Canoa1',
        'Canoa2',
        'Canoa3',
        'Canoa4',
        'Tambor',
        'Humedad',
        'Roturas',
        'CveEmpl1',
        'NomEmpl1',
        'Metros1',
        'Turno1',
        'CveEmpl2',
        'NomEmpl2',
        'Metros2',
        'Turno2',
        'CveEmpl3',
        'NomEmpl3',
        'Metros3',
        'Turno3',
        'Solidos',
    ];

    protected $casts = [
        'Id' => 'integer',
        'Fecha' => 'date',
        'HoraInicial' => 'string',
        'HoraFinal' => 'string',
        'Tiempo' => 'string',
        'NoJulio' => 'string',
        'KgBruto' => 'float',
        'Tara' => 'float',
        'KgNeto' => 'float',
        'Canoa1' => 'float',
        'Canoa2' => 'float',
        'Canoa3' => 'float',
        'Canoa4' => 'float',
        'Tambor' => 'float',
        'Humedad' => 'float',
        'Roturas' => 'integer',
        'Turno1' => 'integer',
        'Metros1' => 'float',
        'Turno2' => 'integer',
        'Metros2' => 'float',
        'Turno3' => 'integer',
        'Metros3' => 'float',
        'Solidos' => 'float',
    ];
}

