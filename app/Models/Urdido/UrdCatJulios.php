<?php

namespace App\Models\Urdido;

use Illuminate\Database\Eloquent\Model;

/**
 * @property float|null $Tara
 */
class UrdCatJulios extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'UrdCatJulios';

    protected $primaryKey = 'Id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'NoJulio',
        'Tara',
        'Departamento',
    ];

    protected $casts = [
        'Id' => 'integer',
        'Tara' => 'float',
    ];
}



