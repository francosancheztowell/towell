<?php

namespace App\Http\Controllers;

use App\Models\TejInventarioTelares;
use App\Models\InvTelasReservadas;
use App\Models\ReqProgramaTejido;
use App\Models\ReqProgramaTejidoLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
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
            // Validar filtros si vienen como query string o body
            $filtros = $request->input('filtros', $request->query('filtros', []));

            if (!empty($filtros)) {
                $request->validate([
                    'filtros' => ['array'],
                    'filtros.*.columna' => ['required','string', Rule::in(self::COLS_TELARES)],
                    'filtros.*.valor'   => ['required','string'],
                ]);
            }

            $q = $this->baseQuery();

            foreach ($filtros as $f) {
                $col = $f['columna'] ?? '';
                $val = trim($f['valor'] ?? '');
                if ($col === '' || $val === '') continue;

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
                'tipo' => ['nullable','string','max:20'], // Agregar tipo para búsqueda precisa
                'metros' => ['nullable','numeric'],
                'no_julio' => ['nullable','string','max:50'],
            ]);

            $noTelar = $request->input('no_telar');
            $tipo = $request->input('tipo');
            $metros = $request->input('metros');
            $noJulio = $request->input('no_julio');

            // Buscar el telar en la tabla tej_inventario_telares
            // Si se proporciona tipo, buscar por no_telar Y tipo para evitar conflictos con telares duplicados
            $query = TejInventarioTelares::where('no_telar', $noTelar)
                ->where('status', 'Activo');

            if ($tipo) {
                // Normalizar tipo para búsqueda (Rizo, Pie, RIZO, PIE, etc.)
                $tipoUpper = strtoupper(trim($tipo));
                $tipoNormalized = $tipoUpper === 'RIZO' ? 'Rizo' : ($tipoUpper === 'PIE' ? 'Pie' : $tipo);
                $query->where('tipo', $tipoNormalized);
            }

            $telar = $query->first();

            if (!$telar) {
                return response()->json([
                    'success' => false,
                    'message' => 'Telar no encontrado o no está activo',
                ], 404);
            }

            // Actualizar metros y no_julio (NO actualizar tipo, solo metros y no_julio)
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

            Log::info("Telar actualizado: {$noTelar} (tipo: {$telar->tipo})", $updateData);

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

    public function liberarTelar(Request $request)
    {
        try {
            $request->validate([
                'no_telar' => ['required','string','max:50'],
                'tipo' => ['nullable','string','max:20'], // Tipo para búsqueda precisa
            ]);

            $noTelar = $request->input('no_telar');
            $tipo = $request->input('tipo');

            // Buscar el telar en la tabla tej_inventario_telares
            $query = TejInventarioTelares::where('no_telar', $noTelar)
                ->where('status', 'Activo');

            if ($tipo) {
                // Normalizar tipo para búsqueda
                $tipoUpper = strtoupper(trim($tipo));
                $tipoNormalized = $tipoUpper === 'RIZO' ? 'Rizo' : ($tipoUpper === 'PIE' ? 'Pie' : $tipo);
                $query->where('tipo', $tipoNormalized);
            }

            $telar = $query->first();

            if (!$telar) {
                return response()->json([
                    'success' => false,
                    'message' => 'Telar no encontrado o no está activo',
                ], 404);
            }

            // Verificar que el telar esté reservado (tenga no_julio y no_orden)
            // Un telar está reservado si tiene ambos campos llenos
            $noJulio = trim($telar->no_julio ?? '');
            $noOrden = trim($telar->no_orden ?? '');
            if (empty($noJulio) || empty($noOrden)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este telar no está reservado (no tiene no_julio y no_orden)',
                ], 400);
            }

            // 1. ELIMINAR físicamente todas las reservas de este telar en InvTelasReservadas
            $reservas = InvTelasReservadas::where('NoTelarId', $noTelar)
                ->where('Status', 'Reservado')
                ->get();

            $reservasEliminadas = 0;
            foreach ($reservas as $reserva) {
                $reserva->delete();
                $reservasEliminadas++;
            }

            // 2. Actualizar el telar: limpiar hilo, metros y no_julio (mantener no_orden)
            $telar->update([
                'hilo' => null,
                'metros' => null,
                'no_julio' => null,
            ]);

            Log::info("Telar liberado: {$noTelar} (tipo: {$telar->tipo})", [
                'reservas_eliminadas' => $reservasEliminadas,
                'campos_limpiados' => ['hilo', 'metros', 'no_julio']
            ]);

            return response()->json([
                'success' => true,
                'message' => "Telar {$noTelar} liberado correctamente. {$reservasEliminadas} reserva(s) eliminada(s).",
                'data' => $telar->fresh(),
                'reservas_eliminadas' => $reservasEliminadas,
            ]);
        } catch (\Throwable $e) {
            Log::error('liberarTelar: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al liberar el telar: ' . $e->getMessage(),
            ], 500);
        }
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

    public function programacionRequerimientos(Request $request)
    {
        // Obtener telares desde la query string o session
        $telaresJson = $request->query('telares');
        $telares = [];

        if ($telaresJson) {
            try {
                $telares = json_decode(urldecode($telaresJson), true);
                if (!is_array($telares)) {
                    $telares = [];
                }
            } catch (\Exception $e) {
                Log::error('Error al parsear telares en programacionRequerimientos: ' . $e->getMessage());
            }
        }

        return view('modulos.programa_urd_eng.programacion-requerimientos', [
            'telaresSeleccionados' => $telares,
        ]);
    }

    /**
     * Obtener datos de resumen por semana desde ReqProgramaTejido y ReqProgramaTejidoLine
     * Filtrado por los telares seleccionados
     */
    public function getResumenSemanas(Request $request)
    {
        try {
            // Obtener telares desde la query string o body
            $telaresJson = $request->input('telares') ?? $request->query('telares');
            $telares = [];

            if ($telaresJson) {
                try {
                    $telares = is_string($telaresJson)
                        ? json_decode(urldecode($telaresJson), true)
                        : $telaresJson;

                    if (!is_array($telares)) {
                        $telares = [];
                    }
                } catch (\Exception $e) {
                    Log::error('Error al parsear telares en getResumenSemanas: ' . $e->getMessage());
                    return response()->json([
                        'success' => false,
                        'message' => 'Error al parsear telares seleccionados',
                        'data' => ['rizo' => [], 'pie' => []]
                    ], 400);
                }
            }

            if (empty($telares)) {
                return response()->json([
                    'success' => true,
                    'message' => 'No hay telares seleccionados',
                    'data' => ['rizo' => [], 'pie' => []]
                ]);
            }

            // Validar que todos los telares tengan el mismo tipo, calibre, hilo y salón
            $primerTelar = $telares[0];
            $tipoEsperado = strtoupper(trim($primerTelar['tipo'] ?? ''));
            $calibreEsperado = trim($primerTelar['calibre'] ?? '');
            $hiloEsperado = trim($primerTelar['hilo'] ?? '');
            $salonEsperado = trim($primerTelar['salon'] ?? '');

            // Validar que el primer telar tenga tipo (obligatorio)
            if (empty($tipoEsperado)) {
                return response()->json([
                    'success' => false,
                    'message' => 'El telar seleccionado debe tener un tipo definido',
                    'data' => ['rizo' => [], 'pie' => []]
                ], 400);
            }

            // Validar que todos los telares tengan el mismo tipo, calibre, hilo y salón
            // Solo validar campos que estén presentes en el primer telar
            foreach ($telares as $index => $telar) {
                $tipoActual = strtoupper(trim($telar['tipo'] ?? ''));
                $calibreActual = trim($telar['calibre'] ?? '');
                $hiloActual = trim($telar['hilo'] ?? '');
                $salonActual = trim($telar['salon'] ?? '');

                // Tipo es obligatorio
                if ($tipoActual !== $tipoEsperado) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Todos los telares seleccionados deben tener el mismo tipo. Telar ' . ($telar['no_telar'] ?? 'N/A') . ' tiene tipo "' . ($telar['tipo'] ?? 'N/A') . '" pero se esperaba tipo "' . ($primerTelar['tipo'] ?? 'N/A') . '".',
                        'data' => ['rizo' => [], 'pie' => []]
                    ], 400);
                }

                // Calibre: solo validar si ambos tienen calibre definido
                if (!empty($calibreEsperado) && !empty($calibreActual)) {
                    $calibreActualNum = is_numeric($calibreActual) ? (float)$calibreActual : 0;
                    $calibreEsperadoNum = is_numeric($calibreEsperado) ? (float)$calibreEsperado : 0;
                    if (abs($calibreActualNum - $calibreEsperadoNum) >= 0.01) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Todos los telares seleccionados deben tener el mismo calibre. Telar ' . ($telar['no_telar'] ?? 'N/A') . ' tiene calibre "' . $calibreActual . '" pero se esperaba calibre "' . $calibreEsperado . '".',
                            'data' => ['rizo' => [], 'pie' => []]
                        ], 400);
                    }
                }

                // Hilo: solo validar si ambos tienen hilo definido
                if (!empty($hiloEsperado) && !empty($hiloActual)) {
                    if ($hiloActual !== $hiloEsperado) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Todos los telares seleccionados deben tener el mismo hilo. Telar ' . ($telar['no_telar'] ?? 'N/A') . ' tiene hilo "' . $hiloActual . '" pero se esperaba hilo "' . $hiloEsperado . '".',
                            'data' => ['rizo' => [], 'pie' => []]
                        ], 400);
                    }
                }

                // Salón: solo validar si ambos tienen salón definido
                if (!empty($salonEsperado) && !empty($salonActual)) {
                    if ($salonActual !== $salonEsperado) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Todos los telares seleccionados deben tener el mismo salón. Telar ' . ($telar['no_telar'] ?? 'N/A') . ' tiene salón "' . $salonActual . '" pero se esperaba salón "' . $salonEsperado . '".',
                            'data' => ['rizo' => [], 'pie' => []]
                        ], 400);
                    }
                }
            }

            // Extraer números de telar
            $noTelares = collect($telares)->pluck('no_telar')->filter()->unique()->values()->toArray();

            if (empty($noTelares)) {
                return response()->json([
                    'success' => true,
                    'message' => 'No se encontraron números de telar válidos',
                    'data' => ['rizo' => [], 'pie' => []]
                ]);
            }

            // Calcular rangos de semanas (4 semanas: actual + 3 adelante)
            $hoy = Carbon::now();
            $inicioSemanaActual = $hoy->copy()->startOfWeek(Carbon::MONDAY);

            $semanas = [];
            for ($i = 0; $i < 4; $i++) {
                $inicio = $inicioSemanaActual->copy()->addWeeks($i);
                $fin = $inicio->copy()->endOfWeek(Carbon::SUNDAY);
                $semanas[] = [
                    'numero' => $i + 1,
                    'inicio' => $inicio->format('Y-m-d'),
                    'fin' => $fin->format('Y-m-d'),
                    'label' => $i === 0 ? 'Sem Actual' : "Sem Actual +{$i}"
                ];
            }

            // Obtener programas de los telares seleccionados
            $programas = ReqProgramaTejido::whereIn('NoTelarId', $noTelares)
                ->where('EnProceso', true)
                ->with('lineas')
                ->get();

            // Obtener calibre y hilo esperados del primer telar
            $calibreEsperado = trim($telares[0]['calibre'] ?? '');
            $hiloEsperado = trim($telares[0]['hilo'] ?? '');
            $salonEsperado = trim($telares[0]['salon'] ?? '');

            // Filtrar programas según el tipo, calibre, hilo y salón esperados
            // Nota: No filtramos por cuenta, ya que puede variar
            if ($tipoEsperado === 'RIZO') {
                // Solo procesar programas Rizo con el hilo esperado y salón
                $programasRizo = $programas->filter(function($p) use ($hiloEsperado, $salonEsperado) {
                    $hiloRizo = trim($p->FibraRizo ?? '');
                    $salonPrograma = trim($p->SalonTejidoId ?? '');
                    return !empty($p->CuentaRizo) && $hiloRizo === $hiloEsperado && $salonPrograma === $salonEsperado;
                });
                $resumenRizo = $this->procesarResumenPorTipo($programasRizo, $semanas, 'Rizo', null, $hiloEsperado);
                $resumenPie = []; // No mostrar datos de Pie
            } elseif ($tipoEsperado === 'PIE') {
                // Solo procesar programas Pie con el calibre esperado y salón
                $programasPie = $programas->filter(function($p) use ($calibreEsperado, $salonEsperado) {
                    $calibrePie = trim($p->CalibrePie ?? '');
                    $salonPrograma = trim($p->SalonTejidoId ?? '');
                    // Comparar calibres como números flotantes para evitar problemas de formato
                    $calibrePieNum = is_numeric($calibrePie) ? (float)$calibrePie : 0;
                    $calibreEsperadoNum = is_numeric($calibreEsperado) ? (float)$calibreEsperado : 0;
                    return !empty($p->CuentaPie) &&
                           abs($calibrePieNum - $calibreEsperadoNum) < 0.01 &&
                           $salonPrograma === $salonEsperado;
                });
                $resumenPie = $this->procesarResumenPorTipo($programasPie, $semanas, 'Pie', null, null, $calibreEsperado);
                $resumenRizo = []; // No mostrar datos de Rizo
            } else {
                // Si el tipo no es reconocido, no mostrar nada
                $resumenRizo = [];
                $resumenPie = [];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'rizo' => $resumenRizo,
                    'pie' => $resumenPie,
                ],
                'semanas' => $semanas,
            ]);

        } catch (\Throwable $e) {
            Log::error('Error en getResumenSemanas: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener resumen de semanas: ' . $e->getMessage(),
                'data' => ['rizo' => [], 'pie' => []]
            ], 500);
        }
    }

    /**
     * Procesar resumen por tipo (Rizo o Pie)
     */
    private function procesarResumenPorTipo($programas, $semanas, $tipo, $cuentaEsperada = null, $hiloEsperado = null, $calibreEsperado = null)
    {
        $resumen = [];

        foreach ($programas as $programa) {
            // Determinar qué campos usar según el tipo
            if ($tipo === 'Rizo') {
                $cuenta = trim($programa->CuentaRizo ?? '');
                $calibre = null; // Rizo no usa calibre en el resumen
                $hilo = trim($programa->FibraRizo ?? '');
                $campoMetros = 'MtsRizo';
            } else { // Pie
                $cuenta = trim($programa->CuentaPie ?? '');
                $calibre = $programa->CalibrePie ?? '';
                $hilo = trim($programa->FibraPie ?? '');
                $campoMetros = 'MtsPie';
            }

            // Validar que la cuenta exista
            if (empty($cuenta)) {
                continue;
            }

            // Si se especifica un hilo esperado (para Rizo), validar que coincida
            if ($hiloEsperado !== null && $tipo === 'Rizo') {
                if ($hilo !== $hiloEsperado) {
                    continue;
                }
            }

            // Si se especifica un calibre esperado (para Pie), validar que coincida
            if ($calibreEsperado !== null && $tipo === 'Pie') {
                $calibreNum = is_numeric($calibre) ? (float)$calibre : 0;
                $calibreEsperadoNum = is_numeric($calibreEsperado) ? (float)$calibreEsperado : 0;
                if (abs($calibreNum - $calibreEsperadoNum) >= 0.01) {
                    continue;
                }
            }

            // Si se especifica una cuenta esperada, validar que coincida (opcional, ya no es requerido)
            if ($cuentaEsperada !== null && $cuenta !== $cuentaEsperada) {
                continue;
            }

            // Usar ItemId o NombreProducto como Modelo
            $modelo = $programa->ItemId ?? $programa->NombreProducto ?? '';
            $telarId = $programa->NoTelarId ?? '';

            // Clave única para agrupar
            $clave = $tipo === 'Rizo'
                ? "{$telarId}|{$cuenta}|{$hilo}|{$modelo}"
                : "{$telarId}|{$cuenta}|{$calibre}|{$modelo}";

            // Inicializar resumen si no existe
            if (!isset($resumen[$clave])) {
                $resumen[$clave] = [
                    'TelarId' => $telarId,
                    'Cuenta' => $tipo === 'Rizo' ? 'CuentaRizo' : 'CuentaPie',
                    'CuentaValor' => $cuenta,
                    'Hilo' => $hilo,
                    'Calibre' => $tipo === 'Pie' ? $calibre : null,
                    'Modelo' => $modelo,
                    'SemActual' => 0,
                    'SemActual1' => 0,
                    'SemActual2' => 0,
                    'SemActual3' => 0,
                    'Total' => 0,
                ];
            }

            // Procesar líneas del programa
            foreach ($programa->lineas as $linea) {
                $fecha = $linea->Fecha;
                if (!$fecha) {
                    continue;
                }

                $metros = $linea->$campoMetros ?? 0;
                if ($metros <= 0) {
                    continue;
                }

                // Determinar a qué semana pertenece esta fecha
                $fechaCarbon = Carbon::parse($fecha);
                $semanaIndex = null;

                foreach ($semanas as $index => $semana) {
                    $inicio = Carbon::parse($semana['inicio'])->startOfDay();
                    $fin = Carbon::parse($semana['fin'])->endOfDay();

                    // Comparar fechas (inclusivo)
                    if ($fechaCarbon->gte($inicio) && $fechaCarbon->lte($fin)) {
                        $semanaIndex = $index;
                        break;
                    }
                }

                // Si la fecha está fuera del rango de 4 semanas, no la contamos
                if ($semanaIndex === null || $semanaIndex >= 4) {
                    continue;
                }

                // Acumular metros en la semana correspondiente
                switch ($semanaIndex) {
                    case 0:
                        $resumen[$clave]['SemActual'] += $metros;
                        break;
                    case 1:
                        $resumen[$clave]['SemActual1'] += $metros;
                        break;
                    case 2:
                        $resumen[$clave]['SemActual2'] += $metros;
                        break;
                    case 3:
                        $resumen[$clave]['SemActual3'] += $metros;
                        break;
                }

                // Acumular total
                $resumen[$clave]['Total'] += $metros;
            }
        }

        // Convertir a array indexado y ordenar
        return collect($resumen)->values()->map(function($item) use ($tipo) {
            // Formatear según el tipo
            if ($tipo === 'Rizo') {
                return [
                    'TelarId' => $item['TelarId'],
                    'CuentaRizo' => $item['CuentaValor'],
                    'Hilo' => $item['Hilo'],
                    'Modelo' => $item['Modelo'],
                    'SemActualMtsRizo' => round($item['SemActual'], 2),
                    'SemActual1MtsRizo' => round($item['SemActual1'], 2),
                    'SemActual2MtsRizo' => round($item['SemActual2'], 2),
                    'SemActual3MtsRizo' => round($item['SemActual3'], 2),
                    'Total' => round($item['Total'], 2),
                ];
            } else {
                return [
                    'TelarId' => $item['TelarId'],
                    'CuentaPie' => $item['CuentaValor'],
                    'CalibrePie' => $item['Calibre'],
                    'Modelo' => $item['Modelo'],
                    'SemActualMtsPie' => round($item['SemActual'], 2),
                    'SemActual1MtsPie' => round($item['SemActual1'], 2),
                    'SemActual2MtsPie' => round($item['SemActual2'], 2),
                    'SemActual3MtsPie' => round($item['SemActual3'], 2),
                    'Total' => round($item['Total'], 2),
                ];
            }
        })->sortBy([
            ['TelarId', 'asc'],
            ['Modelo', 'asc'],
        ])->values()->toArray();
    }
}
