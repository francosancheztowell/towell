<?php

namespace App\Http\Controllers;

use App\Models\InvTelasReservadas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

class InvTelasReservadasController extends Controller
{
    /** ====== Constantes de negocio / conexión ====== */
    private const TI_CONN     = 'sqlsrv_ti';     // alias en config/database.php
    private const DATAAREA    = 'PRO';
    private const LOC_TELA    = 'A-JUL/TELA';
    private const LIMIT_TI    = 2000;

    // Patrones para derivar Tipo a partir de ItemId
    private const PATTERN_RIZO = '%JU-ENG-RI%';
    private const PATTERN_PIE  = '%JU-ENG-PI%';

    /** Columnas que el front puede filtrar (salvo NoTelarId que se aplica post-merge) */
    private const ALLOWED_FILTERS = [
        'ItemId','ConfigId','InventSizeId','InventColorId','InventLocationId',
        'InventBatchId','WMSLocationId','InventSerialId','Tipo',
        'InventQty','Metros','ProdDate','NoTelarId'
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
        // 'Tipo' se maneja especial (por patrones)
    ];

    /**
     * GET inventario disponible (TI-PRO) + marca si ya está reservado localmente.
     * Recibe filtros opcionales: [{ columna, valor }]. Soporta querystring o body.
     */
    public function disponible(Request $request)
    {
        try {
            $filtros = $this->normalizeFilters($request->input('filtros', $request->query('filtros', [])));

            if (!empty($filtros)) {
                $request->validate([
                    'filtros'            => ['array'],
                    'filtros.*.columna'  => ['required','string', Rule::in(self::ALLOWED_FILTERS)],
                    'filtros.*.valor'    => ['required','string'],
                ]);
            }

            // Separar filtro especial NoTelarId (se aplica después del merge)
            $filtroNoTelarId = null;
            $filtrosTi = [];
            foreach ($filtros as $f) {
                if ($f['columna'] === 'NoTelarId') {
                    $filtroNoTelarId = trim($f['valor'] ?? '');
                } else {
                    $filtrosTi[] = $f;
                }
            }

            // 1) Mapa “dimKey -> NoTelarId” de lo ya reservado
            $reservadasMap = [];
            InvTelasReservadas::query()
                ->where('Status', 'Reservado')
                ->select([
                    'ItemId','ConfigId','InventSizeId','InventColorId',
                    'InventLocationId','InventBatchId','WMSLocationId','InventSerialId',
                    'NoTelarId'
                ])
                ->chunk(500, function ($chunk) use (&$reservadasMap) {
                    foreach ($chunk as $r) {
                        $reservadasMap[$this->dimKey($r)] = $r->NoTelarId;
                    }
                });

            // 2) Traer disponible TI-PRO
            $rows = $this->queryDisponibleFromTiPro($filtrosTi, self::LIMIT_TI);

            // 3) Merge + filtro por NoTelarId (si viene)
            $out = [];
            $wantOnlyAvailable = false;
            if ($filtroNoTelarId !== null && $filtroNoTelarId !== '') {
                $v = mb_strtolower($filtroNoTelarId, 'UTF-8');
                $wantOnlyAvailable = in_array($v, ['null','vacío','vacio','disponible'], true);
            }

            foreach ($rows as $row) {
                $row->NoTelarId = $reservadasMap[$this->dimKey($row)] ?? null;

                if ($filtroNoTelarId !== null && $filtroNoTelarId !== '') {
                    if ($wantOnlyAvailable) {
                        if ($row->NoTelarId !== null && $row->NoTelarId !== '') {
                            continue; // ya reservado
                        }
                    } else {
                        if (stripos((string)($row->NoTelarId ?? ''), $filtroNoTelarId) === false) {
                            continue;
                        }
                    }
                }

                $out[] = $row;
            }

            return response()->json([
                'success' => true,
                'data'    => $out,
                'total'   => count($out),
            ]);

        } catch (Throwable $e) {
            Log::error('InvDisponible error', ['msg'=>$e->getMessage()]);
            return response()->json(['success'=>false,'message'=>'Error al obtener inventario disponible'], 500);
        }
    }

    /** POST reservar (idempotente por índice único) */
    public function reservar(Request $request)
    {
        try {
            $data = $request->validate([
                'NoTelarId'        => ['required','string','max:10'],
                'SalonTejidoId'    => ['nullable','string','max:20'],

                'ItemId'           => ['required','string','max:50'],
                'ConfigId'         => ['nullable','string','max:30'],
                'InventSizeId'     => ['nullable','string','max:10'],
                'InventColorId'    => ['nullable','string','max:10'],
                'InventLocationId' => ['nullable','string','max:10'],
                'InventBatchId'    => ['nullable','string','max:20'],
                'WMSLocationId'    => ['nullable','string','max:10'],
                'InventSerialId'   => ['nullable','string','max:20'],

                'Tipo'      => ['nullable','string','max:20'],
                'Metros'    => ['nullable','numeric'],
                'InventQty' => ['nullable','numeric'],
                'ProdDate'  => ['nullable','date'],

                'NumeroEmpleado' => ['nullable','string','max:20'],
                'NombreEmpl'     => ['nullable','string','max:120'],
            ]);

            $data['Status'] = 'Reservado';

            $created = false;
            $msg = 'Pieza reservada correctamente';

            try {
                InvTelasReservadas::create($data);
                $created = true;
            } catch (\Illuminate\Database\QueryException $qe) {
                // 2601/2627 = índice único
                if (!in_array($qe->getCode(), [2601, 2627], true)) {
                    throw $qe;
                }
                $msg = 'La pieza ya estaba reservada (no se duplicó).';
            }

            return response()->json([
                'success' => true,
                'created' => $created,
                'message' => $msg,
            ]);
        } catch (Throwable $e) {
            Log::error('Reservar error', ['msg'=>$e->getMessage()]);
            return response()->json(['success'=>false,'message'=>'Error al reservar la pieza'], 500);
        }
    }

    /** GET reservas por telar */
    public function porTelar(string $noTelar)
    {
        $rows = InvTelasReservadas::where('NoTelarId', $noTelar)
            ->where('Status', 'Reservado')
            ->orderByDesc('Id')
            ->limit(500)
            ->get();

        return response()->json(['success'=>true,'data'=>$rows,'total'=>$rows->count()]);
    }

    /** POST cancelar por Id o por clave dimensional + telar */
    public function cancelar(Request $request)
    {
        $request->validate([
            'Id'               => ['nullable','integer'],
            'NoTelarId'        => ['required_without:Id','string'],
            'ItemId'           => ['required_without:Id','string'],
            'ConfigId'         => ['nullable','string'],
            'InventSizeId'     => ['nullable','string'],
            'InventColorId'    => ['nullable','string'],
            'InventLocationId' => ['nullable','string'],
            'InventBatchId'    => ['nullable','string'],
            'WMSLocationId'    => ['nullable','string'],
            'InventSerialId'   => ['nullable','string'],
        ]);

        $q = InvTelasReservadas::query();

        if ($request->filled('Id')) {
            $q->where('Id', $request->Id);
        } else {
            $q->where('NoTelarId', $request->NoTelarId)
              ->where('ItemId', $request->ItemId)
              ->where('ConfigId', $request->ConfigId)
              ->where('InventSizeId', $request->InventSizeId)
              ->where('InventColorId', $request->InventColorId)
              ->where('InventLocationId', $request->InventLocationId)
              ->where('InventBatchId', $request->InventBatchId)
              ->where('WMSLocationId', $request->WMSLocationId)
              ->where('InventSerialId', $request->InventSerialId);
        }

        $updated = $q->update(['Status' => 'Cancelado']);

        return response()->json(['success'=>true,'updated'=>$updated>0]);
    }

    /* ===================== Helpers ===================== */

    /** Normaliza filtros desde querystring/body a [{columna,valor}] */
    private function normalizeFilters($raw): array
    {
        if (!is_array($raw)) return [];
        $out = [];
        foreach ($raw as $f) {
            if (is_array($f) && isset($f['columna'], $f['valor'])) {
                $out[] = ['columna' => (string)$f['columna'], 'valor' => (string)$f['valor']];
            }
        }
        return $out;
    }

    /** Llave única de la pieza (para cruzar con reservas locales) */
    private function dimKey($obj): string
    {
        if (is_array($obj)) {
            return implode('|', [
                $obj['ItemId'] ?? null,
                $obj['ConfigId'] ?? null,
                $obj['InventSizeId'] ?? null,
                $obj['InventColorId'] ?? null,
                $obj['InventLocationId'] ?? null,
                $obj['InventBatchId'] ?? null,
                $obj['WMSLocationId'] ?? null,
                $obj['InventSerialId'] ?? null,
            ]);
        }
        return implode('|', [
            $obj->ItemId ?? null,
            $obj->ConfigId ?? null,
            $obj->InventSizeId ?? null,
            $obj->InventColorId ?? null,
            $obj->InventLocationId ?? null,
            $obj->InventBatchId ?? null,
            $obj->WMSLocationId ?? null,
            $obj->InventSerialId ?? null,
        ]);
    }

    /**
     * Ejecuta el SELECT en TI-PRO con todos los filtros base:
     * - s.AvailPhysical > 0
     * - s.DATAAREAID = d.DATAAREAID = ser.DATAAREAID = 'PRO'
     * - d.InventLocationId = 'A-JUL/TELA'
     * - JOINs: d.InventDimId = s.InventDimId; ser.InventSerialId = d.InventSerialId AND ser.ItemId = s.ItemId
     * - Tipo por patrones del ItemId.
     */
    private function queryDisponibleFromTiPro(array $filtros = [], int $limit = self::LIMIT_TI): array
    {
        $cn = DB::connection(self::TI_CONN);

        $sql = "
SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;

SELECT TOP ($limit)
    s.ItemId,
    d.ConfigId,
    d.InventSizeId,
    d.InventColorId,
    d.InventLocationId,
    d.InventBatchId,
    d.WMSLocationId,
    d.InventSerialId,
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
        $params = [
            self::PATTERN_RIZO,
            self::PATTERN_PIE,
            self::DATAAREA,
            self::LOC_TELA,
            self::DATAAREA,
            self::DATAAREA,
            self::PATTERN_RIZO,
            self::PATTERN_PIE,
        ];

        // Aplicar filtros adicionales
        foreach ($filtros as $f) {
            $col = $f['columna'] ?? null;
            $val = trim($f['valor'] ?? '');
            if (!$col || $val === '') continue;

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
                // Igualdad por fecha (DATE)
                try {
                    $date = \Carbon\Carbon::parse($val)->format('Y-m-d');
                    $sql .= " AND CAST(ser.ProdDate AS DATE) = ? ";
                    $params[] = $date;
                } catch (Throwable) {
                    $sql .= " AND CAST(ser.ProdDate AS NVARCHAR(23)) LIKE ? ";
                    $params[] = '%'.$val.'%';
                }
                continue;
            }

            if ($col === 'InventQty' || $col === 'Metros') {
                $expr = self::FILTER_SQL[$col]; // numéricos
                if (is_numeric($val)) {
                    $sql .= " AND $expr = ? ";
                    $params[] = (float)$val;
                } else {
                    $sql .= " AND CAST($expr AS NVARCHAR(50)) LIKE ? ";
                    $params[] = '%'.$val.'%';
                }
                continue;
            }

            // Texto genérico
            if (isset(self::FILTER_SQL[$col])) {
                $expr = self::FILTER_SQL[$col];
                $sql  .= " AND LOWER(CAST($expr AS NVARCHAR(100))) LIKE ? ";
                $params[] = '%'.mb_strtolower($val,'UTF-8').'%';
            }
        }

        $sql .= " ORDER BY s.ItemId, d.ConfigId;";

        return $cn->select($sql, $params);
    }
}
