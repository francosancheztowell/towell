<?php

namespace App\Models\Planeacion;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $Id
 * @property string|null $SalonTejidoId
 * @property string $NoTelarId
 * @property string $FibraId
 * @property float|null $Eficiencia
 * @property string|null $Densidad
 */
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
    protected $primaryKey = 'Id';

    /**
     * Campos que se pueden asignar masivamente
     */
    protected $fillable = [
        'SalonTejidoId',  // Salón
        'NoTelarId',      // Telar (Nombre del telar)
        'FibraId',        // Tipo de Hilo
        'Eficiencia',     // Eficiencia (Real/Float)
        'Densidad',        // Densidad
    ];

    /**
     * Campos que deben ser tratados como fechas
     */

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
     * Casts para tipos de datos
     */
    protected $casts = [
        'Eficiencia' => 'float',
    ];

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
