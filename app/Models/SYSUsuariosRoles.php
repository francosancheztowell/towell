<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SYSUsuariosRoles extends Model
{
    protected $table = 'SYSUsuariosRoles';

    protected $connection = 'sqlsrv'; // Especificar conexión SQL Server

    // La tabla no tiene una columna 'id' como PK, usa clave compuesta (idusuario, idrol)
    protected $primaryKey = null;
    public $incrementing = false;

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
     * Relación con Usuario
     */
    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'idusuario', 'idusuario');
    }

    /**
     * Scope para permisos con acceso
     */
    public function scopeConAcceso($query)
    {
        return $query->where('acceso', true);
    }

    /**
     * Scope para un usuario específico
     */
    public function scopePorUsuario($query, $idusuario)
    {
        return $query->where('idusuario', $idusuario);
    }

    /**
     * Scope para un rol específico
     */
    public function scopePorRol($query, $idrol)
    {
        return $query->where('idrol', $idrol);
    }
}













