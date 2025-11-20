<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SYSRoles extends Model
{
    protected $table = 'SYSRoles';

    protected $connection = 'sqlsrv'; // Especificar conexión SQL Server

    // Habilitar timestamps si existen en la tabla
    public $timestamps = true;

    protected $primaryKey = 'idrol';

    /**
     * Get the route key for the model.
     *
     * @return string
     */
    public function getRouteKeyName()
    {
        return 'idrol';
    }

    protected $fillable = [
        'orden',
        'modulo',
        'acceso',
        'crear',
        'modificar',
        'eliminar',
        'reigstrar', // Mantiene el nombre original
        'imagen',
        'Dependencia',
        'Nivel'
    ];

    protected $casts = [
        'acceso' => 'integer',
        'crear' => 'integer',
        'modificar' => 'integer',
        'eliminar' => 'integer',
        'reigstrar' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    /**
     * Obtener todos los módulos ordenados por el campo orden
     */
    public static function getModulosOrdenados()
    {
        return self::orderBy('orden')->get();
    }

    /**
     * Obtener solo módulos principales (Nivel = 1)
     */
    public static function getModulosPrincipales()
    {
        return self::where('Nivel', 1)
            ->whereNull('Dependencia')
            ->where('acceso', true)
            ->orderBy('orden')
            ->get();
    }

    /**
     * Obtener submódulos de un módulo específico
     * @param string $ordenPadre - Orden del módulo padre (ejemplo: '100', '200', '300')
     */
    public static function getSubmodulos($ordenPadre)
    {
        return self::where('Dependencia', $ordenPadre)
            ->where('Nivel', '>', 1)
            ->where('acceso', true)
            ->orderBy('Nivel')
            ->orderBy('orden')
            ->get();
    }

    /**
     * Obtener módulos con acceso activo
     */
    public static function getModulosConAcceso()
    {
        return self::where('acceso', true)->orderBy('orden')->get();
    }

    /**
     * Verificar si es un módulo principal
     */
    public function esModuloPrincipal()
    {
        return $this->Nivel == 1 && is_null($this->Dependencia);
    }

    /**
     * Verificar si es un submódulo
     */
    public function esSubmodulo()
    {
        return $this->Nivel > 1 && !is_null($this->Dependencia);
    }

    /**
     * Obtener todos los módulos ordenados jerárquicamente por Dependencia y Nivel
     */
    public static function getModulosJerarquicos()
    {
        return self::orderBy('Dependencia', 'ASC')
            ->orderBy('Nivel', 'ASC')
            ->orderBy('orden', 'ASC')
            ->get();
    }

    /**
     * Obtener módulos de un nivel específico
     */
    public static function getModulosPorNivel($nivel)
    {
        return self::where('Nivel', $nivel)
            ->orderBy('orden')
            ->get();
    }

    /**
     * Obtener submódulos de un módulo padre específico
     */
    public static function getSubmodulosPorDependencia($dependencia)
    {
        return self::where('Dependencia', $dependencia)
            ->orderBy('Nivel', 'ASC')
            ->orderBy('orden', 'ASC')
            ->get();
    }

    /**
     * Relación con permisos de usuarios
     */
    public function usuariosRoles()
    {
        return $this->hasMany(SYSUsuariosRoles::class, 'idrol', 'idrol');
    }

    /**
     * Obtener la estructura jerárquica completa
     */
    public static function getEstructuraJerarquica()
    {
        $modulos = self::getModulosJerarquicos();
        $estructura = [];

        foreach ($modulos as $modulo) {
            if ($modulo->Nivel == 1) {
                // Módulo principal
                $estructura[$modulo->orden] = [
                    'modulo' => $modulo,
                    'submodulos' => []
                ];
            } else {
                // Submódulo - encontrar el padre
                $padre = $modulo->Dependencia;
                if (isset($estructura[$padre])) {
                    $estructura[$padre]['submodulos'][] = $modulo;
                }
            }
        }

        return $estructura;
    }

    /**
     * Verificar si es un módulo de nivel 1 (principal)
     */
    public function esModuloNivel1()
    {
        return $this->Nivel == 1;
    }

    /**
     * Obtener el nivel del módulo
     */
    public function getNivel()
    {
        return $this->Nivel;
    }

    /**
     * Obtener la dependencia del módulo
     */
    public function getDependencia()
    {
        return $this->Dependencia;
    }

    /**
     * Relación con permisos de usuario
     */
    public function permisosUsuario()
    {
        return $this->hasMany(SYSUsuariosRoles::class, 'idrol', 'idrol');
    }

    /**
     * Relación con módulo padre
     */
    public function moduloPadre()
    {
        return $this->belongsTo(SYSRoles::class, 'Dependencia', 'orden');
    }

    /**
     * Relación con submódulos
     */
    public function submódulos()
    {
        return $this->hasMany(SYSRoles::class, 'Dependencia', 'orden');
    }

    /**
     * Scope para módulos principales
     */
    public function scopeModulosPrincipales($query)
    {
        return $query->where('Nivel', 1)->whereNull('Dependencia');
    }

    /**
     * Scope para submódulos de un módulo padre
     */
    public function scopeSubmodulosDe($query, $dependencia, $nivel = 2)
    {
        return $query->where('Dependencia', $dependencia)->where('Nivel', $nivel);
    }

    /**
     * Scope para módulos con acceso
     */
    public function scopeConAcceso($query)
    {
        return $query->where('acceso', true);
    }

    /**
     * Obtener módulos con permisos de un usuario
     */
    public function scopeConPermisosUsuario($query, $idusuario)
    {
        return $query->whereHas('permisosUsuario', function($q) use ($idusuario) {
            $q->where('idusuario', $idusuario)->where('acceso', true);
        });
    }
}
