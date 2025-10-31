<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Carbon\Carbon;

/**
 * Procesador de datos para importaciones Excel.
 * Extrae toda la lógica de conversión y validación de datos.
 */
class ImportDataProcessor
{
    /**
     * Normaliza una clave de texto (sin tildes, espacios extras, minúsculas).
     */
    public function normKey(string $s): string
    {
        $s = preg_replace('/\s+/u', ' ', $s);
        $s = trim(mb_strtolower($s));

        $from = 'áéíóúüñÁÉÍÓÚÜÑ';
        $to   = 'aeiouunAEIOUUN';
        $s = strtr($s, array_combine(
            preg_split('//u', $from, -1, PREG_SPLIT_NO_EMPTY),
            preg_split('//u', $to, -1, PREG_SPLIT_NO_EMPTY)
        ));

        $s = preg_replace('/[^a-z0-9 ]/u', '', $s);

        return preg_replace('/\s+/u', ' ', $s);
    }

    /**
     * Obtiene un valor STRING con trim y longitud máxima.
     */
    public function getString(array $assoc, array $vals, array $cands, ?int $posIdx1Based, int $maxLen = 255): ?string
    {
        $v = $this->pick($assoc, $vals, $cands, $posIdx1Based);
        if ($v === null) return null;
        $s = trim((string)$v);
        if ($s === '') return null;
        return mb_substr($s, 0, $maxLen);
    }

    /**
     * Obtiene un valor STRING con búsqueda EXACTA primero.
     */
    public function getStringExact(array $assoc, array $vals, array $cands, ?int $posIdx1Based, int $maxLen = 255): ?string
    {
        foreach ($cands as $k) {
            if (array_key_exists($k, $assoc)) {
                $s = trim((string)$assoc[$k]);
                return $s === '' ? null : mb_substr($s, 0, $maxLen);
            }
        }

        $assocNorm = [];
        foreach ($assoc as $k => $v) $assocNorm[$this->normKey($k)] = $v;

        foreach ($cands as $k) {
            $nk = $this->normKey($k);
            if (array_key_exists($nk, $assocNorm)) {
                $s = trim((string)$assocNorm[$nk]);
                return $s === '' ? null : mb_substr($s, 0, $maxLen);
            }
        }

        if ($posIdx1Based !== null) {
            $idx = $posIdx1Based - 1;
            if ($idx >= 0 && $idx < count($vals)) {
                $s = trim((string)($vals[$idx] ?? null));
                return $s === '' ? null : mb_substr($s, 0, $maxLen);
            }
        }
        return null;
    }

    /**
     * Obtiene un valor INTEGER.
     */
    public function getInteger(array $assoc, array $vals, array $cands, ?int $posIdx1Based): ?int
    {
        $v = $this->pick($assoc, $vals, $cands, $posIdx1Based);
        if ($v === null || $v === '') return null;
        if (is_numeric($v)) return (int)$v;
        $s = preg_replace('/[^\d\-]/', '', (string)$v);
        if ($s === '' || $s === '-' || $s === '--') return null;
        return is_numeric($s) ? (int)$s : null;
    }

    /**
     * Obtiene un valor FLOAT (fórmula).
     */
    public function getFloat(array $assoc, array $vals, array $cands, ?int $posIdx1Based): ?float
    {
        $v = $this->pick($assoc, $vals, $cands, $posIdx1Based);
        if ($v === null || $v === '') return null;
        if (is_numeric($v)) return (float)$v;
        $vv = str_replace([' ', ','], ['', '.'], (string)$v);
        return is_numeric($vv) ? (float)$vv : null;
    }

    /**
     * Obtiene un valor DATE (Carbon).
     */
    public function getDate(array $assoc, array $vals, array $cands, ?int $posIdx1Based): ?Carbon
    {
        $v = $this->pick($assoc, $vals, $cands, $posIdx1Based);
        if ($v === null || $v === '') return null;
        try {
            if (is_numeric($v)) return Carbon::instance(ExcelDate::excelToDateTimeObject($v));
            $v = trim((string)$v);
            if ($v === '') return null;
            $formats = ['d-m-Y','d/m/Y','Y-m-d','d-m-y','d/m/y','d M Y'];
            foreach ($formats as $fmt) {
                $dt = Carbon::createFromFormat($fmt, $v);
                if ($dt !== false) return $dt;
            }
            return Carbon::parse($v);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Obtiene valor TOTAL con validación.
     */
    public function getTotalValue(array $assoc, array $vals, ?int $posIdx1Based): ?int
    {
        $totalCandidates = ['Pasadas TOTAL', 'Pasadas|TOTAL', 'TOTAL', 'Total', 'TotalMarbetes', 'Total'];

        foreach ($totalCandidates as $candidate) {
            if (array_key_exists($candidate, $assoc)) {
                $value = $assoc[$candidate];
                if ($value !== null && $value !== '') {
                    $intValue = $this->convertToInt($value);
                    if ($intValue !== null && $this->isValidTotalValue($intValue)) {
                        return $intValue;
                    }
                }
            }
        }

        if ($posIdx1Based !== null) {
            $idx = $posIdx1Based - 1;
            if ($idx >= 0 && $idx < count($vals)) {
                $value = $vals[$idx] ?? null;
                if ($value !== null && $value !== '') {
                    $intValue = $this->convertToInt($value);
                    if ($intValue !== null && $this->isValidTotalValue($intValue)) {
                        return $intValue;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Intenta obtener el valor por:
     *   1) clave exacta,
     *   2) clave exacta normalizada (sin tildes/ni signos),
     *   3) "contiene" sobre claves normalizadas,
     *   4) fallback por posición.
     */
    private function pick(array $assoc, array $vals, array $cands, ?int $posIdx1Based)
    {
        // 1) exacto
        foreach ($cands as $k) {
            if (array_key_exists($k, $assoc)) return $assoc[$k];
        }

        // 2) exacto normalizado
        $assocNorm = [];
        foreach ($assoc as $k => $v) $assocNorm[$this->normKey($k)] = $v;

        foreach ($cands as $k) {
            $nk = $this->normKey($k);
            if (array_key_exists($nk, $assocNorm)) return $assocNorm[$nk];
        }

        // 3) contiene (sobre normalizados)
        foreach ($cands as $k) {
            $nk = $this->normKey($k);
            foreach ($assoc as $kk => $vv) {
                if (str_contains($this->normKey($kk), $nk)) return $vv;
            }
        }

        // 4) fallback por posición
        if ($posIdx1Based !== null) {
            $idx = $posIdx1Based - 1;
            if ($idx >= 0 && $idx < count($vals)) return $vals[$idx] ?? null;
        }

        return null;
    }

    /**
     * Valida que el Total sea > 0.
     */
    private function isValidTotalValue(int $value): bool
    {
        return $value > 0;
    }

    /**
     * Convierte valor a integer.
     */
    private function convertToInt($value): ?int
    {
        if ($value === null || $value === '') return null;
        if (is_numeric($value)) return (int)$value;
        $cleaned = preg_replace('/[^\d\-]/', '', (string)$value);
        if ($cleaned === '' || $cleaned === '-' || $cleaned === '--') return null;
        return is_numeric($cleaned) ? (int)$cleaned : null;
    }
}
