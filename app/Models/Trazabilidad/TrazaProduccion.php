<?php

namespace App\Models\Trazabilidad;

use Illuminate\Database\Eloquent\Model;

class TrazaProduccion extends Model
{
    protected $table = 'TrazaProduccion';
    protected $connection = 'sqlsrv';
    protected $primaryKey = 'Id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'Flogs',
        'Tipo',
        'Cliente',
        'Agente',
        'Fecha',
        'Articulo',
        'NombreArticulo',
        'Tamano',
        'Color',
        'NombreColor',
        'Cantidad',
        'Peso',
        'Almacen',
        'NombreAlmacen',
        'Orden',
    ];

    protected $casts = [
        'Fecha' => 'date',
        'Cantidad' => 'float',
        'Peso' => 'float',
    ];
}
