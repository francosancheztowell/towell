<?php

namespace App\Helpers;

/**
 * Helper para truncar valores STRING según límites de BD
 * Basado en la migración 2025_10_10_000002_create_req_programa_tejido_table.php
 */
class StringTruncator
{
    /**
     * Mapa de limites de campos en BD (desde migración)
     * Si no aparece, no hay límite o es numérico
     */
    private static array $fieldLimits = [
        'CuentaRizo'     => 10,
        'SalonTejidoId'  => 10,
        'NoTelarId'      => 10,
        'Ultimo'         => 2,
        'CambioHilo'     => 2,
        'Maquina'        => 15,
        'FlogsId'        => 20,
        'NombreProyecto' => 60,
        'CustName'       => 60,
        'AplicacionId'   => 10,
        'Observaciones'  => 100,
        'TipoPedido'     => 20,
        'DobladilloId'   => 20,
        'CodColorTrama'  => null, // String sin límite explícito en migración
        'ColorTrama'     => null,
        'FibraTrama'     => 15,
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
        'FibraComb1'     => 15,
        'CodColorComb1'  => null,
        'NombreCC1'      => null,
        'FibraComb2'     => 15,
        'CodColorComb2'  => null,
        'NombreCC2'      => null,
        'FibraComb3'     => 15,
        'CodColorComb3'  => null,
        'NombreCC3'      => null,
        'FibraComb4'     => 15,
        'CodColorComb4'  => null,
        'NombreCC4'      => null,
        'FibraComb5'     => 15,
        'CodColorComb5'  => null,
        'NombreCC5'      => null,
        'FibraPie'       => 15,
        'CodColorCtaPie' => null,
        'NombreCPie'     => null,
        'NombreProducto' => 60,   // Extrapolado de NombreProyecto
        'MedidaPlano'    => null, // Integer
        'Rasurado'       => 2,    // Límite de 2 caracteres según migración
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
