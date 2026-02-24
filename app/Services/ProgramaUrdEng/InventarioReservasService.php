<?php

declare(strict_types=1);

namespace App\Services\ProgramaUrdEng;

use App\Models\Inventario\InvTelasReservadas;
use App\Models\Tejido\TejInventarioTelares;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Lógica de negocio: inventario disponible (TI-PRO), reservas por telar,
 * reservar y cancelar. Usado por InventarioDisponibleController (GET) y ReservaInventarioController (POST).
 */
class InventarioReservasService
{
    /** Conexión TI / ubicación */
    private const TI_CONN     = 'sqlsrv_ti';
    private const DATAAREA    = 'PRO';
    private const LOC_TELA    = 'A-JUL/TELA';
    private const LIMIT_TI    = 2000;

    private const PATTERN_RIZO = '%JU-ENG-RI%';
    private const PATTERN_PIE  = '%JU-ENG-PI%';

    /** Columnas que el front puede filtrar (NoTelarId se aplica post-merge) */
    public const ALLOWED_FILTERS = [
        'ItemId', 'ConfigId', 'InventSizeId', 'InventColorId', 'InventLocationId',
        'InventBatchId', 'WMSLocationId', 'InventSerialId', 'Tipo',
        'InventQty', 'Metros', 'ProdDate', 'NoTelarId',
    ];

    /** Mapa UI -> columna SQL (para filtros) */
    private const FILTER_SQL = [
        'ItemId'           => 's.ItemId',
        'ConfigId'         => 'd.ConfigId',
        'InventSizeId'     => 'd.InventSizeId',
        'InventColorId'    => 'd.InventColorId',
        'InventLocationId' => 'd.InventLocationId',
        'InventBatchId'    => 'd.InventBatchId',
        'WMSLocationId'    => 'd.WMSLocationId',
        'InventSerialId'   => 'd.InventSerialId',
        'InventQty'        => 'ISNULL(s.PhysicalInvent,0)',
        'Metros'           => 'ISNULL(ser.TwMts,0)',
        'ProdDate'         => 'ser.ProdDate',
    ];

    /** Normaliza filtros desde querystring/body a [{columna, valor}] */
    public function normalizeFilters($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $f) {
            if (is_array($f) && isset($f['columna'], $f['valor'])) {
                $out[] = ['columna' => (string) $f['columna'], 'valor' => (string) $f['valor']];
            }
        }
        return $out;
    }

    /** Normaliza un valor para la clave dimensional. */
    public function normalizeDimValue($value): string
    {
        if ($value === null || $value === 'null' || $value === 'NULL') {
            return '';
        }
        return trim((string) $value);
    }

    /** Llave única de la pieza (para cruzar con reservas locales). */
    public function dimKey($obj): string
    {
        $fields = [
            'ItemId', 'ConfigId', 'InventSizeId', 'InventColorId',
            'InventLocationId', 'InventBatchId', 'WMSLocationId', 'InventSerialId',
        ];
        $values = [];
        foreach ($fields as $field) {
            if (is_array($obj)) {
                $value = $obj[$field] ?? null;
            } else {
                $value = $obj->$field ?? null;
            }
            $values[] = $this->normalizeDimValue($value);
        }
        return implode('|', $values);
    }

    /**
     * Inventario disponible (TI-PRO) fusionado con reservas locales.
     * Retorna ['data' => array, 'total' => int].
     */
    public function getDisponibleData(array $filtros): array
    {
        $filtroNoTelarId = null;
        $filtrosTi = [];
        foreach ($filtros as $f) {
            if (($f['columna'] ?? '') === 'NoTelarId') {
                $filtroNoTelarId = trim($f['valor'] ?? '');
            } else {
                $filtrosTi[] = $f;
            }
        }

        $reservadasMap = [];
        $reservadasCompletas = [];

        InvTelasReservadas::query()
            ->where('Status', 'Reservado')
            ->select([
                'Id', 'ItemId', 'ConfigId', 'InventSizeId', 'InventColorId',
                'InventLocationId', 'InventBatchId', 'WMSLocationId', 'InventSerialId',
                'NoTelarId', 'Tipo', 'Metros', 'InventQty', 'ProdDate', 'SalonTejidoId',
            ])
            ->chunk(500, function ($chunk) use (&$reservadasMap, &$reservadasCompletas) {
                foreach ($chunk as $r) {
                    $key = $this->dimKey($r);
                    $reservadasMap[$key] = $r->NoTelarId;
                    $reservadasCompletas[$key] = $r;
                }
            });

        $rows = $this->queryDisponibleFromTiPro($filtrosTi, self::LIMIT_TI);
        $keysEnTI = [];
        $out = [];
        $wantOnlyAvailable = false;
        if ($filtroNoTelarId !== null && $filtroNoTelarId !== '') {
            $v = mb_strtolower($filtroNoTelarId, 'UTF-8');
            $wantOnlyAvailable = in_array($v, ['null', 'vacío', 'vacio', 'disponible'], true);
        }

        foreach ($rows as $row) {
            $rowKey = $this->dimKey($row);
            $keysEnTI[$rowKey] = true;
            $row->NoTelarId = $reservadasMap[$rowKey] ?? null;

            if ($filtroNoTelarId !== null && $filtroNoTelarId !== '') {
                if ($wantOnlyAvailable) {
                    if ($row->NoTelarId !== null && $row->NoTelarId !== '') {
                        continue;
                    }
                } else {
                    if (stripos((string) ($row->NoTelarId ?? ''), $filtroNoTelarId) === false) {
                        continue;
                    }
                }
            }
            $out[] = $row;
        }

        foreach ($reservadasCompletas as $key => $reserva) {
            if (isset($keysEnTI[$key])) {
                continue;
            }
            if ($wantOnlyAvailable) {
                continue;
            }
            if ($filtroNoTelarId !== null && $filtroNoTelarId !== '') {
                if (stripos((string) ($reserva->NoTelarId ?? ''), $filtroNoTelarId) === false) {
                    continue;
                }
            }

            $rowReservado = (object) [
                'ItemId' => $reserva->ItemId ?? '',
                'ConfigId' => $reserva->ConfigId ?? '',
                'InventSizeId' => $reserva->InventSizeId ?? '',
                'InventColorId' => $reserva->InventColorId ?? '',
                'InventLocationId' => $reserva->InventLocationId ?? '',
                'InventBatchId' => $reserva->InventBatchId ?? '',
                'WMSLocationId' => $reserva->WMSLocationId ?? '',
                'InventSerialId' => $reserva->InventSerialId ?? '',
                'Tipo' => $reserva->Tipo ?? null,
                'Metros' => $reserva->Metros ?? 0,
                'InventQty' => $reserva->InventQty ?? 0,
                'ProdDate' => $reserva->ProdDate
                    ? ($reserva->ProdDate instanceof Carbon ? $reserva->ProdDate->toDateTimeString() : (string) $reserva->ProdDate)
                    : null,
                'NoTelarId' => $reserva->NoTelarId ?? '',
                'ReservaId' => $reserva->Id ?? null,
                'NoDisponibleEnTI' => true,
                'SalonTejidoId' => $reserva->SalonTejidoId ?? null,
            ];

            $cumpleFiltros = true;
            foreach ($filtrosTi as $f) {
                $col = $f['columna'] ?? null;
                $val = trim($f['valor'] ?? '');
                if (!$col || $val === '') {
                    continue;
                }
                $valorCampo = $rowReservado->$col ?? null;
                $valorCampoStr = $valorCampo === null ? '' : trim((string) $valorCampo);

                if ($col === 'Tipo') {
                    $v = mb_strtolower($val, 'UTF-8');
                    $tipoReserva = mb_strtolower($rowReservado->Tipo ?? '', 'UTF-8');
                    if ((strpos($v, 'rizo') !== false && $tipoReserva !== 'rizo') ||
                        (strpos($v, 'pie') !== false && $tipoReserva !== 'pie')) {
                        $cumpleFiltros = false;
                        break;
                    }
                    continue;
                }
                if (stripos($valorCampoStr, $val) === false) {
                    $cumpleFiltros = false;
                    break;
                }
            }
            if ($cumpleFiltros) {
                $out[] = $rowReservado;
            }
        }

        return ['data' => $out, 'total' => count($out)];
    }

    /** Reservas activas por número de telar. */
    public function getReservasPorTelar(string $noTelar)
    {
        return InvTelasReservadas::where('NoTelarId', $noTelar)
            ->where('Status', 'Reservado')
            ->orderByDesc('Id')
            ->limit(500)
            ->get()
            ->map(function ($r) {
                $r->dimKey = $this->dimKey($r);
                return $r;
            });
    }

    /** Diagnóstico: reservas recientes con dimKey. */
    public function getDiagnosticoReservas(?string $noTelar, int $limit): \Illuminate\Support\Collection
    {
        $query = InvTelasReservadas::where('Status', 'Reservado')->orderByDesc('Id');
        if ($noTelar !== null && $noTelar !== '') {
            $query->where('NoTelarId', $noTelar);
        }
        return $query->limit($limit)->get();
    }

    /**
     * Ejecuta la reserva (crear registro + actualizar telar).
     * $data ya validado y con TejInventarioTelaresId, Status, Fecha, Turno, etc.
     * Retorna ['created' => bool, 'message' => string].
     */
    public function ejecutarReserva(array $data): array
    {
        $created = false;
        $msg = 'Pieza reservada correctamente';

        // Derivar InventBatchId del prefijo de InventSerialId (ej. 00061-744 → 00061)
        $serialId = trim((string) ($data['InventSerialId'] ?? ''));
        if ($serialId !== '' && strpos($serialId, '-') !== false) {
            $prefijo = trim(explode('-', $serialId)[0] ?? '');
            if ($prefijo !== '') {
                $data['InventBatchId'] = $prefijo;
            }
        }

        try {
            InvTelasReservadas::create($data);
            $created = true;
        } catch (\Illuminate\Database\QueryException $qe) {
            if (!in_array($qe->getCode(), [2601, 2627], true)) {
                throw $qe;
            }
            $msg = 'La pieza ya estaba reservada (no se duplicó).';
        }

        $tejInventarioTelaresId = $data['TejInventarioTelaresId'] ?? null;
        if ($tejInventarioTelaresId) {
            try {
                $telar = TejInventarioTelares::where('id', $tejInventarioTelaresId)
                    ->where('status', 'Activo')
                    ->first();
                if ($telar) {
                    $telar->Reservado = true;
                    if (isset($data['ConfigId'])) {
                        $telar->ConfigId = $this->normalizeDimValue($data['ConfigId']);
                    }
                    if (isset($data['InventSizeId'])) {
                        $telar->InventSizeId = $this->normalizeDimValue($data['InventSizeId']);
                    }
                    if (isset($data['InventColorId'])) {
                        $telar->InventColorId = $this->normalizeDimValue($data['InventColorId']);
                    }
                    if (array_key_exists('InventBatchId', $data)) {
                        $telar->LoteProveedor = $this->normalizeDimValue($data['InventBatchId']);
                    }
                    if (array_key_exists('NoProveedor', $data) && $data['NoProveedor'] !== null && $data['NoProveedor'] !== '') {
                        $telar->NoProveedor = $this->normalizeDimValue($data['NoProveedor']);
                    }
                    $telar->save();
                } else {
                    Log::warning('No se encontró registro específico para reservar por ID', [
                        'tej_inventario_telares_id' => $tejInventarioTelaresId,
                    ]);
                }
            } catch (Throwable $e) {
                Log::warning('Error al actualizar campo Reservado en telar', [
                    'tej_inventario_telares_id' => $tejInventarioTelaresId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return ['created' => $created, 'message' => $msg];
    }

    /**
     * Cancela reserva(s) por Id o por clave dimensional.
     * Retorna ['updated' => bool]. Si no quedan reservas activas para el telar, pone Reservado=0.
     */
    public function ejecutarCancelar(array $input): array
    {
        $q = InvTelasReservadas::query();
        if (!empty($input['Id'])) {
            $q->where('Id', $input['Id']);
        } else {
            $q->where('NoTelarId', $input['NoTelarId'])
                ->where('ItemId', $input['ItemId'])
                ->where('ConfigId', $input['ConfigId'] ?? '')
                ->where('InventSizeId', $input['InventSizeId'] ?? '')
                ->where('InventColorId', $input['InventColorId'] ?? '')
                ->where('InventLocationId', $input['InventLocationId'] ?? '')
                ->where('InventBatchId', $input['InventBatchId'] ?? '')
                ->where('WMSLocationId', $input['WMSLocationId'] ?? '')
                ->where('InventSerialId', $input['InventSerialId'] ?? '');
        }

        $reservasACancelar = $q->get();
        $noTelarId = $reservasACancelar->isNotEmpty() ? $reservasACancelar->first()->NoTelarId : null;
        $updated = $q->update(['Status' => 'Cancelado']);

        if ($updated > 0 && $noTelarId) {
            try {
                $tieneReservasActivas = InvTelasReservadas::where('NoTelarId', $noTelarId)
                    ->where('Status', 'Reservado')
                    ->exists();
                if (!$tieneReservasActivas) {
                    TejInventarioTelares::where('no_telar', $noTelarId)
                        ->where('status', 'Activo')
                        ->update(['Reservado' => false]);
                }
            } catch (Throwable $e) {
                Log::warning('Error al actualizar campo Reservado al cancelar reserva', [
                    'noTelarId' => $noTelarId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return ['updated' => $updated > 0];
    }

    /** Query TI-PRO: inventario disponible con filtros. */
    private function queryDisponibleFromTiPro(array $filtros = [], int $limit = self::LIMIT_TI): array
    {
        $cn = DB::connection(self::TI_CONN);
        $params = [
            self::PATTERN_RIZO, self::PATTERN_PIE,
            self::DATAAREA, self::LOC_TELA, self::DATAAREA,
            self::DATAAREA, self::PATTERN_RIZO, self::PATTERN_PIE,
        ];
        $sql = "
SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;

SELECT TOP ($limit)
    LTRIM(RTRIM(ISNULL(s.ItemId, ''))) AS ItemId,
    LTRIM(RTRIM(ISNULL(d.ConfigId, ''))) AS ConfigId,
    LTRIM(RTRIM(ISNULL(d.InventSizeId, ''))) AS InventSizeId,
    LTRIM(RTRIM(ISNULL(d.InventColorId, ''))) AS InventColorId,
    LTRIM(RTRIM(ISNULL(d.InventLocationId, ''))) AS InventLocationId,
    LTRIM(RTRIM(ISNULL(d.InventBatchId, ''))) AS InventBatchId,
    LTRIM(RTRIM(ISNULL(d.WMSLocationId, ''))) AS WMSLocationId,
    LTRIM(RTRIM(ISNULL(d.InventSerialId, ''))) AS InventSerialId,
    CASE
        WHEN s.ItemId LIKE ? THEN 'Rizo'
        WHEN s.ItemId LIKE ? THEN 'Pie'
        ELSE NULL
    END AS Tipo,
    ISNULL(ser.TwMts, 0)          AS Metros,
    ISNULL(s.PhysicalInvent, 0)   AS InventQty,
    ser.ProdDate
FROM InventSum AS s WITH (NOLOCK)
INNER JOIN InventDim AS d WITH (NOLOCK)
        ON d.InventDimId = s.InventDimId
       AND d.DATAAREAID  = ?
       AND d.InventLocationId = ?
LEFT JOIN InventSerial AS ser WITH (NOLOCK)
       ON ser.InventSerialId = d.InventSerialId
      AND ser.ItemId        = s.ItemId
      AND ser.DATAAREAID    = ?
WHERE s.DATAAREAID   = ?
  AND s.AvailPhysical > 0
  AND (s.ItemId LIKE ? OR s.ItemId LIKE ?)
";
        foreach ($filtros as $f) {
            $col = $f['columna'] ?? null;
            $val = trim($f['valor'] ?? '');
            if (!$col || $val === '') {
                continue;
            }
            if ($col === 'Tipo') {
                $v = mb_strtolower($val, 'UTF-8');
                if (strpos($v, 'rizo') !== false) {
                    $sql .= " AND s.ItemId LIKE ? ";
                    $params[] = self::PATTERN_RIZO;
                } elseif (strpos($v, 'pie') !== false) {
                    $sql .= " AND s.ItemId LIKE ? ";
                    $params[] = self::PATTERN_PIE;
                }
                continue;
            }
            if ($col === 'ProdDate') {
                try {
                    $date = Carbon::parse($val)->format('Y-m-d');
                    $sql .= " AND CAST(ser.ProdDate AS DATE) = ? ";
                    $params[] = $date;
                } catch (Throwable) {
                    $sql .= " AND CAST(ser.ProdDate AS NVARCHAR(23)) LIKE ? ";
                    $params[] = '%' . $val . '%';
                }
                continue;
            }
            if ($col === 'InventQty' || $col === 'Metros') {
                $expr = self::FILTER_SQL[$col];
                if (is_numeric($val)) {
                    $sql .= " AND $expr = ? ";
                    $params[] = (float) $val;
                } else {
                    $sql .= " AND CAST($expr AS NVARCHAR(50)) LIKE ? ";
                    $params[] = '%' . $val . '%';
                }
                continue;
            }
            if (isset(self::FILTER_SQL[$col])) {
                $expr = self::FILTER_SQL[$col];
                $sql .= " AND LOWER(CAST($expr AS NVARCHAR(100))) LIKE ? ";
                $params[] = '%' . mb_strtolower($val, 'UTF-8') . '%';
            }
        }
        $sql .= " ORDER BY s.ItemId, d.ConfigId;";
        return $cn->select($sql, $params);
    }
}
