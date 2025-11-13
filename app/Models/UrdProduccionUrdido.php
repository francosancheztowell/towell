<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrdProduccionUrdido extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'UrdProduccionUrdido';

    protected $primaryKey = 'Id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'Folio',
        'Fecha',
        'HoraInicial',
        'HoraFinal',
        'NoJulio',
        'Hilos',
        'KgBruto',
        'Tara',
        'KgNeto',
        'Hilatura',
        'Maquina',
        'Operac',
        'Transf',
        'TipoAtado',
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
    ];

    protected $casts = [
        'Id' => 'integer',
        'Fecha' => 'date',
        'HoraInicial' => 'string',
        'HoraFinal' => 'string',
        'Hilos' => 'integer',
        'KgBruto' => 'float',
        'Tara' => 'float',
        'KgNeto' => 'float',
        'Hilatura' => 'integer',
        'Maquina' => 'integer',
        'Operac' => 'integer',
        'Transf' => 'integer',
        'Turno1' => 'integer',
        'Metros1' => 'float',
        'Turno2' => 'integer',
        'Metros2' => 'float',
        'Turno3' => 'integer',
        'Metros3' => 'float',
    ];
}

