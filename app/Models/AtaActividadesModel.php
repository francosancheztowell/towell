<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AtaActividadesModel extends Model
{
    //
    protected $table = 'AtaActividades';
    protected $connection = 'sqlsrv';
    public $timestamps = false;

    protected $fillable = [
        'ActividadId',
        'Porcentaje'
    ];
}
