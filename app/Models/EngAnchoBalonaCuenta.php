<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EngAnchoBalonaCuenta extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'EngAnchoBalonaCuenta';

    protected $primaryKey = 'Id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'Cuenta',
        'RizoPie',
        'AnchoBalona',
    ];

    protected $casts = [
        'AnchoBalona' => 'integer',
    ];
}

