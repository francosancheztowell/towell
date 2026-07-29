<?php

namespace App\Models\Mecanicos;

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
        'TurnoRecibe',
        'CveOperador',
        'NomOperador',
        'Estatus',
        'HoraInicio',
        'HoraFin',
    ];

    protected $casts = [
        'Fecha' => 'date',
        'TurnoRecibe' => 'integer',
    ];

    public function lineas(): HasMany
    {
        return $this->hasMany(MecVerificaMaquinaLineModel::class, 'Folio', 'Folio');
    }
}
