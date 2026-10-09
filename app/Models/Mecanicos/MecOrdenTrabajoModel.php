<?php

namespace App\Models\Mecanicos;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Cabecera de una orden de trabajo mecánica (MecOrdenTrabajoTable).
 *
 * Flujo: Activo → Terminado (el mecánico finaliza) → Calificado → Autorizado.
 * Cancelado es terminal.
 *
 * @property string $Folio
 * @property Carbon|null $Fecha
 * @property string|null $TelarId
 * @property string|null $FolioParo
 * @property string|null $Estatus
 * @property-read Collection<int, MecOrdenTrabajoLineModel> $lineas
 */
class MecOrdenTrabajoModel extends Model
{
    public const ESTATUS_ACTIVO = 'Activo';

    public const ESTATUS_TERMINADO = 'Terminado';

    public const ESTATUS_CALIFICADO = 'Calificado';

    public const ESTATUS_AUTORIZADO = 'Autorizado';

    public const ESTATUS_CANCELADO = 'Cancelado';

    protected $connection = 'sqlsrv';

    protected $table = 'MecOrdenTrabajoTable';

    protected $primaryKey = 'Folio';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'Folio',
        'Fecha',
        'TelarId',
        'FolioParo',
        'TipoFalla',
        'Falla',
        'Comentarios',
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

    /** @return HasMany<MecOrdenTrabajoLineModel, $this> */
    public function lineas(): HasMany
    {
        return $this->hasMany(MecOrdenTrabajoLineModel::class, 'Folio', 'Folio')->orderBy('Id');
    }

    /** NULL o vacío cuenta como Activo: así quedaron las filas previas al flujo de estatus. */
    public function estatus(): string
    {
        return (string) ($this->Estatus ?: self::ESTATUS_ACTIVO);
    }

    /** El mecánico solo captura (cabecera y renglones) mientras la orden está Activa. */
    public function admiteCaptura(): bool
    {
        return $this->estatus() === self::ESTATUS_ACTIVO;
    }
}
