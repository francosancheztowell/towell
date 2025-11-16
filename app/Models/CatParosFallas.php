<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatParosFallas extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'CatParosFallas';

    protected $primaryKey = 'Id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'Id',
        'TipoFallaId',
        'Departamento',
    ];

    protected $casts = [
        'Id' => 'integer',
    ];

    /**
     * Relación con CatTipoFalla
     */
    public function tipoFalla()
    {
        return $this->belongsTo(CatTipoFalla::class, 'TipoFallaId', 'TipoFallaId');
    }
}


