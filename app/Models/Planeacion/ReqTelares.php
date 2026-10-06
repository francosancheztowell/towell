<?php

namespace App\Models\Planeacion;

use App\Livewire\Mecanicos\VerificaMaquina\Show;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

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
        'Grupo',           // Mapeo de columna "Grupo"
        'VelocidadSTD',    // Mapeo de columna "VelocidadSTD"
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

    /** Nombre por defecto: "JAC 201", "Smith 305" o las tres primeras letras del salón. */
    public static function nombreSugerido(?string $salon, ?string $telar): string
    {
        $up = strtoupper(trim((string) $salon));
        $prefijo = str_contains($up, 'JACQUARD') ? 'JAC' : (str_contains($up, 'SMITH') ? 'Smith' : strtoupper(substr($up, 0, 3)));

        return trim($prefijo.' '.$telar);
    }

    /** Telar por su llave de ruta "Salon_Telar" (el salón puede traer guiones bajos). */
    public static function porLlave(string $llave): ?self
    {
        $pos = strrpos($llave, '_');

        return $pos === false ? null : self::where('SalonTejidoId', substr($llave, 0, $pos))
            ->where('NoTelarId', substr($llave, $pos + 1))
            ->first();
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
     * Cualquier alta/baja/cambio (store/update/destroy, import Excel, o
     * cualquier otro módulo que escriba este catálogo compartido) invalida el
     * catálogo cacheado en Estado de Máquina (Show::telaresCatalogo).
     */
    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(Show::CACHE_KEY_TELARES));
        static::deleted(fn () => Cache::forget(Show::CACHE_KEY_TELARES));
    }
}
