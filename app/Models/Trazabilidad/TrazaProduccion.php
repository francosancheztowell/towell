<?php

namespace App\Models\Trazabilidad;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TrazaProduccion extends Model
{
    protected $table = 'TrazaProduccion';

    protected $connection = 'sqlsrv';

    protected $primaryKey = 'Id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'Flogs',
        'Tipo',
        'Cliente',
        'Agente',
        'Fecha',
        'Articulo',
        'NombreArticulo',
        'Tamano',
        'Color',
        'NombreColor',
        'Cantidad',
        'Peso',
        'Almacen',
        'NombreAlmacen',
        'Orden',
        'Localidad',
    ];

    protected $casts = [
        'Fecha' => 'date',
        'Cantidad' => 'float',
        'Peso' => 'float',
    ];

    /** Filtro de la pantalla → columna. Única definición del filtro de Trazabilidad. */
    private const COLUMNAS_FILTRO = [
        'flog' => 'Flogs',
        'articulo' => 'Articulo',
        'tamano' => 'Tamano',
    ];

    /**
     * Aplica los filtros de Trazabilidad (los vacíos se ignoran).
     *
     * @param  array<string, mixed>  $filtros  ['flog', 'articulo', 'tamano']
     * @param  string|null  $excepto  Filtro que no se aplica (facetas: las opciones de un
     *                                selector no se acotan por su propio valor).
     */
    public function scopeFiltrados(Builder $query, array $filtros, ?string $excepto = null): void
    {
        foreach (self::COLUMNAS_FILTRO as $filtro => $columna) {
            $valor = trim((string) ($filtros[$filtro] ?? ''));
            if ($filtro !== $excepto && $valor !== '') {
                // pdo_sqlsrv manda los strings como NVARCHAR; contra estas columnas VARCHAR con
                // intercalación SQL_* eso convierte la columna y el índice se recorre entero
                // (medido: 135 ms vs 31 ms por consulta). El CAST deja buscar en el índice.
                // $columna viene de COLUMNAS_FILTRO (lista fija); el valor viaja como binding.
                $query->whereRaw('['.$columna.'] = CAST(? AS varchar(100))', [$valor]);
            }
        }
    }
}
