<?php

namespace App\Helpers;

/**
 * @file StringTruncator.php
 * @description Helper para truncar valores STRING según límites de BD, evitando el error SQL
 *              "String or binary data would be truncated". Límites basados en migración
 *              ReqProgramaTejido. truncateModelAttributes() para modelos Eloquent.
 * @dependencies Ninguno (standalone)
 */
class StringTruncator
{
    /**
     * Mapa de limites de campos en BD (desde migración)
     * Si no aparece, no hay límite o es numérico
     */
    private static array $fieldLimits = [
        'CuentaRizo'     => 20,
        'SalonTejidoId'  => 10,
        'NoTelarId'      => 10,
        'Ultimo'         => 2,
        'CambioHilo'     => 2,
        'Maquina'        => 15,
        'FlogsId'        => 40,
        'NombreProyecto' => 50,
        'CustName'       => 60,
        'AplicacionId'   => 10,
        'Observaciones'  => 100,
        'TipoPedido'     => 10,
        'DobladilloId'   => 40,
        'CodColorTrama'  => 40,
        'ColorTrama'     => 40,
        'FibraTrama'     => 40,
        'CodColorC1'     => null,
        'NomColorC1'     => null,
        'CodColorC2'     => null,
        'NomColorC2'     => null,
        'CodColorC3'     => null,
        'NomColorC3'     => null,
        'CodColorC4'     => null,
        'NomColorC4'     => null,
        'CodColorC5'     => null,
        'NomColorC5'     => null,
        'FibraComb1'     => 50, // Ampliado de 15 a 50 según actualización de BD
        'CodColorComb1'  => 40,
        'NombreCC1'      => 60, // Según migración: string('NombreCC1', 60)
        'FibraComb2'     => 50, // Ampliado de 15 a 50 según actualización de BD
        'CodColorComb2'  => 10, // Según migración: string('CodColorComb2', 10)
        'NombreCC2'      => 60, // Según migración: string('NombreCC2', 60)
        'FibraComb3'     => 50, // Ampliado de 15 a 50 según actualización de BD
        'CodColorComb3'  => 40,
        'NombreCC3'      => 60, // Según migración: string('NombreCC3', 60)
        'FibraComb4'     => 50, // Ampliado de 15 a 50 según actualización de BD
        'CodColorComb4'  => 40,
        'NombreCC4'      => 60, // Según migración: string('NombreCC4', 60)
        'FibraComb5'     => 50, // Ampliado de 15 a 50 según actualización de BD
        'CodColorComb5'  => 40,
        'NombreCC5'      => 60, // Según migración: string('NombreCC5', 60)
        'FibraPie'       => 15,
        'CodColorCtaPie' => 10,
        'NombreCPie'     => 60,
        'NombreProducto' => 50,
        'MedidaPlano'    => null, // Integer
        'Rasurado'       => 10,
        // Campos adicionales para ReqProgramaTejido (UpdateTejido, etc.)
        'InventSizeId'   => 20,
        'ItemId'         => 20,
        'TamanoClave'    => 50,
        'FibraRizo'      => 40,
        'CuentaPie'      => 20,
        'CalibreComb1'   => 40,
        'CalibreComb2'   => 40,
        'CalibreComb3'   => 40,
        'CalibreComb4'   => 40,
        'CalibreComb5'   => 40,
    ];

    /**
     * Trunca un valor string según el límite del campo
     * Si no hay límite definido, retorna el valor sin modificar
     *
     * @param string $fieldName Nombre del campo en BD
     * @param mixed $value Valor a truncar
     * @return mixed Valor truncado o null/original si no aplica
     */
    public static function truncate(string $fieldName, $value): mixed
    {
        // Null o no string → no hacer nada
        if ($value === null || $value === '') {
            return $value;
        }

        // Convertir a string si no lo es
        $str = (string) $value;

        // Buscar límite del campo
        $limit = self::$fieldLimits[$fieldName] ?? null;

        // Si no hay límite, retornar tal cual
        if ($limit === null) {
            return $str;
        }

        // Si la longitud es mayor, truncar
        if (strlen($str) > $limit) {
            return substr($str, 0, $limit);
        }

        return $str;
    }

    /**
     * Trunca múltiples campos de un array
     * Útil para aplicar truncamiento masivo a un payload
     *
     * @param array $data Array con campo => valor
     * @return array Array con valores truncados
     */
    public static function truncateArray(array $data): array
    {
        $result = [];
        foreach ($data as $field => $value) {
            $result[$field] = self::truncate($field, $value);
        }
        return $result;
    }

    /**
     * Trunca los atributos string de un modelo Eloquent in-place
     * para evitar el error SQL "String or binary data would be truncated".
     *
     * @param object $model Modelo con getAttributes()
     * @return void
     */
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

    /**
     * Obtiene el límite de un campo
     * Retorna null si no hay límite
     */
    public static function getLimit(string $fieldName): ?int
    {
        return self::$fieldLimits[$fieldName] ?? null;
    }

    /**
     * Verifica si un valor excede el límite del campo
     */
    public static function exceedsLimit(string $fieldName, $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }
        $limit = self::getLimit($fieldName);
        if ($limit === null) {
            return false;
        }
        return strlen((string) $value) > $limit;
    }
}
?>
