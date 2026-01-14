<?php

namespace App\Models\Planeacion;

use Illuminate\Database\Eloquent\Model;

class ReqMatrizHilos extends Model
{
    protected $table = 'ReqMatrizHilos';
    
    // La tabla usa 'Id' con mayúscula como clave primaria
    protected $primaryKey = 'Id';
    
    protected $keyType = 'int';
    
    public $incrementing = true;
    
    public $timestamps = false;

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
