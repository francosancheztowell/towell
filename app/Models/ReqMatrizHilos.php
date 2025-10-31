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
        'NombreColor'
    ];

    protected $casts = [
        'Calibre' => 'decimal:4',
        'Calibre2' => 'decimal:4',
    ];
}
