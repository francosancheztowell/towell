<?php

namespace App\Models\Sistema;

use Illuminate\Database\Eloquent\Model;

class SysDepartamentos extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'dbo.SysDepartamentos';

    protected $primaryKey = 'Depto';
    public $incrementing = false;
    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'Depto',
        'Descripcion',
    ];

    protected $casts = [
        'Depto' => 'string',
        'Descripcion' => 'string',
    ];
}


