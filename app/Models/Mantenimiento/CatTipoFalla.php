<?php

namespace App\Models\Mantenimiento;

use Illuminate\Database\Eloquent\Model;

class CatTipoFalla extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'CatTipoFalla';

    protected $primaryKey = 'TipoFallaId';
    public $incrementing = false;
    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'TipoFallaId',
    ];

    /**
     * Relación con CatParosFallas
     */
    public function parosFallas()
    {
        return $this->hasMany(CatParosFallas::class, 'TipoFallaId', 'TipoFallaId');
    }

    /**
     * Relación con ManFallasParos
     */
    public function fallasParos()
    {
        return $this->hasMany(ManFallasParos::class, 'TipoFallaId', 'TipoFallaId');
    }
}

