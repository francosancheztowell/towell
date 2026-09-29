<?php

namespace App\Services\Tejido;

use Illuminate\Database\Eloquent\Model;

/**
 * Guarda el orden de una secuencia de Tejido (drag & drop) con un UPDATE ... CASE por bloque
 * en vez de un UPDATE por fila (PERF 19-02). Compatible con SQL Server 2008 R2.
 */
class OrdenSecuencia
{
    /** Parámetros por fila: WHEN ? THEN ? + el ? del IN. SQL Server acepta 2 100 por sentencia. */
    private const FILAS_POR_BLOQUE = 600;

    /**
     * @param  class-string<Model>  $modelo
     * @param  list<array<string, int>>  $items  [[$llave => 201, $campo => 1], ...]
     * @param  array<string, mixed>  $extra  Columnas fijas para todas las filas (p. ej. Updated_At).
     */
    public static function actualizar(string $modelo, string $llave, string $campo, array $items, array $extra = []): void
    {
        // Llave repetida: gana la última, como en el UPDATE por fila de antes (en CASE ganaría la primera).
        $items = array_values(array_column($items, null, $llave));
        if ($items === []) {
            return;
        }

        /** @var Model $instancia */
        $instancia = new $modelo;
        $conexion = $instancia->getConnection();
        $gramatica = $conexion->getQueryGrammar();
        $tabla = $gramatica->wrapTable($instancia->getTable());
        $columnaLlave = $gramatica->wrap($llave);

        $conexion->transaction(function () use ($items, $llave, $campo, $extra, $conexion, $gramatica, $tabla, $columnaLlave) {
            foreach (array_chunk($items, self::FILAS_POR_BLOQUE) as $bloque) {
                $casos = '';
                $enlaces = [];
                $llaves = [];
                foreach ($bloque as $item) {
                    $casos .= ' WHEN ? THEN ?';
                    $enlaces[] = $item[$llave];
                    $enlaces[] = $item[$campo];
                    $llaves[] = $item[$llave];
                }

                $sets = [$gramatica->wrap($campo).' = CASE '.$columnaLlave.$casos.' END'];
                foreach ($extra as $columna => $valor) {
                    $sets[] = $gramatica->wrap($columna).' = ?';
                    $enlaces[] = $valor;
                }

                $marcas = implode(', ', array_fill(0, count($llaves), '?'));
                $conexion->update(
                    'UPDATE '.$tabla.' SET '.implode(', ', $sets).' WHERE '.$columnaLlave.' IN ('.$marcas.')',
                    array_merge($enlaces, $llaves)
                );
            }
        });
    }
}
