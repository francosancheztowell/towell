<?php

namespace App\Models\Planeacion;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReqVelocidadStd extends Model
{
    use HasFactory;

    protected $table = 'dbo.ReqVelocidadStd';
    protected $primaryKey = 'Id';
    public $timestamps = false;

    protected $fillable = [
        'SalonTejidoId',
        'NoTelarId',
        'FibraId',
        'Velocidad',
        'Densidad'
    ];

    protected $casts = [
        'Velocidad' => 'float'
    ];


    public function getRouteKeyName()
    {
        return 'Id';
    }

    public static function obtenerTodos()
    {
        return self::orderBy('SalonTejidoId')
                  ->orderBy('NoTelarId')
                  ->orderBy('FibraId')
                  ->get();
    }

    public static function buscar($salon = null, $telar = null, $fibra = null, $densidad = null)
    {
        $query = self::query();
        if ($salon) $query->where('SalonTejidoId', 'like', "%{$salon}%");
        if ($telar) $query->where('NoTelarId', 'like', "%{$telar}%");
        if ($fibra) $query->where('FibraId', 'like', "%{$fibra}%");
        if ($densidad) $query->where('Densidad', 'like', "%{$densidad}%");
        return $query->orderBy('SalonTejidoId')
                    ->orderBy('NoTelarId')
                    ->orderBy('FibraId')
                    ->get();
    }

    public static function existeVelocidad($telar, $fibra, $densidad)
    {
        return self::where('NoTelarId', $telar)
                  ->where('FibraId', $fibra)
                  ->where('Densidad', $densidad)
                  ->exists();
    }

    public static function crearDesdeExcel($datos)
    {
        return self::create([
            'SalonTejidoId' => $datos['salon'] ?? null,
            'NoTelarId' => $datos['telar'] ?? null,
            'FibraId' => $datos['fibra'] ?? null,
            'Velocidad' => $datos['velocidad'] ?? null,
            'Densidad' => $datos['densidad'] ?? 'Normal'
        ]);
    }

    public function actualizarDesdeExcel($datos)
    {
        return $this->update([
            'SalonTejidoId' => $datos['salon'] ?? $this->SalonTejidoId,
            'NoTelarId' => $datos['telar'] ?? $this->NoTelarId,
            'FibraId' => $datos['fibra'] ?? $this->FibraId,
            'Velocidad' => $datos['velocidad'] ?? $this->Velocidad,
            'Densidad' => $datos['densidad'] ?? $this->Densidad
        ]);
    }
}
