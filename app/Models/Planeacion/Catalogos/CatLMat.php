<?php

namespace App\Models\Planeacion\Catalogos;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CatLMat extends Model
{
    use HasFactory;

    protected $table = 'CatLMat';

    protected $primaryKey = 'Id';

    public $incrementing = true;

    public $timestamps = false;

    public const COLUMNS = [
        'Id', 'Orden', 'Salon', 'Nombre', 'Descrip', 'PesoCrudo', 'ItemId', 'ConfigId',
        'InventSizeId', 'InventColorId', 'NombreColor', 'InventLocationId', 'Qty', 'Porcentaje',
        'ItemIdCrudo', 'InventSizeCrudo', 'Luchaje', 'CodigoDibujo',
        'FechaRegistro', 'HoraRegistro', 'UsuarioRegistro',
    ];

    protected $fillable = self::COLUMNS;

    protected $casts = [
        'Id' => 'integer',
        'Qty' => 'float',
        'Porcentaje' => 'float',
        'Luchaje' => 'integer',
        'FechaRegistro' => 'date',
        'HoraRegistro' => 'string',
        'UsuarioRegistro' => 'string',
    ];
}
