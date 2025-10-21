<?php
// This helper will delete .0 y limita decimales a solo 2
if (!function_exists('decimales')) {
    function decimales($valor)
    {
        if (is_numeric($valor)) {
            return rtrim(rtrim(number_format($valor, 2, '.', ''), '0'), '.');
        }
        return $valor;
    }
}

if (!function_exists('formatearFecha')) {
    function formatearFecha($fecha)
    {
        if (empty($fecha)) return '';

        try {
            $carbon = \Carbon\Carbon::parse($fecha);

            if ($carbon->format('H:i:s') === '00:00:00') {
                return $carbon->format('d-m-Y'); // año con 4 dígitos
            } else {
                return $carbon->format('d-m-Y H:i'); // hora visible si no es cero
            }
        } catch (\Exception $e) {
            return $fecha;
        }
    }
}

if (!function_exists('formatearFechaInputLocal')) {
    function formatearFechaInputLocal($fecha)
    {
        if (empty($fecha)) return '';

        try {
            $carbon = \Carbon\Carbon::parse($fecha);
            return $carbon->format('Y-m-d\TH:i'); // <-- el formato mágico para input
        } catch (\Exception $e) {
            return '';
        }
    }
}


if (!function_exists('getFotoUsuarioUrl')) {
    function getFotoUsuarioUrl($foto) {
        if (empty($foto)) {
            return null;
        }

        // Verificar si el archivo existe físicamente
        $archivoPath = storage_path('app/public/usuarios/' . $foto);

        if (!file_exists($archivoPath)) {
            return null;
        }

        // Generar URL con timestamp para evitar caché
        return asset('storage/usuarios/' . $foto) . '?v=' . time();
    }
}
