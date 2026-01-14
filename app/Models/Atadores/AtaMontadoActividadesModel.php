<?php

namespace App\Models\Atadores;

use Illuminate\Database\Eloquent\Model;

class AtaMontadoActividadesModel extends Model
{
    //
    protected $table = 'AtaMontadoActividades';
    protected $connection = 'sqlsrv';
    public $timestamps = false;

    protected $fillable = [
        'NoJulio',
        'NoProduccion',
        'ActividadId',
        'Porcentaje',
        'Estado',
        'CveEmpl',
        'NomEmpl',
        'Turno'
    ];
}
