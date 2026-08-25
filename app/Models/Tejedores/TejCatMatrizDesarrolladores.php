<?php

namespace App\Models\Tejedores;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Catalogo de hilos de la captura de desarrolladores.
 *
 * Un renglon es un hilo completo: el codigo de AX que lo nombra, el codigo interno
 * que se guarda como "Calibre" y el divisor que se guarda como "Hilo". Que los dos
 * numeros vivan juntos es justamente el punto: capturados por separado se desparejan
 * y la formula de L.Mat calcula el peso con el divisor equivocado.
 */
class TejCatMatrizDesarrolladores extends Model
{
    protected $connection = 'sqlsrv';

    protected $table = 'TejCatMatrizDesarrolladores';

    protected $primaryKey = 'Id';

    protected $fillable = ['Codigo', 'CodigoInterno', 'Divisor', 'Nombre', 'Vigente'];

    protected $casts = [
        'Divisor' => 'float',
        'Vigente' => 'boolean',
    ];

    public function scopeVigentes(Builder $query): Builder
    {
        return $query->where('Vigente', 1);
    }

    /** Lo que ve el operador en el desplegable. */
    public function getEtiquetaAttribute(): string
    {
        return trim($this->Nombre).' ('.trim($this->Codigo).')';
    }

    /**
     * Normaliza un calibre o un divisor para poder compararlos.
     *
     * Las columnas de origen son float, asi que el mismo valor llega como '10',
     * '10.0' o '8.0999999999999996' segun de donde venga.
     */
    public static function normalizar($valor): ?string
    {
        if ($valor === null || trim((string) $valor) === '') {
            return null;
        }

        $numero = (float) str_replace(',', '.', trim((string) $valor));

        return $numero == 0.0 ? null : (string) round($numero, 2);
    }
}
