<?php

namespace App\Models\Sistema\Monitoreo;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * dbo.SYSMonVista: una pantalla abierta (carga completa o navegación suave), la registra el cliente.
 */
class MonVista extends Model
{
    use MassPrunable;

    protected $connection = 'sqlsrv';

    protected $table = 'SYSMonVista';

    protected $primaryKey = 'Id';

    public $timestamps = false;

    protected $fillable = [
        'Uuid', 'SesionId', 'DispositivoId', 'UsuarioId', 'Ruta', 'Url', 'Tipo', 'Inicio', 'Fin',
        'VisibleMs', 'ServidorMs', 'ConsultasN', 'ConsultasMs', 'TtfbMs', 'DomMs', 'CargaMs', 'Kb',
    ];

    protected $casts = [
        'Id' => 'integer',
        'SesionId' => 'integer',
        'DispositivoId' => 'integer',
        'UsuarioId' => 'integer',
        'Inicio' => 'datetime',
        'Fin' => 'datetime',
        'VisibleMs' => 'integer',
        'ServidorMs' => 'integer',
        'ConsultasN' => 'integer',
        'ConsultasMs' => 'integer',
        'TtfbMs' => 'integer',
        'DomMs' => 'integer',
        'CargaMs' => 'integer',
        'Kb' => 'integer',
    ];

    public function prunable(): Builder
    {
        return static::where('Inicio', '<', now()->subDays((int) config('monitoreo.retencion.vista_dias', 90)));
    }
}
