<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SSYSFoliosSecuencias extends Model
{
    protected $table = 'SSYSFoliosSecuencias';

    protected $primaryKey = 'Id';

    public $timestamps = false;

    protected $fillable = [
        'Modulo',
        'Prefijo',
        'Consecutivo',
    ];

    protected $casts = [
        'Consecutivo' => 'integer',
    ];
}

