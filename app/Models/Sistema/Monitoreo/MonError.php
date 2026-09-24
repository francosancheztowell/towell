<?php

namespace App\Models\Sistema\Monitoreo;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * dbo.SYSMonError: errores agrupados por huella. Las ocurrencias viven en SYSMonErrorEvento.
 *
 * @property int $Id
 * @property string $Huella
 * @property string $Origen
 * @property string $Clase
 * @property string $Mensaje
 * @property string|null $Archivo
 * @property int|null $Linea
 * @property string|null $Ruta
 * @property string $Estado
 * @property int $Ocurrencias
 * @property \Illuminate\Support\Carbon $PrimeraVez
 * @property \Illuminate\Support\Carbon $UltimaVez
 * @property int|null $ResueltoPor
 * @property \Illuminate\Support\Carbon|null $ResueltoEn
 * @property string|null $Nota
 * @property \Illuminate\Support\Carbon|null $AlertadoEn
 */
class MonError extends Model
{
    use MassPrunable;

    public const ESTADOS = ['nuevo', 'visto', 'resuelto', 'ignorado'];

    protected $connection = 'sqlsrv';

    protected $table = 'SYSMonError';

    protected $primaryKey = 'Id';

    public $timestamps = false;

    protected $fillable = [
        'Huella', 'Origen', 'Clase', 'Mensaje', 'Archivo', 'Linea', 'Ruta', 'Estado', 'Ocurrencias',
        'PrimeraVez', 'UltimaVez', 'ResueltoPor', 'ResueltoEn', 'Nota', 'AlertadoEn',
    ];

    protected $casts = [
        'Id' => 'integer',
        'Linea' => 'integer',
        'Ocurrencias' => 'integer',
        'PrimeraVez' => 'datetime',
        'UltimaVez' => 'datetime',
        'ResueltoPor' => 'integer',
        'ResueltoEn' => 'datetime',
        'AlertadoEn' => 'datetime',
    ];

    public function eventos(): HasMany
    {
        return $this->hasMany(MonErrorEvento::class, 'ErrorId', 'Id');
    }

    /** Resueltos o ignorados que no han vuelto a ocurrir en la ventana de retención. */
    public function prunable(): Builder
    {
        return static::whereIn('Estado', ['resuelto', 'ignorado'])
            ->where('UltimaVez', '<', now()->subDays((int) config('monitoreo.retencion.error_resuelto_dias', 180)));
    }
}
