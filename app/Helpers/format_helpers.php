<?php
// This helper will delete .0 and limit decimals to 2
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
                return $carbon->format('d-m-Y');
            } else {
                return $carbon->format('d-m-Y H:i');
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
            return $carbon->format('Y-m-d\\TH:i');
        } catch (\Exception $e) {
            return '';
        }
    }
}

if (!function_exists('getFotoUsuarioUrl')) {
    function getFotoUsuarioUrl($foto)
    {
        if (empty($foto)) {
            return null;
        }

        $relativePath = 'images/fotos_usuarios/' . $foto;
        $archivoPath = public_path($relativePath);

        if (!file_exists($archivoPath)) {
            return null;
        }

        $pathInfo = pathinfo($relativePath);
        $webpRelativePath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '.webp';
        $webpPath = public_path($webpRelativePath);

        if (file_exists($webpPath)) {
            return asset($webpRelativePath) . '?v=' . filemtime($webpPath);
        }

        return asset($relativePath) . '?v=' . filemtime($archivoPath);
    }
}
