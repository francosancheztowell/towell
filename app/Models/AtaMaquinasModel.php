<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AtaMaquinasModel extends Model
{
    //
    protected $table = 'AtaMaquinas';
    protected $connection = 'sqlsrv';
    public $timestamps = false;

    protected $fillable = [
        'MaquinaId'
    ];
}
