<?php

namespace App\Models\Sistema;

use Illuminate\Database\Eloquent\Model;

class SysDepartamento extends Model
{
    protected $connection = 'sqlsrv';

    protected $table = 'dbo.SysDepartamentos';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'Depto',
        'Descripcion',
    ];

    protected $casts = [
        'id' => 'integer',
        'Depto' => 'string',
        'Descripcion' => 'string',
    ];
}
