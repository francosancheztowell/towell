<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ManOperadoresMantenimiento extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'dbo.ManOperadoresMantenimiento';

    protected $primaryKey = 'Id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'CveEmpl',
        'NomEmpl',
        'Turno',
        'Depto',
        'Telefono',
    ];

    protected $casts = [
        'Id' => 'integer',
        'Turno' => 'integer',
    ];
}
