<?php

namespace App\Models\Atadores;

use Illuminate\Database\Eloquent\Model;

class AtaKmMontadoModel extends Model
{
    protected $table = 'AtaKmMontado';

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
        'FechaInicio' => 'date',
        'FechaFin' => 'date',
    ];
}
