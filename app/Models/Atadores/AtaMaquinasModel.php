<?php

namespace App\Models\Atadores;

use Illuminate\Database\Eloquent\Model;

class AtaMaquinasModel extends Model
{
    protected $table = 'AtaMaquinas';
    protected $connection = 'sqlsrv';
    protected $primaryKey = 'MaquinaId';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'MaquinaId'
    ];
}
