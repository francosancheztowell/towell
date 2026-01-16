<?php

namespace App\Http\Controllers\Planeacion\ProgramaTejido\funciones;

use App\Models\Planeacion\ReqCalendarioLine;
use App\Models\Planeacion\ReqModelosCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Observers\ReqProgramaTejidoObserver;
use App\Http\Controllers\Planeacion\ProgramaTejido\helper\TejidoHelpers;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BalancearTejido
{
    /** Cache para modelo (TamanoClave) */
    private static array $modeloCache = [];
    /** Cache de líneas por calendario (ya parseadas) */
    private static array $calLinesCache = []; // [calId => [ ['ini'=>Carbon,'fin'=>Carbon,'ini_ts'=>int,'fin_ts'=>int], ... ] ]

    /** gaps muy pequeños (ej. 2s entre :29:58 y :30:00) se ignoran */
    private const SMALL_GAP_SECONDS = 5;
    // =========================================================
    // PREVIEW EXACTO (NO GUARDA) - PARA EL MODAL (CALENDARIO)
    // =========================================================
    public static function previewFechas(Request $request)
    {
        $request->validate([
            'cambios' => 'required|array|min:1',
            'cambios.*.id' => 'required|integer',
            'cambios.*.total_pedido' => 'required|numeric|min:0',
            'cambios.*.modo' => 'nullable|string|in:saldo,total',
            'ord_compartida' => 'required|integer',
        ]);

        $cambios = $request->input('cambios', []);

        $ids = array_values(array_unique(array_map(fn($c) => (int)$c['id'], $cambios)));
        $regs = ReqProgramaTejido::whereIn('Id', $ids)->get()->keyBy('Id');

        // Warm caches (NO cambia resultados, solo evita N+1)
        self::warmCachesFromProgramas($regs->values());

        $resp = [];

        foreach ($cambios as $cambio) {
            $id = (int)$cambio['id'];
            /** @var ReqProgramaTejido|null $r */
            $r = $regs->get($id);
            if (!$r) continue;

            $produccion = (float)($r->Produccion ?? 0);
            $input      = (float)$cambio['total_pedido'];

            [$total, $saldo] = self::resolverTotalSaldo($input, $produccion, $cambio['modo'] ?? 'total');

            // Mutar EN MEMORIA (no save)
            $r->TotalPedido = $total;
            $r->SaldoPedido = $saldo;

            $fechaInicioOriginal = !empty($r->FechaInicio)
                ? Carbon::parse($r->FechaInicio)
                : null;

            [$inicio, $fin, $horas] = self::calcularInicioFinExactos($r);

            $resp[] = [
                'id'            => (int)$r->Id,
                'fecha_inicio'  => $fechaInicioOriginal ? $fechaInicioOriginal->format('Y-m-d H:i:s') : null,
                'fecha_final'   => $fin ? $fin->format('Y-m-d H:i:s') : null,
                'horas_prod'    => $horas,
                'saldo'         => $saldo,
                'total'         => $total,
                'calendario_id' => $r->CalendarioId ?? null,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $resp,
        ]);
    }

    // =========================================================
    // GUARDAR (CALENDARIO EXACTO) - IGNORA fecha_final del front
    // =========================================================
    public static function actualizarPedidos(Request $request)
    {
        $dispatcher = ReqProgramaTejido::getEventDispatcher();

        try {
            $request->validate([
                'cambios' => 'required|array|min:1',
                'cambios.*.id' => 'required|integer',
                'cambios.*.total_pedido' => 'required|numeric|min:0',
                'cambios.*.fecha_final' => 'nullable|string', // la ignoramos
                'cambios.*.modo' => 'nullable|string|in:saldo,total',
                'ord_compartida' => 'required|integer',
            ]);

            $cambios = $request->input('cambios', []);



            $ids = array_values(array_unique(array_map(fn($c) => (int)$c['id'], $cambios)));
            $regs = ReqProgramaTejido::whereIn('Id', $ids)->get()->keyBy('Id');



            // Warm caches (NO cambia resultados, solo evita N+1)
            self::warmCachesFromProgramas($regs->values());

            ReqProgramaTejido::unsetEventDispatcher();

            $idsAfectados = [];
            $porTelar = [];
            $idsActualizados = []; // IDs que ya fueron actualizados manualmente

            DB::beginTransaction();

            // PASO 1: Actualizar TODOS los registros en $cambios primero

            foreach ($cambios as $cambio) {
                $id = (int)$cambio['id'];

                /** @var ReqProgramaTejido|null $registro */
                $registro = $regs->get($id);
                if (!$registro) {
                    Log::warning('BalancearTejido: Registro no encontrado', ['id' => $id]);
                    continue;
                }

                $produccion = (float)($registro->Produccion ?? 0);
                $input      = (float)$cambio['total_pedido'];

                [$total, $saldo] = self::resolverTotalSaldo($input, $produccion, $cambio['modo'] ?? 'total');

                $registro->TotalPedido = $total;
                $registro->SaldoPedido = $saldo;

                $fechaFinalAntes = $registro->FechaFinal;

                // Recalcular fechas exactas SI hay FechaInicio
                if (!empty($registro->FechaInicio)) {
                    [$inicio, $fin, $horas] = self::calcularInicioFinExactos($registro);

                    if ($inicio) $registro->FechaInicio = $inicio->format('Y-m-d H:i:s');
                    if ($fin)    $registro->FechaFinal  = $fin->format('Y-m-d H:i:s');

                }

                // Fórmulas exactas
                if (!empty($registro->FechaInicio) && !empty($registro->FechaFinal)) {
                    $formulas = self::calcularFormulasEficiencia($registro);
                    foreach ($formulas as $campo => $valor) {
                        $registro->{$campo} = $valor;
                    }
                }

                $registro->save();
                $idsAfectados[] = (int)$registro->Id;
                $idsActualizados[(int)$registro->Id] = true; // Marcar como ya actualizado

                // Para cascada: guardar información del telar
                $key = trim((string)$registro->SalonTejidoId) . '|' . trim((string)$registro->NoTelarId);
                if (!isset($porTelar[$key])) {
                    $porTelar[$key] = [];
                }
                $porTelar[$key][] = (int)$registro->Id;
            }

            // PASO 2: Cascada por telar - usar el registro más temprano de cada telar como base
            foreach ($porTelar as $key => $idsDelTelar) {
                // Obtener todos los registros del telar ordenados por FechaInicio
                $salonTelar = explode('|', $key);
                $salon = $salonTelar[0];
                $telar = $salonTelar[1];

                $todosRegistrosTelar = ReqProgramaTejido::query()
                    ->where('SalonTejidoId', $salon)
                    ->where('NoTelarId', $telar)
                    ->orderBy('FechaInicio', 'asc')
                    ->orderBy('Id', 'asc')
                    ->get();

                if ($todosRegistrosTelar->isEmpty()) continue;

                // Warm caches
                self::warmCachesFromProgramas($todosRegistrosTelar);

                // Encontrar el índice del registro más temprano que fue actualizado
                $idxBase = null;
                foreach ($todosRegistrosTelar as $idx => $r) {
                    if (isset($idsActualizados[(int)$r->Id])) {
                        $idxBase = $idx;
                        break;
                    }
                }

                if ($idxBase === null) {
                    Log::warning('BalancearTejido: No se encontró registro base', ['telar_key' => $key]);
                    continue;
                }

                $base = $todosRegistrosTelar[$idxBase];
                $cursor = !empty($base->FechaFinal)
                    ? Carbon::parse($base->FechaFinal)
                    : Carbon::parse($base->FechaInicio);


                // Continuar desde el siguiente registro después del base
                $cascadaCount = 0;
                for ($i = $idxBase + 1; $i < $todosRegistrosTelar->count(); $i++) {
                    $r = $todosRegistrosTelar[$i];


                    // Si ya fue actualizado manualmente, usar su FechaFinal como cursor y continuar
                    if (isset($idsActualizados[(int)$r->Id])) {
                        if (!empty($r->FechaFinal)) {
                            $cursor = Carbon::parse($r->FechaFinal);
                        }
                        continue;
                    }

                    // Actualizar este registro en cascada
                    $inicio = $cursor->copy();
                    if (!empty($r->CalendarioId)) {
                        $snap = self::snapInicioAlCalendario($r->CalendarioId, $inicio);
                        if ($snap) $inicio = $snap;
                    }

                    $r->FechaInicio = $inicio->format('Y-m-d H:i:s');

                    $horas = self::calcularHorasProd($r);

                    if ($horas > 0) {
                        $fin = !empty($r->CalendarioId)
                            ? (self::calcularFechaFinalDesdeInicio($r->CalendarioId, $inicio, $horas) ?: $inicio->copy()->addSeconds((int) round($horas * 3600)))
                            : $inicio->copy()->addSeconds((int) round($horas * 3600));
                    } else {
                        $fin = $inicio->copy()->addDays(30);
                    }

                    $r->FechaFinal = $fin->format('Y-m-d H:i:s');

                    $formulas = self::calcularFormulasEficiencia($r);
                    foreach ($formulas as $campo => $valor) $r->{$campo} = $valor;

                    $r->save();
                    $idsAfectados[] = (int)$r->Id;
                    $cascadaCount++;

                    $cursor = $fin->copy();
                }

            }
            DB::commit();


            // Restaurar dispatcher
            if ($dispatcher) {
                ReqProgramaTejido::setEventDispatcher($dispatcher);
            }

            // Regenerar líneas diarias (observer) manualmente (mismo orden, pero 1 query)
            $idsAfectados = array_values(array_unique(array_filter($idsAfectados)));



            if (!empty($idsAfectados)) {
                $observer = new ReqProgramaTejidoObserver();
                $regsObs = ReqProgramaTejido::whereIn('Id', $idsAfectados)->get()->keyBy('Id');

                foreach ($idsAfectados as $id) {
                    $reg = $regsObs->get($id);
                    if (!$reg) continue;
                    try {
                        $observer->saved($reg);
                    } catch (\Throwable $e) {
                        Log::error('BalancearTejido: Error en observer', [
                            'id' => $id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }


            return response()->json([
                'success' => true,
                'message' => 'Balanceo aplicado correctamente',
                'actualizados' => count($idsAfectados),
                'registros_ids' => $idsAfectados, // IDs de los registros actualizados para actualizar sin recargar
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('BalancearTejido: Error en actualizarPedidos', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);

            if ($dispatcher) {
                ReqProgramaTejido::setEventDispatcher($dispatcher);
            }

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar los pedidos: ' . $e->getMessage(),
            ], 500);
        }
    }

    public static function getRegistrosPorOrdCompartida(int $ordCompartida)
    {
        try {
            $registros = ReqProgramaTejido::query()
                ->select([
                    'Id',
                    'SalonTejidoId',
                    'NoTelarId',
                    'ItemId',
                    'NombreProducto',
                    'TamanoClave',
                    'TotalPedido',
                    'PorcentajeSegundos',
                    'SaldoPedido',
                    'Produccion',
                    'FechaInicio',
                    'FechaFinal',
                    'OrdCompartida',
                    'OrdCompartidaLider',
                    'FibraRizo',
                    'VelocidadSTD',
                    'EficienciaSTD',
                    'NoTiras',
                    'Luchaje',
                    'PesoCrudo',
                    'EnProceso',
                    'Ultimo',
                    'StdDia'
                ])
                ->where('OrdCompartida', $ordCompartida)
                ->orderBy('FechaInicio', 'asc')
                ->orderBy('NoTelarId', 'asc')
                ->get();

            $totalOriginal = $registros->sum('TotalPedido');
            $totalSaldo = $registros->sum('SaldoPedido');

            return response()->json([
                'success' => true,
                'registros' => $registros,
                'total_original' => $totalOriginal,
                'total_saldo' => $totalSaldo,
                'cantidad_registros' => $registros->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los registros: ' . $e->getMessage()
            ], 500);
        }
    }

    // =========================================================
    // Core: inicio + fin exacto por calendario
    // =========================================================
    private static function calcularInicioFinExactos(ReqProgramaTejido $r): array
    {
        if (empty($r->FechaInicio)) return [null, null, 0.0];

        $inicio = Carbon::parse($r->FechaInicio);

        // Snap a calendario (misma lógica, solo sin query repetido)
        if (!empty($r->CalendarioId)) {
            $snap = self::snapInicioAlCalendario($r->CalendarioId, $inicio);
            if ($snap) $inicio = $snap;
        }

        $horasNecesarias = self::calcularHorasProd($r);

        if ($horasNecesarias <= 0) {
            $fin = $inicio->copy()->addDays(30);
            return [$inicio, $fin, 0.0];
        }

        if (!empty($r->CalendarioId)) {
            $fin = self::calcularFechaFinalDesdeInicio($r->CalendarioId, $inicio, $horasNecesarias);
            if (!$fin) {
                $fin = $inicio->copy()->addSeconds((int) round($horasNecesarias * 3600));
            }
            return [$inicio, $fin, $horasNecesarias];
        }
        $fin = $inicio->copy()->addSeconds((int) round($horasNecesarias * 3600));
        return [$inicio, $fin, $horasNecesarias];
    }
    // =========================================================
    // Resolver total/saldo
    // =========================================================
    private static function resolverTotalSaldo(float $input, float $produccion, ?string $modo): array
    {
        $input = max(0, $input);
        $produccion = max(0, $produccion);

        if ($modo === 'saldo') {
            $saldo = $input;
            $total = $produccion + $saldo;
            return [$total, $saldo];
        }

        $total = $input;
        $saldo = max(0, $total - $produccion);
        return [$total, $saldo];
    }

    private static function diffSecondsNullable($a, $b): ?int
    {
        if (empty($a) || empty($b)) return null;
        try {
            $ca = Carbon::parse($a);
            $cb = Carbon::parse($b);
            return abs($ca->diffInSeconds($cb));
        } catch (\Throwable $e) {
            return null;
        }
    }

    // =========================================================
    // Cascada por telar
    // =========================================================
    private static function cascadeFechasTelarDesde(ReqProgramaTejido $base, array $idsExcluidos = []): array
    {
        $salon = trim((string)$base->SalonTejidoId);
        $telar = trim((string)$base->NoTelarId);

        $rows = ReqProgramaTejido::query()
            ->where('SalonTejidoId', $salon)
            ->where('NoTelarId', $telar)
            ->orderBy('FechaInicio', 'asc')
            ->orderBy('Id', 'asc')
            ->get();

        if ($rows->isEmpty()) return [];

        // Warm caches para lo que viene en cascada (solo performance)
        self::warmCachesFromProgramas($rows);

        $idx = $rows->search(fn($r) => (int)$r->Id === (int)$base->Id);
        if ($idx === false) return [];

        $cursor = !empty($rows[$idx]->FechaFinal)
            ? Carbon::parse($rows[$idx]->FechaFinal)
            : Carbon::parse($rows[$idx]->FechaInicio);

        $ids = [];

        for ($i = $idx + 1; $i < $rows->count(); $i++) {
            $r = $rows[$i];

            // Saltar registros que ya fueron actualizados manualmente
            if (isset($idsExcluidos[(int)$r->Id])) {
                // Si este registro ya fue actualizado, usar su FechaFinal como cursor
                if (!empty($r->FechaFinal)) {
                    $cursor = Carbon::parse($r->FechaFinal);
                }
                continue;
            }

            $inicio = $cursor->copy();
            if (!empty($r->CalendarioId)) {
                $snap = self::snapInicioAlCalendario($r->CalendarioId, $inicio);
                if ($snap) $inicio = $snap;
            }

            $r->FechaInicio = $inicio->format('Y-m-d H:i:s');

            $horas = self::calcularHorasProd($r);

            if ($horas > 0) {
                $fin = !empty($r->CalendarioId)
                    ? (self::calcularFechaFinalDesdeInicio($r->CalendarioId, $inicio, $horas) ?: $inicio->copy()->addSeconds((int) round($horas * 3600)))
                    : $inicio->copy()->addSeconds((int) round($horas * 3600));
            } else {
                $fin = $inicio->copy()->addDays(30);
            }

            $r->FechaFinal = $fin->format('Y-m-d H:i:s');

            $formulas = self::calcularFormulasEficiencia($r);
            foreach ($formulas as $campo => $valor) $r->{$campo} = $valor;

            $r->save();
            $ids[] = (int)$r->Id;

            $cursor = $fin->copy();
        }

        return $ids;
    }

    // =========================================================
    // HorasProd EXACTO (igual a DuplicarTejido)
    // =========================================================
    private static function calcularHorasProd(ReqProgramaTejido $p): float
    {
        $vel   = (float) ($p->VelocidadSTD ?? 0);
        $efic  = (float) ($p->EficienciaSTD ?? 0);
        $cantidad = self::sanitizeNumber($p->SaldoPedido ?? $p->Produccion ?? $p->TotalPedido ?? 0);

        $m = self::getModeloParams($p->TamanoClave ?? null, $p);

        return TejidoHelpers::calcularHorasProd(
            $vel,
            $efic,
            $cantidad,
            (float)($m['no_tiras'] ?? 0),
            (float)($m['total'] ?? 0),
            (float)($m['luchaje'] ?? 0),
            (float)($m['repeticiones'] ?? 0)
        );
    }

    // =========================================================
    // Calendario (misma lógica, pero con cache)
    // =========================================================
    private static function snapInicioAlCalendario(string $calendarioId, Carbon $fechaInicio): ?Carbon
    {
        $lines = self::getCalendarioLines($calendarioId);
        return TejidoHelpers::snapInicioAlCalendario($calendarioId, $fechaInicio, $lines);
    }

    /**
     * FechaFinal recorriendo líneas reales (FechaInicio/FechaFin).
     * MISMA lógica que tu while, solo sin query por iteración.
     */
    public static function calcularFechaFinalDesdeInicio(string $calendarioId, Carbon $fechaInicio, float $horasNecesarias): ?Carbon
    {
        $segundosRestantes = (int) round(max(0, $horasNecesarias) * 3600);
        $cursor = $fechaInicio->copy();

        $lines = self::getCalendarioLines($calendarioId);

        $iter = 0;
        $maxIter = 200000;

        $idx = 0;

        while ($segundosRestantes > 0 && $iter < $maxIter) {
            $iter++;

            $cursorTs = $cursor->getTimestamp();

            // Simula: where FechaFin > $cursor orderBy FechaInicio first()
            while ($idx < count($lines) && $lines[$idx]['fin_ts'] <= $cursorTs) {
                $idx++;
            }

            // Para ser fiel a tu código: si no hay línea, en DB sería null y tronaría al parsear.
            // Aquí devolvemos null para que tu caller pueda aplicar el fallback que ya tienes.
            if ($idx >= count($lines)) {
                return null;
            }

            $ini = $lines[$idx]['ini'];
            $fin = $lines[$idx]['fin'];

            // Gap antes de la línea
            if ($cursor->lt($ini)) {
                $gapSec = $ini->getTimestamp() - $cursorTs;

                if ($gapSec <= self::SMALL_GAP_SECONDS) {
                    $cursor = $ini->copy();
                    continue;
                }

                $cursor = $ini->copy();
                continue;
            }

            // Consumir dentro de la línea
            if ($cursor->gte($fin)) {
                $cursor = $fin->copy();
                continue;
            }

            $disponibles = (int) ($fin->getTimestamp() - $cursorTs);
            if ($disponibles <= 0) {
                $cursor = $fin->copy();
                continue;
            }

            $usar = min($disponibles, $segundosRestantes);

            $cursor->addSeconds((int)$usar);
            $segundosRestantes -= (int)$usar;
        }

        return $cursor;
    }

    // =========================================================
    // Fórmulas EXACTAS
    // =========================================================
    private static function calcularFormulasEficiencia(ReqProgramaTejido $programa): array
    {
        try {
            $m = self::getModeloParams($programa->TamanoClave ?? null, $programa);
            return TejidoHelpers::calcularFormulasEficiencia($programa, $m, true, true, false);
        } catch (\Throwable $e) {
            Log::warning('BalancearTejido: Error al calcular formulas', [
                'error' => $e->getMessage(),
                'programa_id' => $programa->Id ?? null,
            ]);
        }

        return [];
    }

    // =========================================================
    // Modelo params (cache) + warm batch (solo performance)
    // =========================================================
    private static function getModeloParams(?string $tamanoClave, ReqProgramaTejido $p): array
    {
        $noTiras = (float)($p->NoTiras ?? 0);
        $luchaje = (float)($p->Luchaje ?? 0);
        $rep     = (float)($p->Repeticiones ?? 0);

        $key = trim((string)$tamanoClave);
        if ($key === '') {
            return [
                'total' => 0.0,
                'no_tiras' => $noTiras,
                'luchaje' => $luchaje,
                'repeticiones' => $rep,
            ];
        }

        if (!isset(self::$modeloCache[$key])) {
            // si no fue warm, cae aquí (igual que tu lógica)
            $m = ReqModelosCodificados::where('TamanoClave', $key)->first();
            self::$modeloCache[$key] = [
                'total' => (float)($m->Total ?? 0),
                'no_tiras' => (float)($m->NoTiras ?? 0),
                'luchaje' => (float)($m->Luchaje ?? 0),
                'repeticiones' => (float)($m->Repeticiones ?? 0),
            ];
        }

        $cached = self::$modeloCache[$key];

        return [
            'total' => (float)$cached['total'],
            'no_tiras' => $noTiras > 0 ? $noTiras : (float)$cached['no_tiras'],
            'luchaje' => $luchaje > 0 ? $luchaje : (float)$cached['luchaje'],
            'repeticiones' => $rep > 0 ? $rep : (float)$cached['repeticiones'],
        ];
    }

    // =========================================================
    // Helpers de cache (solo performance, no cambia resultados)
    // =========================================================
    private static function warmCachesFromProgramas($programas): void
    {
        $calIds = [];
        $tamKeys = [];

        foreach ($programas as $p) {
            $calId = trim((string)($p->CalendarioId ?? ''));
            if ($calId !== '') $calIds[] = $calId;

            $tk = trim((string)($p->TamanoClave ?? ''));
            if ($tk !== '') $tamKeys[] = $tk;
        }

        self::warmCalendarios($calIds);
        self::warmModelos($tamKeys);
    }

    private static function warmCalendarios(array $calIds): void
    {
        $calIds = array_values(array_unique(array_filter(array_map(fn($x) => trim((string)$x), $calIds))));
        if (empty($calIds)) return;

        $missing = [];
        foreach ($calIds as $id) {
            if ($id !== '' && !isset(self::$calLinesCache[$id])) {
                $missing[] = $id;
                self::$calLinesCache[$id] = []; // inicializa para evitar doble carga
            }
        }
        if (empty($missing)) return;

        $rows = ReqCalendarioLine::query()
            ->whereIn('CalendarioId', $missing)
            ->orderBy('CalendarioId')
            ->orderBy('FechaInicio')
            ->get(['CalendarioId', 'FechaInicio', 'FechaFin']);

        foreach ($rows as $row) {
            $calId = trim((string)$row->CalendarioId);
            if ($calId === '') continue;

            $ini = Carbon::parse($row->FechaInicio);
            $fin = Carbon::parse($row->FechaFin);

            self::$calLinesCache[$calId][] = [
                'ini' => $ini,
                'fin' => $fin,
                'ini_ts' => $ini->getTimestamp(),
                'fin_ts' => $fin->getTimestamp(),
            ];
        }
    }

    private static function getCalendarioLines(string $calendarioId): array
    {
        $calendarioId = trim((string)$calendarioId);
        if ($calendarioId === '') return [];

        if (!isset(self::$calLinesCache[$calendarioId])) {
            self::warmCalendarios([$calendarioId]);
        }

        return self::$calLinesCache[$calendarioId] ?? [];
    }

    private static function warmModelos(array $tamanoClaves): void
    {
        $keys = array_values(array_unique(array_filter(array_map(fn($x) => trim((string)$x), $tamanoClaves))));
        if (empty($keys)) return;

        $missing = [];
        foreach ($keys as $k) {
            if ($k !== '' && !isset(self::$modeloCache[$k])) {
                $missing[] = $k;
            }
        }
        if (empty($missing)) return;

        $rows = ReqModelosCodificados::query()
            ->whereIn('TamanoClave', $missing)
            ->get(['TamanoClave', 'Total', 'NoTiras', 'Luchaje', 'Repeticiones']);

        foreach ($rows as $m) {
            $k = trim((string)$m->TamanoClave);
            if ($k === '') continue;
            self::$modeloCache[$k] = [
                'total' => (float)($m->Total ?? 0),
                'no_tiras' => (float)($m->NoTiras ?? 0),
                'luchaje' => (float)($m->Luchaje ?? 0),
                'repeticiones' => (float)($m->Repeticiones ?? 0),
            ];
        }
    }

    private static function sanitizeNumber($value): float
    {
        return TejidoHelpers::sanitizeNumber($value);
    }


// Funcion de balanceo automatico con fecha fin objetivo
    public static function balancearAutomatico(Request $request)
    {
        // aqui valida las ordenes compartidas para que sea la misma
        $request->validate([
            'ord_compartida' => 'required|integer',
            'fecha_fin_objetivo' => 'required|date',
        ]);

        $ordCompartida = (int)$request->input('ord_compartida');
        $fechaFinObjetivo = Carbon::parse($request->input('fecha_fin_objetivo'));
        $registros = ReqProgramaTejido::where('OrdCompartida', $ordCompartida)
            ->orderBy('FechaInicio', 'asc')
            ->orderBy('NoTelarId', 'asc')
            ->get();

        // Warm caches para optimizar
        self::warmCachesFromProgramas($registros);

        $nuevosPedidos = [];
        $horasDisponiblesTotal = 0;
        $horasNecesariasOriginales = 0;
        $totalOriginal = (float)$registros->sum(fn($r) => (float)($r->TotalPedido ?? 0));

        $ajustarTotal = false; // En balanceo por fecha objetivo, priorizamos la fecha final

        // Convertir a array indexado para poder acceder por índice
        $registrosArray = $registros->values()->all();
        // entre menos tolerancia se comporta mejor
        $toleranciaHoras = 0.001; // Muy pequeña para mayor precisión
        $toleranciaSegundos = (int)($toleranciaHoras * 3600); // Convertir horas a segundos

        // PASO 1: Identificar qué registros ya cumplen con la fecha objetivo
        // Estos NO deben cambiar
        $registrosQueCumplen = [];
        $registrosQueNecesitanAjuste = [];

        $totalRegistros = count($registrosArray);
        foreach ($registrosArray as $indice => $reg) {
            $produccion = (float)($reg->Produccion ?? 0);
            $pedidoActual = (float)($reg->TotalPedido ?? 0);

            // Calculamos horas originales solo para referencia informativa
            $saldoOriginal = max(0, $pedidoActual - $produccion);
            $regTemp = clone $reg;
            $regTemp->TotalPedido = $pedidoActual;
            $regTemp->SaldoPedido = $saldoOriginal;
            $horasNecesariasOriginales += self::calcularHorasProd($regTemp);

            if (empty($reg->FechaInicio)) {
                $registrosQueCumplen[] = ['indice' => $indice, 'reg' => $reg, 'pedido' => $pedidoActual];
                continue;
            }

            $fechaInicioReg = Carbon::parse($reg->FechaInicio);
            if (!empty($reg->CalendarioId)) {
                $snap = self::snapInicioAlCalendario($reg->CalendarioId, $fechaInicioReg);
                if ($snap) $fechaInicioReg = $snap;
            }

            // Verificar si el pedido actual ya es correcto
            $regTemp = clone $reg;
            $regTemp->TotalPedido = $pedidoActual;
            $regTemp->SaldoPedido = $saldoOriginal;
            $horasActual = self::calcularHorasProd($regTemp);
            $fechaFinalActual = null;
            if ($horasActual > 0) {
                $fechaInicioTemp = $fechaInicioReg->copy();
                if (!empty($reg->CalendarioId)) {
                    $snap = self::snapInicioAlCalendario($reg->CalendarioId, $fechaInicioTemp);
                    if ($snap) $fechaInicioTemp = $snap;
                }

                $fechaFinalActual = !empty($reg->CalendarioId)
                    ? (self::calcularFechaFinalDesdeInicio($reg->CalendarioId, $fechaInicioTemp, $horasActual) ?: $fechaInicioTemp->copy()->addSeconds((int)round($horasActual * 3600)))
                    : $fechaInicioTemp->copy()->addSeconds((int)round($horasActual * 3600));
            }

            $cumpleObjetivo = false;
            if ($fechaFinalActual) {
                if ($fechaFinalActual->toDateString() === $fechaFinObjetivo->toDateString()) {
                    $cumpleObjetivo = true;
                }
                $diferenciaSegundos = abs($fechaFinObjetivo->getTimestamp() - $fechaFinalActual->getTimestamp());
                if ($diferenciaSegundos <= $toleranciaSegundos && $fechaFinalActual->getTimestamp() <= $fechaFinObjetivo->getTimestamp()) {
                    $cumpleObjetivo = true;
                }
            }

            // Caso especial: si solo hay 2 registros, balancear siempre el primero
            if ($totalRegistros === 2 && $indice === 1) {
                $registrosQueCumplen[] = ['indice' => $indice, 'reg' => $reg, 'pedido' => $pedidoActual];
            } elseif ($cumpleObjetivo) {
                $registrosQueCumplen[] = ['indice' => $indice, 'reg' => $reg, 'pedido' => $pedidoActual];
            } else {
                $registrosQueNecesitanAjuste[] = ['indice' => $indice, 'reg' => $reg];
            }
        }

        // PASO 2: Primero mantener los que ya cumplen
        foreach ($registrosQueCumplen as $item) {
            $nuevosPedidos[$item['reg']->Id] = [
                'id' => (int)$item['reg']->Id,
                'total_pedido' => $item['pedido'],
                'modo' => 'total'
            ];
        }

        // PASO 3: Procesar los que necesitan ajuste en el orden actual
        foreach ($registrosQueNecesitanAjuste as $item) {
            $reg = $item['reg'];
            $produccion = (float)($reg->Produccion ?? 0);
            $pedidoActual = (float)($reg->TotalPedido ?? 0);
            $saldoActual = max(0, $pedidoActual - $produccion);
            $fechaInicioReg = Carbon::parse($reg->FechaInicio);
            if (!empty($reg->CalendarioId)) {
                $snap = self::snapInicioAlCalendario($reg->CalendarioId, $fechaInicioReg);
                if ($snap) $fechaInicioReg = $snap;
            }
            $horasDispReg = self::calcularHorasDisponiblesHastaFecha(
                $reg->CalendarioId ?? null,
                $fechaInicioReg,
                $fechaFinObjetivo
            );
            $horasDisponiblesTotal += $horasDispReg;

            // Calcular Pedido Exacto
            $saldoCalculado = self::calcularPedidoParaFechaObjetivo(
                $reg,
                $fechaInicioReg,
                $fechaFinObjetivo,
                $produccion,
                $saldoActual
            );

            $saldoFinal = max(0, round($saldoCalculado));
            $pedidoFinal = max(1, $produccion + $saldoFinal);
            $fechaFinalCalc = null;
            if (!empty($reg->FechaInicio)) {
                $regTemp = clone $reg;
                $regTemp->TotalPedido = $pedidoFinal;
                $regTemp->SaldoPedido = $saldoFinal;
                $horasCalc = self::calcularHorasProd($regTemp);
                $fechaInicioTemp = Carbon::parse($reg->FechaInicio);
                if (!empty($reg->CalendarioId)) {
                    $snap = self::snapInicioAlCalendario($reg->CalendarioId, $fechaInicioTemp);
                    if ($snap) $fechaInicioTemp = $snap;
                }
                if ($horasCalc > 0) {
                    $fechaFinalCalc = !empty($reg->CalendarioId)
                        ? (self::calcularFechaFinalDesdeInicio($reg->CalendarioId, $fechaInicioTemp, $horasCalc) ?: $fechaInicioTemp->copy()->addSeconds((int)round($horasCalc * 3600)))
                        : $fechaInicioTemp->copy()->addSeconds((int)round($horasCalc * 3600));
                }
            }
            $nuevosPedidos[$reg->Id] = [
                'id' => (int)$reg->Id,
                'total_pedido' => $pedidoFinal,
                'modo' => 'total'
            ];
        }

        // Ajuste final: mantener el total original sumando la diferencia al primero (orden FechaInicio/NoTelar)
        if ($ajustarTotal) {
            $totalNuevo = 0.0;
            foreach ($nuevosPedidos as $item) {
                $totalNuevo += (float)$item['total_pedido'];
            }
            $diff = $totalOriginal - $totalNuevo;

            $primeroReg = $registrosArray[0] ?? null;
            if (abs($diff) >= 1 && $primeroReg) {
                $primeroId = (int)$primeroReg->Id;
                $prodPrimero = (float)($primeroReg->Produccion ?? 0);
                $totalActualPrimero = isset($nuevosPedidos[$primeroId])
                    ? (float)$nuevosPedidos[$primeroId]['total_pedido']
                    : (float)($primeroReg->TotalPedido ?? 0);

                $nuevoTotalPrimero = $totalActualPrimero + $diff;
                $minPrimero = max(1, $prodPrimero);
                if ($nuevoTotalPrimero < $minPrimero) {
                    $nuevoTotalPrimero = $minPrimero;
                }

                $nuevosPedidos[$primeroId] = [
                    'id' => $primeroId,
                    'total_pedido' => (int) round($nuevoTotalPrimero),
                    'modo' => 'total'
                ];
            }

            $totalNuevoFinal = 0.0;
            foreach ($nuevosPedidos as $item) {
                $totalNuevoFinal += (float)$item['total_pedido'];
            }

            $rest = $totalOriginal - $totalNuevoFinal;
            if (abs($rest) >= 1 && $primeroReg) {
                $primeroId = (int)$primeroReg->Id;
                $prodPrimero = (float)($primeroReg->Produccion ?? 0);
                $totalActualPrimero = isset($nuevosPedidos[$primeroId])
                    ? (float)$nuevosPedidos[$primeroId]['total_pedido']
                    : (float)($primeroReg->TotalPedido ?? 0);

                $nuevoTotalPrimero = $totalActualPrimero + $rest;
                $minPrimero = max(1, $prodPrimero);
                if ($nuevoTotalPrimero < $minPrimero) {
                    $nuevoTotalPrimero = $minPrimero;
                }

                $nuevosPedidos[$primeroId] = [
                    'id' => $primeroId,
                    'total_pedido' => (int) round($nuevoTotalPrimero),
                    'modo' => 'total'
                ];

                $totalNuevoFinal = 0.0;
                foreach ($nuevosPedidos as $item) {
                    $totalNuevoFinal += (float)$item['total_pedido'];
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Balanceo calculado correctamente',
            'cambios' => array_values($nuevosPedidos),
            'horas_disponibles_sumadas' => $horasDisponiblesTotal, // Suma de horas de todas las máquinas
            'horas_necesarias_originales' => $horasNecesariasOriginales,
        ]);
    }

    /**
     * Calcula el pedido exacto necesario para llegar a una fecha objetivo
     * Usa búsqueda binaria para mayor precisión
     */
    private static function calcularPedidoParaFechaObjetivo(
        ReqProgramaTejido $reg,
        Carbon $fechaInicio,
        Carbon $fechaObjetivo,
        float $produccion,
        float $saldoActual = 0.0
    ): float {
        // Calcular horas disponibles hasta fecha objetivo
        $horasDisponibles = self::calcularHorasDisponiblesHastaFecha(
            $reg->CalendarioId ?? null,
            $fechaInicio,
            $fechaObjetivo
        );

        if ($horasDisponibles <= 0) {
            // No hay horas disponibles, devolver saldo actual
            return $saldoActual;
        }

        // Calcular eficiencia del registro (horas por unidad de saldo)
        $regTemp = clone $reg;
        $regTemp->TotalPedido = $produccion + 1000; // Valor de prueba en saldo
        $regTemp->SaldoPedido = 1000;
        $horasPrueba = self::calcularHorasProd($regTemp);

        if ($horasPrueba <= 0) {
            // No se puede calcular eficiencia, usar saldo actual
            return $saldoActual;
        }

        $eficienciaHoras = $horasPrueba / 1000.0; // horas por unidad de saldo

        // Estimación inicial basada en eficiencia
        $saldoEstimado = ($horasDisponibles / max($eficienciaHoras, 0.0001));

        // Búsqueda binaria para encontrar el pedido exacto
        $minSaldo = 0.0;
        $maxSaldo = max($saldoActual + 1, $saldoEstimado * 3); // Límite superior amplio
        $mejorSaldo = $saldoEstimado;
        $mejorDiferencia = PHP_FLOAT_MAX;

        $maxIteraciones = 70; // Más iteraciones para mayor precisión
        $toleranciaSegundos = 70; // 1 minuto de tolerancia

        for ($iter = 0; $iter < $maxIteraciones; $iter++) {
            $saldoPrueba = ($minSaldo + $maxSaldo) / 2.1;

            // Calcular fecha final con este pedido
            $regTemp = clone $reg;
            $regTemp->TotalPedido = $produccion + $saldoPrueba;
            $regTemp->SaldoPedido = max(0, $saldoPrueba);
            $horas = self::calcularHorasProd($regTemp);

            if ($horas <= 0) {
                $minSaldo = $saldoPrueba;
                continue;
            }

            // Calcular fecha final
            $fechaInicioTemp = $fechaInicio->copy();
            if (!empty($reg->CalendarioId)) {
                $snap = self::snapInicioAlCalendario($reg->CalendarioId, $fechaInicioTemp);
                if ($snap) $fechaInicioTemp = $snap;
            }

            $fechaFinal = !empty($reg->CalendarioId)
                ? (self::calcularFechaFinalDesdeInicio($reg->CalendarioId, $fechaInicioTemp, $horas) ?: $fechaInicioTemp->copy()->addSeconds((int)round($horas * 3600)))
                : $fechaInicioTemp->copy()->addSeconds((int)round($horas * 3600));

            $diferenciaSegundos = abs($fechaObjetivo->getTimestamp() - $fechaFinal->getTimestamp());

            if ($diferenciaSegundos < $mejorDiferencia) {
                $mejorDiferencia = $diferenciaSegundos;
                $mejorSaldo = $saldoPrueba;
            }

            if ($diferenciaSegundos <= $toleranciaSegundos) {
                // Llegamos a la tolerancia, salir
                break;
            }

            // Ajustar búsqueda binaria
            if ($fechaFinal->getTimestamp() < $fechaObjetivo->getTimestamp()) {
                // Necesitamos más pedido
                $minSaldo = $saldoPrueba;
            } else {
                // Tenemos demasiado pedido
                $maxSaldo = $saldoPrueba;
            }

            // Si el rango es muy pequeño, salir
            if (($maxSaldo - $minSaldo) < 0.1) {
                break;
            }
        }

        return max(0, $mejorSaldo);
    }

    /**
     * Calcula las horas disponibles desde fechaInicio hasta fechaFin usando el calendario
     */
    private static function calcularHorasDisponiblesHastaFecha(?string $calendarioId, Carbon $fechaInicio, Carbon $fechaFin): float
    {
        if (empty($calendarioId)) {
            // Sin calendario: horas continuas
            $segundos = max(0, $fechaFin->getTimestamp() - $fechaInicio->getTimestamp());
            return $segundos / 3600.0;
        }

        // Con calendario: usar las líneas del calendario
        $lines = self::getCalendarioLines($calendarioId);
        if (empty($lines)) {
            // Sin líneas de calendario: horas continuas
            $segundos = max(0, $fechaFin->getTimestamp() - $fechaInicio->getTimestamp());
            return $segundos / 3600.0;
        }

        $cursor = $fechaInicio->copy();
        $segundosTotales = 0;
        $fechaFinTs = $fechaFin->getTimestamp();
        $idx = 0;
        $iter = 0;
        $maxIter = 200000;

        while ($cursor->getTimestamp() < $fechaFinTs && $iter < $maxIter) {
            $iter++;
            $cursorTs = $cursor->getTimestamp();

            // Buscar siguiente línea disponible
            while ($idx < count($lines) && $lines[$idx]['fin_ts'] <= $cursorTs) {
                $idx++;
            }

            if ($idx >= count($lines)) {
                break; // No hay más líneas disponibles
            }

            $ini = $lines[$idx]['ini'];
            $fin = $lines[$idx]['fin'];

            // Si hay gap antes de la línea, saltarlo
            if ($cursor->lt($ini)) {
                $cursor = $ini->copy();
                continue;
            }

            // Si la línea ya pasó, siguiente
            if ($cursor->gte($fin)) {
                $idx++;
                continue;
            }

            // Calcular segundos disponibles en esta línea hasta fechaFin
            $finLineaTs = min($fin->getTimestamp(), $fechaFinTs);
            $disponibles = max(0, $finLineaTs - $cursorTs);
            $segundosTotales += $disponibles;

            // Mover cursor al final de esta línea o fechaFin
            $cursor = Carbon::createFromTimestamp($finLineaTs);

            if ($finLineaTs >= $fin->getTimestamp()) {
                $idx++; // Pasar a siguiente línea
            }
        }

        return $segundosTotales / 3600.0;
    }
}
