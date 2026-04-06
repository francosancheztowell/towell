<?php

namespace App\Services\Planeacion\CatCodificados\Excel;

use Carbon\Carbon;
use DateTimeInterface;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

final class CatCodificadosExcelRowMapper
{
    private const MAX_TEXT_LENGTH = 2040;

    /**
     * @var array<string, int>
     */
    private const FLOAT_PRECISION_FIELDS = [
        'CalibreRizo' => 1,
        'CalibrePie2' => 1,
    ];

    /**
     * @var array<string, bool>
     */
    private const INT_FIELDS = [
        'OrdPrincipal' => true,
        'OrdCompartida' => true,
        'TelarId' => true,
        'Peine' => true,
        'Ancho' => true,
        'Largo' => true,
        'MedidaPlano' => true,
        'VelocidadSTD' => true,
        'NoTiras' => true,
        'Repeticiones' => true,
        'TramaAnchoPeine' => true,
        'LogLuchaTotal' => true,
        'PasadasTramaFondoC1' => true,
        'PasadasComb1' => true,
        'PasadasComb2' => true,
        'PasadasComb3' => true,
        'PasadasComb4' => true,
        'PasadasComb5' => true,
        'MinutosCambio' => true,
        'pzaXrollo' => true,
    ];

    /**
     * @var array<string, bool>
     */
    private const FLOAT_FIELDS = [
        'Produccion' => true,
        'Saldos' => true,
        'Cantidad' => true,
        'Tra' => true,
        'CalibreTrama2' => true,
        'CalibreRizo' => true,
        'CalibreRizo2' => true,
        'CalibrePie' => true,
        'CalibrePie2' => true,
        'NoMarbete' => true,
        'CalTramaFondoC1' => true,
        'CalTramaFondoC12' => true,
        'CalibreComb1' => true,
        'CalibreComb12' => true,
        'CalibreComb2' => true,
        'CalibreComb22' => true,
        'CalibreComb3' => true,
        'CalibreComb32' => true,
        'CalibreComb4' => true,
        'CalibreComb42' => true,
        'CalibreComb5' => true,
        'CalibreComb52' => true,
        'Total' => true,
        'PesoMuestra' => true,
        'Tejidas' => true,
        'CantidadProducir_2' => true,
    ];

    /**
     * @var array<string, bool>
     */
    private const DATE_FIELDS = [
        'FechaTejido' => true,
        'FechaCumplimiento' => true,
        'FechaCompromiso' => true,
    ];

    /**
     * @var array<string, bool>
     */
    private const TIME_FIELDS = [
        'HrInicio' => true,
        'HrTermino' => true,
    ];

    /**
     * @var array<string, bool>
     */
    private const BOOL_FIELDS = [
        'OrdCompartidaLider' => true,
    ];

    /**
     * @param  array<int, mixed>  $row
     * @param  array<int, string>  $columnMap
     * @return array<string, mixed>
     */
    public function map(array $row, array $columnMap): array
    {
        $payload = [];

        foreach ($columnMap as $index => $field) {
            $value = $row[$index] ?? null;
            $cleaned = $this->cleanValue($value, $field);

            if ($cleaned === null) {
                continue;
            }

            $payload[$field] = $cleaned;
        }

        if ($payload === []) {
            return [];
        }

        if (isset($payload['FibraId']) && !isset($payload['ColorTrama'])) {
            $payload['ColorTrama'] = $payload['FibraId'];
        }

        if (isset($payload['FibraComb1']) && !isset($payload['NomColorC1'])) {
            $payload['NomColorC1'] = $payload['FibraComb1'];
        }

        if (isset($payload['FibraComb2']) && !isset($payload['NomColorC2'])) {
            $payload['NomColorC2'] = $payload['FibraComb2'];
        }

        if (isset($payload['FibraComb3']) && !isset($payload['NomColorC3'])) {
            $payload['NomColorC3'] = $payload['FibraComb3'];
        }

        if (isset($payload['FibraComb4']) && !isset($payload['NomColorC4'])) {
            $payload['NomColorC4'] = $payload['FibraComb4'];
        }

        if (isset($payload['FibraComb5']) && !isset($payload['NomColorC5'])) {
            $payload['NomColorC5'] = $payload['FibraComb5'];
        }

        return $payload;
    }

    private function cleanValue(mixed $value, string $field): mixed
    {
        if ($this->isNullLike($value)) {
            return null;
        }

        if (isset(self::DATE_FIELDS[$field])) {
            return $this->parseDate($value);
        }

        if (isset(self::TIME_FIELDS[$field])) {
            return $this->parseTime($value);
        }

        if (isset(self::BOOL_FIELDS[$field])) {
            return $this->parseBool($value);
        }

        if (isset(self::INT_FIELDS[$field])) {
            return $this->parseInt($value);
        }

        if (isset(self::FLOAT_FIELDS[$field])) {
            return $this->parseFloatForField($value, $field);
        }

        return $this->cleanText($value);
    }

    private function parseDate(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_numeric($value)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        $stringValue = trim((string) $value);
        if ($stringValue === '') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd-M-y', 'd-M-Y', 'm/d/Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $stringValue)->format('Y-m-d');
            } catch (\Throwable) {
            }
        }

        try {
            return Carbon::parse($stringValue)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseTime(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('H:i:s');
        }

        if (is_numeric($value)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('H:i:s');
            } catch (\Throwable) {
                return null;
            }
        }

        $stringValue = trim((string) $value);
        if ($stringValue === '') {
            return null;
        }

        foreach (['H:i:s', 'H:i'] as $format) {
            try {
                return Carbon::createFromFormat($format, $stringValue)->format('H:i:s');
            } catch (\Throwable) {
            }
        }

        return $stringValue;
    }

    private function parseBool(mixed $value): ?int
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (is_numeric($value)) {
            return (float) $value !== 0.0 ? 1 : 0;
        }

        $normalized = mb_strtolower(trim((string) $value));

        return match ($normalized) {
            '1', 'true', 'si', 'sí', 'yes', 'verdadero' => 1,
            '0', 'false', 'no', 'falso' => 0,
            default => null,
        };
    }

    private function parseInt(mixed $value): ?int
    {
        if (!is_numeric($this->normalizeNumericString((string) $value)) && !is_numeric($value)) {
            return null;
        }

        return (int) round((float) $this->normalizeNumericValue($value));
    }

    private function parseFloat(mixed $value): ?float
    {
        if (!is_numeric($this->normalizeNumericString((string) $value)) && !is_numeric($value)) {
            return null;
        }

        $parsed = (float) $this->normalizeNumericValue($value);

        return $parsed;
    }

    private function parseFloatForField(mixed $value, string $field): ?float
    {
        $parsed = $this->parseFloat($value);
        if ($parsed === null) {
            return null;
        }

        if (!isset(self::FLOAT_PRECISION_FIELDS[$field])) {
            return $parsed;
        }

        return round($parsed, self::FLOAT_PRECISION_FIELDS[$field]);
    }

    private function cleanText(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            return $this->stringifyNumber($value);
        }

        $text = trim((string) $value);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = trim($text);

        if ($text === '') {
            return null;
        }

        return mb_substr($text, 0, self::MAX_TEXT_LENGTH);
    }

    private function isNullLike(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            $normalized = mb_strtolower(trim($value));

            return in_array($normalized, ['', 'na', 'n/a', 'null', '-', 'nan'], true);
        }

        return false;
    }

    private function normalizeNumericValue(mixed $value): float
    {
        return (float) $this->normalizeNumericString((string) $value);
    }

    private function normalizeNumericString(string $value): string
    {
        return str_replace(',', '', trim($value));
    }

    private function stringifyNumber(float $value): string
    {
        if (floor($value) === $value) {
            return (string) (int) $value;
        }

        return rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');
    }
}
