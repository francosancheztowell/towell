<?php

declare(strict_types=1);

namespace App\Support\Programas;

/**
 * Oficial de un julio a partir de NomEmpl1–3 / Metros1–3.
 * La misma regla que el modal de calificar julios: gana el mayor metraje;
 * si nadie tiene metros, el primer nombre o clave capturados.
 */
final class OperadorPorMetros
{
    public static function mostrar(?string $nombre, ?string $clave): string
    {
        $nom = trim((string) ($nombre ?? ''));
        if ($nom !== '') {
            return $nom;
        }

        return trim((string) ($clave ?? ''));
    }

    /**
     * Turnos con metros > 0. Sin nombre ni clave, el rótulo es "Turno N".
     *
     * @return list<array{nombre: string, metros: float}>
     */
    public static function conMetros(object $row): array
    {
        $ops = [];
        foreach ([1, 2, 3] as $n) {
            $mts = (float) ($row->{"Metros{$n}"} ?? 0);
            if ($mts <= 0) {
                continue;
            }
            $nombre = self::mostrar(self::texto($row, "NomEmpl{$n}"), self::texto($row, "CveEmpl{$n}"));
            $ops[] = ['nombre' => $nombre !== '' ? $nombre : "Turno {$n}", 'metros' => round($mts)];
        }

        return $ops;
    }

    public static function mayorMetros(object $row): string
    {
        $slots = [];
        foreach ([1, 2, 3] as $n) {
            $slots[] = [
                'm' => round((float) ($row->{"Metros{$n}"} ?? 0), 4),
                'nom' => self::texto($row, "NomEmpl{$n}"),
                'cve' => self::texto($row, "CveEmpl{$n}"),
            ];
        }

        $ganador = self::nombreDelMaximo($slots);
        if ($ganador !== '') {
            return $ganador;
        }

        foreach ($slots as $slot) {
            $nombre = self::mostrar($slot['nom'], $slot['cve']);
            if ($nombre !== '') {
                return $nombre;
            }
        }

        return '';
    }

    /**
     * @param  list<array{m: float, nom: ?string, cve: ?string}>  $slots
     */
    private static function nombreDelMaximo(array $slots): string
    {
        $max = max(array_column($slots, 'm'));
        if ($max <= 0) {
            return '';
        }

        foreach ($slots as $slot) {
            if ($slot['m'] !== $max) {
                continue;
            }
            $nombre = self::mostrar($slot['nom'], $slot['cve']);
            if ($nombre !== '') {
                return $nombre;
            }
        }

        return '';
    }

    private static function texto(object $row, string $campo): ?string
    {
        $valor = $row->{$campo} ?? null;

        return $valor === null ? null : (string) $valor;
    }
}
