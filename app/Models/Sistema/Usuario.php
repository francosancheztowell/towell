<?php

namespace App\Models\Sistema;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Hash;

/**
 * @property int $idusuario
 * @property string|null $numero_empleado
 * @property string $nombre
 * @property string $contrasenia
 * @property string|null $area
 * @property string|null $telefono
 * @property string|null $turno
 * @property string|null $foto
 * @property string|null $puesto
 * @property string|null $correo
 * @property string|null $remember_token
 */
class Usuario extends Authenticatable
{
    protected $connection = 'sqlsrv'; // Usar la conexión correcta

    protected $table = 'dbo.SYSUsuario'; // Usar la tabla correcta con esquema

    protected $primaryKey = 'idusuario'; // Cambiar a la clave primaria correcta

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = true; // Si tu tabla no tiene timestamps, pon esto

    protected $fillable = [
        'idusuario',
        'numero_empleado',
        'nombre',
        'contrasenia',
        'area',
        'telefono',
        'turno',
        'foto',
        'puesto',
        'correo',
        'remember_token',
    ];

    protected $hidden = [
        'contrasenia',
        'remember_token',
    ];

    // Método para obtener la contraseña sin procesar
    public function getAuthPassword()
    {
        return $this->contrasenia;
    }

    // Método para obtener el nombre del campo de contraseña
    public function getAuthPasswordName()
    {
        return 'contrasenia';
    }

    // Mutador para encriptar la contraseña automáticamente cuando se establece
    public function setContraseniaAttribute($value)
    {
        // Solo hashear si no está ya hasheado
        if (! empty($value) && ! str_starts_with($value, '$2y$')) {
            $this->attributes['contrasenia'] = Hash::make($value);
        } else {
            $this->attributes['contrasenia'] = $value;
        }
    }

    // Para que {usuario} en la ruta resuelva por idusuario
    public function getRouteKeyName()
    {
        return 'idusuario';
    }

    /**
     * Relación con permisos de usuario
     */
    public function permisos()
    {
        return $this->hasMany(SYSUsuariosRoles::class, 'idusuario', 'idusuario');
    }

    /**
     * Obtener módulos con permisos del usuario
     */
    public function modulosConPermisos()
    {
        return $this->hasManyThrough(
            SYSRoles::class,
            SYSUsuariosRoles::class,
            'idusuario',
            'idrol',
            'idusuario',
            'idrol'
        )->where('SYSUsuariosRoles.acceso', true);
    }

    /**
     * Scope para usuarios activos
     */
    public function scopeActivos($query)
    {
        return $query->whereNotNull('numero_empleado');
    }

    /**
     * Scope para buscar por área
     */
    public function scopePorArea($query, $area)
    {
        return $query->where('area', $area);
    }
}
