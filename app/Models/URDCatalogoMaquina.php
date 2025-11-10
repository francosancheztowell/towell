<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class URDCatalogoMaquina extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'URDCatalogoMaquinas';

    // Clave primaria string (no autoincremental)
    protected $primaryKey = 'MaquinaId';
    public $incrementing = false;
    protected $keyType = 'string';

    // La tabla no tiene created_at / updated_at
    public $timestamps = false;

    protected $fillable = [
        'MaquinaId',
        'Nombre',
        'Departamento',
    ];
}
