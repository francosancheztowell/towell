<?php

namespace App\Models\Sistema;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo para dbo.SYSMensajes
 * Campos: Id, DepartamentoId, Telefono, Token, Activo, FechaRegistro, Nombre,
 * Desarrolladores, DesarrolladoresPrue, NotificarAtadoJulio, CorteSEF, MarcasFinales, ReporteElectrico,
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
        'UsuarioId',
        'Telefono',
        'Token',
        'Activo',
        'Nombre',
        'Desarrolladores',
        'DesarrolladoresPrue',
        'NotificarAtadoJulio',
        'CorteSEF',
        'MarcasFinales',
        'ReporteElectrico',
        'ReporteMecanico',
        'ReporteTiempoMuerto',
        'Atadores',
        'InvTrama',
        'UrdidoCalidad',
        'Calidad',
        'Andon',
    ];

    protected $casts = [
        'Id' => 'integer',
        'DepartamentoId' => 'integer',
        'UsuarioId' => 'integer',
        'Telefono' => 'string',
        'Token' => 'string',
        'Activo' => 'boolean',
        'FechaRegistro' => 'datetime',
        'Nombre' => 'string',
        'Desarrolladores' => 'boolean',
        'DesarrolladoresPrue' => 'boolean',
        'NotificarAtadoJulio' => 'boolean',
        'CorteSEF' => 'boolean',
        'MarcasFinales' => 'boolean',
        'ReporteElectrico' => 'boolean',
        'ReporteMecanico' => 'boolean',
        'ReporteTiempoMuerto' => 'boolean',
        'Atadores' => 'boolean',
        'InvTrama' => 'boolean',
        'UrdidoCalidad' => 'boolean',
        'Calidad' => 'boolean',
        'Andon' => 'boolean',
    ];

    public function departamento()
    {
        return $this->belongsTo(SysDepartamento::class, 'DepartamentoId', 'id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'UsuarioId', 'idusuario');
    }

    /**
     * Columnas de módulo permitidas para notificaciones Telegram (deben existir en la tabla).
     */
    public static function columnasModuloPermitidas(): array
    {
        return [
            'InvTrama',
            'Desarrolladores',
            'DesarrolladoresPrue',
            'NotificarAtadoJulio',
            'CorteSEF',
            'MarcasFinales',
            'ReporteElectrico',
            'ReporteMecanico',
            'ReporteTiempoMuerto',
            'Atadores',
            'UrdidoCalidad',
            'Calidad',
            'Andon',
        ];
    }

    /**
     * Correos de los suscriptores de un reporte: misma fila que Telegram, pero el
     * correo se lee de SYSUsuario (UsuarioId) para no duplicar el dato.
     *
     * @return list<string>
     */
    public static function getCorreosPorModulo(string $columna): array
    {
        if (! in_array($columna, self::columnasModuloPermitidas(), true)) {
            return [];
        }

        return self::soloCorreosValidos(
            self::query()
                ->where('Activo', true)
                ->where($columna, true)
                ->join('dbo.SYSUsuario as u', 'u.idusuario', '=', 'dbo.SYSMensajes.UsuarioId')
                ->pluck('u.correo')
                ->all()
        );
    }

    /**
     * @param  array<int, mixed>  $correos
     * @return list<string>
     */
    public static function soloCorreosValidos(array $correos): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $correo): string => trim((string) $correo), $correos),
            static fn (string $correo): bool => filter_var($correo, FILTER_VALIDATE_EMAIL) !== false,
        )));
    }

    /**
     * Obtiene los chat IDs (Token) de registros que tienen el módulo activo.
     * Solo registros con Activo = 1 y la columna $columna = 1.
     *
     * @param  string  $columna  Nombre de la columna (ej: 'InvTrama')
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
