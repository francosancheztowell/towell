<?php

namespace App\Models\Sistema\Monitoreo;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * dbo.SYSMonAcceso: bitácora de accesos (login, fallidos, bloqueos, logout, acciones de admin).
 */
class MonAcceso extends Model
{
    use MassPrunable;

    public const TIPOS = [
        'login', 'login_fallido', 'logout', 'logout_remoto', 'recordarme', 'bloqueo', 'authz_denegaria', 'admin_accion',
    ];

    protected $connection = 'sqlsrv';

    protected $table = 'SYSMonAcceso';

    protected $primaryKey = 'Id';

    public $timestamps = false;

    protected $fillable = [
        'Fecha', 'Tipo', 'NumeroEmpleado', 'UsuarioId', 'DispositivoId', 'Ip', 'Motivo', 'ActorId',
    ];

    protected $casts = [
        'Id' => 'integer',
        'Fecha' => 'datetime',
        'UsuarioId' => 'integer',
        'DispositivoId' => 'integer',
        'ActorId' => 'integer',
    ];

    public function prunable(): Builder
    {
        return static::where('Fecha', '<', now()->subDays((int) config('monitoreo.retencion.acceso_dias', 365)));
    }
}
