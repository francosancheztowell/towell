<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Muchos UPDATE de un jalón: UPDATE … SET col = CASE Id WHEN ? THEN ? … END en lotes (la primera corrida de
 * costos toca miles de julios y uno por fila no cabe en una petición). SQL crudo porque el query builder no
 * arma CASE con bindings. Tabla y columnas van en el SQL, así que solo se aceptan las de $permitidas.
 */
final class ActualizacionPorId
{
    /** Parámetros por petición: SQL Server acepta 2100. */
    private const PARAMETROS = 2000;

    /**
     * @param  array<int, array<string, float|null>>  $valores  Id => [columna => valor]; todas las filas con las mismas columnas
     * @param  list<string>  $permitidas  columnas que la tabla deja escribir
     */
    public static function ejecutar(string $tabla, array $valores, array $permitidas): void
    {
        if ($valores === []) {
            return;
        }
        preg_match('/^\w+$/', $tabla) || throw new \InvalidArgumentException("Tabla no válida: {$tabla}");
        $columnas = array_keys(reset($valores));
        $fuera = array_diff($columnas, $permitidas);
        $fuera === [] || throw new \InvalidArgumentException('Columnas no permitidas: '.implode(', ', $fuera));

        // Por fila: 2 parámetros por columna (WHEN ? THEN ?) + 1 del IN.
        $porLote = max(1, intdiv(self::PARAMETROS, 2 * count($columnas) + 1));
        foreach (array_chunk($valores, $porLote, true) as $lote) {
            $sets = [];
            $bindings = [];
            foreach ($columnas as $col) {
                $sets[] = sprintf('[%s] = CASE [Id]%s END', $col, str_repeat(' WHEN ? THEN ?', count($lote)));
                foreach ($lote as $id => $v) {
                    array_push($bindings, $id, $v[$col]);
                }
            }
            $ids = array_keys($lote);
            DB::connection('sqlsrv')->update(
                sprintf('UPDATE [%s] SET %s WHERE [Id] IN (%s)', $tabla, implode(', ', $sets), implode(',', array_fill(0, count($ids), '?'))),
                [...$bindings, ...$ids],
            );
        }
    }
}
