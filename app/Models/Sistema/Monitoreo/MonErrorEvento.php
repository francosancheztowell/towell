<?php

namespace App\Models\Sistema\Monitoreo;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * dbo.SYSMonErrorEvento: una ocurrencia concreta de un SYSMonError (máx. N por huella y día).
 */
class MonErrorEvento extends Model
{
    use MassPrunable;

    protected $connection = 'sqlsrv';

    protected $table = 'SYSMonErrorEvento';

    protected $primaryKey = 'Id';

    public $timestamps = false;

    protected $fillable = [
        'ErrorId', 'Fecha', 'UsuarioId', 'DispositivoId', 'SesionId', 'Url', 'Metodo', 'Status',
        'VersionFront', 'Traza',
    ];

    protected $casts = [
        'Id' => 'integer',
        'ErrorId' => 'integer',
        'Fecha' => 'datetime',
        'UsuarioId' => 'integer',
        'DispositivoId' => 'integer',
        'SesionId' => 'integer',
        'Status' => 'integer',
    ];

    public function error(): BelongsTo
    {
        return $this->belongsTo(MonError::class, 'ErrorId', 'Id');
    }

    public function prunable(): Builder
    {
        return static::where('Fecha', '<', now()->subDays((int) config('monitoreo.retencion.evento_dias', 90)));
    }
}
