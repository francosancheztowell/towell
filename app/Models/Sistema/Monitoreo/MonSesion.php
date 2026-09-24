<?php

namespace App\Models\Sistema\Monitoreo;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * dbo.SYSMonSesion: una sesión de trabajo (login o restauración por "recordarme") en un dispositivo.
 */
class MonSesion extends Model
{
    use MassPrunable;

    protected $connection = 'sqlsrv';

    protected $table = 'SYSMonSesion';

    protected $primaryKey = 'Id';

    public $timestamps = false;

    protected $fillable = [
        'DispositivoId', 'UsuarioId', 'Origen', 'Ip', 'Inicio', 'UltimaActividad', 'Fin', 'MotivoFin',
    ];

    protected $casts = [
        'Id' => 'integer',
        'DispositivoId' => 'integer',
        'UsuarioId' => 'integer',
        'Inicio' => 'datetime',
        'UltimaActividad' => 'datetime',
        'Fin' => 'datetime',
    ];

    public function prunable(): Builder
    {
        return static::where('Inicio', '<', now()->subDays((int) config('monitoreo.retencion.sesion_dias', 180)));
    }
}
