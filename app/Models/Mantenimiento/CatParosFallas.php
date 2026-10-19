<?php

namespace App\Models\Mantenimiento;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $Id
 * @property string|null $TipoFallaId
 * @property string|null $Departamento
 * @property string|null $Falla
 * @property string|null $Descripcion
 * @property bool|null $Necesario
 */
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
        'Necesario',
    ];

    protected $casts = [
        'Id' => 'integer',
        'Necesario' => 'boolean',
    ];

    /**
     * Relación con CatTipoFalla
     */
    public function tipoFalla()
    {
        return $this->belongsTo(CatTipoFalla::class, 'TipoFallaId', 'TipoFallaId');
    }
}
