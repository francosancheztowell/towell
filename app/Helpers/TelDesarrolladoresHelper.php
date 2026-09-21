<?php

namespace App\Helpers;

class TelDesarrolladoresHelper
{
    /**
     * Karl Mayer no teje rizo/pie ni combinaciones: teje cuatro barras.
     *
     * No existe Barra5 en base, asi que el numero es fijo y no una constante por si
     * algun dia crece. Las 24 columnas se llaman igual en ReqProgramaTejido y en
     * CatCodificados, asi que el mismo mapa sirve para leer y para escribir.
     */
    public const BARRAS = [1, 2, 3, 4];

    /**
     * Las cuatro barras de una orden, tal como se ven en la captura.
     *
     * Se devuelven siempre las cuatro, vacias incluidas: en Karl Mayer el renglon no
     * se agrega ni se quita, es una posicion fisica de la maquina.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function detallesKarlMayer($ordenData): array
    {
        return array_map(static fn (int $n): array => [
            'Cuenta' => data_get($ordenData, "CuentaBarra{$n}") ?? '',
            'Calibre' => data_get($ordenData, "CalibreBarra{$n}") ?? '',
            // Karl Mayer no guarda divisor: la columna que acompana al calibre es Cuenta.
            'Hilo' => '',
            'Fibra' => data_get($ordenData, "FibraBarra{$n}") ?? '',
            'CodColor' => data_get($ordenData, "CodColorBarra{$n}") ?? '',
            'NombreColor' => data_get($ordenData, "ColorBarra{$n}") ?? '',
            'Pasadas' => data_get($ordenData, "PasadasBarra{$n}") ?? '',
            'pasadasField' => "PasadasBarra{$n}",
        ], self::BARRAS);
    }

    /**
     * Mapea una fila de detalle de una orden a un arreglo estándar.
     *
     * @param  mixed  $ordenData
     * @param  string  $calibreKey
     * @param  string  $hiloKey
     * @param  string  $fibraKey
     * @param  string  $colorKey
     * @param  string  $nombreColorKey
     * @param  string  $pasadasKey
     */
    public static function mapDetalleFila($ordenData, $calibreKey, $hiloKey, $fibraKey, $colorKey, $nombreColorKey, $pasadasKey): array
    {
        $nombreColor = data_get($ordenData, $nombreColorKey);
        $alternateNombreColor = null;

        if (substr($nombreColorKey, 0, 8) === 'NombreCC' && $nombreColor === null) {
            $indice = (int) filter_var($nombreColorKey, FILTER_SANITIZE_NUMBER_INT);
            $alternateNombreColor = data_get($ordenData, "NomColorC{$indice}");
        }

        return [
            'Calibre' => data_get($ordenData, $calibreKey) ?? '',
            'Hilo' => data_get($ordenData, $hiloKey) ?? '',
            'Fibra' => data_get($ordenData, $fibraKey) ?? '',
            'CodColor' => data_get($ordenData, $colorKey) ?? '',
            'NombreColor' => $nombreColor ?? $alternateNombreColor ?? '',
            'Pasadas' => data_get($ordenData, $pasadasKey) ?? '',
            'pasadasField' => $pasadasKey,
        ];
    }
}
