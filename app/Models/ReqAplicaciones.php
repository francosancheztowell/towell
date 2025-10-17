<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReqAplicaciones extends Model
{
    use HasFactory;

    protected $table = 'dbo.ReqAplicaciones';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'AplicacionId',
        'Nombre',
        'SalonTejidoId',
        'NoTelarId'
    ];

    protected $casts = [
        'id' => 'integer'
    ];

    public function getRouteKeyName()
    {
        return 'id';
    }

    public static function obtenerTodas()
    {
        return self::orderBy('SalonTejidoId')
                  ->orderBy('AplicacionId')
                  ->get();
    }

    public static function buscar($salon = null, $telar = null, $clave = null, $nombre = null)
    {
        $query = self::query();
        if ($salon) $query->where('SalonTejidoId', 'like', "%{$salon}%");
        if ($telar) $query->where('NoTelarId', 'like', "%{$telar}%");
        if ($clave) $query->where('AplicacionId', 'like', "%{$clave}%");
        if ($nombre) $query->where('Nombre', 'like', "%{$nombre}%");
        return $query->orderBy('SalonTejidoId')
                    ->orderBy('AplicacionId')
                    ->get();
    }

    public static function existeAplicacion($clave)
    {
        return self::where('AplicacionId', $clave)->exists();
    }

    public static function crearDesdeExcel($datos)
    {
        return self::create([
            'AplicacionId' => $datos['clave'] ?? null,
            'Nombre' => $datos['nombre'] ?? null,
            'SalonTejidoId' => $datos['salon'] ?? null,
            'NoTelarId' => $datos['telar'] ?? null
        ]);
    }

    public function actualizarDesdeExcel($datos)
    {
        return $this->update([
            'AplicacionId' => $datos['clave'] ?? $this->AplicacionId,
            'Nombre' => $datos['nombre'] ?? $this->Nombre,
            'SalonTejidoId' => $datos['salon'] ?? $this->SalonTejidoId,
            'NoTelarId' => $datos['telar'] ?? $this->NoTelarId
        ]);
    }
}

