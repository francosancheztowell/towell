<?php

namespace App\Models\Mantenimiento;

use Illuminate\Database\Eloquent\Model;

class ManFallasParos extends Model
{
    protected $connection = 'sqlsrv';

    protected $table = 'dbo.ManFallasParos';

    protected $primaryKey = 'Id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'Folio',
        'Estatus',
        'Fecha',
        'Hora',
        'Depto',
        'MaquinaId',
        'TipoFallaId',
        'Falla',
        'Descripcion',
        'HoraFin',
        'CveEmpl',
        'NomEmpl',
        'Turno',
        'CveAtendio',
        'NomAtendio',
        'TurnoAtendio',
        'Obs',
        'OrdenTrabajo',
        'Enviado',
        'ObsCierre',
        'Calidad',
        'FechaFin',
    ];

    protected $casts = [
        'Id' => 'integer',
        'Fecha' => 'date',
        'Turno' => 'integer',
        'TurnoAtendio' => 'integer',
        'Enviado' => 'boolean',
        'Calidad' => 'integer',
        'FechaFin' => 'date',
    ];

    public function tipoFalla()
    {
        return $this->belongsTo(CatTipoFalla::class, 'TipoFallaId', 'TipoFallaId');
    }

    /**
     * ¿Ya hay un paro Activo en esta máquina con este tipo de falla?
     *
     * Única definición de la regla: la usan tanto el alta manual de Mantenimiento
     * como el paro automático de Crudo.
     *
     * Comparación directa (SARGable) para que SQL Server pueda usar índices: la
     * colación es case-insensitive y en NVARCHAR ignora espacios finales.
     */
    public static function hayActivoEnMaquina(?string $maquinaId, ?string $tipoFallaId): bool
    {
        // lockForUpdate (WITH (rowlock,updlock,holdlock) en SQL Server) bloquea el
        // rango consultado hasta que la transacción llamante termine: dos POST
        // concurrentes para el mismo telar/tipo ya no pueden pasar ambos el check
        // y crear dos paros activos. Sin transacción abierta el hint no tiene
        // efecto distinto a una lectura normal.
        return static::query()
            ->where('Estatus', 'Activo')
            ->where('MaquinaId', trim((string) $maquinaId))
            ->where('TipoFallaId', trim((string) $tipoFallaId))
            ->lockForUpdate()
            ->exists();
    }
}
