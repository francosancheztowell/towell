<?php
declare(strict_types=1);

namespace App\Http\Controllers\ProgramaUrdEng\ReservarProgramar;

use App\Http\Controllers\Controller;
use App\Models\Tejido\TejInventarioTelares;
use App\Models\Inventario\InvTelasReservadas;
use App\Models\Planeacion\ReqProgramaTejido;

use App\Models\Urdido\URDCatalogoMaquina;
use App\Http\Controllers\ProgramaUrdEng\ReservarProgramar\InvTelasReservadasController;
use App\Models\Engomado\EngAnchoBalonaCuenta;
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

        // Validación rápida de no_orden: sincronizar reservas con telares que tienen no_orden
        $this->validarYActualizarNoOrden($rows);

        return view('modulos.programa_urd_eng.reservar-programar', [
            'inventarioTelares' => $this->normalizeTelares($rows),
        ]);
    }

    /**
     * Validación rápida: si un telar tiene no_orden, las reservas activas deben tener:
     * El mismo no_orden en InventBatchId (para órdenes programadas)
     * Actualiza las reservas automáticamente si no coinciden
     * Optimizado: usa una sola consulta por batch de telares
     * 
     * NOTA: La tabla InvTelasReservadas NO tiene columna NoOrden, solo InventBatchId
     */
    private function validarYActualizarNoOrden($telares)
    {
        try {
            // Filtrar telares que tienen no_orden y agrupar por no_telar|tipo|no_orden
            $mapaNoOrden = [];
            foreach ($telares as $telar) {
                $noOrden = trim((string)($telar->no_orden ?? ''));
                // Solo validar si el telar tiene no_orden
                if (empty($noOrden)) continue;

                $noTelar = $telar->no_telar ?? null;
                $tipo = $this->normalizeTipo($telar->tipo ?? null);
                if (empty($noTelar)) continue;

                $clave = $noTelar . '|' . ($tipo ?? '');
                // Si ya existe, usar el no_orden más reciente (último visto)
                $mapaNoOrden[$clave] = [
                    'no_telar' => $noTelar,
                    'no_orden' => $noOrden,
                    'tipo' => $tipo
                ];
            }

            if (empty($mapaNoOrden)) {
                return;
            }

            // Procesar en chunks para evitar consultas muy grandes
            $chunks = array_chunk($mapaNoOrden, 50, true);
            foreach ($chunks as $chunk) {
                foreach ($chunk as $clave => $datos) {
                    $noTelar = $datos['no_telar'];
                    $noOrden = $datos['no_orden'];
                    $tipo = $datos['tipo'];

                    // Buscar reservas activas para este telar
                    $query = InvTelasReservadas::where('NoTelarId', $noTelar)
                        ->where('Status', 'Reservado');

                    if ($tipo) {
                        $query->where('Tipo', $tipo);
                    }

                    // Actualizar reservas que no tienen el mismo no_orden en InventBatchId
                    // Solo actualizar si hay diferencias (InventBatchId es NULL, vacío, o diferente)
                    $query->where(function($q) use ($noOrden) {
                        $q->whereNull('InventBatchId')
                          ->orWhere('InventBatchId', '!=', $noOrden)
                          ->orWhere('InventBatchId', '');
                    })->update([
                        'InventBatchId' => $noOrden, // InventBatchId debe ser igual a no_orden
                        'updated_at' => now()
                    ]);
                }
            }
        } catch (\Throwable $e) {
            // Error silencioso - no afectar la carga de la página
            Log::warning('Error en validación no_orden', [
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ]);
        }
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

                // Para 'hilo', usar igualdad exacta (case-insensitive)
                // Asegurarse de excluir NULL y valores vacíos
                if ($col === 'hilo') {
                    $q->whereNotNull($col)
                      ->where($col, '!=', '')
                      ->whereRaw('LOWER(TRIM(' . $col . ')) = LOWER(TRIM(?))', [$val]);
                } else {
                    $q->where($col, 'like', "%{$val}%");
                }
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
        return app(InvTelasReservadasController::class)->disponible($request);
    }

    public function programarTelar(Request $request)
    {
        try {
            $request->validate(['no_telar' => ['required','string','max:50']]);
            $noTelar = (string)$request->string('no_telar');

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
                'localidad' => ['nullable','string','max:10'],
                'tipo_atado' => ['nullable','string','in:Normal,Especial'],
                'hilo'     => ['nullable','string','max:50'],
                'id'       => ['nullable','integer'], // ID del registro específico (si se proporciona, solo actualiza ese registro)
            ]);

            $noTelar = (string)$request->input('no_telar');
            $tipo    = $this->normalizeTipo($request->input('tipo'));
            $id      = $request->input('id'); // ID del registro específico

            $update = [];
            if ($request->filled('metros'))   $update['metros']   = (float)$request->input('metros');
            if ($request->filled('no_julio')) $update['no_julio'] = (string)$request->input('no_julio');
            if ($request->filled('no_orden')) $update['no_orden'] = (string)$request->input('no_orden');
            if ($request->filled('localidad')) $update['localidad'] = (string)$request->input('localidad');
            if ($request->filled('tipo_atado')) $update['tipo_atado'] = (string)$request->input('tipo_atado');
            if ($request->filled('hilo')) $update['hilo'] = (string)$request->input('hilo');

            if (empty($update)) {
                return response()->json(['success'=>false,'message'=>'No hay campos para actualizar'], 400);
            }

            // Si se proporciona un ID, actualizar SOLO ese registro específico
            if ($id) {
                $telar = TejInventarioTelares::where('id', $id)
                    ->where('no_telar', $noTelar)
                    ->where('status', self::STATUS_ACTIVO)
                    ->first();

                if (!$telar) {
                    return response()->json(['success'=>false,'message'=>'Registro específico no encontrado o no está activo'], 404);
                }

                $telar->update($update);

                Log::info('actualizarTelar: Registro específico actualizado', [
                    'id' => $id,
                    'no_telar' => $noTelar,
                    'tipo' => $tipo,
                    'campos_actualizados' => array_keys($update)
                ]);

                return response()->json([
                    'success' => true,
                    'message' => "Registro específico del telar {$noTelar} actualizado correctamente",
                    'data'    => $telar->fresh(),
                ]);
            }

            // Si NO se proporciona ID, mantener comportamiento anterior (actualizar todos los registros del telar/tipo)
            // Esto es para compatibilidad con código existente que no envía el ID
            $query = TejInventarioTelares::where('no_telar', $noTelar)
                ->where('status', self::STATUS_ACTIVO);

            if ($tipo !== null) $query->where('tipo', $tipo);

            $telares = $query->get();
            if ($telares->isEmpty()) {
                return response()->json(['success'=>false,'message'=>'Telar no encontrado o no está activo'], 404);
            }

            // Actualizar TODOS los registros activos del telar (y tipo si se especifica)
            $actualizados = 0;
            foreach ($telares as $telar) {
                $telar->update($update);
                $actualizados++;
            }

            // Retornar el primer registro actualizado para compatibilidad
            $telar = $telares->first();

            return response()->json([
                'success' => true,
                'message' => "Telar {$noTelar} actualizado correctamente ({$actualizados} registro(s))",
                'data'    => $telar->fresh(),
            ]);
        } catch (\Throwable $e) {
            Log::error('actualizarTelar', ['msg' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Error al actualizar el telar: '.$e->getMessage()], 500);
        }
    }

    public function reservarInventario(Request $request)
    {
        return app(InvTelasReservadasController::class)->reservar($request);
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

            // 2) Verificar si quedan otras reservas activas para este telar (por si hay múltiples tipos)
            $tieneOtrasReservas = InvTelasReservadas::where('NoTelarId', $noTelar)
                ->where('Status', 'Reservado')
                ->exists();

            // 3) Limpiar campos de reserva y programación
            $updateData = [
                'hilo'=>null,
                'metros'=>null,
                'no_julio'=>null,
                'no_orden'=>null,
                'Reservado'=>false, // Siempre poner en 0 al liberar
                'Programado'=>false
            ];

            $telar->update($updateData);



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
            // Manejar datos que vienen en el body (JSON) o query string
            $raw = $request->input('telares') ?? $request->query('telares');
            $telares = [];

            if ($raw) {
                // Si es string, intentar parsearlo (viene de query string URL-encoded)
                if (is_string($raw)) {
                    $decoded = json_decode(urldecode($raw), true);
                    $telares = is_array($decoded) ? $decoded : [];
                } elseif (is_array($raw)) {
                    // Si ya es array (viene del body JSON parseado por Laravel)
                    $telares = $raw;
                }
            }

            if (empty($telares)) {
                $semanas = $this->construirSemanas(5);
                return response()->json([
                    'success' => true,
                    'message' => 'No hay telares seleccionados',
                    'data'    => ['rizo' => [], 'pie' => []],
                    'semanas' => $semanas,
                ]);
            }

            // Flag para fallback de metros (default: true)
            $usarFallbackMetros = (bool)$request->boolean('fallback_metros', self::FALLBACK_METROS_DEFAULT);

            // Validación de consistencia entre telares
            $v = $this->validarTelaresConsistentes($telares);
            if ($v['error']) {
                $semanas = $this->construirSemanas(5);
                return response()->json([
                    'success' => false,
                    'message' => $v['mensaje'],
                    'data'    => ['rizo' => [], 'pie' => []],
                    'semanas' => $semanas,
                ], 400);
            }

            $tipoEsperado     = $v['tipo'];         // 'RIZO' | 'PIE'
            $calibreEsperado  = $v['calibre'];      // string|null
            $calibreEsVacio   = $v['calibre_vacio'];
            $hiloEsperado     = $v['hilo'];         // string|null ('' => buscar vacíos)
            $salonEsperado    = $v['salon'];        // MAYUS o ''

            $noTelares = collect($telares)->pluck('no_telar')->filter()->unique()->values()->toArray();
            if (empty($noTelares)) {
                $semanas = $this->construirSemanas(5);
                return response()->json([
                    'success' => true,
                    'message' => 'No se encontraron números de telar válidos',
                    'data'    => ['rizo' => [], 'pie' => []],
                    'semanas' => $semanas,
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

            // Programas + líneas (eager) - Seleccionar campos específicos de ReqProgramaTejidoLine
            $programas = ReqProgramaTejido::whereIn('NoTelarId', $noTelares)
                ->with(['lineas' => function($query) use ($fechaIni, $fechaFin) {
                    // Usar DB::raw para asegurar que SQL Server reconozca los nombres de columna
                    $query->select([
                        'Id',
                        'ProgramaId',
                        'Fecha',
                        DB::raw('[Pie] as Pie'),
                        DB::raw('[Rizo] as Rizo'),
                        'MtsRizo',
                        'MtsPie'
                    ])
                          ->whereRaw("CAST(Fecha AS DATE) >= CAST(? AS DATE)", [$fechaIni])
                          ->whereRaw("CAST(Fecha AS DATE) <= CAST(? AS DATE)", [$fechaFin])
                          ->orderBy('Fecha');
                }])
                ->get();


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
                        Log::debug('Filtrado programa RIZO - Calibre', [
                            'ProgramaId' => $p->Id ?? null,
                            'CalibreEsperado' => $calibreEsperado,
                            'CalibrePrograma' => $p->CalibreRizo ?? null,
                            'matchCalibre' => $matchCalibre,
                        ]);
                    }

                    $resultadoFinal = $matchSalon && $tieneCuentaRizo && $matchHiloTelar && $matchCalibre;

                    Log::debug('Filtrado programa RIZO - Resultado final', [
                        'ProgramaId' => $p->Id ?? null,
                        'matchSalon' => $matchSalon,
                        'tieneCuentaRizo' => $tieneCuentaRizo,
                        'matchHiloTelar' => $matchHiloTelar,
                        'matchCalibre' => $matchCalibre,
                        'resultadoFinal' => $resultadoFinal,
                    ]);

                    return $resultadoFinal;
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
            $semanas = $this->construirSemanas(5);
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener resumen de semanas: '.$e->getMessage(),
                'data'    => ['rizo' => [], 'pie' => []],
                'semanas' => $semanas,
            ], 500);
        }
    }

    /**
     * Genera resumen por tipo usando líneas de ReqProgramaTejidoLine.
     * **IMPORTANTE**: No se filtra por Total > 0 al final; si existe el programa, se regresa
     * con ceros en las semanas cuando no hay metros en el rango.
     */
    /**
     * @param \Illuminate\Support\Collection|\Traversable|array<int,mixed> $programas
     * @param array<int,array<string,mixed>> $semanas
     * @param string $tipo 'Rizo'|'Pie'
     * @param string|null $cuentaEsperada
     * @param string|null $hiloEsperado
     * @param float|int|string|null $calibreEsperado
     * @param bool $calibreEsVacio
     * @param array<int,mixed> $lineasPorPrograma
     * @param string|null $fechaIni
     * @param string|null $fechaFin
     * @param bool $usarFallbackMetros
     * @return array<int,array<string,mixed>>
     */
    private function procesarResumenPorTipo(
        $programas,
        array &$semanas,
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
                $campoKilos   = 'Rizo'; // Para RIZO usar columna Rizo
            } else {
                $cuenta       = trim((string)($programa->CuentaPie ?? ''));
                $calibre      = $programa->CalibrePie ?? null;
                $fibraPieRaw  = $programa->FibraPie ?? null;
                $hilo         = ($fibraPieRaw !== null && trim((string)$fibraPieRaw) !== '') ? trim((string)$fibraPieRaw) : '';
                $campoMetros  = 'MtsPie';
                $campoKilos   = 'Pie'; // Para PIE usar columna Pie
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
                            ->select([
                                'Id',
                                'ProgramaId',
                                'Fecha',
                                DB::raw('[Pie] as Pie'),
                                DB::raw('[Rizo] as Rizo'),
                                'MtsRizo',
                                'MtsPie'
                            ])
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

                // Kilos desde la línea según el tipo (Rizo o Pie)
                // Con DB::raw y alias, debería funcionar directamente con acceso a propiedad
                if ($tipo === 'Rizo') {
                    // Para RIZO: usar columna Rizo
                    $kilos = (float)($ln->Rizo ?? 0);
                } else {
                    // Para PIE: usar columna Pie
                    $kilos = (float)($ln->Pie ?? 0);
                }

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

                $semKey = $idx === 0 ? 'SemActual' : 'SemActual' . $idx;
                $semKilosKey = $idx === 0 ? 'SemActualKilos' : 'SemActual' . $idx . 'Kilos';
                $resumen[$clave][$semKey] += $mts;
                $resumen[$clave][$semKilosKey] += $kilos;
                $this->agregarTotalesSemana($semanas, $idx, $mts, $kilos);

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
            ->where('status', '=', self::STATUS_ACTIVO) // Solo mostrar registros con status = 'Activo'
            ->select(array_merge(['id'], self::COLS_TELARES)); // Incluir id para identificar el registro específico
    }

    private function normalizeTelares($rows)
    {
        return collect($rows)->values()->map(function ($r, int $i) {
            return [
                'id'         => $r->id ?? null, // ID del registro específico en tej_inventario_telares
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
        // Obtener el inicio de la semana actual (lunes)
        $inicioBase = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $out = [];

        for ($i = 0; $i < $n; $i++) {
            // Crear una copia nueva del inicio base y agregar las semanas correspondientes
            $ini = $inicioBase->copy()->addWeeks($i);
            // Asegurar que el inicio esté al inicio del día (lunes 00:00:00)
            $ini->startOfDay();
            // Calcular el fin de semana (domingo 23:59:59)
            $fin = $ini->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();

            $out[] = [
                'numero' => $i + 1,
                'inicio' => $ini->format('Y-m-d'),
                'fin'    => $fin->format('Y-m-d'),
                'label'  => $i === 0 ? 'Sem Actual' : "Sem Actual +{$i}",
                'total_metros' => 0.0,
                'total_kilos' => 0.0,
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

    private function agregarTotalesSemana(array &$semanas, int $idx, float $mts, float $kilos): void
    {
        if (!isset($semanas[$idx])) {
            return;
        }

        $semanas[$idx]['total_metros'] = (float)($semanas[$idx]['total_metros'] ?? 0) + $mts;
        $semanas[$idx]['total_kilos'] = (float)($semanas[$idx]['total_kilos'] ?? 0) + $kilos;
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

    /** Calibre: $vacio=true => requiere null/vacío; valor numérico => tolerancia 0.11; null => sin filtro */
    private function matchCalibre($esperado, bool $vacio, $actual): bool
    {
        if ($vacio) return ($actual === null || $actual === '' || trim((string)$actual) === '');
        if ($esperado === null || $esperado === '') return true;
        $a = is_numeric($actual) ? (float)$actual : 0;
        $e = (float)$esperado;
        $diff = abs($a - $e);
        $result = $diff <= 0.11; // Tolerancia 0.11 para cubrir errores de precisión de punto flotante (16.0 vs 16.1)
        return $result;
    }

    /** Salón: si $esperado vacío no filtra
     * NOTA: ITEMA y SMITH comparten el salón "SMIT" en ReqProgramaTejido
     */
    private function matchSalon(string $esperado, string $actual): bool
    {
        if ($esperado === '') return true;

        $esp = strtoupper(trim($esperado));
        $act = strtoupper(trim($actual));

        // Coincidencia exacta
        if ($act === $esp) return true;

        // Mapeo especial: ITEMA y SMITH ambos usan SMIT en ReqProgramaTejido
        if ($esp === 'ITEMA' && $act === 'SMIT') return true;
        if ($esp === 'SMITH' && $act === 'SMIT') return true;
        if ($esp === 'SMIT' && ($act === 'ITEMA' || $act === 'SMITH')) return true;

        return false;
    }

    private function nullSiVacio($v)
    {
        return ($v === '' ? null : $v);
    }

    /**
     * Buscar BOM (L.Mat Urdido) con autocompletado
     * Filtra por BOMID que empieza con 'URD' y DataAreaId = 'PRO'
     */
    public function buscarBomUrdido(Request $request)
    {
        try {
            $query = trim((string) $request->query('q', ''));

            // Consulta base: BOMs de urdido empiezan con 'URD'
            $q = DB::connection('sqlsrv_ti')
                ->table('BOMTABLE as bt')
                ->where('bt.DATAAREAID', 'PRO')
                ->where('bt.ITEMGROUPID', 'JUL-URD') // Grupo requerido
                ->where('bt.BOMID', 'LIKE', 'URD %'); // BOMs de urdido empiezan con 'URD '

            // Filtrar por término de búsqueda
            if (strlen($query) >= 1) {
                $q->where(function($subQ) use ($query) {
                    $subQ->where('bt.BOMID', 'LIKE', '%' . $query . '%')
                         ->orWhere('bt.NAME', 'LIKE', '%' . $query . '%');
                });
            }

            $results = $q->select(
                    'bt.BOMID as BOMID',
                    'bt.NAME as NAME'
                )
              ->distinct()
              ->orderBy('bt.BOMID')
              ->limit(20)
              ->get();

            return response()->json($results);
        } catch (\Throwable $e) {
            Log::error('buscarBomUrdido: Error', ['message' => $e->getMessage(), 'query' => $query ?? '']);
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

            // Join BOM + BOMTABLE + InventDim
            // Filtros: BomId seleccionado, DATAAREAID = 'PRO', ITEMGROUPID = 'JUL-URD'
            // Agrupar por ItemId y ConfigId, sumando BomQty
            $results = DB::connection('sqlsrv_ti')
                ->table('BOM as b')
                ->join('BOMTABLE as bt', function($join) {
                    $join->on('bt.BOMID', '=', 'b.BOMID')
                         ->on('bt.DATAAREAID', '=', 'b.DATAAREAID');
                })
                ->join('INVENTDIM as id', 'id.INVENTDIMID', '=', 'b.INVENTDIMID')
                ->join('INVENTTABLE as it', function($join) {
                    $join->on('it.ITEMID', '=', 'b.ITEMID')
                         ->on('it.DATAAREAID', '=', 'b.DATAAREAID');
                })
                ->where('b.BOMID', $bomId)
                ->where('b.DATAAREAID', 'PRO')
                ->where('id.DATAAREAID', 'PRO')
                ->where('bt.DATAAREAID', 'PRO')
                ->where('bt.ITEMGROUPID', 'JUL-URD')
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
     * Filtrado por ItemId y ConfigId de los materiales de urdido (Tabla 2)
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

            // Obtener configIds - puede venir como configIds[] en query string
            $configIds = $request->input('configIds', $request->query('configIds', []));

            // Si viene como string, convertirlo a array
            if (is_string($configIds)) {
                $configIds = [$configIds];
            }

            // Si es array asociativo con claves numéricas, convertir a array indexado
            if (is_array($configIds)) {
                $configIds = array_values($configIds);
            } else {
                $configIds = [];
            }

            if (empty($itemIds)) {
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
                return response()->json([]);
            }

            // Filtrar y limpiar ConfigIds
            $configIds = array_filter(array_map(function($id) {
                $cleaned = trim((string) $id);
                return $cleaned !== '' && $cleaned !== null ? $cleaned : null;
            }, $configIds), function($id) {
                return $id !== null;
            });

            $configIds = array_values($configIds);

            // Consulta - filtrar por ItemId y ConfigId si están disponibles
            // Filtros según especificación:
            // InventSum: ItemId IN (itemIds), AvailPhysical <> 0, DATAAREAID = 'PRO'
            // InventDim: InventDimId = InventSum.InventDimId (JOIN),
            //            InventLocationId IN ('A-MP', 'A-MPBB'),
            //            ConfigId IN (configIds) si están disponibles,
            //            DATAAREAID = 'PRO'
            // InventSerial: InventSerialId = InventDim.InventSerialId (JOIN),
            //               ItemId = InventSum.ItemId (JOIN),
            //               DATAAREAID = 'PRO'
            $query = DB::connection('sqlsrv_ti')
                ->table('InventSum as sum')
                // JOIN: InventDim.InventDimId = InventSum.InventDimId
                ->join('InventDim as dim', 'dim.INVENTDIMID', '=', 'sum.INVENTDIMID')
                // JOIN: InventSerial.ItemId = InventSum.ItemId AND InventSerial.InventSerialId = InventDim.InventSerialId
                ->join('InventSerial as ser', function($join) {
                    $join->on('sum.ITEMID', '=', 'ser.ITEMID')
                         ->on('ser.INVENTSERIALID', '=', 'dim.INVENTSERIALID');
                })
                // InventSum.ItemId IN (itemIds de materiales de urdido)
                ->whereIn('sum.ITEMID', $itemIds)
                // InventSum.AvailPhysical <> 0 (inventario físico disponible, después de reservas)
                ->whereRaw('sum.PhysicalInvent <> 0')
                // InventSum.DATAAREAID = 'PRO'
                ->where('sum.DATAAREAID', 'PRO')
                // InventDim.DATAAREAID = 'PRO'
                ->where('dim.DATAAREAID', 'PRO')
                // InventDim.InventLocationId IN ('A-MP', 'A-MPBB')
                ->whereIn('dim.INVENTLOCATIONID', ['A-MP', 'A-MPBB'])
                // InventSerial.DATAAREAID = 'PRO'
                ->where('ser.DATAAREAID', 'PRO');

            // InventDim.ConfigId IN (configIds de materiales de urdido) - solo si hay ConfigIds disponibles
            if (!empty($configIds)) {
                $query->whereIn('dim.CONFIGID', $configIds);
            }

            // Optimizar consulta: solo seleccionar campos necesarios y limitar ordenamiento
            $results = $query->select([
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
                    'ser.TWTIRAS as TwTiras',
                    'ser.TWCALIDADFLOG as TwCalidadFlog',
                    'ser.TWCLIENTEFLOG as TwClienteFlog'
                ])
                ->orderBy('sum.ITEMID')
                ->get();

            return response()->json($results);
        } catch (\Throwable $e) {
            Log::error('getMaterialesEngomado: Error', [
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ]);
            return response()->json(['error' => 'Error al obtener materiales de engomado'], 500);
        }
    }

    /**
     * Obtener anchos de balona por cuenta y tipo (Rizo/Pie)
     */
    public function getAnchosBalona(Request $request)
    {
        try {
            $request->validate([
                'cuenta' => ['nullable', 'string', 'max:10'],
                'tipo' => ['nullable', 'string', 'in:Rizo,Pie'],
            ]);

            $cuenta = $request->input('cuenta');
            $tipo = $request->input('tipo');

            $query = EngAnchoBalonaCuenta::query();

            if ($cuenta) {
                $query->where('Cuenta', $cuenta);
            }

            if ($tipo) {
                $query->where('RizoPie', $tipo);
            }

            $resultados = $query->orderBy('AnchoBalona')->get();

            return response()->json([
                'success' => true,
                'data' => $resultados->map(function ($item) {
                    return [
                        'id' => $item->Id,
                        'anchoBalona' => $item->AnchoBalona,
                        'cuenta' => $item->Cuenta,
                        'rizoPie' => $item->RizoPie,
                    ];
                }),
            ]);
        } catch (\Throwable $e) {
            Log::error('getAnchosBalona: Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Error al obtener anchos de balona: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener máquinas de engomado
     */
    public function getMaquinasEngomado(Request $request)
    {
        try {
            $maquinas = URDCatalogoMaquina::where('Departamento', 'Engomado')
                ->orderBy('Nombre')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $maquinas->map(function ($maquina) {
                    return [
                        'maquinaId' => $maquina->MaquinaId,
                        'nombre' => $maquina->Nombre,
                        'departamento' => $maquina->Departamento,
                    ];
                }),
            ]);
        } catch (\Throwable $e) {
            Log::error('getMaquinasEngomado: Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Error al obtener máquinas de engomado: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buscar BOM (L.Mat Engomado) con autocompletado
     * Filtra por BOMID que empieza con 'ENG' y DataAreaId = 'PRO'
     */
    public function buscarBomEngomado(Request $request)
    {
        try {
            $query = trim((string) $request->query('q', ''));

            // Consulta base: BOMs de engomado empiezan con 'ENG'
            $q = DB::connection('sqlsrv_ti')
                ->table('BOMTABLE as bt')
                ->where('bt.ITEMGROUPID', 'JUL-ENG')
                ->where('bt.DATAAREAID', 'PRO')
                ->where('bt.BOMID', 'LIKE', 'ENG %'); // BOMs de engomado empiezan con 'ENG '

            // Filtrar por término de búsqueda
            if (strlen($query) >= 1) {
                $q->where(function($subQ) use ($query) {
                    $subQ->where('bt.BOMID', 'LIKE', '%' . $query . '%')
                         ->orWhere('bt.NAME', 'LIKE', '%' . $query . '%');
                });
            }

            $results = $q->select(
                    'bt.BOMID as BOMID',
                    'bt.NAME as NAME'
                )
              ->distinct()
              ->orderBy('bt.BOMID')
              ->limit(20)
              ->get();

            return response()->json($results);
        } catch (\Throwable $e) {
            Log::error('buscarBomEngomado: Error', ['message' => $e->getMessage(), 'query' => $query ?? '']);
            return response()->json(['error' => 'Error al buscar BOM de engomado'], 500);
        }
    }
}
