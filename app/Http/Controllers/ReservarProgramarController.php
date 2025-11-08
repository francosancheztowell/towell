<?php

namespace App\Http\Controllers;

use App\Models\TejInventarioTelares;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class ReservarProgramarController extends Controller
{
    private const COLS_TELARES = [
        'no_telar','tipo','cuenta','calibre','fecha','turno','hilo','metros',
        'no_julio','no_orden','tipo_atado','salon'
    ];

    public function index()
    {
        $rows  = $this->baseQuery()->limit(1000)->get();
        $data  = $this->normalizeTelares($rows);

        return view('modulos.programa_urd_eng.reservar-programar', [
            'inventarioTelares' => $data,
        ]);
    }

    public function getInventarioTelares(Request $request)
    {
        try {
            $request->validate([
                'filtros' => ['nullable','array'],
                'filtros.*.columna' => ['required','string', Rule::in(self::COLS_TELARES)],
                'filtros.*.valor'   => ['required','string'],
            ]);

            $q = $this->baseQuery();

            foreach ($request->input('filtros', []) as $f) {
                $col = $f['columna']; $val = trim($f['valor'] ?? '');
                if ($val === '') continue;

                if ($col === 'fecha') {
                    if ($date = $this->parseDateFlexible($val)) {
                        $q->whereDate('fecha', $date->toDateString());
                    }
                    continue;
                }

                // Para turno/calibre/metros permitimos contains; el resto también (SQL Server usa CI por defecto)
                $q->where($col, 'like', "%{$val}%");
            }

            $rows = $q->orderBy('no_telar')->orderBy('tipo')->limit(2000)->get();
            $data = $this->normalizeTelares($rows);

            return response()->json([
                'success' => true,
                'data'    => $data->values(),
                'total'   => $data->count(),
            ]);
        } catch (\Throwable $e) {
            Log::error('getInventarioTelares: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener inventario de telares',
            ], 500);
        }
    }

    public function getInventarioDisponible(Request $request)
    {
        // Delegar a InvTelasReservadasController
        return app(\App\Http\Controllers\InvTelasReservadasController::class)->disponible($request);
    }

    public function programarTelar(Request $request)
    {
        try {
            $request->validate([
                'no_telar' => ['required','string','max:50'],
            ]);

            $noTelar = (string)$request->string('no_telar');

            // TODO: lógica real
            Log::info("Programar telar: {$noTelar}");

            return response()->json([
                'success' => true,
                'message' => "El telar {$noTelar} ha sido programado exitosamente.",
                'no_telar'=> $noTelar,
            ]);
        } catch (\Throwable $e) {
            Log::error('programarTelar: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al programar el telar',
            ], 500);
        }
    }

    public function actualizarTelar(Request $request)
    {
        try {
            $request->validate([
                'no_telar' => ['required','string','max:50'],
                'metros' => ['nullable','numeric'],
                'no_julio' => ['nullable','string','max:50'],
            ]);

            $noTelar = $request->input('no_telar');
            $metros = $request->input('metros');
            $noJulio = $request->input('no_julio');

            // Buscar el telar en la tabla tej_inventario_telares
            $telar = TejInventarioTelares::where('no_telar', $noTelar)
                ->where('status', 'Activo')
                ->first();

            if (!$telar) {
                return response()->json([
                    'success' => false,
                    'message' => 'Telar no encontrado o no está activo',
                ], 404);
            }

            // Actualizar metros y no_julio
            $updateData = [];
            if ($metros !== null) {
                $updateData['metros'] = $metros;
            }
            if ($noJulio !== null) {
                $updateData['no_julio'] = $noJulio;
            }

            if (!empty($updateData)) {
                $telar->update($updateData);
            }

            Log::info("Telar actualizado: {$noTelar}", $updateData);

            return response()->json([
                'success' => true,
                'message' => "Telar {$noTelar} actualizado correctamente",
                'data' => $telar->fresh(),
            ]);
        } catch (\Throwable $e) {
            Log::error('actualizarTelar: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el telar: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function reservarInventario(Request $request)
    {
        // Delegar a InvTelasReservadasController
        return app(\App\Http\Controllers\InvTelasReservadasController::class)->reservar($request);
    }

    public function getColumnOptions(Request $request)
    {
        $type = $request->input('table_type', 'telares');

        $telares = [
            ['field' => 'no_telar',  'label' => 'No. Telar'],
            ['field' => 'tipo',      'label' => 'Tipo'],
            ['field' => 'cuenta',    'label' => 'Cuenta'],
            ['field' => 'calibre',   'label' => 'Calibre'],
            ['field' => 'fecha',     'label' => 'Fecha'],
            ['field' => 'turno',     'label' => 'Turno'],
            ['field' => 'hilo',      'label' => 'Hilo'],
            ['field' => 'metros',    'label' => 'Metros'],
            ['field' => 'no_julio',  'label' => 'No. Julio'],
            ['field' => 'no_orden',  'label' => 'No. Orden'],
            ['field' => 'tipo_atado','label' => 'Tipo Atado'],
            ['field' => 'salon',     'label' => 'Salón'],
        ];

        $inventario = [
            ['field' => 'ItemId',           'label' => 'Artículo'],
            ['field' => 'Tipo',             'label' => 'Tipo'],
            ['field' => 'ConfigId',         'label' => 'Fibra'],
            ['field' => 'InventSizeId',     'label' => 'Cuenta'],
            ['field' => 'InventColorId',    'label' => 'Cod Color'],
            ['field' => 'InventBatchId',    'label' => 'Lote'],
            ['field' => 'WMSLocationId',    'label' => 'Localidad'],
            ['field' => 'InventSerialId',   'label' => 'Num. Julio'],
            ['field' => 'ProdDate',         'label' => 'Fecha'],
            ['field' => 'Metros',           'label' => 'Metros'],
            ['field' => 'InventQty',        'label' => 'Kilos'],
            ['field' => 'NoTelarId',        'label' => 'Telar'],
        ];

        return response()->json([
            'success' => true,
            'columns' => $type === 'telares' ? $telares : $inventario,
        ]);
    }

    /* ======================= PRIVADOS ======================= */

    private function baseQuery()
    {
        return TejInventarioTelares::query()
            ->where('status', 'Activo')
            ->select(self::COLS_TELARES);
    }

    private function normalizeTelares($rows)
    {
        return collect($rows)->values()->map(function($r, $i) {
            $fecha = $this->normalizeDate($r->fecha ?? null);
            return [
                'no_telar'   => $this->normalizeTelar($r->no_telar ?? null),
                'tipo'       => $this->str($r->tipo ?? null),
                'cuenta'     => $this->str($r->cuenta ?? null),
                'calibre'    => $this->num($r->calibre ?? null),
                'fecha'      => $fecha,                       // ISO para JS
                'turno'      => $this->str($r->turno ?? null),
                'hilo'       => $this->str($r->hilo ?? null),
                'metros'     => $this->num($r->metros ?? null),
                'no_julio'   => $this->str($r->no_julio ?? null),
                'no_orden'   => $this->str($r->no_orden ?? null),
                'tipo_atado' => $this->str($r->tipo_atado ?? 'Normal'),
                'salon'      => $this->str($r->salon ?? 'Jacquard'),
                '_index'     => $i,
            ];
        });
    }

    private function normalizeTelar($v)
    {
        if ($v === null || $v === '') return '';
        return is_numeric($v) ? (int)$v : (string)$v;
    }

    private function str($v) { return $v === null ? '' : trim((string)$v); }
    private function num($v) { return $v === null || $v === '' ? 0.0 : (float)$v; }

    private function normalizeDate($v)
    {
        if ($v === null) return null;
        try {
            return ($v instanceof Carbon ? $v : Carbon::parse($v))->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseDateFlexible(string $v): ?Carbon
    {
        $v = trim($v);
        foreach (['Y-m-d','d/m/Y','d-m-Y','Y/m/d','Y.m.d','d.m.Y'] as $fmt) {
            try { return Carbon::createFromFormat($fmt, $v)->startOfDay(); } catch (\Throwable) {}
        }
        try { return Carbon::parse($v)->startOfDay(); } catch (\Throwable) { return null; }
    }
}
