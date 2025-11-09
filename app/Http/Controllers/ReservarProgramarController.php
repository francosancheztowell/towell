<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\TejInventarioTelares;
use App\Models\InvTelasReservadas;
use App\Models\ReqProgramaTejido;
use App\Models\ReqProgramaTejidoLine;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ReservarProgramarController extends Controller
{
    private const STATUS_ACTIVO = 'Activo';

    private const COLS_TELARES = [
        'no_telar','tipo','cuenta','calibre','fecha','turno','hilo','metros',
        'no_julio','no_orden','tipo_atado','salon',
    ];

    /* =========================== PÚBLICOS ============================ */

    public function index()
    {
        $rows = $this->baseQuery()->limit(1000)->get();

        return view('modulos.programa_urd_eng.reservar-programar', [
            'inventarioTelares' => $this->normalizeTelares($rows),
        ]);
    }

    public function getInventarioTelares(Request $request)
    {
        try {
            $filtros = $request->input('filtros', $request->query('filtros', []));

            if (!empty($filtros)) {
                $request->validate([
                    'filtros'           => ['array'],
                    'filtros.*.columna' => ['required', 'string', Rule::in(self::COLS_TELARES)],
                    'filtros.*.valor'   => ['required', 'string'],
                ]);
            }

            $q = $this->baseQuery();

            foreach ($filtros as $f) {
                $col = trim((string)($f['columna'] ?? ''));
                $val = trim((string)($f['valor'] ?? ''));

                if ($col === '' || $val === '') continue;

                if ($col === 'fecha') {
                    $date = $this->parseDateFlexible($val);
                    if ($date) $q->whereDate('fecha', $date->toDateString());
                    continue;
                }

                $q->where($col, 'like', "%{$val}%");
            }

            $rows = $q->orderBy('no_telar')->orderBy('tipo')->limit(2000)->get();

            return response()->json([
                'success' => true,
                'data'    => $this->normalizeTelares($rows)->values(),
                'total'   => $rows->count(),
            ]);
        } catch (\Throwable $e) {
            Log::error('getInventarioTelares', ['msg' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Error al obtener inventario de telares'], 500);
        }
    }

    public function getInventarioDisponible(Request $request)
    {
        return app(\App\Http\Controllers\InvTelasReservadasController::class)->disponible($request);
    }

    public function programarTelar(Request $request)
    {
        try {
            $request->validate(['no_telar' => ['required','string','max:50']]);
            $noTelar = (string)$request->string('no_telar');

            Log::info('Programar telar', ['no_telar' => $noTelar]);

            return response()->json([
                'success' => true,
                'message' => "El telar {$noTelar} ha sido programado exitosamente.",
                'no_telar'=> $noTelar,
            ]);
        } catch (\Throwable $e) {
            Log::error('programarTelar', ['msg' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Error al programar el telar'], 500);
        }
    }

    public function actualizarTelar(Request $request)
    {
        try {
            $request->validate([
                'no_telar' => ['required','string','max:50'],
                'tipo'     => ['nullable','string','max:20'],
                'metros'   => ['nullable','numeric'],
                'no_julio' => ['nullable','string','max:50'],
            ]);

            $noTelar = (string)$request->input('no_telar');
            $tipo    = $this->normalizeTipo($request->input('tipo'));

            $query = TejInventarioTelares::where('no_telar', $noTelar)
                ->where('status', self::STATUS_ACTIVO);

            if ($tipo !== null) $query->where('tipo', $tipo);

            $telar = $query->first();
            if (!$telar) {
                return response()->json(['success'=>false,'message'=>'Telar no encontrado o no está activo'], 404);
            }

            $update = [];
            if ($request->filled('metros'))   $update['metros']   = (float)$request->input('metros');
            if ($request->filled('no_julio')) $update['no_julio'] = (string)$request->input('no_julio');

            if ($update) $telar->update($update);

            Log::info('Telar actualizado', ['no_telar' => $noTelar, 'tipo' => $telar->tipo, 'update' => $update]);

            return response()->json([
                'success' => true,
                'message' => "Telar {$noTelar} actualizado correctamente",
                'data'    => $telar->fresh(),
            ]);
        } catch (\Throwable $e) {
            Log::error('actualizarTelar', ['msg' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Error al actualizar el telar: '.$e->getMessage()], 500);
        }
    }

    public function reservarInventario(Request $request)
    {
        return app(\App\Http\Controllers\InvTelasReservadasController::class)->reservar($request);
    }

    public function liberarTelar(Request $request)
    {
        try {
            $request->validate([
                'no_telar' => ['required','string','max:50'],
                'tipo'     => ['nullable','string','max:20'],
            ]);

            $noTelar = (string)$request->input('no_telar');
            $tipo    = $this->normalizeTipo($request->input('tipo'));

            $q = TejInventarioTelares::where('no_telar', $noTelar)
                ->where('status', self::STATUS_ACTIVO);

            if ($tipo !== null) $q->where('tipo', $tipo);

            $telar = $q->first();
            if (!$telar) {
                return response()->json(['success'=>false,'message'=>'Telar no encontrado o no está activo'], 404);
            }

            // Debe estar reservado (ambos campos)
            $noJulio = trim((string)($telar->no_julio ?? ''));
            $noOrden = trim((string)($telar->no_orden ?? ''));
            if ($noJulio === '' || $noOrden === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Este telar no está reservado (no tiene no_julio y no_orden)',
                ], 400);
            }

            // 1) Eliminar reservas activas del telar
            $reservas = InvTelasReservadas::where('NoTelarId', $noTelar)
                ->where('Status', 'Reservado')
                ->get();

            $eliminadas = 0;
            foreach ($reservas as $r) { $r->delete(); $eliminadas++; }

            // 2) Limpiar campos (conservar no_orden)
            $telar->update(['hilo'=>null,'metros'=>null,'no_julio'=>null]);

            Log::info('Telar liberado', [
                'no_telar' => $noTelar,
                'tipo'     => $telar->tipo,
                'reservas_eliminadas' => $eliminadas,
            ]);

            return response()->json([
                'success' => true,
                'message' => "Telar {$noTelar} liberado correctamente. {$eliminadas} reserva(s) eliminada(s).",
                'data'    => $telar->fresh(),
                'reservas_eliminadas' => $eliminadas,
            ]);
        } catch (\Throwable $e) {
            Log::error('liberarTelar', ['msg' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Error al liberar el telar: '.$e->getMessage()], 500);
        }
    }

    public function getColumnOptions(Request $request)
    {
        $type = $request->input('table_type', 'telares');

        $telares = [
            ['field'=>'no_telar','label'=>'No. Telar'],
            ['field'=>'tipo','label'=>'Tipo'],
            ['field'=>'cuenta','label'=>'Cuenta'],
            ['field'=>'calibre','label'=>'Calibre'],
            ['field'=>'fecha','label'=>'Fecha'],
            ['field'=>'turno','label'=>'Turno'],
            ['field'=>'hilo','label'=>'Hilo'],
            ['field'=>'metros','label'=>'Metros'],
            ['field'=>'no_julio','label'=>'No. Julio'],
            ['field'=>'no_orden','label'=>'No. Orden'],
            ['field'=>'tipo_atado','label'=>'Tipo Atado'],
            ['field'=>'salon','label'=>'Salón'],
        ];

        $inventario = [
            ['field'=>'ItemId','label'=>'Artículo'],
            ['field'=>'Tipo','label'=>'Tipo'],
            ['field'=>'ConfigId','label'=>'Fibra'],
            ['field'=>'InventSizeId','label'=>'Cuenta'],
            ['field'=>'InventColorId','label'=>'Cod Color'],
            ['field'=>'InventBatchId','label'=>'Lote'],
            ['field'=>'WMSLocationId','label'=>'Localidad'],
            ['field'=>'InventSerialId','label'=>'Num. Julio'],
            ['field'=>'ProdDate','label'=>'Fecha'],
            ['field'=>'Metros','label'=>'Metros'],
            ['field'=>'InventQty','label'=>'Kilos'],
            ['field'=>'NoTelarId','label'=>'Telar'],
        ];

        return response()->json([
            'success' => true,
            'columns' => $type === 'telares' ? $telares : $inventario,
        ]);
    }

    public function programacionRequerimientos(Request $request)
    {
        $telares = [];
        $telaresJson = $request->query('telares');

        if ($telaresJson) {
            try {
                $telares = json_decode(urldecode($telaresJson), true) ?: [];
            } catch (\Throwable $e) {
                Log::error('programacionRequerimientos: parse JSON', ['msg' => $e->getMessage()]);
                    $telares = [];
            }
        }

        return view('modulos.programa_urd_eng.programacion-requerimientos', [
            'telaresSeleccionados' => $telares,
        ]);
    }

    /**
     * Resumen de 5 semanas para telares seleccionados, por tipo (Rizo/Pie)
     */
    public function getResumenSemanas(Request $request)
    {
        try {
            $raw = $request->input('telares') ?? $request->query('telares');
            $telares = [];

            if ($raw) {
                $telares = is_string($raw) ? json_decode(urldecode($raw), true) : $raw;
                if (!is_array($telares)) $telares = [];
            }

            if (empty($telares)) {
                return response()->json([
                    'success' => true,
                    'message' => 'No hay telares seleccionados',
                    'data'    => ['rizo' => [], 'pie' => []],
                ]);
            }

            // Validación de consistencia entre telares
            $v = $this->validarTelaresConsistentes($telares);
            if ($v['error']) {
                return response()->json([
                    'success' => false,
                    'message' => $v['mensaje'],
                    'data'    => ['rizo' => [], 'pie' => []],
                ], 400);
            }

            $tipoEsperado     = $v['tipo'];         // 'RIZO' | 'PIE'
            $calibreEsperado  = $v['calibre'];      // string|null
            $calibreEsVacio   = $v['calibre_vacio'];
            $hiloEsperado     = $v['hilo'];         // string|null ('' => buscar vacíos)
            $salonEsperado    = $v['salon'];        // MAYUS o ''

            $noTelares = collect($telares)->pluck('no_telar')->filter()->unique()->values()->toArray();
            if (empty($noTelares)) {
                    return response()->json([
                    'success' => true,
                    'message' => 'No se encontraron números de telar válidos',
                    'data'    => ['rizo' => [], 'pie' => []],
                ]);
            }

            $semanas = $this->construirSemanas(5);
            [$fechaIni, $fechaFin] = [$semanas[0]['inicio'], $semanas[4]['fin']];

            Log::info('getResumenSemanas - Inicio procesamiento', [
                'noTelares' => $noTelares,
                'tipoEsperado' => $tipoEsperado,
                'calibreEsperado' => $calibreEsperado,
                'calibreEsVacio' => $calibreEsVacio,
                'hiloEsperado' => $hiloEsperado,
                'salonEsperado' => $salonEsperado,
                'fechaIni' => $fechaIni,
                'fechaFin' => $fechaFin,
                'semanas' => $semanas
            ]);

            // Cargar TODOS los programas del telar con SUS líneas relacionadas usando eager loading
            // Esto asegura que cuando se busca un programa, automáticamente se cargan sus líneas
            // Para SQL Server, usar CAST para comparar fechas correctamente
            // IMPORTANTE: Solo cargar campos que existen en la BD (FibraRizo, FibraPie, NO HiloRizo/HiloPie/Hilo)
            $programas = ReqProgramaTejido::whereIn('NoTelarId', $noTelares)
                ->with(['lineas' => function($query) use ($fechaIni, $fechaFin) {
                    // Cargar solo las líneas en el rango de fechas usando la relación
                    // Para SQL Server, usar CAST para comparar fechas correctamente
                    $query->whereRaw("CAST(Fecha AS DATE) >= CAST(? AS DATE)", [$fechaIni])
                          ->whereRaw("CAST(Fecha AS DATE) <= CAST(? AS DATE)", [$fechaFin])
                          ->orderBy('Fecha');
                }])
                ->get();

            Log::info('getResumenSemanas - Programas encontrados con líneas relacionadas (eager loading)', [
                'noTelares' => $noTelares,
                'totalProgramas' => $programas->count(),
                'programas_con_EnProceso' => $programas->where('EnProceso', true)->count(),
                'programas_sin_EnProceso' => $programas->where('EnProceso', false)->count(),
                'programas_por_telar' => $programas->groupBy('NoTelarId')->map(fn($g) => $g->count())->toArray(),
                'programas_con_lineas' => $programas->filter(fn($p) => $p->lineas->count() > 0)->count(),
                'programas_detallados' => $programas->map(function($p) {
                    return [
                        'Id' => $p->Id,
                        'NoTelarId' => $p->NoTelarId,
                        'CuentaRizo' => $p->CuentaRizo,
                        'CuentaPie' => $p->CuentaPie,
                        'FibraRizo' => $p->FibraRizo,
                        'FibraPie' => $p->FibraPie ?? null,
                        'SalonTejidoId' => $p->SalonTejidoId,
                        'EnProceso' => $p->EnProceso,
                        'CalibreRizo' => $p->CalibreRizo ?? null,
                        'TotalLineas' => $p->lineas->count(),
                        'LineasConMtsRizo' => $p->lineas->filter(fn($l) => ($l->MtsRizo ?? 0) > 0)->count(),
                        'LineasConMtsPie' => $p->lineas->filter(fn($l) => ($l->MtsPie ?? 0) > 0)->count(),
                    ];
                })->toArray()
            ]);

            // Convertir la relación lineas en un array agrupado por ProgramaId para compatibilidad con el código existente
            $lineasPorPrograma = [];
            foreach ($programas as $programa) {
                $programaId = $programa->Id;
                if ($programaId && $programa->lineas->count() > 0) {
                    $lineasPorPrograma[$programaId] = $programa->lineas;
                }
            }

            Log::info('getResumenSemanas - Líneas agrupadas por programa (desde relación)', [
                'programas_con_lineas' => count($lineasPorPrograma),
                'total_lineas' => collect($lineasPorPrograma)->sum(fn($l) => $l->count()),
                'lineas_por_programa' => array_map(fn($l) => $l->count(), $lineasPorPrograma)
            ]);

            // Verificar si existen programas específicos que el usuario mencionó (3085, 3084, 3078, 3079)
            // y cargar SUS líneas usando la relación para ver cómo se vinculan
            $programasEspecificos = [3085, 3084, 3078, 3079];
            $programasEspecificosEncontrados = ReqProgramaTejido::whereIn('Id', $programasEspecificos)
                ->with(['lineas' => function($query) use ($fechaIni, $fechaFin) {
                    // Cargar líneas en el rango de fechas usando la relación
                    $query->whereRaw("CAST(Fecha AS DATE) >= CAST(? AS DATE)", [$fechaIni])
                          ->whereRaw("CAST(Fecha AS DATE) <= CAST(? AS DATE)", [$fechaFin])
                          ->orderBy('Fecha');
                }])
                ->get();

            if ($programasEspecificosEncontrados->count() > 0) {
                Log::info('Programas específicos mencionados por el usuario encontrados en la BD (con líneas relacionadas)', [
                    'programas_encontrados' => $programasEspecificosEncontrados->map(function($p) use ($noTelares, $fechaIni, $fechaFin) {
                        $estaEnTelaresBuscados = in_array($p->NoTelarId, $noTelares);
                        $lineasConMtsRizo = $p->lineas->filter(fn($l) => ($l->MtsRizo ?? 0) > 0);
                        return [
                            'Id' => $p->Id,
                            'NoTelarId' => $p->NoTelarId,
                            'EstaEnTelaresBuscados' => $estaEnTelaresBuscados,
                            'TelaresBuscados' => $noTelares,
                            'CuentaRizo' => $p->CuentaRizo,
                            'FibraRizo' => $p->FibraRizo,
                            'FibraPie' => $p->FibraPie ?? null,
                            'CalibreRizo' => $p->CalibreRizo,
                            'SalonTejidoId' => $p->SalonTejidoId,
                            'EnProceso' => $p->EnProceso,
                            'TotalLineasEnRango' => $p->lineas->count(),
                            'LineasConMtsRizo' => $lineasConMtsRizo->count(),
                            'EjemploLineasConMtsRizo' => $lineasConMtsRizo->take(5)->map(function($l) {
                                return [
                                    'Id' => $l->Id,
                                    'Fecha' => $l->Fecha instanceof Carbon ? $l->Fecha->format('Y-m-d') : (string)$l->Fecha,
                                    'MtsRizo' => $l->MtsRizo ?? 0,
                                    'MtsPie' => $l->MtsPie ?? 0
                                ];
                            })->toArray(),
                            'RangoFechas' => "{$fechaIni} a {$fechaFin}"
                        ];
                    })->toArray()
                ]);
            } else {
                Log::info('Programas específicos mencionados por el usuario NO encontrados en la BD', [
                    'programas_buscados' => $programasEspecificos
                ]);
            }

            if ($tipoEsperado === 'RIZO') {
                // Filtrar programas que coincidan con los criterios
                $programasFiltrados = $programas->filter(function ($p) use ($salonEsperado, $hiloEsperado, $calibreEsperado, $calibreEsVacio) {
                    $matchSalon = $this->matchSalon($salonEsperado, (string)($p->SalonTejidoId ?? ''));
                    $tieneCuentaRizo = !empty($p->CuentaRizo);

                    // Obtener FibraRizo correctamente (es el único campo que existe en la BD para hilo en RIZO)
                    $fibraRizoRaw = $p->FibraRizo ?? null;
                    $hiloPrograma = '';
                    if ($fibraRizoRaw !== null && $fibraRizoRaw !== '' && trim((string)$fibraRizoRaw) !== '') {
                        $hiloPrograma = trim((string)$fibraRizoRaw);
                    }

                    $matchHilo = $this->matchHilo($hiloEsperado, $hiloPrograma);

                    // También verificar calibre si es necesario
                    $matchCalibre = true;
                    if (!$calibreEsVacio && $calibreEsperado !== null) {
                        $calibrePrograma = $p->CalibreRizo ?? null;
                        $matchCalibre = $this->matchCalibre($calibreEsperado, $calibreEsVacio, $calibrePrograma);
                    }

                    return $matchSalon && $tieneCuentaRizo && $matchHilo && $matchCalibre;
                })->values();

                // Log detallado de FibraRizo para cada programa antes de filtrar
                Log::info('getResumenSemanas - Programas Rizo ANTES de filtrar', [
                    'totalProgramas' => $programas->count(),
                    'filtros_aplicados' => [
                        'salonEsperado' => $salonEsperado,
                        'hiloEsperado' => $hiloEsperado,
                        'hiloEsperado_type' => gettype($hiloEsperado),
                        'calibreEsperado' => $calibreEsperado,
                        'calibreEsVacio' => $calibreEsVacio
                    ],
                    'programas_con_CuentaRizo' => $programas->filter(fn($p) => !empty($p->CuentaRizo))->count(),
                    'programas_con_FibraRizo_coincidente' => $programas->filter(function($p) use ($hiloEsperado) {
                        $fibraRizo = $p->FibraRizo ?? null;
                        $hiloObtenido = $fibraRizo !== null && $fibraRizo !== '' ? trim((string)$fibraRizo) : '';
                        return $this->matchHilo($hiloEsperado, $hiloObtenido);
                    })->count(),
                    'programas_ids' => $programas->pluck('Id')->toArray(),
                    'programas_con_FibraRizo_detalle' => $programas->map(function($p) use ($hiloEsperado) {
                        $fibraRizo = $p->FibraRizo;
                        $hiloObtenido = $fibraRizo !== null && $fibraRizo !== '' ? trim((string)$fibraRizo) : '';
                        $matchResult = $this->matchHilo($hiloEsperado, $hiloObtenido);
                        return [
                            'Id' => $p->Id,
                            'NoTelarId' => $p->NoTelarId,
                            'FibraRizo_raw' => $fibraRizo,
                            'FibraRizo_type' => gettype($fibraRizo),
                            'FibraRizo_isNull' => $fibraRizo === null,
                            'FibraRizo_isEmpty' => $fibraRizo === '',
                            'HiloObtenido' => $hiloObtenido,
                            'HiloEsperado' => $hiloEsperado,
                            'MatchResult' => $matchResult,
                            'CuentaRizo' => $p->CuentaRizo,
                        ];
                    })->toArray(),
                ]);

                Log::info('getResumenSemanas - Programas Rizo filtrados', [
                    'totalProgramasFiltrados' => $programasFiltrados->count(),
                    'programasFiltrados_ids' => $programasFiltrados->pluck('Id')->toArray(),
                    'lineasPorPrograma_keys' => array_keys($lineasPorPrograma),
                    'lineasPorPrograma_counts' => array_map(fn($c) => $c->count(), $lineasPorPrograma),
                    'lineasPorPrograma_detalle' => array_map(function($lineas, $pid) {
                        return [
                            'ProgramaId' => $pid,
                            'TotalLineas' => $lineas->count(),
                            'LineasConMtsRizo' => $lineas->filter(fn($l) => ($l->MtsRizo ?? 0) > 0)->count(),
                            'LineasConMtsPie' => $lineas->filter(fn($l) => ($l->MtsPie ?? 0) > 0)->count(),
                            'EjemploLineas' => $lineas->take(3)->map(fn($l) => [
                                'Id' => $l->Id,
                                'Fecha' => $l->Fecha instanceof Carbon ? $l->Fecha->format('Y-m-d') : (string)$l->Fecha,
                                'MtsRizo' => $l->MtsRizo ?? 0,
                                'MtsPie' => $l->MtsPie ?? 0
                            ])->toArray()
                        ];
                    }, $lineasPorPrograma, array_keys($lineasPorPrograma)),
                    'rango_fechas' => "{$fechaIni} a {$fechaFin}"
                ]);

                $resumenRizo = $this->procesarResumenPorTipo(
                    $programasFiltrados, $semanas, 'Rizo',
                    null, $hiloEsperado,
                    $this->nullSiVacio($v['calibre_original']),
                    $calibreEsVacio,
                    $lineasPorPrograma,
                    $fechaIni,
                    $fechaFin
                );

                Log::info('getResumenSemanas - Resumen Rizo generado', [
                    'totalItems' => count($resumenRizo),
                    'rango_fechas' => "{$fechaIni} a {$fechaFin}"
                ]);

                return response()->json([
                    'success' => true,
                    'data'    => ['rizo' => $resumenRizo, 'pie' => []],
                'semanas' => $semanas,
            ]);
            }

            // PIE
            $programasFiltrados = $programas->filter(function ($p) use ($salonEsperado, $hiloEsperado, $calibreEsperado, $calibreEsVacio) {
                $matchSalon = $this->matchSalon($salonEsperado, (string)($p->SalonTejidoId ?? ''));
                $tieneCuentaPie = !empty($p->CuentaPie);

                // Obtener FibraPie correctamente (es el único campo que existe en la BD para hilo en PIE)
                $fibraPieRaw = $p->FibraPie ?? null;
                $hiloPrograma = '';
                if ($fibraPieRaw !== null && $fibraPieRaw !== '' && trim((string)$fibraPieRaw) !== '') {
                    $hiloPrograma = trim((string)$fibraPieRaw);
                }

                $matchHilo = $this->matchHilo($hiloEsperado, $hiloPrograma);
                $matchCalibre = $this->matchCalibre($calibreEsperado, $calibreEsVacio, $p->CalibrePie ?? null);

                return $matchSalon && $tieneCuentaPie && $matchCalibre && $matchHilo;
            })->values();

            $resumenPie = $this->procesarResumenPorTipo(
                $programasFiltrados, $semanas, 'Pie',
                null, $hiloEsperado, $calibreEsperado, $calibreEsVacio, $lineasPorPrograma,
                $fechaIni,
                $fechaFin
            );

            return response()->json([
                'success' => true,
                'data'    => ['rizo' => [], 'pie' => $resumenPie],
                'semanas' => $semanas,
            ]);
        } catch (\Throwable $e) {
            Log::error('getResumenSemanas', ['msg' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener resumen de semanas: '.$e->getMessage(),
                'data'    => ['rizo' => [], 'pie' => []],
            ], 500);
        }
    }

    /**
     * Genera resumen por tipo usando líneas de ReqProgramaTejidoLine
     * Primero intenta usar las líneas desde la relación cargada con eager loading,
     * luego desde el array agrupado, y finalmente carga las líneas directamente si es necesario
     * @param array<string,\Illuminate\Support\Collection> $lineasPorPrograma Array de respaldo con líneas agrupadas por ProgramaId
     * @param string $fechaIni Fecha inicial para cargar líneas si no están disponibles
     * @param string $fechaFin Fecha final para cargar líneas si no están disponibles
     */
    private function procesarResumenPorTipo($programas, array $semanas, string $tipo,
        $cuentaEsperada = null, $hiloEsperado = null, $calibreEsperado = null, bool $calibreEsVacio = false, array $lineasPorPrograma = [], string $fechaIni = null, string $fechaFin = null)
    {
        $resumen = [];

        foreach ($programas as $programa) {
            if ($tipo === 'Rizo') {
                $cuenta      = trim((string)($programa->CuentaRizo ?? ''));
                $calibre     = $programa->CalibreRizo ?? $programa->Calibre ?? null;
                // IMPORTANTE: Solo usar FibraRizo (es el único campo que existe en la BD para hilo en RIZO)
                $fibraRizoRaw = $programa->FibraRizo ?? null;
                $hilo = '';
                if ($fibraRizoRaw !== null && $fibraRizoRaw !== '' && trim((string)$fibraRizoRaw) !== '') {
                    $hilo = trim((string)$fibraRizoRaw);
                }
                $campoMetros = 'MtsRizo';

                // Log detallado para debugging de FibraRizo
                Log::debug("procesarResumenPorTipo - Programa Rizo - Obteniendo hilo", [
                    'ProgramaId' => $programa->Id ?? null,
                    'NoTelarId' => $programa->NoTelarId ?? null,
                    'FibraRizo_raw' => $fibraRizoRaw,
                    'FibraRizo_type' => gettype($fibraRizoRaw),
                    'FibraRizo_isNull' => $fibraRizoRaw === null,
                    'FibraRizo_isEmpty' => $fibraRizoRaw === '',
                    'Hilo_obtenido' => $hilo,
                    'HiloEsperado' => $hiloEsperado,
                    'HiloEsperado_type' => gettype($hiloEsperado),
                ]);
            } else {
                $cuenta      = trim((string)($programa->CuentaPie ?? ''));
                $calibre     = $programa->CalibrePie ?? null;
                // IMPORTANTE: Solo usar FibraPie (es el único campo que existe en la BD para hilo en PIE)
                $fibraPieRaw = $programa->FibraPie ?? null;
                $hilo = '';
                if ($fibraPieRaw !== null && $fibraPieRaw !== '' && trim((string)$fibraPieRaw) !== '') {
                    $hilo = trim((string)$fibraPieRaw);
                }
                $campoMetros = 'MtsPie';
            }

            if ($cuenta === '') continue;

            // Log antes de validar hilo
            $matchHiloResult = $this->matchHilo($hiloEsperado, $hilo);
            Log::debug("procesarResumenPorTipo - Validando hilo", [
                'ProgramaId' => $programa->Id ?? null,
                'Tipo' => $tipo,
                'Hilo_obtenido' => $hilo,
                'HiloEsperado' => $hiloEsperado,
                'MatchResult' => $matchHiloResult,
            ]);

            if (!$matchHiloResult) continue;
            if (!$this->matchCalibre($calibreEsperado, $calibreEsVacio, $calibre)) continue;
            if ($cuentaEsperada !== null && $cuenta !== $cuentaEsperada) continue;

            $modelo  = $programa->ItemId ?? $programa->NombreProducto ?? '';
            $telarId = (string)($programa->NoTelarId ?? '');

            $clave = $tipo === 'Rizo'
                ? "{$telarId}|{$cuenta}|{$hilo}|{$modelo}"
                : "{$telarId}|{$cuenta}|{$calibre}|{$modelo}";

            $resumen[$clave] ??= [
                'TelarId'   => $telarId,
                'CuentaValor' => $cuenta,
                'Hilo'      => $hilo,
                'Calibre'   => $calibre,
                'Modelo'    => $modelo,
                'SemActual' => 0, 'SemActual1' => 0, 'SemActual2' => 0, 'SemActual3' => 0, 'SemActual4' => 0,
                'Total'     => 0,
            ];

            // Obtener las líneas del programa usando la relación Eloquent
            // Esto es la forma correcta: cuando se carga un programa, automáticamente tiene acceso a sus líneas
            $programaId = $programa->Id ?? null;
            $lineas = collect();

            if ($programaId) {
                // PRIORIDAD 1: Usar la relación cargada con eager loading (la forma correcta)
                if ($programa->relationLoaded('lineas')) {
                    $lineas = $programa->lineas;
                    Log::debug("Usando líneas desde relación cargada (eager loading)", [
                        'ProgramaId' => $programaId,
                        'TotalLineas' => $lineas->count()
                    ]);
                }
                // PRIORIDAD 2: Usar el array agrupado (compatibilidad con código anterior)
                elseif (isset($lineasPorPrograma[$programaId])) {
                    $lineas = $lineasPorPrograma[$programaId];
                    Log::debug("Usando líneas desde array agrupado", [
                        'ProgramaId' => $programaId,
                        'TotalLineas' => $lineas->count()
                    ]);
                }
                // PRIORIDAD 3: Cargar las líneas directamente usando la relación (fallback)
                else {
                    if ($fechaIni && $fechaFin) {
                        // Para SQL Server, usar CAST para comparar fechas correctamente
                        $lineas = $programa->lineas()
                            ->whereRaw("CAST(Fecha AS DATE) >= CAST(? AS DATE)", [$fechaIni])
                            ->whereRaw("CAST(Fecha AS DATE) <= CAST(? AS DATE)", [$fechaFin])
                            ->orderBy('Fecha')
                            ->get();
                        Log::debug("Cargando líneas directamente desde relación (fallback)", [
                            'ProgramaId' => $programaId,
                            'TotalLineas' => $lineas->count(),
                            'FechaIni' => $fechaIni,
                            'FechaFin' => $fechaFin
                        ]);
                    } else {
                        // Si no hay fechas, cargar todas las líneas del programa
                        $lineas = $programa->lineas;
                        Log::debug("Cargando todas las líneas del programa (sin filtro de fecha)", [
                            'ProgramaId' => $programaId,
                            'TotalLineas' => $lineas->count()
                        ]);
                    }
                }
            }

            // Verificar si hay líneas con MtsRizo > 0 para este programa (solo para debugging)
            $lineasConMtsRizo = $lineas->filter(function($l) use ($campoMetros) {
                $mts = (float)($l->{$campoMetros} ?? 0);
                return $mts > 0;
            });

            if ($tipo === 'Rizo' && $lineasConMtsRizo->count() > 0) {
                Log::info("Programa Rizo con líneas que tienen MtsRizo > 0", [
                    'ProgramaId' => $programaId,
                    'TelarId' => $telarId,
                    'Cuenta' => $cuenta,
                    'Hilo' => $hilo,
                    'Calibre' => $calibre,
                    'TotalLineas' => $lineas->count(),
                    'LineasConMtsRizo' => $lineasConMtsRizo->count(),
                    'EjemploLineasConMtsRizo' => $lineasConMtsRizo->take(5)->map(function($l) {
                        return [
                            'Id' => $l->Id,
                            'Fecha' => $l->Fecha instanceof Carbon ? $l->Fecha->format('Y-m-d') : (string)$l->Fecha,
                            'MtsRizo' => $l->MtsRizo ?? 0,
                            'MtsPie' => $l->MtsPie ?? 0,
                            'Rizo' => $l->Rizo ?? 0,
                            'Pie' => $l->Pie ?? 0
                        ];
                    })->toArray()
                ]);
            }

            Log::debug("Procesando programa {$tipo}", [
                'ProgramaId' => $programaId,
                'TelarId' => $telarId,
                'Cuenta' => $cuenta,
                'TotalLineas' => $lineas->count(),
                'LineasConMtsRizo' => $tipo === 'Rizo' ? $lineasConMtsRizo->count() : 0,
                'LineasPorPrograma_keys' => array_keys($lineasPorPrograma)
            ]);

            $lineasProcesadasCount = 0;
            $lineasSaltadasCount = 0;

            foreach ($lineas as $ln) {
                $fecha = $ln->Fecha ?? null;

                // Log detallado de cada línea
                Log::debug("Procesando línea en procesarResumenPorTipo", [
                    'LineaId' => $ln->Id ?? 'N/A',
                    'ProgramaId' => $programaId ?? 'N/A',
                    'Fecha_raw' => $fecha,
                    'Fecha_type' => gettype($fecha),
                    'Fecha_isCarbon' => $fecha instanceof Carbon,
                    'Fecha_isDateTime' => $fecha instanceof \DateTime,
                    'CampoMetros' => $campoMetros,
                    'MtsRizo' => $ln->MtsRizo ?? 0,
                    'MtsPie' => $ln->MtsPie ?? 0,
                ]);

                if (!$fecha) {
                    $lineasSaltadasCount++;
                    Log::debug("Línea sin fecha, saltando", [
                        'LineaId' => $ln->Id ?? 'N/A',
                        'ProgramaId' => $programaId ?? 'N/A'
                    ]);
                continue;
            }

                $mts = (float)($ln->{$campoMetros} ?? 0);

                // Log detallado de los metros
                Log::debug("Línea - Verificando metros", [
                    'LineaId' => $ln->Id ?? 'N/A',
                    'ProgramaId' => $programaId ?? 'N/A',
                    'Fecha' => $fecha instanceof Carbon ? $fecha->format('Y-m-d') : (string)$fecha,
                    'CampoMetros' => $campoMetros,
                    'MtsRizo' => $ln->MtsRizo ?? 0,
                    'MtsPie' => $ln->MtsPie ?? 0,
                    'Metros_calculados' => $mts,
                    'Tipo' => $tipo
                ]);

                if ($mts <= 0) {
                    $lineasSaltadasCount++;
                    Log::debug("Línea con metros <= 0, saltando", [
                        'LineaId' => $ln->Id ?? 'N/A',
                        'Fecha' => $fecha instanceof Carbon ? $fecha->format('Y-m-d') : (string)$fecha,
                        'Metros' => $mts,
                        'CampoMetros' => $campoMetros,
                        'MtsRizo' => $ln->MtsRizo ?? 0,
                        'MtsPie' => $ln->MtsPie ?? 0,
                        'Tipo' => $tipo
                    ]);
                    continue;
                }

                // Convertir fecha a Carbon
                try {
                    if ($fecha instanceof Carbon) {
                        $f = $fecha->copy();
                    } elseif ($fecha instanceof \DateTime) {
                        $f = Carbon::instance($fecha);
                    } elseif (is_string($fecha)) {
                        // Intentar parsear como fecha Y-m-d
                        $f = Carbon::createFromFormat('Y-m-d', $fecha);
                        if (!$f) {
                            // Fallback a parse genérico
                            $f = Carbon::parse($fecha);
                        }
                    } else {
                        $f = Carbon::parse($fecha);
                    }
                    $f->setTime(0, 0, 0);

                    Log::debug("Fecha parseada correctamente", [
                        'LineaId' => $ln->Id ?? 'N/A',
                        'Fecha_original' => $fecha,
                        'Fecha_parsed' => $f->format('Y-m-d'),
                        'Fecha_timestamp' => $f->timestamp,
                        'Metros' => $mts
                    ]);
                } catch (\Throwable $e) {
                    $lineasSaltadasCount++;
                    Log::warning("Error parseando fecha de línea", [
                        'LineaId' => $ln->Id ?? 'N/A',
                        'ProgramaId' => $programaId ?? 'N/A',
                        'Fecha' => $fecha,
                        'Fecha_type' => gettype($fecha),
                        'Error' => $e->getMessage()
                    ]);
                    continue;
                }

                $idx = $this->semanaIndex($semanas, $f);
                if ($idx === null) {
                    $lineasSaltadasCount++;
                    Log::debug("Fecha fuera del rango de semanas", [
                        'LineaId' => $ln->Id ?? 'N/A',
                        'Fecha' => $f->format('Y-m-d'),
                        'Rango' => "{$semanas[0]['inicio']} a {$semanas[4]['fin']}",
                        'Metros' => $mts
                    ]);
                continue;
            }

                $lineasProcesadasCount++;

                // Acumular metros en la semana correspondiente
                if ($idx === 0)      $resumen[$clave]['SemActual']  += $mts;
                elseif ($idx === 1)  $resumen[$clave]['SemActual1'] += $mts;
                elseif ($idx === 2)  $resumen[$clave]['SemActual2'] += $mts;
                elseif ($idx === 3)  $resumen[$clave]['SemActual3'] += $mts;
                elseif ($idx === 4)  $resumen[$clave]['SemActual4'] += $mts;

                $resumen[$clave]['Total'] += $mts;

                Log::debug("Línea procesada exitosamente", [
                    'LineaId' => $ln->Id ?? 'N/A',
                    'Fecha' => $f->format('Y-m-d'),
                    'SemanaIndex' => $idx,
                    'SemanaLabel' => $semanas[$idx]['label'] ?? 'N/A',
                    'Metros' => $mts,
                    'Total_acumulado' => $resumen[$clave]['Total']
                ]);
            }

            if ($lineasProcesadasCount > 0 || $lineasSaltadasCount > 0) {
                Log::info("Programa {$tipo} procesado", [
                    'TelarId' => $telarId,
                    'ProgramaId' => $programaId ?? 'N/A',
                    'LineasProcesadas' => $lineasProcesadasCount,
                    'LineasSaltadas' => $lineasSaltadasCount,
                    'TotalLineas' => $lineas->count(),
                    'Clave' => $clave
                ]);
            }
        }

        return collect($resumen)->values()->map(function ($it) use ($tipo) {
            if ($tipo === 'Rizo') {
                return [
                    'TelarId'            => $it['TelarId'],
                    'CuentaRizo'         => $it['CuentaValor'],
                    'Hilo'               => $it['Hilo'],
                    'Calibre'            => $it['Calibre'],
                    'Modelo'             => $it['Modelo'],
                    'SemActualMtsRizo'   => round((float)$it['SemActual'], 2),
                    'SemActual1MtsRizo'  => round((float)$it['SemActual1'], 2),
                    'SemActual2MtsRizo'  => round((float)$it['SemActual2'], 2),
                    'SemActual3MtsRizo'  => round((float)$it['SemActual3'], 2),
                    'SemActual4MtsRizo'  => round((float)$it['SemActual4'], 2),
                    'Total'              => round((float)$it['Total'], 2),
                ];
            }

            return [
                'TelarId'            => $it['TelarId'],
                'CuentaPie'          => $it['CuentaValor'],
                'Hilo'               => $it['Hilo'],
                'CalibrePie'         => $it['Calibre'],
                'Modelo'             => $it['Modelo'],
                'SemActualMtsPie'    => round((float)$it['SemActual'], 2),
                'SemActual1MtsPie'   => round((float)$it['SemActual1'], 2),
                'SemActual2MtsPie'   => round((float)$it['SemActual2'], 2),
                'SemActual3MtsPie'   => round((float)$it['SemActual3'], 2),
                'SemActual4MtsPie'   => round((float)$it['SemActual4'], 2),
                'Total'              => round((float)$it['Total'], 2),
            ];
        })->sortBy([['TelarId','asc'], ['Modelo','asc']])->values()->toArray();
    }

    /* =========================== PRIVADOS ============================ */

    private function baseQuery()
    {
        return TejInventarioTelares::query()
            ->where('status', self::STATUS_ACTIVO)
            ->select(self::COLS_TELARES);
    }

    private function normalizeTelares($rows)
    {
        return collect($rows)->values()->map(function ($r, int $i) {
            return [
                'no_telar'   => $this->normalizeTelar($r->no_telar ?? null),
                'tipo'       => $this->str($r->tipo ?? null),
                'cuenta'     => $this->str($r->cuenta ?? null),
                'calibre'    => $this->num($r->calibre ?? null),
                'fecha'      => $this->normalizeDate($r->fecha ?? null),
                'turno'      => $this->str($r->turno ?? null),
                'hilo'       => $this->str($r->hilo ?? null),
                'metros'     => $this->num($r->metros ?? null),
                'no_julio'   => $this->str($r->no_julio ?? null),
                'no_orden'   => $this->str($r->no_orden ?? null),
                'tipo_atado' => $this->str($r->tipo_atado ?? 'Normal'),
                'salon'      => $this->str($r->salon ?? null),
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
    private function num($v) { return ($v === null || $v === '') ? 0.0 : (float)$v; }

    private function normalizeDate($v): ?string
    {
        if ($v === null) return null;
        try { return ($v instanceof Carbon ? $v : Carbon::parse($v))->toIso8601String(); }
        catch (\Throwable) { return null; }
    }

    private function parseDateFlexible(string $v): ?Carbon
    {
        $v = trim($v);
        foreach (['Y-m-d','d/m/Y','d-m-Y','Y/m/d','Y.m.d','d.m.Y'] as $fmt) {
            try { return Carbon::createFromFormat($fmt, $v)->startOfDay(); } catch (\Throwable) {}
        }
        try { return Carbon::parse($v)->startOfDay(); } catch (\Throwable) { return null; }
    }

    /** Normaliza el tipo a 'Rizo' | 'Pie' | null */
    private function normalizeTipo($tipo): ?string
    {
        if ($tipo === null) return null;
        $t = strtoupper(trim((string)$tipo));
        return $t === 'RIZO' ? 'Rizo' : ($t === 'PIE' ? 'Pie' : null);
    }

    /** Valida que los telares compartan tipo y (si existen) calibre/hilo/salón */
    private function validarTelaresConsistentes(array $telares): array
    {
        $t0 = $telares[0] ?? [];

        $tipo   = strtoupper(trim((string)($t0['tipo'] ?? '')));
        if ($tipo === '') return ['error'=>true,'mensaje'=>'El telar seleccionado debe tener un tipo definido'];

        $calibreOriginal = (string)($t0['calibre'] ?? '');
        $calibreVacio    = ($calibreOriginal === '');
        $calibreRef      = $calibreVacio ? null : $calibreOriginal;

        $hiloRef   = (string)($t0['hilo'] ?? '');
        $salonRef  = strtoupper(trim((string)($t0['salon'] ?? '')));

        foreach ($telares as $t) {
            $tipoAct = strtoupper(trim((string)($t['tipo'] ?? '')));
            if ($tipoAct !== $tipo) return ['error'=>true,'mensaje'=>"Todos los telares deben tener el mismo tipo. {$tipoAct} ≠ {$tipo}"];

            $calAct = (string)($t['calibre'] ?? '');
            if ($calibreRef !== null && $calAct !== '') {
                if (abs(((float)$calAct) - ((float)$calibreRef)) >= 0.01) {
                    return ['error'=>true,'mensaje'=>"Todos los telares deben tener el mismo calibre. {$calAct} ≠ {$calibreRef}"];
                }
            }

            $hiloAct = trim((string)($t['hilo'] ?? ''));
            if ($hiloRef !== '' && $hiloAct !== '' && strcasecmp($hiloAct, $hiloRef) !== 0) {
                return ['error'=>true,'mensaje'=>"Todos los telares deben tener el mismo hilo. {$hiloAct} ≠ {$hiloRef}"];
            }

            $salAct = strtoupper(trim((string)($t['salon'] ?? '')));
            if ($salonRef !== '' && $salAct !== '' && $salAct !== $salonRef) {
                return ['error'=>true,'mensaje'=>"Todos los telares deben tener el mismo salón. {$salAct} ≠ {$salonRef}"];
            }
        }

                return [
            'error'            => false,
            'tipo'             => $tipo,
            'calibre'          => $calibreRef,
            'calibre_vacio'    => $calibreVacio,
            'calibre_original' => $calibreOriginal,
            'hilo'             => $hiloRef, // '' => buscar vacíos
            'salon'            => $salonRef,
        ];
    }

    /** Construye N semanas desde el lunes actual */
    private function construirSemanas(int $n = 5): array
    {
        $inicio = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $ini = $inicio->copy()->addWeeks($i);
            $fin = $ini->copy()->endOfWeek(Carbon::SUNDAY);
            $out[] = [
                'numero' => $i + 1,
                'inicio' => $ini->format('Y-m-d'),
                'fin'    => $fin->format('Y-m-d'),
                'label'  => $i === 0 ? 'Sem Actual' : "Sem Actual +{$i}",
            ];
        }
        return $out;
    }

    /** Devuelve el índice [0..n-1] de la semana o null si la fecha está fuera del rango */
    private function semanaIndex(array $semanas, Carbon $fecha): ?int
    {
        foreach ($semanas as $i => $sem) {
            $ini = Carbon::parse($sem['inicio'])->startOfDay();
            $fin = Carbon::parse($sem['fin'])->endOfDay();
            if ($fecha->greaterThanOrEqualTo($ini) && $fecha->lessThanOrEqualTo($fin)) return $i;
        }
        return null;
    }

    /** Carga todas las líneas de programas en un rango y las agrupa por ProgramaId */
    private function cargarLineasPorPrograma(array $programaIds, string $ini, string $fin): array
    {
        if (empty($programaIds)) {
            Log::warning('cargarLineasPorPrograma: programaIds vacío');
            return [];
        }

        // IMPORTANTE: Consultar líneas directamente desde ReqProgramaTejidoLine
        // Para SQL Server, usar CAST para convertir fechas a DATE para comparación más precisa
        try {
            // Primero intentar con whereDate (método estándar de Laravel)
            $lineas = ReqProgramaTejidoLine::whereIn('ProgramaId', $programaIds)
                ->whereDate('Fecha', '>=', $ini)
                ->whereDate('Fecha', '<=', $fin)
                ->orderBy('ProgramaId')
                ->orderBy('Fecha')
                ->get();

            Log::debug('cargarLineasPorPrograma - Consulta con whereDate', [
                'programaIds' => $programaIds,
                'fechaInicio' => $ini,
                'fechaFin' => $fin,
                'totalLineas' => $lineas->count(),
                'sql' => ReqProgramaTejidoLine::whereIn('ProgramaId', $programaIds)
                    ->whereDate('Fecha', '>=', $ini)
                    ->whereDate('Fecha', '<=', $fin)
                    ->toSql()
            ]);

            // Si no se encuentran líneas, intentar con CAST (más compatible con SQL Server)
            if ($lineas->isEmpty()) {
                Log::info('cargarLineasPorPrograma - Intentando consulta alternativa con CAST');
                $lineas = ReqProgramaTejidoLine::whereIn('ProgramaId', $programaIds)
                    ->whereRaw("CAST(Fecha AS DATE) >= CAST(? AS DATE)", [$ini])
                    ->whereRaw("CAST(Fecha AS DATE) <= CAST(? AS DATE)", [$fin])
                    ->orderBy('ProgramaId')
                    ->orderBy('Fecha')
                    ->get();

                Log::info('cargarLineasPorPrograma - Consulta con CAST', [
                    'totalLineas' => $lineas->count()
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('cargarLineasPorPrograma - Error en consulta', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // Fallback a consulta básica sin filtro de fecha
            $lineas = collect();
        }

        // Log detallado para debugging
        Log::info('cargarLineasPorPrograma - Líneas cargadas', [
            'programaIds_count' => count($programaIds),
            'programaIds' => $programaIds,
            'fechaInicio' => $ini,
            'fechaFin' => $fin,
            'totalLineas' => $lineas->count(),
            'ejemplo_lineas' => $lineas->take(10)->map(function($l) {
                $fecha = $l->Fecha;
                return [
                    'Id' => $l->Id,
                    'ProgramaId' => $l->ProgramaId,
                    'Fecha' => $fecha ? (
                        $fecha instanceof Carbon
                            ? $fecha->format('Y-m-d')
                            : (is_string($fecha) ? $fecha : (string)$fecha)
                    ) : 'NULL',
                    'Fecha_type' => gettype($fecha),
                    'Fecha_isCarbon' => $fecha instanceof Carbon,
                    'MtsRizo' => $l->MtsRizo ?? 0,
                    'MtsPie' => $l->MtsPie ?? 0,
                ];
            })->toArray(),
        ]);

        // Si no se encuentran líneas, intentar sin filtro de fecha para debug
        if ($lineas->isEmpty()) {
            Log::warning('cargarLineasPorPrograma - No se encontraron líneas con filtro de fecha', [
                'programaIds' => $programaIds,
                'fechaInicio' => $ini,
                'fechaFin' => $fin,
                'intentando_sin_filtro' => true
            ]);

            // Consultar sin filtro de fecha para ver si existen líneas
            $lineasSinFiltro = ReqProgramaTejidoLine::whereIn('ProgramaId', $programaIds)
                ->orderBy('ProgramaId')
                ->orderBy('Fecha')
                ->get(); // Sin límite para ver todas

            Log::info('cargarLineasPorPrograma - TODAS las líneas sin filtro de fecha', [
                'totalLineasSinFiltro' => $lineasSinFiltro->count(),
                'rango_buscado' => "{$ini} a {$fin}",
                'todas_las_lineas' => $lineasSinFiltro->map(function($l) use ($ini, $fin) {
                    $fecha = $l->Fecha;
                    $fechaStr = $fecha ? (
                        $fecha instanceof Carbon
                            ? $fecha->format('Y-m-d')
                            : (is_string($fecha) ? $fecha : (string)$fecha)
                    ) : 'NULL';

                    // Verificar si la fecha está dentro del rango
                    $fechaCarbon = $fecha instanceof Carbon ? $fecha : ($fecha ? Carbon::parse($fecha) : null);
                    $dentroRango = false;
                    if ($fechaCarbon) {
                        $iniCarbon = Carbon::parse($ini)->startOfDay();
                        $finCarbon = Carbon::parse($fin)->endOfDay();
                        $dentroRango = $fechaCarbon->greaterThanOrEqualTo($iniCarbon) &&
                                      $fechaCarbon->lessThanOrEqualTo($finCarbon);
                    }

                    return [
                        'Id' => $l->Id,
                        'ProgramaId' => $l->ProgramaId,
                        'Fecha' => $fechaStr,
                        'Fecha_type' => gettype($fecha),
                        'Fecha_isCarbon' => $fecha instanceof Carbon,
                        'MtsRizo' => $l->MtsRizo ?? 0,
                        'MtsPie' => $l->MtsPie ?? 0,
                        'DentroRango' => $dentroRango,
                        'Fecha_timestamp' => $fechaCarbon ? $fechaCarbon->timestamp : null,
                        'Rango_inicio_timestamp' => Carbon::parse($ini)->startOfDay()->timestamp,
                        'Rango_fin_timestamp' => Carbon::parse($fin)->endOfDay()->timestamp,
                    ];
                })->toArray()
            ]);

            // Intentar consulta alternativa usando CAST o CONVERT para SQL Server
            // Esto puede ayudar si whereDate no funciona correctamente
            try {
                $lineasAlternativa = ReqProgramaTejidoLine::whereIn('ProgramaId', $programaIds)
                    ->whereRaw("CAST(Fecha AS DATE) >= CAST(? AS DATE)", [$ini])
                    ->whereRaw("CAST(Fecha AS DATE) <= CAST(? AS DATE)", [$fin])
                    ->orderBy('ProgramaId')
                    ->orderBy('Fecha')
                    ->get();

                Log::info('cargarLineasPorPrograma - Consulta alternativa con CAST', [
                    'totalLineasAlternativa' => $lineasAlternativa->count(),
                    'ejemplo_lineas' => $lineasAlternativa->take(5)->map(function($l) {
                        $fecha = $l->Fecha;
                        return [
                            'Id' => $l->Id,
                            'ProgramaId' => $l->ProgramaId,
                            'Fecha' => $fecha instanceof Carbon ? $fecha->format('Y-m-d') : (string)$fecha,
                            'MtsRizo' => $l->MtsRizo ?? 0,
                            'MtsPie' => $l->MtsPie ?? 0,
                        ];
                    })->toArray()
                ]);

                // Si la consulta alternativa encuentra líneas, usarlas
                if ($lineasAlternativa->isNotEmpty()) {
                    $lineas = $lineasAlternativa;
                    Log::info('cargarLineasPorPrograma - Usando líneas de consulta alternativa');
                }
            } catch (\Throwable $e) {
                Log::warning('cargarLineasPorPrograma - Error en consulta alternativa', [
                    'error' => $e->getMessage()
                ]);
            }
        }

        $map = [];
        foreach ($lineas as $l) {
            $pid = $l->ProgramaId;
            if (!isset($map[$pid])) {
                $map[$pid] = collect();
            }
            $map[$pid]->push($l);
        }

        Log::info('cargarLineasPorPrograma - Agrupación', [
            'programas_con_lineas' => count($map),
            'programas_con_lineas_keys' => array_keys($map),
            'lineas_por_programa' => array_map(fn($c) => $c->count(), $map),
        ]);

        return $map;
    }

    /** Hilo: '' => buscar vacío/null; null => sin filtro; string => coincidencia exacta (case-insensitive) */
    private function matchHilo($esperado, string $actual): bool
    {
        $act = trim($actual);
        $esp = $esperado !== null ? trim((string)$esperado) : null;

        if ($esperado === null) {
            return true; // Sin filtro
        }

        if ($esperado === '' || $esp === '') {
            return ($act === '' || $act === null || $act === 'null');
        }

        // Comparación case-insensitive
        $result = strcasecmp($act, $esp) === 0;

        // Solo log si no coincide (para debugging)
        if (!$result) {
            Log::debug("matchHilo - No coincide", [
                'esperado' => $esp,
                'actual' => $act,
                'comparacion' => "case-insensitive: '{$act}' vs '{$esp}'"
            ]);
        }

        return $result;
    }

    /** Calibre: $vacio=true => requiere null/vacío; valor numérico => tolerancia 0.01; null => sin filtro */
    private function matchCalibre($esperado, bool $vacio, $actual): bool
    {
        if ($vacio) return ($actual === null || $actual === '' || trim((string)$actual) === '');
        if ($esperado === null || $esperado === '') return true;
        $a = is_numeric($actual) ? (float)$actual : 0;
        $e = (float)$esperado;
        return abs($a - $e) < 0.01;
    }

    /** Salón: si $esperado vacío no filtra */
    private function matchSalon(string $esperado, string $actual): bool
    {
        if ($esperado === '') return true;
        return strtoupper(trim($actual)) === strtoupper(trim($esperado));
    }

    private function nullSiVacio($v)
    {
        return ($v === '' ? null : $v);
    }
}
