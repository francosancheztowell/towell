<?php

namespace App\Models\Mecanicos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $Id
 * @property string $Folio
 * @property string|null $CveOperador
 * @property string|null $NomOperador
 * @property bool|null $Ajusto
 * @property bool|null $Reparo
 * @property bool|null $Cambio
 * @property bool|null $Lubrico
 * @property bool|null $FaltaRefacc
 * @property string|null $HoraInicial
 * @property string|null $HoraFinal
 * @property int|null $Calificacion
 */
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
        'Fecha',
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
        'Fecha' => 'date',
    ];

    public function ordenTrabajo(): BelongsTo
    {
        return $this->belongsTo(MecOrdenTrabajoModel::class, 'Folio', 'Folio');
    }
}
