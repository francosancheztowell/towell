<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class catDesarrolladoresModel extends Model
{
    //
    use HasFactory;

    protected $table = 'cat_desarrolladores';

    protected $fillable = [
        'clave_empleado',
        'nombre',
        'Turno'
    ];

    public $timestamps = false;
        
}
