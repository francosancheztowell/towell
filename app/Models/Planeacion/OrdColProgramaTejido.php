<?php

namespace App\Models\Planeacion;

use Illuminate\Database\Eloquent\Model;

class OrdColProgramaTejido extends Model
{
    protected $table = 'OrdColProgramaTejido';
    protected $primaryKey = 'Id';
    public $timestamps = false;
    protected $fillable = [
        'UsuarioId',
        'Columna',
        'Estado',
    ];
    protected $casts = [
        'Estado' => 'boolean',
    ];
}

