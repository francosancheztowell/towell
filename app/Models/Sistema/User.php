<?php

namespace App\Models\Sistema;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    // Indicar que la tabla es 'SYSUsuario' en lugar de 'users'
    protected $table = 'SYSUsuario';
    protected $connection = 'sqlsrv';
    protected $primaryKey = 'idusuario';
    public $incrementing = true;
    protected $keyType = 'int';

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

    // No usar casts 'hashed' ya que manejamos contraseñas manualmente
    // para soportar tanto texto plano como hasheadas

    // Usar el numero_empleado como username para autenticación
    public function getAuthIdentifierName()
    {
        return 'numero_empleado';
    }

    // Método para obtener la contraseña sin procesar
    public function getAuthPassword()
    {
        return $this->contrasenia;
    }
}
