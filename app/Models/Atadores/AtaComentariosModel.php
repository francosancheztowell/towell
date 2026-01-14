<?php

namespace App\Models\Atadores;

use Illuminate\Database\Eloquent\Model;

class AtaComentariosModel extends Model
{
    protected $table = 'AtaComentarios';
    protected $connection = 'sqlsrv';
    protected $primaryKey = 'Nota1';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'Nota1',
        'Nota2'
    ];
}
