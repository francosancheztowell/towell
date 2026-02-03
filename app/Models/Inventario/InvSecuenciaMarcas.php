<?php

namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Model;

class InvSecuenciaMarcas extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'dbo.InvSecuenciaMarcas';
    protected $primaryKey = 'NoTelarId';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'NoTelarId',
        'SalonTejidoId',
        'Orden',
    ];

    protected $casts = [
        'NoTelarId' => 'integer',
        'Orden' => 'integer',
    ];
}
