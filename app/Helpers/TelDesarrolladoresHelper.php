<?php

namespace App\Helpers;

class TelDesarrolladoresHelper
{
    /**
     * Mapea una fila de detalle de una orden a un arreglo estándar.
     *
     * @param mixed  $ordenData
     * @param string $calibreKey
     * @param string $hiloKey
     * @param string $fibraKey
     * @param string $colorKey
     * @param string $nombreColorKey
     * @param string $pasadasKey
     * @return array
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

