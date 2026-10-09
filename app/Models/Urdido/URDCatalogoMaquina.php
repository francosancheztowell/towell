<?php

namespace App\Models\Urdido;

use App\Livewire\Mecanicos\VerificaMaquina\Show;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Catálogo único de máquinas (Urdido, Engomado, telares de Tejido y demás áreas).
 * Los telares son las filas cuyo Departamento es un salón de Tejido; reemplaza la
 * lectura de ReqTelares e InvSecuenciaTelares (Secuencia = orden del inventario de telas).
 *
 * @property int $Id
 * @property string $MaquinaId
 * @property string|null $Nombre
 * @property string|null $Departamento
 * @property int|null $Secuencia
 */
class URDCatalogoMaquina extends Model
{
    /** Departamentos que son salones de telares. Itema y Smith son un solo salón. */
    public const DEPARTAMENTOS_TELARES = ['Jacquard', 'Itema', 'Smith', 'Karl Mayer'];

    protected $connection = 'sqlsrv';

    protected $table = 'URDCatalogoMaquinas';

    // Clave primaria string (no autoincremental); Id es IDENTITY con índice único.
    protected $primaryKey = 'MaquinaId';

    public $incrementing = false;

    protected $keyType = 'string';

    // La tabla no tiene created_at / updated_at
    public $timestamps = false;

    protected $fillable = [
        'MaquinaId',
        'Nombre',
        'Departamento',
        'Codificacion',
        'Secuencia',
    ];

    protected $casts = [
        'Id' => 'integer',
        'Secuencia' => 'integer',
    ];

    /** @param  Builder<self>  $query */
    public function scopeTelares(Builder $query): void
    {
        $query->whereIn('Departamento', self::DEPARTAMENTOS_TELARES);
    }

    /** Salón como lo guardaba ReqTelares.SalonTejidoId (Jacquard / Smith / KM). */
    public static function salonDe(?string $departamento): string
    {
        $departamento = trim((string) $departamento);

        return match (strtoupper($departamento)) {
            'ITEMA', 'SMITH' => 'Smith',
            'KARL MAYER' => 'KM',
            default => $departamento,
        };
    }

    /** Departamento de telares para lo que se captura como salón (JACQUARD, SMIT, KM…); null si no es un salón de telares. */
    public static function departamentoDeSalon(?string $salon): ?string
    {
        return match (strtoupper(preg_replace('/\s+/', ' ', trim((string) $salon)) ?? '')) {
            'JACQUARD', 'JAC' => 'Jacquard',
            'ITEMA' => 'Itema',
            'SMITH', 'SMIT' => 'Smith',
            'KARL MAYER', 'KARLMAYER', 'KM' => 'Karl Mayer',
            default => null,
        };
    }

    /** Codificación con el patrón de los telares existentes: TOW-TEL201-TEJI, TOW-KM401-TEJI. */
    public static function codificacionTelar(string $departamento, string $telar): string
    {
        return 'TOW-'.($departamento === 'Karl Mayer' ? 'KM' : 'TEL').$telar.'-TEJI';
    }

    public function salon(): string
    {
        return self::salonDe($this->Departamento);
    }

    /** Nombre como lo guardaba ReqTelares.Nombre: "JAC 201", "Smith 305", "KM 401". */
    public static function nombreTelarDe(string $salon, string $telar): string
    {
        $up = strtoupper(trim($salon));
        $prefijo = str_contains($up, 'JACQUARD') ? 'JAC' : (str_contains($up, 'SMITH') ? 'Smith' : substr($up, 0, 3));

        return trim($prefijo.' '.trim($telar));
    }

    public function nombreTelar(): string
    {
        return self::nombreTelarDe($this->salon(), $this->MaquinaId);
    }

    /** Tipo de telar como lo guardaba InvSecuenciaTelares.TipoTelar: JACQUARD / ITEMA / SMIT / KARL MAYER. */
    public function tipoTelar(): string
    {
        $tipo = strtoupper(trim((string) $this->Departamento));

        return $tipo === 'SMITH' ? 'SMIT' : $tipo;
    }

    /** Cualquier alta/baja/cambio invalida el catálogo de telares cacheado en Estado de Máquina. */
    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(Show::CACHE_KEY_TELARES));
        static::deleted(fn () => Cache::forget(Show::CACHE_KEY_TELARES));
    }
}
