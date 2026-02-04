<?php

namespace App\Models\Tejedores;

use Illuminate\Database\Eloquent\Model;

class TelTelaresOperador extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'TelTelaresOperador';

    // Usar Id como clave primaria (IDENTITY)
    protected $primaryKey = 'Id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'Id',
        'numero_empleado',
        'nombreEmpl',
        'NoTelarId',
        'Turno',
        'SalonTejidoId',
        'Supervisor',
    ];

    protected $casts = [
        'Supervisor' => 'boolean',
    ];
}
