<?php

declare(strict_types=1);

namespace App\Services\ProgramaUrdEng;

use Illuminate\Database\Eloquent\Model;

/**
 * PERF-08: un INSERT de varias filas en vez de un Model::create() por fila.
 *
 * Cada fila pasa por fill() del modelo, así que llega a la BD con los mismos valores que
 * pondría create() (fechas con el formato de la gramática, timestamps si el modelo los usa).
 * No dispara eventos de modelo: úsese solo con modelos sin observers.
 */
final class InsercionEnBloques
{
    /** SQL Server acepta como máximo 2 100 parámetros por sentencia; se deja uno de margen. */
    private const MAX_PARAMETROS = 2099;

    /** Y como máximo 1 000 filas por INSERT … VALUES (error 10738). */
    private const MAX_FILAS = 1000;

    /**
     * @param  class-string<Model>  $modelo
     * @param  array<int, array<string, mixed>>  $filas  atributos de cada fila, todas con las mismas claves
     * @return int filas insertadas
     */
    public static function insertar(string $modelo, array $filas): int
    {
        if ($filas === []) {
            return 0;
        }

        $valores = array_map(static function (array $atributos) use ($modelo): array {
            $m = new $modelo;
            $m->fill($atributos);
            if ($m->usesTimestamps()) {
                $m->updateTimestamps();
            }

            return $m->getAttributes();
        }, $filas);

        $porBloque = max(1, min(self::MAX_FILAS, intdiv(self::MAX_PARAMETROS, count($valores[0]))));
        foreach (array_chunk($valores, $porBloque) as $bloque) {
            $modelo::query()->toBase()->insert($bloque);
        }

        return count($valores);
    }
}
