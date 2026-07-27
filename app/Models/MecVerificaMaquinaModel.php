<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MecVerificaMaquinaModel extends Model
{
    protected $connection = 'sqlsrv';

    protected $table = 'MecVerificaMaquinaTable';

    protected $primaryKey = 'Folio';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'Folio',
        'Fecha',
        'TelarId',
        'FolioParo',
        'Falla',
        'FechaParo',
        'HoraParo',
        'Estatus',
        'Orden',
        'Turno',
    ];

    protected $casts = [
        'Fecha' => 'date',
        'FechaParo' => 'date',
        'Turno' => 'integer',
    ];

    public function lineas(): HasMany
    {
        return $this->hasMany(MecVerificaMaquinaLineModel::class, 'Folio', 'Folio');
    }
}
