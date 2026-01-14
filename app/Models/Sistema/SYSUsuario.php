<?php

namespace App\Models\Sistema;

use Illuminate\Database\Eloquent\Model;

class SYSUsuario extends Model
{
    protected $table = 'SYSUsuario';

    protected $connection = 'sqlsrv'; // Especificar conexión SQL Server

    protected $primaryKey = 'idusuario';

    protected $fillable = [
        'numero_empleado',
        'nombre',
        'area',
        'turno',
        'telefono',
        'correo',
        'contrasenia',
        'enviarMensaje',
        'foto',
        'puesto'
    ];

    protected $casts = [
        'enviarMensaje' => 'boolean'
    ];

    public $timestamps = false;

    /**
     * Relación con SYSUsuariosRoles
     */
    public function roles()
    {
        return $this->hasMany(SYSUsuariosRoles::class, 'idusuario', 'idusuario');
    }
}















