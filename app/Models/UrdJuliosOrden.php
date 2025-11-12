<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrdJuliosOrden extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'UrdJuliosOrden';

    protected $primaryKey = 'Id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'Folio',
        'Julios',
        'Hilos',
        'Obs',
    ];

    protected $casts = [
        'Id' => 'integer',
        'Julios' => 'integer',
        'Hilos' => 'integer',
    ];

    /**
     * Relación con UrdProgramaUrdido (N:1)
     */
    public function programaUrdido()
    {
        return $this->belongsTo(UrdProgramaUrdido::class, 'Folio', 'Folio');
    }
}

