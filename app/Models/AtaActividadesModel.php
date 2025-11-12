<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AtaActividadesModel extends Model
{
    protected $table = 'AtaActividades';
    protected $connection = 'sqlsrv';
    protected $primaryKey = 'ActividadId';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'ActividadId',
        'Porcentaje'
    ];
}
