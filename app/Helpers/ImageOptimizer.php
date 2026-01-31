<?php

namespace App\Helpers;

use Exception;
use Illuminate\Http\UploadedFile;

/**
 * Optimiza imágenes para módulos: redimensiona y comprime para que carguen más rápido.
 * Usa PHP GD (redimensionado + compresión JPEG/PNG).
 */
class ImageOptimizer
{
    /** Tamaño máximo del lado largo (ancho o alto) para iconos de módulo. */
    public const DEFAULT_MAX_SIZE = 400;

    /** Calidad JPEG (1-100). 82 es un buen equilibrio tamaño/calidad. */
    public const JPEG_QUALITY = 82;

    /** Nivel de compresión PNG (0-9). 6 es buen equilibrio. */
    public const PNG_COMPRESSION = 6;

    /**
     * Optimiza un archivo subido y lo guarda en la ruta indicada.
     * Redimensiona si supera maxSize y comprime según el tipo.
     *
     * @param  UploadedFile  $file  Archivo subido (imagen)
     * @param  string  $destPath  Ruta completa donde guardar (ej. public_path('images/fotos_modulos/nombre.jpg'))
     * @param  int  $maxSize  Lado máximo en píxeles (ancho o alto). Por defecto 400.
     * @return string  Nombre del archivo guardado (sin ruta)
     *
     * @throws Exception si GD no está disponible o la imagen no se puede procesar
     */
    public static function optimizeAndSave(UploadedFile $file, string $destPath, int $maxSize = self::DEFAULT_MAX_SIZE): string
    {
        if (! extension_loaded('gd')) {
            throw new Exception('PHP GD no está instalado. No se puede optimizar la imagen.');
        }

        $fullPath = $file->getRealPath();
        $mime = $file->getMimeType();
        $extension = strtolower($file->getClientOriginalExtension() ?: pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));

        $image = self::loadImage($fullPath, $mime, $extension);
        if (! $image) {
            throw new Exception('No se pudo cargar la imagen para optimizar.');
        }

        $width = imagesx($image);
        $height = imagesy($image);

        if ($width > $maxSize || $height > $maxSize) {
            $newDimensions = self::calculateDimensions($width, $height, $maxSize);
            $resized = imagecreatetruecolor($newDimensions['width'], $newDimensions['height']);
            if (! $resized) {
                imagedestroy($image);
                throw new Exception('No se pudo crear la imagen redimensionada.');
            }
            imagecopyresampled(
                $resized, $image,
                0, 0, 0, 0,
                $newDimensions['width'], $newDimensions['height'],
                $width, $height
            );
            imagedestroy($image);
            $image = $resized;
        }

        $saved = self::saveImage($image, $destPath, $extension);
        imagedestroy($image);

        if (! $saved) {
            throw new Exception('No se pudo guardar la imagen optimizada.');
        }

        return basename($destPath);
    }

    /**
     * Optimiza un archivo ya existente en disco (sobrescribe el original).
     * Útil para optimizar imágenes ya subidas.
     *
     * @param  string  $filePath  Ruta completa al archivo (ej. public_path('images/fotos_modulos/foo.jpg'))
     * @param  int  $maxSize  Lado máximo en píxeles
     * @return bool  true si se optimizó correctamente
     */
    public static function optimizeFile(string $filePath, int $maxSize = self::DEFAULT_MAX_SIZE): bool
    {
        if (! extension_loaded('gd') || ! is_file($filePath)) {
            return false;
        }

        $mime = mime_content_type($filePath);
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        $image = self::loadImage($filePath, $mime, $extension);
        if (! $image) {
            return false;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        if ($width > $maxSize || $height > $maxSize) {
            $newDimensions = self::calculateDimensions($width, $height, $maxSize);
            $resized = imagecreatetruecolor($newDimensions['width'], $newDimensions['height']);
            if (! $resized) {
                imagedestroy($image);
                return false;
            }
            imagecopyresampled(
                $resized, $image,
                0, 0, 0, 0,
                $newDimensions['width'], $newDimensions['height'],
                $width, $height
            );
            imagedestroy($image);
            $image = $resized;
        }

        $saved = self::saveImage($image, $filePath, $extension);
        imagedestroy($image);

        return $saved;
    }

    /**
     * Carga un recurso de imagen GD desde ruta y MIME/extension.
     *
     * @return \GdImage|false
     */
    private static function loadImage(string $path, string $mime, string $extension)
    {
        if (str_contains($mime, 'jpeg') || $extension === 'jpg' || $extension === 'jpeg') {
            return @imagecreatefromjpeg($path);
        }
        if (str_contains($mime, 'png') || $extension === 'png') {
            return @imagecreatefrompng($path);
        }
        if (str_contains($mime, 'gif') || $extension === 'gif') {
            return @imagecreatefromgif($path);
        }
        if (str_contains($mime, 'webp') || $extension === 'webp') {
            if (function_exists('imagecreatefromwebp')) {
                return @imagecreatefromwebp($path);
            }
        }

        return false;
    }

    /**
     * Guarda el recurso GD en la ruta indicada con la extensión dada.
     *
     * @param  \GdImage  $image
     * @return bool
     */
    private static function saveImage($image, string $destPath, string $extension): bool
    {
        $dir = dirname($destPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        switch (strtolower($extension)) {
            case 'jpg':
            case 'jpeg':
                return imagejpeg($image, $destPath, self::JPEG_QUALITY);
            case 'png':
                return imagepng($image, $destPath, self::PNG_COMPRESSION);
            case 'gif':
                return imagegif($image, $destPath);
            case 'webp':
                if (function_exists('imagewebp')) {
                    return imagewebp($image, $destPath, 82);
                }
                return imagepng($image, $destPath, self::PNG_COMPRESSION);
            default:
                return imagejpeg($image, $destPath, self::JPEG_QUALITY);
        }
    }

    private static function calculateDimensions(int $width, int $height, int $maxSize): array
    {
        if ($width <= $maxSize && $height <= $maxSize) {
            return ['width' => $width, 'height' => $height];
        }
        $ratio = min($maxSize / $width, $maxSize / $height);

        return [
            'width' => (int) round($width * $ratio),
            'height' => (int) round($height * $ratio),
        ];
    }
}
