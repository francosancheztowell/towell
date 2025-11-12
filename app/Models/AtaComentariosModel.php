<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AtaComentariosModel extends Model
{
    //
    protected $table = 'AtaComentarios';
    protected $connection = 'sqlsrv';
    public $timestamps = false;

    protected $fillable = [
        'Nota1',
        'Nota2'
    ];
}
