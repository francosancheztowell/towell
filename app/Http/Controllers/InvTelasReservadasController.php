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
    /**
     * GET inventario disponible (TI-PRO), EXCLUYENDO lo ya reservado localmente.
     * Opcionalmente puedes mandar filtros: [{columna, valor}]
     *
     * Conexión externa sugerida en config/database.php: 'sqlsrv_ti'
     */
    public function disponible(Request $request)
    {
        try {
            // Validar filtros solo si se envían
            $filtros = $request->input('filtros', []);
            if (!empty($filtros)) {
                $request->validate([
                    'filtros' => ['array'],
                    'filtros.*.columna' => ['required','string', Rule::in([
                        'ItemId','ConfigId','InventSizeId','InventColorId','InventLocationId',
                        'InventBatchId','WMSLocationId','InventSerialId','Tipo'
                    ])],
                    'filtros.*.valor'   => ['required','string'],
                ]);
            }

            // 1) Traer llaves reservadas (local) con NoTelarId
            $reservadas = InvTelasReservadas::query()
                ->where('Status', 'Reservado')
                ->get([
                    'ItemId','ConfigId','InventSizeId','InventColorId',
                    'InventLocationId','InventBatchId','WMSLocationId','InventSerialId',
                    'NoTelarId'
                ])
                ->map(function($r) {
                    return [
                        'key' => $this->dimKey($r),
                        'NoTelarId' => $r->NoTelarId
                    ];
                });
            $reservadasMap = [];
            foreach ($reservadas as $r) {
                $reservadasMap[$r['key']] = $r['NoTelarId'];
            }

            // 2) Traer disponible desde TI-PRO
            $rows = $this->queryDisponibleFromTiPro($filtros);

            // 3) Mostrar todas las piezas (disponibles y reservadas) con NoTelarId si existe
            $filtrado = [];
            foreach ($rows as $row) {
                $key = $this->dimKey($row);
                // Si está reservado, agregar el NoTelarId; si no, null
                $row->NoTelarId = $reservadasMap[$key] ?? null;
                $filtrado[] = $row;
            }

            return response()->json([
                'success' => true,
                'data'    => $filtrado,
                'total'   => count($filtrado),
            ]);
        } catch (Throwable $e) {
            Log::error('InvDisponible error: '.$e->getMessage(), ['trace'=>$e->getTraceAsString()]);
            return response()->json(['success'=>false,'message'=>'Error al obtener inventario disponible'], 500);
        }
    }

    /**
     * POST reservar: guarda en InvTelasReservadas (idempotente por índice único).
     * Espera el payload con las mismas columnas de la grilla de abajo + telar.
     */
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

            // Limpieza mínima
            $data['Status'] = 'Reservado';

            // Intento de inserción (protege duplicados por Unique Index)
            try {
                InvTelasReservadas::create($data);
                $created = true;
                $msg = 'Pieza reservada correctamente';
            } catch (\Illuminate\Database\QueryException $qe) {
                // 2601 / 2627 = violation unique index
                if (in_array($qe->getCode(), [2601, 2627])) {
                    $created = false;
                    $msg = 'La pieza ya estaba reservada (no se duplicó).';
                } else {
                    throw $qe;
                }
            }

            return response()->json([
                'success' => true,
                'created' => $created,
                'message' => $msg,
            ]);
        } catch (Throwable $e) {
            Log::error('Reservar error: '.$e->getMessage());
            return response()->json(['success'=>false,'message'=>'Error al reservar la pieza'], 500);
        }
    }

    /** GET reservas por telar (para mostrar “lo reservado” arriba/abajo) */
    public function porTelar(string $noTelar)
    {
        $rows = InvTelasReservadas::where('NoTelarId', $noTelar)
            ->where('Status', 'Reservado')
            ->orderByDesc('Id')
            ->get();

        return response()->json(['success'=>true, 'data'=>$rows, 'total'=>$rows->count()]);
    }

    /** POST cancelar por Id o por clave dimensional + telar */
    public function cancelar(Request $request)
    {
        $request->validate([
            'Id'        => ['nullable','integer'],
            'NoTelarId' => ['required_without:Id','string'],
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

    // ----------------------- helpers -----------------------

    /** Arma una llave única de la pieza (obj puede ser stdClass o array/Eloquent) */
    private function dimKey($obj): string
    {
        $g = fn($k) => is_array($obj) ? ($obj[$k] ?? null) : (is_object($obj) ? ($obj->$k ?? null) : null);
        return implode('|', [
            $g('ItemId'), $g('ConfigId'), $g('InventSizeId'), $g('InventColorId'),
            $g('InventLocationId'), $g('InventBatchId'), $g('WMSLocationId'), $g('InventSerialId')
        ]);
    }

    /** Ejecuta el SELECT en TI-PRO con los joins y filtros que definiste */
    private function queryDisponibleFromTiPro(array $filtros = []): array
    {
        $cn = DB::connection('sqlsrv_ti'); // <-- pon este alias en config/database.php

        // Base query (SQL Server 2008 compatible)
        $sql = "
SELECT
    s.ItemId,
    d.ConfigId,
    d.InventSizeId,
    d.InventColorId,
    d.InventLocationId,
    d.InventBatchId,
    d.WMSLocationId,
    d.InventSerialId,
    CASE
        WHEN s.ItemId LIKE '%JU-ENG-RI%' THEN 'Rizo'
        WHEN s.ItemId LIKE '%JU-ENG-PI%' THEN 'Pie'
        ELSE NULL
    END AS Tipo,
    CAST(ISNULL(ser.TwMts, 0) AS DECIMAL(18,4)) AS Metros,
    CAST(ISNULL(s.PhysicalInvent, 0) AS DECIMAL(18,4)) AS InventQty,
    ser.ProdDate
FROM InventSum s
JOIN InventDim d
  ON d.InventDimId = s.InventDimId
 AND d.DATAAREAID = 'PRO'
 AND d.InventLocationId = 'A-JUL/TELA'
LEFT JOIN InventSerial ser
  ON ser.InventSerialId = d.InventSerialId
 AND ser.ItemId = s.ItemId
 AND ser.DATAAREAID = 'PRO'
WHERE s.DATAAREAID = 'PRO'
  AND s.AvailPhysical <> 0
  AND (s.ItemId LIKE '%JU-ENG-RI%' OR s.ItemId LIKE '%JU-ENG-PI%')
";
        $params = [];

        // Filtros simples (LIKE case-insensitive)
        foreach ($filtros as $f) {
            $col = $f['columna'] ?? null;
            $val = trim($f['valor'] ?? '');
            if (!$col || $val === '') continue;

            // Solo se permite filtrar columnas proyectadas
            $allowed = [
                'ItemId','ConfigId','InventSizeId','InventColorId','InventLocationId',
                'InventBatchId','WMSLocationId','InventSerialId','Tipo'
            ];
            if (!in_array($col, $allowed, true)) continue;

            if ($col === 'Tipo') {
                $sql .= " AND (CASE WHEN s.ItemId LIKE '%JU-ENG-RI%' THEN 'Rizo'
                                    WHEN s.ItemId LIKE '%JU-ENG-PI%' THEN 'Pie' ELSE NULL END) LIKE ? ";
                $params[] = '%'.mb_strtolower($val,'UTF-8').'%';
            } else {
                $sql .= " AND LOWER(CAST($col AS NVARCHAR(100))) LIKE ? ";
                $params[] = '%'.mb_strtolower($val,'UTF-8').'%';
            }
        }

        $sql .= " ORDER BY s.ItemId, d.ConfigId ";

        return $cn->select($sql, $params);
    }
}
