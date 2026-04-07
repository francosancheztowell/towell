<?php

namespace App\Models\Engomado;

use Illuminate\Database\Eloquent\Model;

class CatDefectosUrdEng extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'CatDefectosUrdEng';
    protected $primaryKey = 'Id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'Clave',
        'Penalizacion',
        'Defecto',
        'CincoS',
        'Seguridad',
        'Activo',
        'CreatedAt',
        'UpdatedAt',
    ];

    protected $casts = [
        'Id' => 'integer',
        'Penalizacion' => 'float',
        'Activo' => 'boolean',
        'CreatedAt' => 'datetime',
        'UpdatedAt' => 'datetime',
    ];
}
