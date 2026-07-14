<?php

declare(strict_types=1);

namespace App\Models\Planeacion\Catalogos;

use Illuminate\Database\Eloquent\Model;

final class CatMatrizCalibres extends Model
{
    protected $connection = 'sqlsrv';

    protected $table = 'CatMatrizCalibres';

    protected $primaryKey = 'Id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'Tipo',
        'Calibre',
        'FibraId',
        'Cuenta',
        'ItemId',
        'ConfigId',
        'InventSizeId',
        'InventColorId',
    ];

    protected $casts = [
        'Id' => 'integer',
        'Calibre' => 'float',
        'Tipo' => 'string',
        'FibraId' => 'string',
        'Cuenta' => 'string',
        'ItemId' => 'string',
        'ConfigId' => 'string',
        'InventSizeId' => 'string',
        'InventColorId' => 'string',
    ];
}
