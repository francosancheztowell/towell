<?php

namespace App\Models\Atadores;

use Illuminate\Database\Eloquent\Model;

class AtaKmEnhebradoModel extends Model
{
    protected $table = 'AtaKmEnhebrado';

    protected $connection = 'sqlsrv';

    public $timestamps = false;

    protected $primaryKey = 'Id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'NoJulio',
        'NoProduccion',
        'CveEmpl1',
        'NomEmpl1',
        'CveEmpl2',
        'NomEmpl2',
        'CveEmpl3',
        'NomEmpl3',
        'FechaInicio',
        'FechaFin',
    ];

    protected $casts = [
        'FechaInicio' => 'datetime',
        'FechaFin' => 'datetime',
    ];
}
