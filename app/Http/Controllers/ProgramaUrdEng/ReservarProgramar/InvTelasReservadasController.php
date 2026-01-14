<?php

namespace App\Http\Controllers\ProgramaUrdEng\ReservarProgramar;

use App\Http\Controllers\Controller;
use App\Models\Inventario\InvTelasReservadas;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

            // 1) Cargar todas las reservas activas y crear mapas
            $reservadasMap = [];  // dimKey -> NoTelarId
            $reservadasCompletas = [];  // dimKey -> objeto completo de reserva
            $totalReservadas = 0;

            InvTelasReservadas::query()
                ->where('Status', 'Reservado')
                ->select([
                    'Id', 'ItemId','ConfigId','InventSizeId','InventColorId',
                    'InventLocationId','InventBatchId','WMSLocationId','InventSerialId',
                    'NoTelarId', 'Tipo', 'Metros', 'InventQty', 'ProdDate', 'SalonTejidoId'
                ])
                ->chunk(500, function ($chunk) use (&$reservadasMap, &$reservadasCompletas, &$totalReservadas) {
                    foreach ($chunk as $r) {
                        $key = $this->dimKey($r);
                        $reservadasMap[$key] = $r->NoTelarId;
                        $reservadasCompletas[$key] = $r;
                        $totalReservadas++;
                    }
                });

            // 2) Traer disponible TI-PRO
            $rows = $this->queryDisponibleFromTiPro($filtrosTi, self::LIMIT_TI);

            // 3) Merge: marcar registros de TI-PRO que están reservados
            $keysEnTI = []; // Claves dimensionales encontradas en TI-PRO
            $out = [];
            $wantOnlyAvailable = false;
            if ($filtroNoTelarId !== null && $filtroNoTelarId !== '') {
                $v = mb_strtolower($filtroNoTelarId, 'UTF-8');
                $wantOnlyAvailable = in_array($v, ['null','vacío','vacio','disponible'], true);
            }

            $coincidencias = 0;
            $noCoincidencias = 0;
            $sampleKeys = [];

            foreach ($rows as $row) {
                $rowKey = $this->dimKey($row);
                $keysEnTI[$rowKey] = true;
                $row->NoTelarId = $reservadasMap[$rowKey] ?? null;

                if ($row->NoTelarId !== null) {
                    $coincidencias++;
                    if (count($sampleKeys) < 3) {
                        $sampleKeys[] = [
                            'key' => $rowKey,
                            'noTelarId' => $row->NoTelarId,
                            'itemId' => $row->ItemId ?? null,
                        ];
                    }
                } else {
                    $noCoincidencias++;
                }

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

            // 4) Agregar reservas que NO están en TI-PRO pero que están reservadas
            // Esto incluye casos donde el inventario ya fue consumido o movido
            $reservasNoEnTI = 0;
            foreach ($reservadasCompletas as $key => $reserva) {
                // Si no está en TI-PRO
                if (!isset($keysEnTI[$key])) {
                    // Si estamos filtrando por "solo disponibles", no agregar reservas
                    if ($wantOnlyAvailable) {
                        continue;
                    }

                    // Si hay filtro por NoTelarId, verificar si coincide
                    if ($filtroNoTelarId !== null && $filtroNoTelarId !== '') {
                        // Si filtramos por un telar específico, verificar
                        if (stripos((string)($reserva->NoTelarId ?? ''), $filtroNoTelarId) === false) {
                            continue;
                        }
                    }
                    // Si no hay filtro por NoTelarId, agregar todas las reservas que no están en TI-PRO

                    // Crear objeto compatible con el formato esperado
                    $rowReservado = (object)[
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
                        'ProdDate' => $reserva->ProdDate ? ($reserva->ProdDate instanceof Carbon ? $reserva->ProdDate->toDateTimeString() : (string)$reserva->ProdDate) : null,
                        'NoTelarId' => $reserva->NoTelarId ?? '',
                        'ReservaId' => $reserva->Id ?? null,
                        'NoDisponibleEnTI' => true, // Flag para indicar que no está en TI-PRO
                        'SalonTejidoId' => $reserva->SalonTejidoId ?? null,
                    ];

                    // Aplicar filtros adicionales si existen (excepto NoTelarId que ya se procesó)
                    $cumpleFiltros = true;
                    foreach ($filtrosTi as $f) {
                        $col = $f['columna'] ?? null;
                        $val = trim($f['valor'] ?? '');
                        if (!$col || $val === '') continue;

                        $valorCampo = $rowReservado->$col ?? null;
                        $valorCampoStr = is_null($valorCampo) ? '' : trim((string)$valorCampo);

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
                        $reservasNoEnTI++;
                    } else {
                    }
                } else {
                }
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

                // NumeroEmpleado y NombreEmpl se asignan automáticamente desde el usuario autenticado
                'NumeroEmpleado' => ['nullable','string','max:20'],
                'NombreEmpl'     => ['nullable','string','max:120'],
            ]);

            // Asignar Status
            $data['Status'] = 'Reservado';

            // ProdDate se procesa automáticamente por el mutator del modelo
            // El mutator convierte fechas inválidas (1900-01-01) a NULL y valida el formato

            // Obtener información del usuario autenticado y asignar automáticamente
            // Estos campos siempre se toman del usuario autenticado para garantizar la integridad
            $usuario = Auth::user();
            if ($usuario) {
                // Asignar automáticamente desde el usuario autenticado
                $data['NumeroEmpleado'] = $usuario->numero_empleado ?? null;
                $data['NombreEmpl'] = $usuario->nombre ?? null;
            } else {
                // Si no hay usuario autenticado, limpiar estos campos (no debería pasar en producción)
                $data['NumeroEmpleado'] = null;
                $data['NombreEmpl'] = null;
                Log::warning('Reservar: No hay usuario autenticado', ['request' => $request->all()]);
            }

            // Normalizar valores dimensionales para consistencia
            $dimFields = ['ItemId', 'ConfigId', 'InventSizeId', 'InventColorId',
                         'InventLocationId', 'InventBatchId', 'WMSLocationId', 'InventSerialId'];
            foreach ($dimFields as $field) {
                if (isset($data[$field])) {
                    $data[$field] = $this->normalizeDimValue($data[$field]);
                }
            }

            $created = false;
            $msg = 'Pieza reservada correctamente';

            try {
                $reserva = InvTelasReservadas::create($data);
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
            Log::error('Reservar error', ['msg'=>$e->getMessage(), 'trace'=>$e->getTraceAsString()]);
            return response()->json(['success'=>false,'message'=>'Error al reservar la pieza: ' . $e->getMessage()], 500);
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

        // Generar dimKey para cada reserva para verificación
        $rowsWithKey = $rows->map(function($r) {
            $r->dimKey = $this->dimKey($r);
            return $r;
        });

        return response()->json([
            'success' => true,
            'data'    => $rowsWithKey,
            'total'   => $rows->count()
        ]);
    }

    /** GET método de diagnóstico: verificar reservas recientes y sus dimKeys */
    public function diagnosticarReservas(Request $request)
    {
        try {
            $limit = (int)($request->query('limit', 10));
            $noTelar = $request->query('noTelar');

            $query = InvTelasReservadas::where('Status', 'Reservado')
                ->orderByDesc('Id');

            if ($noTelar) {
                $query->where('NoTelarId', $noTelar);
            }

            $reservas = $query->limit($limit)->get();

            $diagnostico = $reservas->map(function($r) {
                return [
                    'id' => $r->Id,
                    'noTelarId' => $r->NoTelarId,
                    'itemId' => $r->ItemId,
                    'configId' => $r->ConfigId,
                    'inventSizeId' => $r->InventSizeId,
                    'inventColorId' => $r->InventColorId,
                    'inventLocationId' => $r->InventLocationId,
                    'inventBatchId' => $r->InventBatchId,
                    'wmsLocationId' => $r->WMSLocationId,
                    'inventSerialId' => $r->InventSerialId,
                    'dimKey' => $this->dimKey($r),
                    'status' => $r->Status,
                    'created_at' => $r->created_at?->toIso8601String(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $diagnostico,
                'total' => $reservas->count(),
            ]);
        } catch (Throwable $e) {
            Log::error('DiagnosticarReservas error', ['msg' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error al diagnosticar reservas: ' . $e->getMessage()
            ], 500);
        }
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

    /**
     * Normaliza un valor para la clave dimensional.
     * Convierte NULL a string vacío, elimina espacios y normaliza.
     */
    private function normalizeDimValue($value): string
    {
        if ($value === null || $value === 'null' || $value === 'NULL') {
            return '';
        }
        if (is_numeric($value)) {
            return trim((string)$value);
        }
        return trim((string)$value);
    }

    /** Llave única de la pieza (para cruzar con reservas locales).
     * IMPORTANTE: Normaliza valores para evitar discrepancias por NULL, espacios, etc.
     */
    private function dimKey($obj): string
    {
        $fields = ['ItemId', 'ConfigId', 'InventSizeId', 'InventColorId',
                   'InventLocationId', 'InventBatchId', 'WMSLocationId', 'InventSerialId'];

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

        // Parámetros base en orden: manteniendo legibilidad con comentarios
        $params = [
            // CASE Tipo: pattern Rizo
            self::PATTERN_RIZO,
            // CASE Tipo: pattern Pie
            self::PATTERN_PIE,
            // JOIN InventDim: DATAAREAID
            self::DATAAREA,
            // JOIN InventDim: InventLocationId (A-JUL/TELA)
            self::LOC_TELA,
            // LEFT JOIN InventSerial: DATAAREAID
            self::DATAAREA,
            // WHERE: DATAAREAID
            self::DATAAREA,
            // WHERE: ItemId LIKE pattern Rizo
            self::PATTERN_RIZO,
            // WHERE: ItemId LIKE pattern Pie
            self::PATTERN_PIE,
        ];

        // Consulta SQL: Determinar Tipo basado en patrones del ItemId
        // Parámetros en orden:
        // 1-2: CASE Tipo (PATTERN_RIZO, PATTERN_PIE)
        // 3: JOIN InventDim DATAAREAID
        // 4: JOIN InventDim InventLocationId
        // 5: LEFT JOIN InventSerial DATAAREAID
        // 6: WHERE DATAAREAID
        // 7-8: WHERE ItemId LIKE (PATTERN_RIZO, PATTERN_PIE)
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

        // Aplicar filtros adicionales dinámicos
        foreach ($filtros as $f) {
            $col = $f['columna'] ?? null;
            $val = trim($f['valor'] ?? '');
            if (!$col || $val === '') continue;

            if ($col === 'Tipo') {
                // Filtro por Tipo: Rizo o Pie
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
                // Filtro por fecha de producción
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
                // Filtro numérico: InventQty o Metros
                $expr = self::FILTER_SQL[$col];
                if (is_numeric($val)) {
                    $sql .= " AND $expr = ? ";
                    $params[] = (float)$val;
                } else {
                    $sql .= " AND CAST($expr AS NVARCHAR(50)) LIKE ? ";
                    $params[] = '%'.$val.'%';
                }
                continue;
            }

            // Filtro de texto genérico para otras columnas
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
