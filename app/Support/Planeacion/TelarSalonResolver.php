<?php

namespace App\Support\Planeacion;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

final class TelarSalonResolver
{
    /**
     * Normaliza el salon a un valor canonico para utileria.
     */
    public static function normalizeSalon(?string $salon, ?string $telar = null): string
    {
        $telarNormalizado = self::normalizeTelar($telar);
        $telarNumero = self::telarNumber($telarNormalizado);
        if ($telarNumero !== null && $telarNumero >= 299) {
            return 'SMIT';
        }

        $salonNormalizado = trim((string) ($salon ?? ''));
        if ($salonNormalizado === '') {
            return '';
        }

        $upper = strtoupper(preg_replace('/\s+/', ' ', $salonNormalizado) ?? $salonNormalizado);
        $compact = str_replace(' ', '', $upper);

        return match (true) {
            in_array($upper, ['ITEMA', 'SMIT', 'SMITH'], true) => 'SMIT',
            in_array($upper, ['JACQUARD', 'JAC'], true), $compact === 'JC5' => 'JACQUARD',
            $upper === 'KARL MAYER', in_array($compact, ['KARLMAYER', 'KM'], true) => 'KARL MAYER',
            default => $salonNormalizado,
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

        if (!empty($salones)) {
            $query->whereIn(DB::raw(static::trimmedColumn('SalonTejidoId')), $salones);
        }

        return $query->whereRaw(static::trimmedColumn('NoTelarId') . ' = ?', [$telarNormalizado]);
    }

    /**
     * Devuelve una llave de orden para ordenar telares numericos correctamente.
     */
    public static function telarSortKey(?string $telar): string
    {
        $telarNormalizado = self::normalizeTelar($telar);
        $numero = self::telarNumber($telarNormalizado);

        if ($numero !== null) {
            return '0|' . str_pad((string) $numero, 10, '0', STR_PAD_LEFT);
        }

        return '1|' . strtoupper($telarNormalizado);
    }

    private static function telarNumber(?string $telar): ?int
    {
        $telarNormalizado = self::normalizeTelar($telar);
        if ($telarNormalizado === '' || !preg_match('/^\d+$/', $telarNormalizado)) {
            return null;
        }

        return (int) $telarNormalizado;
    }

    private static function trimmedColumn(string $column): string
    {
        return 'LTRIM(RTRIM([' . $column . ']))';
    }
}
