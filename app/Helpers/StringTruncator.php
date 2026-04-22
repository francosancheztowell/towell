<?php

namespace App\Helpers;

/**
 * Helper para truncar valores string según límites reales de BD y evitar
 * SQLSTATE[22001] "String or binary data would be truncated".
 */
class StringTruncator
{
    /**
     * Mapa de límites de campos en BD.
     * Si no aparece, no se aplica truncamiento.
     */
    private static array $fieldLimits = [
        'CuentaRizo'       => 10,
        'SalonTejidoId'    => 10,
        'NoTelarId'        => 10,
        'Ultimo'           => 2,
        'CambioHilo'       => 2,
        'Maquina'          => 15,
        'CalendarioId'     => 25,
        'TamanoClave'      => 20,
        'NoExisteBase'     => 20,
        'ItemId'           => 20,
        'InventSizeId'     => 10,
        'Rasurado'         => 2,
        'NombreProducto'   => 100,
        'NoProduccion'     => 15,
        'FlogsId'          => 60,
        'NombreProyecto'   => 80,
        'CustName'         => 80,
        'AplicacionId'     => 30,
        'Observaciones'    => 200,
        'TipoPedido'       => 20,
        'FibraTrama'       => 15,
        'DobladilloId'     => 20,
        'CodColorTrama'    => 10,
        'ColorTrama'       => 80,
        'FibraRizo'        => 80,
        'CodColorComb1'    => 10,
        'CodColorComb2'    => 10,
        'CodColorComb3'    => 10,
        'CodColorComb4'    => 10,
        'CodColorComb5'    => 10,
        'NombreCC1'        => 60,
        'NombreCC2'        => 60,
        'NombreCC3'        => 60,
        'NombreCC4'        => 60,
        'NombreCC5'        => 60,
        'FibraComb1'       => 50,
        'FibraComb2'       => 50,
        'FibraComb3'       => 50,
        'FibraComb4'       => 50,
        'FibraComb5'       => 50,
        'CalibreComb1'     => 20,
        'CalibreComb2'     => 20,
        'CalibreComb3'     => 20,
        'CalibreComb4'     => 20,
        'CalibreComb5'     => 20,
        'CuentaPie'        => 10,
        'CodColorCtaPie'   => 10,
        'NombreCPie'       => 60,
        'FibraPie'         => 15,
        'CategoriaCalidad' => 20,
        'CombinaTram'      => 80,
        'BomId'            => 30,
        'BomName'          => 100,
        'HiloAX'           => 30,
        'Prioridad'        => 150,
        'UsuarioCrea'      => 50,
        'UsuarioModifica'  => 50,
    ];

    public static function getFieldLimits(): array
    {
        return self::$fieldLimits;
    }

    public static function truncate(string $fieldName, mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $str = (string) $value;
        $limit = self::$fieldLimits[$fieldName] ?? null;

        if ($limit === null) {
            return $str;
        }

        if (mb_strlen($str) > $limit) {
            return mb_substr($str, 0, $limit);
        }

        return $str;
    }

    public static function truncateArray(array $data): array
    {
        $result = [];

        foreach ($data as $field => $value) {
            $result[$field] = self::truncate($field, $value);
        }

        return $result;
    }

    public static function truncateModelAttributes(object $model): void
    {
        $attrs = $model->getAttributes();
        $truncated = self::truncateArray($attrs);

        foreach ($truncated as $key => $val) {
            if (array_key_exists($key, $attrs) && $attrs[$key] !== $val) {
                $model->$key = $val;
            }
        }
    }

    public static function getLimit(string $fieldName): ?int
    {
        return self::$fieldLimits[$fieldName] ?? null;
    }

    public static function exceedsLimit(string $fieldName, mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        $limit = self::getLimit($fieldName);
        if ($limit === null) {
            return false;
        }

        return mb_strlen((string) $value) > $limit;
    }
}
