<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Hash;

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
        if (!empty($value) && !str_starts_with($value, '$2y$')) {
            $this->attributes['contrasenia'] = Hash::make($value);
        } else {
            $this->attributes['contrasenia'] = $value;
        }
    }

    // Relación con telares (si la necesitas)
    public function telares()
    {
        return $this->belongsToMany(CatalagoTelar::class, 'telares_usuario', 'usuario_id', 'telar_id');
    }

    // Para que {usuario} en la ruta resuelva por idusuario
    public function getRouteKeyName()
    {
        return 'idusuario';
    }
}
