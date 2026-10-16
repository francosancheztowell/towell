<?php

namespace App\Models\Trazabilidad;

use App\Support\FiltroAx;
use App\ValueObjects\Trazabilidad\TrazabilidadFilters;
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

    /** Filtro de la pantalla → columna y largo del CAST. Única definición del filtro de Trazabilidad. */
    private const COLUMNAS_FILTRO = [
        'flog' => ['Flogs', 25],
        'articulo' => ['Articulo', 20],
        'tamano' => ['Tamano', 10],
    ];

    /**
     * Aplica los filtros de Trazabilidad (los vacíos se ignoran).
     *
     * - flog/articulo/tamano: sintaxis de AX (lista, rango, comodín; ver FiltroAx).
     * - cliente: lista de nombres exactos.
     * - anio/mes: solo las filas de esos periodos (años × meses). Pedido y facturado de AX
     *   son del Flog entero y no se acotan.
     *
     * @param  array<string, mixed>  $filtros  TrazabilidadFilters::toArray()
     * @param  string|null  $excepto  Filtro que no se aplica (facetas: las opciones de un
     *                                selector no se acotan por su propio valor).
     */
    public function scopeFiltrados(Builder $query, array $filtros, ?string $excepto = null): void
    {
        $f = TrazabilidadFilters::fromArray($filtros);

        foreach (self::COLUMNAS_FILTRO as $filtro => [$columna, $largo]) {
            if ($filtro !== $excepto) {
                // pdo_sqlsrv manda los strings como NVARCHAR; contra estas columnas VARCHAR con
                // intercalación SQL_* eso convierte la columna y el índice se recorre entero
                // (medido: 135 ms vs 31 ms por consulta). FiltroAx pone el CAST.
                FiltroAx::texto($query->getQuery(), $columna, $f->{$filtro}, $largo);
            }
        }

        if ($excepto !== 'cliente' && $f->cliente !== []) {
            $query->whereRaw(
                '[Cliente] IN ('.implode(', ', array_fill(0, count($f->cliente), 'CAST(? AS varchar(60))')).')',
                $f->cliente
            );
        }

        if ($excepto !== 'anio' && $f->anio !== '') {
            $rangos = $f->rangos();
            // Fecha es DATE: el rango deja usar el índice (YEAR()/MONTH() no). Las filas del periodo, no todo el Flog: con un Flog elegido, cambiar el mes no
            // cambiaba nada. Pedido y facturado de AX siguen siendo del Flog entero.
            $query->where(function ($q) use ($rangos): void {
                foreach ($rangos as [$desde, $hasta]) {
                    $q->orWhere(fn ($r) => $r->where('Fecha', '>=', $desde)->where('Fecha', '<', $hasta));
                }
            });
        }
    }
}
