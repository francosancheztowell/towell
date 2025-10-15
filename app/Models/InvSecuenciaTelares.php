<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvSecuenciaTelares extends Model
{
    protected $table = 'InvSecuenciaTelares';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'telar',
        'tipo',
        'secuencia',
        'activo',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'telar' => 'integer',
        'secuencia' => 'integer',
        'activo' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Obtener la secuencia de telares por tipo
     */
    public static function getSecuenciaByTipo($tipo)
    {
        // Obtener todos los telares ordenados por secuencia
        return self::orderBy('secuencia', 'asc')
            ->pluck('telar')
            ->toArray();
    }

    /**
     * Obtener todos los telares ordenados por secuencia
     */
    public static function getTelares($tipo = null)
    {
        $query = self::orderBy('secuencia', 'asc');

        if ($tipo) {
            $query->where('tipo', strtoupper($tipo));
        }

        return $query->get();
    }
}

