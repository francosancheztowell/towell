<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReqEficienciaStd extends Model
{
    use HasFactory;

    /**
     * Nombre de la tabla asociada al modelo
     */
    protected $table = 'dbo.ReqEficienciaStd';

    /**
     * Clave primaria de la tabla
     */
    protected $primaryKey = 'id';

    /**
     * Campos que se pueden asignar masivamente
     */
    protected $fillable = [
        'SalonTejidoId',  // Salón
        'NoTelarId',      // Telar (Nombre del telar)
        'FibraId',        // Tipo de Hilo
        'Eficiencia',     // Eficiencia (Real/Float)
        'Densidad'        // Densidad
    ];

    /**
     * Campos que deben ser tratados como fechas
     */
    protected $dates = [];

    /**
     * Indica si el modelo debe usar timestamps automáticos
     */
    public $timestamps = false;

    /**
     * Obtener el nombre de la clave para route model binding
     */
    public function getRouteKeyName()
    {
        return 'id';
    }

    /**
     * Casts para tipos de datos
     */
    protected $casts = [
        'Eficiencia' => 'float'
    ];

    /**
     * Obtener todas las eficiencias ordenadas por salón y telar
     */
    public static function obtenerTodos()
    {
        return self::orderBy('SalonTejidoId')
                  ->orderBy('NoTelarId')
                  ->orderBy('FibraId')
                  ->get();
    }

    /**
     * Buscar eficiencias por criterios específicos
     */
    public static function buscar($salon = null, $telar = null, $fibra = null, $densidad = null)
    {
        $query = self::query();

        if ($salon) {
            $query->where('SalonTejidoId', 'like', "%{$salon}%");
        }

        if ($telar) {
            $query->where('NoTelarId', 'like', "%{$telar}%");
        }

        if ($fibra) {
            $query->where('FibraId', 'like', "%{$fibra}%");
        }

        if ($densidad) {
            $query->where('Densidad', 'like', "%{$densidad}%");
        }

        return $query->orderBy('SalonTejidoId')
                    ->orderBy('NoTelarId')
                    ->orderBy('FibraId')
                    ->get();
    }

    /**
     * Verificar si existe una eficiencia con el mismo telar y fibra
     */
    public static function existeEficiencia($telar, $fibra)
    {
        return self::where('NoTelarId', $telar)
                  ->where('FibraId', $fibra)
                  ->exists();
    }

    /**
     * Crear una nueva eficiencia desde datos de Excel
     */
    public static function crearDesdeExcel($datos)
    {
        return self::create([
            'SalonTejidoId' => $datos['salon'] ?? null,
            'NoTelarId' => $datos['telar'] ?? null,
            'FibraId' => $datos['fibra'] ?? null,
            'Eficiencia' => $datos['eficiencia'] ?? null,
            'Densidad' => $datos['densidad'] ?? null
        ]);
    }

    /**
     * Actualizar eficiencia existente desde datos de Excel
     */
    public function actualizarDesdeExcel($datos)
    {
        return $this->update([
            'SalonTejidoId' => $datos['salon'] ?? $this->SalonTejidoId,
            'NoTelarId' => $datos['telar'] ?? $this->NoTelarId,
            'FibraId' => $datos['fibra'] ?? $this->FibraId,
            'Eficiencia' => $datos['eficiencia'] ?? $this->Eficiencia,
            'Densidad' => $datos['densidad'] ?? $this->Densidad
        ]);
    }

    /**
     * Accessor para obtener el salón formateado
     */
    public function getSalonAttribute()
    {
        return $this->SalonTejidoId;
    }

    /**
     * Accessor para obtener el telar formateado
     */
    public function getTelarAttribute()
    {
        return $this->NoTelarId;
    }

    /**
     * Accessor para obtener la fibra formateada
     */
    public function getFibraAttribute()
    {
        return $this->FibraId;
    }
}

