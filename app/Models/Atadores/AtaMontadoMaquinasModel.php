<?php

namespace App\Models\Atadores;

use Illuminate\Database\Eloquent\Model;

class AtaMontadoMaquinasModel extends Model
{
    //
    protected $table = 'AtaMontadoMaquinas';
    protected $connection = 'sqlsrv';
    public $timestamps = false;
    protected $primaryKey = 'Id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'NoJulio',
        'NoProduccion',
        'MaquinaId',
        'Estado',
        'CveEmpl',
        'NomEmpleado',
        'NomEmpl',
    ]; 
}
