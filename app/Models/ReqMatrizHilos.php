<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReqMatrizHilos extends Model
{
    protected $table = 'ReqMatrizHilos';

    protected $fillable = [
        'Hilo',
        'Calibre',
        'Calibre2',
        'CalibreAX',
        'Fibra',
        'CodColor',
        'NombreColor',
        'N1',
        'N2',
    ];

    protected $casts = [
        'Calibre' => 'decimal:4',
        'Calibre2' => 'decimal:4',
        'N1' => 'decimal:4',
        'N2' => 'decimal:4',
    ];
}
