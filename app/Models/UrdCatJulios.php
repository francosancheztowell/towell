<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrdCatJulios extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'UrdCatJulios';

    protected $primaryKey = 'Id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'NoJulio',
        'Tara',
        'Departamento',
    ];

    protected $casts = [
        'Id' => 'integer',
        'Tara' => 'float',
    ];
}

