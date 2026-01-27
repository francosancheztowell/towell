<?php

namespace App\Models\Engomado;

use Illuminate\Database\Eloquent\Model;

class CatUbicaciones extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'CatUbicaciones';

    protected $primaryKey = 'Id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'Codigo',
    ];

    protected $casts = [
        'Id' => 'integer',
        'Codigo' => 'string',
    ];
}
