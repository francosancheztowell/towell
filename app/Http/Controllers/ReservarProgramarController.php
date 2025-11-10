<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\TejInventarioTelares;
use App\Models\InvTelasReservadas;
use App\Models\ReqProgramaTejido;
use App\Models\ReqProgramaTejidoLine;
use App\Models\URDCatalogoMaquina;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ReservarProgramarController extends Controller
{
    private const STATUS_ACTIVO = 'Activo';
    private const FALLBACK_METROS_DEFAULT = true; // Usa MtsPie si MtsRizo==0 (y viceversa)

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
                'no_orden' => ['nullable','string','max:50'],
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
            if ($request->filled('no_orden')) $update['no_orden'] = (string)$request->input('no_orden');

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

            // 2) Limpiar campos de reserva
            $telar->update(['hilo'=>null,'metros'=>null,'no_julio'=>null,'no_orden'=>null]);

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

        // Obtener máquinas de urdido desde la base de datos
        $maquinasUrdido = URDCatalogoMaquina::where('Departamento', 'Urdido')
            ->orderBy('Nombre')
            ->pluck('Nombre')
            ->toArray();

        return view('modulos.programa_urd_eng.programacion-requerimientos', [
            'telaresSeleccionados' => $telares,
            'opcionesUrdido' => $maquinasUrdido,
        ]);
    }

    public function creacionOrdenes(Request $request)
    {
        $telares = [];
        $telaresJson = $request->query('telares');

        if ($telaresJson) {
            try {
                $telares = json_decode(urldecode($telaresJson), true) ?: [];
            } catch (\Throwable $e) {
                Log::error('creacionOrdenes: parse JSON', ['msg' => $e->getMessage()]);
                $telares = [];
            }
        }

        // Obtener máquinas de urdido desde la base de datos
        $maquinasUrdido = URDCatalogoMaquina::where('Departamento', 'Urdido')
            ->orderBy('Nombre')
            ->pluck('Nombre')
            ->toArray();

        return view('modulos.programa_urd_eng.creacion-ordenes', [
            'telaresSeleccionados' => $telares,
            'opcionesUrdido' => $maquinasUrdido,
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

            // Flag para fallback de metros (default: true)
            $usarFallbackMetros = (bool)$request->boolean('fallback_metros', self::FALLBACK_METROS_DEFAULT);

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

            // Hilos reales por telar
            $telaresConHilo = TejInventarioTelares::whereIn('no_telar', $noTelares)
                ->where('status', self::STATUS_ACTIVO)
                ->select('no_telar', 'tipo', 'hilo', 'salon', 'calibre')
                ->get()
                ->groupBy('no_telar')
                ->map(fn($g) => $g->first());

            $hiloPorTelar = [];
            foreach ($telaresConHilo as $telar) {
                $noTelar = (string)($telar->no_telar ?? '');
                $hiloTelar = trim((string)($telar->hilo ?? ''));
                if ($noTelar !== '') $hiloPorTelar[$noTelar] = $hiloTelar;
            }

            Log::info('getResumenSemanas - Verificación de telares desde BD', [
                'hiloPorTelar' => $hiloPorTelar,
                'hiloEsperadoDesdeValidacion' => $hiloEsperado,
                'telares_bd' => $telaresConHilo->map(function($t) {
                    return [
                        'no_telar' => $t->no_telar,
                        'tipo' => $t->tipo,
                        'hilo' => $t->hilo,
                        'salon' => $t->salon,
                        'calibre' => $t->calibre,
                    ];
                })->values()->toArray()
            ]);

            Log::info('getResumenSemanas - Inicio procesamiento', [
                'noTelares' => $noTelares,
                'tipoEsperado' => $tipoEsperado,
                'calibreEsperado' => $calibreEsperado,
                'calibreEsVacio' => $calibreEsVacio,
                'hiloEsperado' => $hiloEsperado,
                'hiloEsperado_type' => gettype($hiloEsperado),
                'salonEsperado' => $salonEsperado,
                'fechaIni' => $fechaIni,
                'fechaFin' => $fechaFin,
                'semanas' => $semanas,
                'usarFallbackMetros' => $usarFallbackMetros,
            ]);

            // Programas + líneas (eager)
            $programas = ReqProgramaTejido::whereIn('NoTelarId', $noTelares)
                ->with(['lineas' => function($query) use ($fechaIni, $fechaFin) {
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

            $lineasPorPrograma = [];
            foreach ($programas as $programa) {
                if ($programa->Id && $programa->lineas->count() > 0) {
                    $lineasPorPrograma[$programa->Id] = $programa->lineas;
                }
            }

            if ($tipoEsperado === 'RIZO') {
                $programasFiltrados = $programas->filter(function ($p) use ($salonEsperado, $hiloEsperado, $calibreEsperado, $calibreEsVacio, $hiloPorTelar) {
                    $matchSalon = $this->matchSalon($salonEsperado, (string)($p->SalonTejidoId ?? ''));
                    $tieneCuentaRizo = !empty($p->CuentaRizo);

                    $fibraRizoRaw = $p->FibraRizo ?? null;
                    $hiloPrograma = ($fibraRizoRaw !== null && trim((string)$fibraRizoRaw) !== '') ? trim((string)$fibraRizoRaw) : '';

                    $noTelarPrograma = (string)($p->NoTelarId ?? '');
                    $hiloTelarBD = $hiloPorTelar[$noTelarPrograma] ?? '';
                    $matchHiloTelar = true;

                    if ($hiloTelarBD !== '') {
                        if ($hiloPrograma === '') {
                            $matchHiloTelar = false;
                            Log::warning('DISCREPANCIA - Telar tiene hilo pero programa no tiene FibraRizo', [
                                'NoTelarId' => $noTelarPrograma,
                                'HiloTelar_BD' => $hiloTelarBD,
                                'FibraRizoPrograma' => '(vacío)',
                                'ProgramaId' => $p->Id ?? null,
                            ]);
                        } else {
                            $matchHiloTelar = $this->matchHilo($hiloTelarBD, $hiloPrograma);
                            if (!$matchHiloTelar) {
                                Log::warning('DISCREPANCIA - Hilo del telar no coincide con FibraRizo del programa', [
                                    'NoTelarId' => $noTelarPrograma,
                                    'HiloTelar_BD' => $hiloTelarBD,
                                    'FibraRizoPrograma' => $hiloPrograma,
                                    'HiloEsperado_Validacion' => $hiloEsperado,
                                    'ProgramaId' => $p->Id ?? null,
                                    'CuentaRizo' => $p->CuentaRizo ?? null,
                                    'SalonTejidoId' => $p->SalonTejidoId ?? null,
                                ]);
                            }
                        }
                    } elseif ($hiloEsperado !== null && $hiloEsperado !== '') {
                        $matchHiloTelar = $this->matchHilo($hiloEsperado, $hiloPrograma);
                    }

                    $matchCalibre = true;
                    if (!$calibreEsVacio && $calibreEsperado !== null) {
                        $matchCalibre = $this->matchCalibre($calibreEsperado, $calibreEsVacio, $p->CalibreRizo ?? null);
                    }

                    return $matchSalon && $tieneCuentaRizo && $matchHiloTelar && $matchCalibre;
                })->values();

                $resumenRizo = $this->procesarResumenPorTipo(
                    $programasFiltrados, $semanas, 'Rizo',
                    null, $hiloEsperado,
                    $this->nullSiVacio($v['calibre_original']),
                    $calibreEsVacio,
                    $lineasPorPrograma,
                    $fechaIni,
                    $fechaFin,
                    $usarFallbackMetros
                );

                return response()->json([
                    'success' => true,
                    'data'    => ['rizo' => $resumenRizo, 'pie' => []],
                    'semanas' => $semanas,
                ]);
            }

            // PIE - Para PIE no se filtra por hilo
            $programasFiltrados = $programas->filter(function ($p) use ($salonEsperado, $calibreEsperado, $calibreEsVacio) {
                $matchSalon = $this->matchSalon($salonEsperado, (string)($p->SalonTejidoId ?? ''));
                $tieneCuentaPie = !empty($p->CuentaPie);
                $matchCalibre = $this->matchCalibre($calibreEsperado, $calibreEsVacio, $p->CalibrePie ?? null);

                // Para PIE, solo se filtra por salón, cuenta y calibre (NO por hilo)
                return $matchSalon && $tieneCuentaPie && $matchCalibre;
            })->values();

            $resumenPie = $this->procesarResumenPorTipo(
                $programasFiltrados, $semanas, 'Pie',
                null, $hiloEsperado, $calibreEsperado, $calibreEsVacio, $lineasPorPrograma,
                $fechaIni,
                $fechaFin,
                $usarFallbackMetros
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
     * Genera resumen por tipo usando líneas de ReqProgramaTejidoLine.
     * **IMPORTANTE**: No se filtra por Total > 0 al final; si existe el programa, se regresa
     * con ceros en las semanas cuando no hay metros en el rango.
     */
    private function procesarResumenPorTipo(
        $programas,
        array $semanas,
        string $tipo,
        $cuentaEsperada = null,
        $hiloEsperado = null,
        $calibreEsperado = null,
        bool $calibreEsVacio = false,
        array $lineasPorPrograma = [],
        string $fechaIni = null,
        string $fechaFin = null,
        bool $usarFallbackMetros = false
    ) {
        $resumen = [];

        foreach ($programas as $programa) {
            if ($tipo === 'Rizo') {
                $cuenta       = trim((string)($programa->CuentaRizo ?? ''));
                $calibre      = $programa->CalibreRizo ?? $programa->Calibre ?? null;
                $fibraRizoRaw = $programa->FibraRizo ?? null;
                $hilo         = ($fibraRizoRaw !== null && trim((string)$fibraRizoRaw) !== '') ? trim((string)$fibraRizoRaw) : '';
                $campoMetros  = 'MtsRizo';
            } else {
                $cuenta       = trim((string)($programa->CuentaPie ?? ''));
                $calibre      = $programa->CalibrePie ?? null;
                $fibraPieRaw  = $programa->FibraPie ?? null;
                $hilo         = ($fibraPieRaw !== null && trim((string)$fibraPieRaw) !== '') ? trim((string)$fibraPieRaw) : '';
                $campoMetros  = 'MtsPie';
            }

            if ($cuenta === '') continue;

            // Para PIE, no se filtra por hilo
            if ($tipo !== 'Pie' && !$this->matchHilo($hiloEsperado, $hilo)) continue;
            if (!$this->matchCalibre($calibreEsperado, $calibreEsVacio, $calibre)) continue;
            if ($cuentaEsperada !== null && $cuenta !== $cuentaEsperada) continue;

            $modelo  = $programa->ItemId ?? $programa->NombreProducto ?? '';
            $telarId = (string)($programa->NoTelarId ?? '');

            $clave = $tipo === 'Rizo'
                ? "{$telarId}|{$cuenta}|{$hilo}|{$modelo}"
                : "{$telarId}|{$cuenta}|{$calibre}|{$modelo}";

            // Siempre inicializar el item para asegurar que aparezca aunque todo sea 0
            $resumen[$clave] ??= [
                'TelarId'   => $telarId,
                'CuentaValor' => $cuenta,
                'Hilo'      => $hilo,
                'Calibre'   => $calibre,
                'Modelo'    => $modelo,
                'SemActual' => 0, 'SemActual1' => 0, 'SemActual2' => 0, 'SemActual3' => 0, 'SemActual4' => 0,
                'SemActualKilos' => 0, 'SemActual1Kilos' => 0, 'SemActual2Kilos' => 0, 'SemActual3Kilos' => 0, 'SemActual4Kilos' => 0,
                'Total'     => 0,
                'TotalKilos' => 0,
            ];

            $programaId = $programa->Id ?? null;
            $lineas = collect();

            if ($programaId) {
                if ($programa->relationLoaded('lineas')) {
                    $lineas = $programa->lineas;
                } elseif (isset($lineasPorPrograma[$programaId])) {
                    $lineas = $lineasPorPrograma[$programaId];
                } else {
                    if ($fechaIni && $fechaFin) {
                        $lineas = $programa->lineas()
                            ->whereRaw("CAST(Fecha AS DATE) >= CAST(? AS DATE)", [$fechaIni])
                            ->whereRaw("CAST(Fecha AS DATE) <= CAST(? AS DATE)", [$fechaFin])
                            ->orderBy('Fecha')
                            ->get();
                    } else {
                        $lineas = $programa->lineas;
                    }
                }
            }

            foreach ($lineas as $ln) {
                $fecha = $ln->Fecha ?? null;
                if (!$fecha) continue;

                // Métrica principal (metros)
                $mts = (float)($ln->{$campoMetros} ?? 0);

                // Fallback de metros si la principal es 0/NULL
                if ($usarFallbackMetros && $mts <= 0) {
                    $altCampo = $campoMetros === 'MtsRizo' ? 'MtsPie' : 'MtsRizo';
                    $alt = (float)($ln->{$altCampo} ?? 0);
                    if ($alt > 0) {
                        Log::debug('Fallback metros usado', [
                            'LineaId'   => $ln->Id ?? null,
                            'Tipo'      => $tipo,
                            'CampoMain' => $campoMetros,
                            'CampoAlt'  => $altCampo,
                            'ValorAlt'  => $alt,
                        ]);
                        $mts = $alt;
                    }
                }

                // Kilos desde la línea
                $kilos = (float)($ln->Kilos ?? 0);

                // Procesar si hay metros o kilos (para mostrar datos incluso si solo hay kilos)
                if ($mts <= 0 && $kilos <= 0) continue;

                // Normalizar fecha
                if ($fecha instanceof Carbon) {
                    $f = $fecha->copy()->setTime(0, 0, 0);
                } elseif ($fecha instanceof \DateTime) {
                    $f = Carbon::instance($fecha)->setTime(0, 0, 0);
                } elseif (is_string($fecha)) {
                    try {
                        $f = Carbon::createFromFormat('Y-m-d', $fecha) ?: Carbon::parse($fecha);
                    } catch (\Throwable) {
                        continue;
                    }
                    $f->setTime(0, 0, 0);
                } else {
                    try { $f = Carbon::parse($fecha)->startOfDay(); } catch (\Throwable) { continue; }
                }

                $idx = $this->semanaIndex($semanas, $f);
                if ($idx === null) continue;

                // Acumular metros y kilos por semana
                if ($idx === 0) {
                    $resumen[$clave]['SemActual'] += $mts;
                    $resumen[$clave]['SemActualKilos'] += $kilos;
                } elseif ($idx === 1) {
                    $resumen[$clave]['SemActual1'] += $mts;
                    $resumen[$clave]['SemActual1Kilos'] += $kilos;
                } elseif ($idx === 2) {
                    $resumen[$clave]['SemActual2'] += $mts;
                    $resumen[$clave]['SemActual2Kilos'] += $kilos;
                } elseif ($idx === 3) {
                    $resumen[$clave]['SemActual3'] += $mts;
                    $resumen[$clave]['SemActual3Kilos'] += $kilos;
                } elseif ($idx === 4) {
                    $resumen[$clave]['SemActual4'] += $mts;
                    $resumen[$clave]['SemActual4Kilos'] += $kilos;
                }

                $resumen[$clave]['Total'] += $mts;
                $resumen[$clave]['TotalKilos'] += $kilos;
            }
        }

        // *** NO filtramos por Total > 0: si existe el programa, se muestra con ceros ***
        return collect($resumen)
            ->values()
            ->map(function ($it) use ($tipo) {
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
                        'SemActualKilosRizo' => round((float)$it['SemActualKilos'], 2),
                        'SemActual1KilosRizo' => round((float)$it['SemActual1Kilos'], 2),
                        'SemActual2KilosRizo' => round((float)$it['SemActual2Kilos'], 2),
                        'SemActual3KilosRizo' => round((float)$it['SemActual3Kilos'], 2),
                        'SemActual4KilosRizo' => round((float)$it['SemActual4Kilos'], 2),
                        'Total'              => round((float)$it['Total'], 2),
                        'TotalKilos'         => round((float)$it['TotalKilos'], 2),
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
                    'SemActualKilosPie'  => round((float)$it['SemActualKilos'], 2),
                    'SemActual1KilosPie' => round((float)$it['SemActual1Kilos'], 2),
                    'SemActual2KilosPie' => round((float)$it['SemActual2Kilos'], 2),
                    'SemActual3KilosPie' => round((float)$it['SemActual3Kilos'], 2),
                    'SemActual4KilosPie' => round((float)$it['SemActual4Kilos'], 2),
                    'Total'              => round((float)$it['Total'], 2),
                    'TotalKilos'         => round((float)$it['TotalKilos'], 2),
                ];
            })
            ->sortBy([['TelarId','asc'], ['Modelo','asc']])
            ->values()
            ->toArray();
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
        $esPie = ($tipo === 'PIE');

        foreach ($telares as $t) {
            $tipoAct = strtoupper(trim((string)($t['tipo'] ?? '')));
            if ($tipoAct !== $tipo) return ['error'=>true,'mensaje'=>"Todos los telares deben tener el mismo tipo. {$tipoAct} ≠ {$tipo}"];

            $calAct = (string)($t['calibre'] ?? '');
            if ($calibreRef !== null && $calAct !== '') {
                if (abs(((float)$calAct) - ((float)$calibreRef)) >= 0.01) {
                    return ['error'=>true,'mensaje'=>"Todos los telares deben tener el mismo calibre. {$calAct} ≠ {$calibreRef}"];
                }
            }

            // Para PIE, no se valida hilo
            if (!$esPie) {
                $hiloAct = trim((string)($t['hilo'] ?? ''));
                if ($hiloRef !== '' && $hiloAct !== '' && strcasecmp($hiloAct, $hiloRef) !== 0) {
                    return ['error'=>true,'mensaje'=>"Todos los telares deben tener el mismo hilo. {$hiloAct} ≠ {$hiloRef}"];
                }
            }

            $salAct = strtoupper(trim((string)($t['salon'] ?? '')));
            if ($salonRef !== '' && $salAct !== '' && $salAct !== $salonRef) {
                return ['error'=>true,'mensaje'=>"Todos los telares deben tener el mismo salón. {$salAct} ≠ {$salonRef}"];
            }
        }

        // Para PIE, establecer hilo como '' (vacío) para que no se use en consultas
        return [
            'error'            => false,
            'tipo'             => $tipo,
            'calibre'          => $calibreRef,
            'calibre_vacio'    => $calibreVacio,
            'calibre_original' => $calibreOriginal,
            'hilo'             => $esPie ? '' : $hiloRef, // '' => buscar vacíos o no filtrar (PIE)
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

        try {
            $lineas = ReqProgramaTejidoLine::whereIn('ProgramaId', $programaIds)
                ->whereDate('Fecha', '>=', $ini)
                ->whereDate('Fecha', '<=', $fin)
                ->orderBy('ProgramaId')
                ->orderBy('Fecha')
                ->get();

            if ($lineas->isEmpty()) {
                $lineas = ReqProgramaTejidoLine::whereIn('ProgramaId', $programaIds)
                    ->whereRaw("CAST(Fecha AS DATE) >= CAST(? AS DATE)", [$ini])
                    ->whereRaw("CAST(Fecha AS DATE) <= CAST(? AS DATE)", [$fin])
                    ->orderBy('ProgramaId')
                    ->orderBy('Fecha')
                    ->get();
            }
        } catch (\Throwable $e) {
            Log::error('cargarLineasPorPrograma - Error en consulta', [
                'error' => $e->getMessage(),
            ]);
            $lineas = collect();
        }

        $map = [];
        foreach ($lineas as $l) {
            $pid = $l->ProgramaId;
            $map[$pid] ??= collect();
            $map[$pid]->push($l);
        }

        return $map;
    }

    /** Hilo: '' => buscar vacío/null; null => sin filtro; string => coincidencia exacta (case-insensitive) */
    private function matchHilo($esperado, string $actual): bool
    {
        $act = trim($actual);
        $esp = $esperado !== null ? trim((string)$esperado) : null;

        if ($esperado === null) return true; // Sin filtro
        if ($esperado === '' || $esp === '') return ($act === '' || $act === 'null');

        return strcasecmp($act, $esp) === 0;
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

    /**
     * Buscar BOM (L.Mat Urdido) con autocompletado
     * Filtra por ItemGroupId = 'JUL-URD' y DataAreaId = 'PRO'
     */
    public function buscarBomUrdido(Request $request)
    {
        try {
            $query = trim((string) $request->query('q', ''));

            if (strlen($query) < 1) {
                return response()->json([]);
            }

            // Buscar en la tabla BOM donde ItemGroupId = 'JUL-URD' y DataAreaId = 'PRO'
            // La búsqueda se hace por BOMID
            // Hacemos join con INVENTTABLE para filtrar por ItemGroupId
            $results = DB::connection('sqlsrv_ti')
                ->table('BOM as b')
                ->join('INVENTTABLE as it', function($join) {
                    $join->on('it.ITEMID', '=', 'b.ITEMID')
                         ->on('it.DATAAREAID', '=', 'b.DATAAREAID');
                })
                ->where('b.DATAAREAID', 'PRO')
                ->where('it.ITEMGROUPID', 'JUL-URD')
                ->where(function($q) use ($query) {
                    $q->where('b.BOMID', 'LIKE', '%' . $query . '%')
                      ->orWhere('it.ITEMNAME', 'LIKE', '%' . $query . '%')
                      ->orWhere('b.ITEMID', 'LIKE', '%' . $query . '%');
                })
                ->select([
                    'b.BOMID as BOMID',
                    'b.ITEMID as ITEMID',
                    'it.ITEMNAME as ITEMNAME'
                ])
                ->distinct()
                ->orderBy('b.BOMID')
                ->limit(20)
                ->get();

            return response()->json($results);
        } catch (\Throwable $e) {
            Log::error('buscarBomUrdido: Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Error al buscar BOM'], 500);
        }
    }

    /**
     * Obtener materiales de urdido por BOMID
     * Join BOM + InventDim filtrado por BOMID seleccionado
     * Campos: ItemId, BomQty (suma), ConfigId
     */
    public function getMaterialesUrdido(Request $request)
    {
        try {
            $bomId = trim((string) $request->query('bomId', ''));

            if (empty($bomId)) {
                return response()->json([]);
            }

            // Join BOM + InventDim
            // Filtros: Bom.BomId = valor seleccionado, Bom.DATAAREAID = 'PRO', InventDim.InventdimId = bom.inventDimId
            // Agrupar por ItemId y ConfigId, sumando BomQty
            $results = DB::connection('sqlsrv_ti')
                ->table('BOM as b')
                ->join('INVENTDIM as id', 'id.INVENTDIMID', '=', 'b.INVENTDIMID')
                ->join('INVENTTABLE as it', function($join) {
                    $join->on('it.ITEMID', '=', 'b.ITEMID')
                         ->on('it.DATAAREAID', '=', 'b.DATAAREAID');
                })
                ->where('b.BOMID', $bomId)
                ->where('b.DATAAREAID', 'PRO')
                ->where('id.DATAAREAID', 'PRO')
                ->select([
                    'b.ITEMID as ItemId',
                    DB::raw('SUM(CAST(b.BOMQTY AS DECIMAL(18,6))) as BomQty'),
                    'id.CONFIGID as ConfigId',
                    DB::raw('MAX(it.ITEMNAME) as ItemName')
                ])
                ->groupBy('b.ITEMID', 'id.CONFIGID')
                ->orderBy('b.ITEMID')
                ->get();

            return response()->json($results);
        } catch (\Throwable $e) {
            Log::error('getMaterialesUrdido: Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Error al obtener materiales'], 500);
        }
    }

    /**
     * Obtener inventario disponible (Materiales Engomado - Tabla 3)
     * Join: InventSum -> InventDim -> InventSerial
     * Filtrado por ItemId de los materiales de urdido
     */
    public function getMaterialesEngomado(Request $request)
    {
        try {
            // Obtener itemIds - puede venir como itemIds[] en query string
            $itemIds = $request->input('itemIds', $request->query('itemIds', []));

            // Si viene como string, convertirlo a array
            if (is_string($itemIds)) {
                $itemIds = [$itemIds];
            }

            // Si es array asociativo con claves numéricas, convertir a array indexado
            if (is_array($itemIds)) {
                $itemIds = array_values($itemIds);
            } else {
                $itemIds = [];
            }

            Log::info('getMaterialesEngomado: ItemIds recibidos (raw)', [
                'itemIds' => $itemIds,
                'type' => gettype($itemIds),
                'all_input' => $request->all(),
                'query' => $request->query()
            ]);

            if (empty($itemIds)) {
                Log::warning('getMaterialesEngomado: ItemIds vacío');
                return response()->json([]);
            }

            // Filtrar y limpiar ItemIds
            $itemIds = array_filter(array_map(function($id) {
                $cleaned = trim((string) $id);
                return $cleaned !== '' ? $cleaned : null;
            }, $itemIds), function($id) {
                return $id !== null;
            });

            $itemIds = array_values($itemIds);

            if (empty($itemIds)) {
                Log::warning('getMaterialesEngomado: ItemIds vacío después de filtrar');
                return response()->json([]);
            }

            Log::info('getMaterialesEngomado: ItemIds después de filtrar', ['itemIds' => $itemIds, 'count' => count($itemIds)]);

            // Join InventSum -> InventDim -> InventSerial
            // Según especificación: InventSum.ItemId = UrdlMat.ItemId
            // Filtros:
            // - InventSum.ItemId IN (ItemIds de materiales de urdido)
            // - InventSum.AvailPhysical <> 0
            // - InventSum.DATAAREAID = 'PRO'
            // - InventDim.InventDimId = InventSum.InventDimId
            // - InventDim.InventLocationId IN ('A-MP', 'A-MPBB')
            // - InventDim.DATAAREAID = 'PRO'
            // - InventSerial.InventSerialId = InventDim.InventSerialId
            // - InventSerial.ItemId = InventSum.ItemId
            // - InventSerial.DATAAREAID = 'PRO'
            $results = DB::connection('sqlsrv_ti')
                ->table('InventSum as sum')
                ->join('InventDim as dim', function($join) {
                    $join->on('dim.INVENTDIMID', '=', 'sum.INVENTDIMID')
                         ->on('dim.DATAAREAID', '=', 'sum.DATAAREAID');
                })
                ->join('InventSerial as ser', function($join) {
                    $join->on('ser.INVENTSERIALID', '=', 'dim.INVENTSERIALID')
                         ->on('ser.ITEMID', '=', 'sum.ITEMID')
                         ->on('ser.DATAAREAID', '=', 'sum.DATAAREAID');
                })
                ->whereIn('sum.ITEMID', array_values($itemIds))
                ->where('sum.DATAAREAID', 'PRO')
                ->where('sum.AVAILPHYSICAL', '<>', 0)
                ->where('dim.DATAAREAID', 'PRO')
                ->whereIn('dim.INVENTLOCATIONID', ['A-MP', 'A-MPBB'])
                ->where('ser.DATAAREAID', 'PRO')
                ->select([
                    'sum.ITEMID as ItemId',
                    'sum.PHYSICALINVENT as PhysicalInvent',
                    'sum.RESERVPHYSICAL as ReservPhysical',
                    'dim.CONFIGID as ConfigId',
                    'dim.INVENTSIZEID as InventSizeId',
                    'dim.INVENTCOLORID as InventColorId',
                    'dim.INVENTLOCATIONID as InventLocationId',
                    'dim.INVENTBATCHID as InventBatchId',
                    'dim.WMSLOCATIONID as WMSLocationId',
                    'dim.INVENTSERIALID as InventSerialId',
                    'ser.PRODDATE as ProdDate',
                    'ser.TWTIRAS as TwTiras'
                ])
                ->orderBy('sum.ITEMID')
                ->orderBy('dim.INVENTLOCATIONID')
                ->orderBy('dim.INVENTSERIALID')
                ->get();

            Log::info('getMaterialesEngomado: Resultados encontrados', ['count' => $results->count()]);

            return response()->json($results);
        } catch (\Throwable $e) {
            Log::error('getMaterialesEngomado: Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);
            return response()->json(['error' => 'Error al obtener materiales de engomado: ' . $e->getMessage()], 500);
        }
    }
}
