<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvSecuenciaTrama extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'dbo.InvSecuenciaTrama';
    protected $primaryKey = 'Id';
    public $timestamps = false;

    protected $fillable = [
        'NoTelar',
        'TipoTelar',
        'Secuencia',
    ];

    protected $casts = [
        'NoTelar' => 'integer',
        'Secuencia' => 'integer',
    ];
}







