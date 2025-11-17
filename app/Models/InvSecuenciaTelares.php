<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvSecuenciaTelares extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'dbo.InvSecuenciaTelares';
    protected $primaryKey = 'Id';
    public $timestamps = false;

    protected $fillable = [
        'NoTelar',
        'TipoTelar',
        'Secuencia',
        'Observaciones',
    ];

    protected $casts = [
        'NoTelar' => 'integer',
        'Secuencia' => 'integer',
        'Created_At' => 'datetime',
        'Updated_At' => 'datetime',
    ];
}






























