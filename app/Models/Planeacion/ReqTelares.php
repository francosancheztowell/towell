<?php

namespace App\Models\Planeacion;

use Illuminate\Database\Eloquent\Model;

class ReqTelares extends Model
{
    /**
     * Nombre de la tabla en la base de datos
     */
    protected $table = 'dbo.ReqTelares';

    /**
     * Clave primaria de la tabla
     */
    protected $primaryKey = 'Id';

    /**
     * Campos que se pueden asignar masivamente
     */
    protected $fillable = [
        'SalonTejidoId',  // Mapeo de columna "Salon"
        'NoTelarId',      // Mapeo de columna "Telar"
        'Nombre',         // Mapeo de columna "Nombre"
        'Grupo'           // Mapeo de columna "Grupo"
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
        return 'Id';
    }

    /**
     * Obtener todos los telares ordenados por salón y telar
     */
    public static function obtenerTodos()
    {
        return self::orderBy('SalonTejidoId')
                  ->orderBy('NoTelarId')
                  ->get();
    }

    /**
     * Buscar telares por criterios específicos
     */
    public static function buscar($salon = null, $telar = null, $nombre = null, $grupo = null)
    {
        $query = self::query();

        if ($salon) {
            $query->where('SalonTejidoId', 'like', "%{$salon}%");
        }

        if ($telar) {
            $query->where('NoTelarId', 'like', "%{$telar}%");
        }

        if ($nombre) {
            $query->where('Nombre', 'like', "%{$nombre}%");
        }

        if ($grupo) {
            $query->where('Grupo', 'like', "%{$grupo}%");
        }

        return $query->orderBy('SalonTejidoId')
                    ->orderBy('NoTelarId')
                    ->get();
    }

    /**
     * Verificar si existe un telar con el mismo salón y número
     */
    public static function existeTelar($salon, $telar)
    {
        return self::where('SalonTejidoId', $salon)
                  ->where('NoTelarId', $telar)
                  ->exists();
    }

    /**
     * Crear un nuevo telar desde datos de Excel
     */
    public static function crearDesdeExcel($datos)
    {
        return self::create([
            'SalonTejidoId' => $datos['salon'] ?? null,
            'NoTelarId' => $datos['telar'] ?? null,
            'Nombre' => $datos['nombre'] ?? null,
            'Grupo' => $datos['grupo'] ?? null
        ]);
    }

    /**
     * Actualizar telar existente desde datos de Excel
     */
    public function actualizarDesdeExcel($datos)
    {
        return $this->update([
            'SalonTejidoId' => $datos['salon'] ?? $this->SalonTejidoId,
            'NoTelarId' => $datos['telar'] ?? $this->NoTelarId,
            'Nombre' => $datos['nombre'] ?? $this->Nombre,
            'Grupo' => $datos['grupo'] ?? $this->Grupo
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
}
