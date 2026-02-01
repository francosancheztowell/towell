<?php

namespace App\Models\Sistema;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo para dbo.SYSMensajes
 * Campos: Id, DepartamentoId, Telefono, Token, Activo, FechaRegistro, Nombre,
 * Desarrolladores, NotificarAtadoJulio, CorteSEF, MarcasFinales, ReporteElectrico,
 * ReporteMecanico, ReporteTiempoMuerto, Atadores
 */
class SYSMensaje extends Model
{
    protected $connection = 'sqlsrv';

    protected $table = 'dbo.SYSMensajes';

    protected $primaryKey = 'Id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'DepartamentoId',
        'Telefono',
        'Token',
        'Activo',
        'Nombre',
        'Desarrolladores',
        'NotificarAtadoJulio',
        'CorteSEF',
        'MarcasFinales',
        'ReporteElectrico',
        'ReporteMecanico',
        'ReporteTiempoMuerto',
        'Atadores',
        'InvTrama',
    ];

    protected $casts = [
        'Id' => 'integer',
        'DepartamentoId' => 'integer',
        'Telefono' => 'string',
        'Token' => 'string',
        'Activo' => 'boolean',
        'FechaRegistro' => 'datetime',
        'Nombre' => 'string',
        'Desarrolladores' => 'boolean',
        'NotificarAtadoJulio' => 'boolean',
        'CorteSEF' => 'boolean',
        'MarcasFinales' => 'boolean',
        'ReporteElectrico' => 'boolean',
        'ReporteMecanico' => 'boolean',
        'ReporteTiempoMuerto' => 'boolean',
        'Atadores' => 'boolean',
        'InvTrama' => 'boolean',
    ];

    public function departamento()
    {
        return $this->belongsTo(SysDepartamento::class, 'DepartamentoId', 'id');
    }

    /**
     * Columnas de módulo permitidas para notificaciones Telegram (deben existir en la tabla).
     */
    public static function columnasModuloPermitidas(): array
    {
        return [
            'InvTrama',
            'Desarrolladores',
            'NotificarAtadoJulio',
            'CorteSEF',
            'MarcasFinales',
            'ReporteElectrico',
            'ReporteMecanico',
            'ReporteTiempoMuerto',
            'Atadores',
        ];
    }

    /**
     * Obtiene los chat IDs (Token) de registros que tienen el módulo activo.
     * Solo registros con Activo = 1 y la columna $columna = 1.
     *
     * @param string $columna Nombre de la columna (ej: 'InvTrama')
     * @return array Lista de Token (chat IDs) únicos
     */
    public static function getChatIdsPorModulo(string $columna): array
    {
        if (! in_array($columna, self::columnasModuloPermitidas(), true)) {
            return [];
        }

        return self::query()
            ->where('Activo', true)
            ->where($columna, true)
            ->whereNotNull('Token')
            ->where('Token', '!=', '')
            ->pluck('Token')
            ->unique()
            ->values()
            ->all();
    }
}
