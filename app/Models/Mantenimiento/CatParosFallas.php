<?php

namespace App\Models\Mantenimiento;

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
        'TipoFallaId',
        'Departamento',
        'Falla',
        'Descripcion',
        'Abreviado',
        'Seccion',
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


