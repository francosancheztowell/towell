<?php

declare(strict_types=1);

namespace App\Models\Costos;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Cuota de costos por departamento y mes. Base de CosCuotasReal y CosCuotasSTD, que
 * comparten forma: Depto, Año, Mes y columnas DECIMAL(18,4).
 *
 * Las dos tablas son heaps sin llave primaria ni identity. La llave es natural:
 * (Depto, Año, Mes), y el CRUD valida que no se repita. Eloquent necesita un nombre de
 * llave para guardar y borrar, así que $primaryKey apunta a Depto, pero getKey() y
 * setKeysForSaveQuery() usan las tres columnas: un update o delete nunca toca otra fila.
 *
 * @property string|null $Depto
 * @property int|null $Año
 * @property int|null $Mes
 */
abstract class CosCuota extends Model
{
    protected $connection = 'sqlsrv';

    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'Depto';

    protected $keyType = 'string';

    public const LLAVE = ['Depto', 'Año', 'Mes'];

    /** Nombre de cada mes (1 = Enero) para mostrar; en la tabla se guarda el número. */
    public const MESES = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
        7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];

    /** Costos fijos: el costeo directo no los toma (el absorbente toma todo). */
    public const FIJOS = ['SabGtosFijos', 'SabProrrateoFijo', 'GtosFijos', 'ProrrateoFijo'];

    /** Etiqueta de cada columna numérica que puede tener una cuota (de todas las tablas). */
    public const ETIQUETAS = [
        'Minutos' => 'Minutos',
        'MinParo' => 'Min. paro',
        'SabMO' => 'Sab. MO',
        'SabGtosFijos' => 'Sab. gtos. fijos',
        'SabMOI' => 'Sab. MOI',
        'SabGtosVariable' => 'Sab. gtos. variable',
        'SabProrrateoFijo' => 'Sab. prorrateo fijo',
        'SabProrrateoVariable' => 'Sab. prorrateo variable',
        'SabMaquila' => 'Sab. maquila',
        'MO' => 'MO',
        'MOI' => 'MOI',
        'GtosFijos' => 'Gtos. fijos',
        'GtosVariables' => 'Gtos. variables',
        'ProrrateoFijo' => 'Prorrateo fijo',
        'ProrrateoVariable' => 'Prorrateo variable',
        'Maquila' => 'Maquila',
    ];

    /**
     * Columnas DECIMAL(18,4) de la tabla, en su orden.
     *
     * @return list<string>
     */
    abstract public static function columnasValor(): array;

    public function getFillable(): array
    {
        return [...self::LLAVE, ...static::columnasValor()];
    }

    protected function casts(): array
    {
        return ['Año' => 'integer', 'Mes' => 'integer']
            + array_fill_keys(static::columnasValor(), 'decimal:4');
    }

    /**
     * Llave de la fila para la UI: (Depto, Año, Mes) en base64url, sin comillas ni
     * caracteres que rompan el wire:click de x-tabla.
     */
    public function getKey(): string
    {
        $json = json_encode([$this->Depto, $this->Año, $this->Mes], JSON_UNESCAPED_UNICODE);

        return rtrim(strtr(base64_encode((string) $json), '+/', '-_'), '=');
    }

    /** Filtra por la llave que devolvió getKey(). Una llave mal formada no encuentra nada. */
    public function scopeClave(Builder $query, string $clave): Builder
    {
        $valores = json_decode((string) base64_decode(strtr($clave, '-_', '+/'), true), true);

        if (! is_array($valores) || count($valores) !== 3) {
            return $query->whereRaw('1 = 0'); // llave inválida: ninguna fila
        }

        return self::dondeLlave($query, array_combine(self::LLAVE, $valores));
    }

    /** La cuota con esa llave, si existe. */
    public static function existente(string $depto, int $año, int $mes): ?static
    {
        return static::query()->where('Depto', $depto)->where('Año', $año)->where('Mes', $mes)->first();
    }

    /**
     * Cuántas cuotas tiene cada año (para los filtros por año).
     *
     * @return array<int, int> año => filas
     */
    public static function conteoPorAnio(): array
    {
        // COUNT por grupo: el ORM no tiene un agregado agrupado sin selectRaw.
        return static::query()
            ->whereNotNull('Año')
            ->select('Año')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('Año')
            ->orderBy('Año')
            ->toBase()
            ->pluck('total', 'Año')
            ->map(fn ($total): int => (int) $total)
            ->all();
    }

    /**
     * Alta o edición en una transacción.
     *
     * @param  array<string, mixed>  $valores
     * @param  string|null  $editando  llave de la fila que se edita; null = alta
     * @param  self|null  $reemplazar  fila con la misma llave que se sobrescribe ("Reemplazar" =
     *                                 editarla). Si se editaba otra fila y se le movió la llave
     *                                 encima, esa se borra: no deben quedar dos con la misma llave.
     */
    public static function guardarCuota(array $valores, ?string $editando, ?self $reemplazar = null): self
    {
        return DB::connection('sqlsrv')->transaction(function () use ($valores, $editando, $reemplazar): self {
            $editada = $editando === null ? null : static::query()->clave($editando)->firstOrFail();

            if ($reemplazar !== null) {
                $editada?->delete();
                $reemplazar->update($valores);

                return $reemplazar;
            }

            if ($editada === null) {
                return static::create($valores);
            }
            $editada->update($valores);

            return $editada;
        });
    }

    /**
     * [año, mes] del mes siguiente (diciembre → enero del año siguiente): lo que propone Duplicar.
     * Sin año o mes se queda como está.
     *
     * @return array{0: int|null, 1: int|null}
     */
    public function mesSiguiente(): array
    {
        if ($this->Año === null || $this->Mes === null) {
            return [$this->Año, $this->Mes];
        }

        return $this->Mes >= 12 ? [$this->Año + 1, 1] : [$this->Año, $this->Mes + 1];
    }

    /** ¿Hay otra fila con esta llave? `$excepto` es la llave actual al editar. */
    public static function llaveOcupada(string $depto, int $año, int $mes, ?string $excepto = null): bool
    {
        $existente = static::existente($depto, $año, $mes);

        return $existente !== null && $existente->getKey() !== $excepto;
    }

    /** Guarda y borra por las tres columnas, con los valores originales (la llave puede editarse). */
    protected function setKeysForSaveQuery($query)
    {
        return self::dondeLlave($query, [
            'Depto' => $this->getOriginal('Depto'),
            'Año' => $this->getOriginal('Año'),
            'Mes' => $this->getOriginal('Mes'),
        ]);
    }

    /**
     * Las columnas de la llave son nullable: un null se busca con IS NULL, no con = NULL.
     *
     * @param  Builder<static>|\Illuminate\Database\Query\Builder  $query
     * @param  array<string, mixed>  $llave
     */
    private static function dondeLlave($query, array $llave)
    {
        foreach ($llave as $columna => $valor) {
            $valor === null ? $query->whereNull($columna) : $query->where($columna, $valor);
        }

        return $query;
    }
}
