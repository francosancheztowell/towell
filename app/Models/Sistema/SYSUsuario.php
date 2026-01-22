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
        'departamento',
        'turno',
        'telefono',
        'correo',
        'contrasenia',
        'enviarMensaje',
        'foto',
        'puesto',
        'Productivo'
    ];

    protected $casts = [
        'enviarMensaje' => 'boolean',
        'Productivo' => 'integer'
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














