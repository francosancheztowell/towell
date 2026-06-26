<?php

namespace App\Models\Trazabilidad;

use Illuminate\Database\Eloquent\Model;

class TrazaVentas extends Model
{
    protected $table = 'TrazaVentas';
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
        'Factura',
        'OrdenCompra',
        'Articulo',
        'NombreArticulo',
        'Tamano',
        'Color',
        'NombreColor',
        'Cantidad',
        'Peso',
        'Almacen',
        'NombreAlmacen',
    ];

    protected $casts = [
        'Fecha' => 'date',
        'Cantidad' => 'float',
        'Peso' => 'float',
    ];
}
