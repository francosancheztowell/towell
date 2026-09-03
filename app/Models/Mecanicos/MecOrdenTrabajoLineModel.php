<?php

namespace App\Models\Mecanicos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MecOrdenTrabajoLineModel extends Model
{
    protected $connection = 'sqlsrv';

    protected $table = 'MecOrdenTrabajoLine';

    protected $primaryKey = 'Id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'Folio',
        'CveOperador',
        'NomOperador',
        'Ajusto',
        'Reparo',
        'Cambio',
        'Lubrico',
        'FaltaRefacc',
        'HoraInicial',
        'HoraFinal',
        'TotalMinutos',
        'Calificacion',
        'CveTejedor',
        'NomTejedor',
        'Turno',
        'comentarios',
    ];

    protected $casts = [
        'Id' => 'integer',
        'Ajusto' => 'boolean',
        'Reparo' => 'boolean',
        'Cambio' => 'boolean',
        'Lubrico' => 'boolean',
        'FaltaRefacc' => 'boolean',
        'TotalMinutos' => 'integer',
        'Calificacion' => 'integer',
        'Turno' => 'integer',
    ];

    public function ordenTrabajo(): BelongsTo
    {
        return $this->belongsTo(MecOrdenTrabajoModel::class, 'Folio', 'Folio');
    }
}
