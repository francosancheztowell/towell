<?php

namespace App\Models\Tejedores;

use App\Models\Atadores\AtaMontadoTelasModel;
use App\Models\Engomado\CatDefectosUrdEng;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Defecto encontrado por el oficial en un julio montado. Varios por julio.
 *
 * @property int $Id
 * @property int $AtaMontadoTelasId
 * @property int $DefectoId
 * @property float $Penalizacion
 * @property Carbon $Fecha
 * @property string $NoEmpleado
 * @property string|null $NomEmpleado
 */
class TejCalificacionJulioMontados extends Model
{
    protected $connection = 'sqlsrv';

    protected $table = 'TejCalificacionJulioMontados';

    protected $primaryKey = 'Id';

    public $timestamps = false;

    protected $fillable = [
        'AtaMontadoTelasId',
        'DefectoId',
        'Penalizacion',
        'Fecha',
        'NoEmpleado',
        'NomEmpleado',
    ];

    protected $casts = [
        'Penalizacion' => 'float',
        'Fecha' => 'datetime',
    ];

    /** @return BelongsTo<AtaMontadoTelasModel, $this> */
    public function montado(): BelongsTo
    {
        return $this->belongsTo(AtaMontadoTelasModel::class, 'AtaMontadoTelasId', 'Id');
    }

    /** @return BelongsTo<CatDefectosUrdEng, $this> */
    public function defecto(): BelongsTo
    {
        return $this->belongsTo(CatDefectosUrdEng::class, 'DefectoId', 'Id');
    }
}
