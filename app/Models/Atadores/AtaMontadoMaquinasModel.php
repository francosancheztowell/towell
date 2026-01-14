<?php

namespace App\Models\Atadores;

use Illuminate\Database\Eloquent\Model;

class AtaMontadoMaquinasModel extends Model
{
    //
    protected $table = 'AtaMontadoMaquinas';
    protected $connection = 'sqlsrv';
    public $timestamps = false;

    protected $fillable = [
        'NoJulio',
        'NoProduccion',
        'MaquinaId',
        'Estado'
    ]; 
}
