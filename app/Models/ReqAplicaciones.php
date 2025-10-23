<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReqAplicaciones extends Model
{
    use HasFactory;

    /**
     * IMPORTANTE (SQL Server):
     * Si tu tabla está en el esquema dbo y se llama ReqAplicaciones,
     * así es correcto referenciarla con el schema:
     */
    protected $table = 'dbo.ReqAplicaciones';

    /**
     * PK real en BD (tal como la tienes): "id"
     * (entero autoincremental)
     */
    protected $primaryKey = 'Id';
    public $incrementing = true;
    protected $keyType = 'int';

    /**
     * La tabla no maneja created_at / updated_at
     */
    public $timestamps = false;

    /**
     * Asignación masiva permitida
     */
    protected $fillable = [
        'AplicacionId',
        'Nombre',
        'SalonTejidoId',
        'NoTelarId',
    ];

    /**
     * Casts útiles
     */
    protected $casts = [
        'Id'           => 'integer',
        'AplicacionId' => 'string',
        'Nombre'       => 'string',
        'SalonTejidoId'=> 'string',
        'NoTelarId'    => 'string',
    ];

    /**
     * (Opcional) Si usas Route Model Binding por id
     */
    public function getRouteKeyName()
    {
        return 'Id';
    }

    /* ============================================================
     |  SCOPES / HELPERS DE CONSULTA
     |============================================================ */

    /**
     * Trae todas ordenadas por Salón, luego Clave y Telar.
     */
    public static function obtenerTodas()
    {
        return self::orderBy('SalonTejidoId')
            ->orderBy('AplicacionId')
            ->orderBy('NoTelarId')
            ->get();
    }

    /**
     * Búsqueda flexible (LIKE) por salón, telar, clave o nombre.
     */
    public static function buscar($salon = null, $telar = null, $clave = null, $nombre = null)
    {
        $q = self::query();

        if (!is_null($salon) && $salon !== '') {
            $q->where('SalonTejidoId', 'like', "%{$salon}%");
        }
        if (!is_null($telar) && $telar !== '') {
            $q->where('NoTelarId', 'like', "%{$telar}%");
        }
        if (!is_null($clave) && $clave !== '') {
            $q->where('AplicacionId', 'like', "%{$clave}%");
        }
        if (!is_null($nombre) && $nombre !== '') {
            $q->where('Nombre', 'like', "%{$nombre}%");
        }

        return $q->orderBy('SalonTejidoId')
            ->orderBy('AplicacionId')
            ->orderBy('NoTelarId')
            ->get();
    }

    /**
     * Verifica existencia por clave exacta.
     */
    public static function existeAplicacion(string $clave): bool
    {
        return self::where('AplicacionId', $clave)->exists();
    }

    /**
     * (Útil si importas desde Excel) Crea un registro a partir de arreglo estándar.
     */
    public static function crearDesdeExcel(array $datos): self
    {
        return self::create([
            'AplicacionId'  => $datos['clave']  ?? null,
            'Nombre'        => $datos['nombre'] ?? null,
            'SalonTejidoId' => $datos['salon']  ?? null,
            'NoTelarId'     => $datos['telar']  ?? null,
        ]);
    }

    /**
     * (Útil si importas desde Excel) Actualiza un registro existente.
     */
    public function actualizarDesdeExcel(array $datos): bool
    {
        return $this->update([
            'AplicacionId'  => $datos['clave']  ?? $this->AplicacionId,
            'Nombre'        => $datos['nombre'] ?? $this->Nombre,
            'SalonTejidoId' => $datos['salon']  ?? $this->SalonTejidoId,
            'NoTelarId'     => $datos['telar']  ?? $this->NoTelarId,
        ]);
    }

    /* ============================================================
     |  HELPERS EXTRA (opcionales)
     |============================================================ */

    /**
     * Búsqueda por id numérico o por clave AplicacionId.
     * Devuelve null si no encuentra.
     */
    public static function findByIdOrClave($idOrClave): ?self
    {
        if (is_numeric($idOrClave)) {
            $m = self::find((int)$idOrClave);
            if ($m) return $m;
        }
        return self::where('AplicacionId', (string)$idOrClave)->first();
    }

    /**
     * Verifica si ya existe la combinación compuesta
     * (AplicacionId + SalonTejidoId + NoTelarId).
     */
    public static function existeCombinacion(string $clave, string $salon, string $telar, ?int $ignorarId = null): bool
    {
        $q = self::where('AplicacionId', $clave)
            ->where('SalonTejidoId', $salon)
            ->where('NoTelarId', $telar);

        if ($ignorarId) {
            $q->where('Id', '<>', $ignorarId);
        }

        return $q->exists();
    }
}
