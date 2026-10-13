<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;
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
 *
 * Para columnas de texto (códigos de Trazabilidad) están valores()/texto(): lista con comas,
 * rango y comodín `*`, con el parámetro como VARCHAR para no anular el índice.
 */
final class FiltroAx
{
    /** Tope de valores por filtro de texto: van como bindings (SQL Server admite 2100). */
    private const MAX_VALORES = 100;

    /**
     * Partes de un filtro de texto escrito como en AX ("7166, 7167,71*"), sin repetir
     * (sin importar mayúsculas) y hasta MAX_VALORES.
     *
     * @return list<string>
     */
    public static function valores(string $texto): array
    {
        $valores = [];
        $vistos = [];
        foreach (explode(',', $texto) as $valor) {
            $valor = trim($valor);
            $clave = mb_strtolower($valor);
            if ($valor !== '' && ! isset($vistos[$clave])) {
                $vistos[$clave] = true;
                $valores[] = $valor;
            }
        }

        return array_slice($valores, 0, self::MAX_VALORES);
    }

    /** Un valor o una lista (selector múltiple) como texto AX normalizado: "a,b,c". */
    public static function unir(mixed $valores): string
    {
        $lista = array_filter(is_array($valores) ? $valores : [$valores], is_scalar(...));

        return implode(',', self::valores(implode(',', array_map(strval(...), $lista))));
    }

    /** Rango o comodín: no se puede buscar en una lista de opciones. */
    public static function esPatron(string $valor): bool
    {
        return str_contains($valor, '..') || str_contains($valor, '*');
    }

    /**
     * Filtro de texto: exacto (`7166`), rango (`7160..7170`, `7160..`, `..7170`) o comodín
     * (`71*`), separados por coma y combinados con OR. Los parámetros llevan CAST a varchar:
     * pdo_sqlsrv los manda como NVARCHAR y contra columnas VARCHAR eso anula el índice
     * (ver TrazaProduccion::filtrados).
     *
     * @param  string  $columna  nombre fijo del código, nunca del usuario.
     */
    public static function texto(QueryBuilder $query, string $columna, string $texto, int $largo = 100): void
    {
        $valores = self::valores($texto);
        if ($valores === []) {
            return;
        }

        $param = 'CAST(? AS varchar('.$largo.'))';
        $col = '['.$columna.']';

        $query->where(function (QueryBuilder $q) use ($valores, $param, $col): void {
            $exactos = [];
            foreach ($valores as $valor) {
                if (str_contains($valor, '..')) {
                    [$desde, $hasta] = array_map('trim', explode('..', $valor, 2));
                    match (true) {
                        $desde !== '' && $hasta !== '' => $q->orWhereRaw($col.' BETWEEN '.$param.' AND '.$param, [$desde, $hasta]),
                        $desde !== '' => $q->orWhereRaw($col.' >= '.$param, [$desde]),
                        $hasta !== '' => $q->orWhereRaw($col.' <= '.$param, [$hasta]),
                        default => null,
                    };
                } elseif (str_contains($valor, '*')) {
                    // '!' como escape: el % _ [ que traiga el valor se buscan literales.
                    $patron = strtr($valor, ['!' => '!!', '%' => '!%', '_' => '!_', '[' => '![', '*' => '%']);
                    $q->orWhereRaw($col.' LIKE '.$param." ESCAPE '!'", [$patron]);
                } else {
                    $exactos[] = $valor;
                }
            }
            if ($exactos !== []) {
                $q->orWhereRaw($col.' IN ('.implode(', ', array_fill(0, count($exactos), $param)).')', $exactos);
            }
        });
    }

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
     * Folios (texto con ceros): "1-20" o "1..20" rango, "001*" comodín, "!5" excluye. Un número
     * corto se rellena a 5 dígitos ("5" → "00005"), así no hay que teclear los ceros.
     *
     * @return Closure(Builder, list<string>): void
     */
    public static function folio(string $columna): Closure
    {
        // ponytail: relleno fijo a 5 dígitos, el largo de los folios de hoy; folios de otro largo se escriben completos.
        $aFolio = fn (string $t): ?string => ($t = trim($t)) === '' ? null : (ctype_digit($t) ? str_pad($t, 5, '0', STR_PAD_LEFT) : $t);

        return function (Builder $query, array $partes) use ($columna, $aFolio): void {
            $partes = array_map(fn (string $p) => str_replace('-', '..', trim($p)), $partes);
            $negadas = array_values(array_filter($partes, fn (string $p) => str_starts_with($p, '!')));
            $comodines = array_filter($partes, fn (string $p) => str_contains($p, '*') && ! str_starts_with($p, '!'));
            $positivas = array_values(array_diff($partes, $negadas, $comodines));

            // Positivas y comodines suman (OR); las negadas restan (AND), como en AX.
            if ($positivas !== [] || $comodines !== []) {
                $query->where(function (Builder $o) use ($columna, $comodines, $positivas, $aFolio): void {
                    foreach ($comodines as $p) {
                        $o->orWhere($columna, 'like', str_replace('*', '%', $p));
                    }
                    if ($positivas !== []) {
                        $o->orWhere(fn (Builder $q) => self::aplicar($q, $columna, $positivas, $aFolio));
                    }
                });
            }
            if ($negadas !== []) {
                self::aplicar($query, $columna, $negadas, $aFolio);
            }
        };
    }

    /**
     * Traduce una parte a una condición, o null si no se entiende.
     *
     * @param  callable(string): (int|float|string|null)  $aValor
     * @return array{negada: bool, op: string, a: int|float|string|null, b?: int|float|string|null}|null
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
     * @param  callable(string): (int|float|string|null)  $aValor
     * @return array{op: string, a: int|float|string|null, b: int|float|string|null}|null
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
     * @param  callable(string): (int|float|string|null)  $aValor
     * @return array{op: string, a: int|float|string}|null
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
     * @param  callable(string): (int|float|string|null)  $aValor
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

    /** @param  array{op: string, a: int|float|string|null, b?: int|float|string|null}  $c */
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
