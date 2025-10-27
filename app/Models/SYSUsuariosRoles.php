<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SYSUsuariosRoles extends Model
{
    protected $table = 'SYSUsuariosRoles';

    protected $connection = 'sqlsrv'; // Especificar conexión SQL Server

    protected $fillable = [
        'idusuario',
        'idrol',
        'acceso',
        'crear',
        'modificar',
        'eliminar',
        'registrar',
        'assigned_at'
    ];

    protected $casts = [
        'acceso' => 'integer',
        'crear' => 'integer',
        'modificar' => 'integer',
        'eliminar' => 'integer',
        'registrar' => 'integer',
        'assigned_at' => 'datetime'
    ];

    public $timestamps = false;

    /**
     * Relación con SYSRoles
     */
    public function rol()
    {
        return $this->belongsTo(SYSRoles::class, 'idrol', 'idrol');
    }

    /**
     * Obtener módulos permitidos para un usuario específico
     */
    public static function getModulosPermitidos($numeroEmpleado)
    {
        return self::join('SYSRoles as r', 'SYSUsuariosRoles.idrol', '=', 'r.idrol')
                   ->join('SYSUsuario as u', 'SYSUsuariosRoles.idusuario', '=', 'u.idusuario')
                   ->where('u.numero_empleado', $numeroEmpleado)
                   ->where('SYSUsuariosRoles.acceso', true)
                   ->select('r.*')
                   ->orderBy('r.orden')
                   ->get();
    }
}













