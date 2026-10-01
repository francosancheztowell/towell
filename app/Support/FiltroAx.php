<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filtros por columna con la sintaxis de AX, para columnas numéricas y de mes. Cada parte
 * (ConTabla ya las separa por comas) puede ser:
 *
 *   9            igual a 9                    enero / ene   el mes 1 (nombre o abreviatura)
 *   1..3         entre 1 y 3 (inclusive)      ene..mar      de enero a marzo
 *   6..  / ..6   desde 6 / hasta 6            >6  >=6  <6  <=6
 *   !9  !1..3    todo menos eso
 *
 * Las partes positivas se suman (OR) y las negadas restan (AND), como en AX. Una parte que no
 * se entiende no aporta nada; si ninguna positiva se entiende, no sale ninguna fila (mejor
 * vacío que ignorar el filtro y enseñar todo).
 *
 * Uso en ConTabla::columnas(): ['campo' => 'Mes', 'filtro' => FiltroAx::mes('Mes')].
 */
final class FiltroAx
{
    /** @return Closure(Builder, list<string>): void */
    public static function numero(string $columna): Closure
    {
        return fn (Builder $query, array $partes) => self::aplicar($query, $columna, $partes, self::aNumero(...));
    }

    /** @return Closure(Builder, list<string>): void */
    public static function mes(string $columna): Closure
    {
        return fn (Builder $query, array $partes) => self::aplicar($query, $columna, $partes, self::aMes(...));
    }

    /**
     * Traduce una parte a una condición, o null si no se entiende.
     *
     * @param  callable(string): (int|float|null)  $aValor
     * @return array{negada: bool, op: string, a: int|float|null, b?: int|float|null}|null
     */
    public static function condicion(string $parte, callable $aValor): ?array
    {
        $parte = trim($parte);
        $negada = str_starts_with($parte, '!');
        if ($negada) {
            $parte = ltrim(substr($parte, 1));
        }

        $condicion = match (true) {
            str_contains($parte, '..') => self::rango($parte, $aValor),
            preg_match('/^(>=|<=|>|<)\s*(.+)$/', $parte, $m) === 1 => self::comparacion($m[1], $m[2], $aValor),
            default => self::comparacion('=', $parte, $aValor),
        };

        return $condicion === null ? null : ['negada' => $negada, ...$condicion];
    }

    /**
     * "a..b", "a.." o "..b". Un extremo escrito que no se entiende invalida el rango.
     *
     * @param  callable(string): (int|float|null)  $aValor
     * @return array{op: string, a: int|float|null, b: int|float|null}|null
     */
    private static function rango(string $parte, callable $aValor): ?array
    {
        [$desde, $hasta] = array_map('trim', explode('..', $parte, 2));
        $a = $desde === '' ? null : $aValor($desde);
        $b = $hasta === '' ? null : $aValor($hasta);
        $roto = ($desde !== '' && $a === null) || ($hasta !== '' && $b === null);

        return $roto || ($a === null && $b === null) ? null : ['op' => '..', 'a' => $a, 'b' => $b];
    }

    /**
     * @param  callable(string): (int|float|null)  $aValor
     * @return array{op: string, a: int|float}|null
     */
    private static function comparacion(string $op, string $texto, callable $aValor): ?array
    {
        $valor = trim($texto) === '' ? null : $aValor($texto);

        return $valor === null ? null : ['op' => $op, 'a' => $valor];
    }

    /** Número tal cual ("2026", "16.5"); cualquier otra cosa no es un número. */
    public static function aNumero(string $texto): int|float|null
    {
        $texto = trim($texto);

        return is_numeric($texto) ? $texto + 0 : null;
    }

    /** Mes 1-12 por número o por nombre/abreviatura de al menos 3 letras, sin importar acentos ni mayúsculas. */
    public static function aMes(string $texto): ?int
    {
        $texto = mb_strtolower(trim($texto));
        if (ctype_digit($texto)) {
            $numero = (int) $texto;

            return $numero >= 1 && $numero <= 12 ? $numero : null;
        }

        $texto = strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']);
        if (mb_strlen($texto) < 3) {
            return null;
        }

        foreach (['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'] as $i => $nombre) {
            if (str_starts_with($nombre, $texto) || ($texto === 'set' && $nombre === 'septiembre')) {
                return $i + 1;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $partes
     * @param  callable(string): (int|float|null)  $aValor
     */
    private static function aplicar(Builder $query, string $columna, array $partes, callable $aValor): void
    {
        $condiciones = array_map(fn (string $parte) => self::condicion($parte, $aValor), $partes);
        $positivas = array_filter($condiciones, fn ($c) => $c !== null && ! $c['negada']);
        $negadas = array_filter($condiciones, fn ($c) => $c !== null && $c['negada']);
        $hayPositivasEscritas = collect($partes)->contains(fn (string $p) => ! str_starts_with(trim($p), '!'));

        if ($hayPositivasEscritas && $positivas === []) {
            $query->whereRaw('1 = 0'); // se escribió algo y nada se entendió: ninguna fila

            return;
        }

        if ($positivas !== []) {
            $query->where(function (Builder $o) use ($columna, $positivas): void {
                foreach ($positivas as $c) {
                    $o->orWhere(fn (Builder $q) => self::una($q, $columna, $c));
                }
            });
        }

        foreach ($negadas as $c) {
            $query->whereNot(fn (Builder $q) => self::una($q, $columna, $c));
        }
    }

    /** @param  array{op: string, a: int|float|null, b?: int|float|null}  $c */
    private static function una(Builder $query, string $columna, array $c): void
    {
        if ($c['op'] !== '..') {
            $query->where($columna, $c['op'], $c['a']);

            return;
        }

        $b = $c['b'] ?? null;
        match (true) {
            $c['a'] !== null && $b !== null => $query->whereBetween($columna, [min($c['a'], $b), max($c['a'], $b)]),
            $c['a'] !== null => $query->where($columna, '>=', $c['a']),
            default => $query->where($columna, '<=', $b),
        };
    }
}
