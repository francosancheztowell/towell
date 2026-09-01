<?php

namespace App\Support\Planeacion;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

final class TelarSalonResolver
{
    /**
     * Normaliza el salon a un valor canonico para utileria.
     *
     * El salon capturado manda; el numero de telar solo decide cuando no viene salon.
     * Antes era al reves (todo telar >= 299 era SMIT) y eso deja fuera a Karl Mayer, que
     * usa los telares 401-402: la orden se capturaba como 'KARL MAYER' y aqui se convertia
     * en SMIT, asi que los catalogos filtrados por salon no encontraban sus filas.
     */
    public static function normalizeSalon(?string $salon, ?string $telar = null): string
    {
        $salonNormalizado = trim((string) ($salon ?? ''));

        if ($salonNormalizado === '') {
            return self::salonDesdeTelar($telar);
        }

        $upper = strtoupper(preg_replace('/\s+/', ' ', $salonNormalizado) ?? $salonNormalizado);
        $compact = str_replace(' ', '', $upper);

        return match (true) {
            in_array($upper, ['ITEMA', 'SMIT', 'SMITH'], true) => 'SMIT',
            // 'JACUARD' es dato sucio de AX (BOMTABLE.TWSALON), no una captura del programa.
            in_array($upper, ['JACQUARD', 'JACUARD', 'JAC'], true), $compact === 'JC5' => 'JACQUARD',
            $upper === 'KARL MAYER', in_array($compact, ['KARLMAYER', 'KM'], true) => 'KARL MAYER',
            default => $salonNormalizado,
        };
    }

    /**
     * Salon deducido del numero de telar, para cuando la fila no trae SalonTejidoId.
     * 201-215 Jacquard, 299-320 Smit, 401-402 Karl Mayer.
     */
    public static function salonDesdeTelar(?string $telar): string
    {
        $numero = self::telarNumber(self::normalizeTelar($telar));

        // Un numero fuera de los rangos conocidos devuelve '' (igual que antes): sin salon
        // no se inventa uno, porque salonAliases('') deja pasar la consulta sin filtro.
        return match (true) {
            $numero === null => '',
            $numero >= 401 && $numero <= 402 => 'KARL MAYER',
            $numero >= 299 && $numero <= 320 => 'SMIT',
            $numero >= 201 && $numero <= 215 => 'JACQUARD',
            default => '',
        };
    }

    /**
     * Devuelve los alias de salon que deben tratarse como equivalentes.
     *
     * @return array<int, string>
     */
    public static function salonAliases(?string $salon, ?string $telar = null): array
    {
        $normalizado = self::normalizeSalon($salon, $telar);

        return match ($normalizado) {
            'SMIT' => ['SMIT', 'SMITH', 'ITEMA'],
            'KARL MAYER' => ['KARL MAYER', 'KARLMAYER', 'KM'],
            'JACQUARD' => ['JACQUARD', 'JAC', 'JC5'],
            '' => [],
            default => [$normalizado],
        };
    }

    /**
     * Formas en que AX escribe este salon en BOMTABLE.TWSALON, que NO son las del programa:
     * AX pone 'KM' donde el programa dice 'KARL MAYER', 'ITEMA' donde dice 'SMIT' y a veces
     * 'JACUARD' mal escrito. Un salon desconocido se busca tal cual.
     *
     * @return array<int, string>
     */
    public static function salonAliasesAx(?string $salon, ?string $telar = null): array
    {
        return match (self::normalizeSalon($salon, $telar)) {
            'SMIT' => ['SMIT', 'ITEMA'],
            'JACQUARD' => ['JACQUARD', 'JACUARD'],
            'KARL MAYER' => ['KM', 'KARL MAYER', 'KARLMAYER'],
            '' => [],
            default => [strtoupper(trim((string) $salon))],
        };
    }

    /**
     * Karl Mayer por salon capturado o, si viene vacio, por numero de telar (401-402).
     * Es el salon con reglas propias de marbetaje: no aplica felpa y las tiras se capturan.
     */
    public static function esKarlMayer(?string $salon, ?string $telar = null): bool
    {
        return self::normalizeSalon($salon, $telar) === 'KARL MAYER';
    }

    /** Salones canonicos conocidos; dar de alta uno nuevo empieza aqui y en normalizeSalon(). */
    public static function salonesCanonicos(): array
    {
        return ['SMIT', 'JACQUARD', 'KARL MAYER'];
    }

    /** Todas las formas de todos los salones conocidos en AX, sin repetir. */
    public static function todosLosAliasesAx(): array
    {
        return ['SMIT', 'ITEMA', 'JACQUARD', 'JACUARD', 'KM', 'KARL MAYER', 'KARLMAYER'];
    }

    /**
     * Normaliza el telar para comparaciones y respuestas JSON.
     */
    public static function normalizeTelar(?string $telar): string
    {
        return trim((string) ($telar ?? ''));
    }

    /**
     * Aplica el filtro fisico de telar considerando equivalencias de salon.
     */
    public static function applyTelarFilter(
        EloquentBuilder|QueryBuilder $query,
        ?string $salon,
        ?string $telar
    ): EloquentBuilder|QueryBuilder {
        $telarNormalizado = self::normalizeTelar($telar);
        $salones = self::salonAliases($salon, $telarNormalizado);

        if ($telarNormalizado === '') {
            return $query;
        }

        if (! empty($salones)) {
            $query->whereIn(DB::raw(self::trimmedColumn('SalonTejidoId')), $salones);
        }

        return $query->whereRaw(self::trimmedColumn('NoTelarId').' = ?', [$telarNormalizado]);
    }

    /**
     * Devuelve una llave de orden para ordenar telares numericos correctamente.
     */
    public static function telarSortKey(?string $telar): string
    {
        $telarNormalizado = self::normalizeTelar($telar);
        $numero = self::telarNumber($telarNormalizado);

        if ($numero !== null) {
            return '0|'.str_pad((string) $numero, 10, '0', STR_PAD_LEFT);
        }

        return '1|'.strtoupper($telarNormalizado);
    }

    private static function telarNumber(?string $telar): ?int
    {
        $telarNormalizado = self::normalizeTelar($telar);
        if ($telarNormalizado === '' || ! preg_match('/^\d+$/', $telarNormalizado)) {
            return null;
        }

        return (int) $telarNormalizado;
    }

    private static function trimmedColumn(string $column): string
    {
        return 'LTRIM(RTRIM(['.$column.']))';
    }
}
