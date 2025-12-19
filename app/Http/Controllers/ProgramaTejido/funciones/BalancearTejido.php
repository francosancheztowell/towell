<?php

namespace App\Http\Controllers\ProgramaTejido\funciones;

use App\Models\ReqCalendarioLine;
use App\Models\ReqModelosCodificados;
use App\Models\ReqProgramaTejido;
use App\Observers\ReqProgramaTejidoObserver;
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

    /** si el cambio de fin es menor a esto, NO cascada (evita “rebotes”) */
    private const CASCADE_THRESHOLD_SECONDS = 300; // 5 min

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

            [$inicio, $fin, $horas] = self::calcularInicioFinExactos($r);

            $resp[] = [
                'id'            => (int)$r->Id,
                'fecha_inicio'  => $inicio ? $inicio->format('Y-m-d H:i:s') : null,
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

            DB::beginTransaction();

            foreach ($cambios as $cambio) {
                $id = (int)$cambio['id'];
                /** @var ReqProgramaTejido|null $registro */
                $registro = $regs->get($id);
                if (!$registro) continue;

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

                    Log::debug('BalancearTejido: recalculo exacto (save)', [
                        'id' => $registro->Id,
                        'input_total' => $input,
                        'total' => $total,
                        'saldo' => $saldo,
                        'horas' => $horas,
                        'inicio' => $registro->FechaInicio,
                        'fin' => $registro->FechaFinal,
                        'calendario_id' => $registro->CalendarioId ?? null,
                    ]);
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

                // Cascada solo si FechaFinal cambió "de verdad"
                $delta = self::diffSecondsNullable($fechaFinalAntes, $registro->FechaFinal);
                $fechaFinalCambiada = ($delta === null)
                    ? !empty($registro->FechaFinal)
                    : ($delta >= self::CASCADE_THRESHOLD_SECONDS);

                if ($fechaFinalCambiada) {
                    $key = trim((string)$registro->SalonTejidoId) . '|' . trim((string)$registro->NoTelarId);
                    $porTelar[$key][] = (int)$registro->Id;
                }
            }

            // Cascada por telar (MISMA consulta que tu código)
            foreach ($porTelar as $key => $idsBase) {
                $base = ReqProgramaTejido::whereIn('Id', $idsBase)
                    ->orderBy('FechaInicio', 'asc')
                    ->orderBy('Id', 'asc')
                    ->first();

                if (!$base) continue;

                $idsCascada = self::cascadeFechasTelarDesde($base);
                if (!empty($idsCascada)) {
                    $idsAfectados = array_merge($idsAfectados, $idsCascada);
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
                        Log::warning('BalancearTejido: Error regenerando líneas', [
                            'id' => $id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Balanceo aplicado correctamente',
                'actualizados' => count($idsAfectados),
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();

            if ($dispatcher) {
                ReqProgramaTejido::setEventDispatcher($dispatcher);
            }

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar los pedidos: ' . $e->getMessage(),
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
                Log::warning('BalancearTejido: calcularFechaFinalDesdeInicio retornó null, usando fallback continuo', [
                    'id' => $r->Id ?? null,
                    'calendario_id' => $r->CalendarioId,
                    'fecha_inicio' => $inicio->format('Y-m-d H:i:s'),
                    'horas_necesarias' => $horasNecesarias,
                ]);
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
    private static function cascadeFechasTelarDesde(ReqProgramaTejido $base): array
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
        if ($efic > 1) $efic = $efic / 100;

        $cantidad = self::sanitizeNumber($p->SaldoPedido ?? $p->Produccion ?? $p->TotalPedido ?? 0);

        $m = self::getModeloParams($p->TamanoClave ?? null, $p);

        $stdToaHra = 0.0;
        if ($m['no_tiras'] > 0 && $m['total'] > 0 && $m['luchaje'] > 0 && $m['repeticiones'] > 0 && $vel > 0) {
            $parte1 = $m['total'];
            $parte2 = (($m['luchaje'] * 0.5) / 0.0254) / $m['repeticiones'];
            $den = ($parte1 + $parte2) / $vel;
            if ($den > 0) {
                $stdToaHra = ($m['no_tiras'] * 60) / $den;
            }
        }

        if ($stdToaHra > 0 && $efic > 0 && $cantidad > 0) {
            return $cantidad / ($stdToaHra * $efic);
        }

        return 0.0;
    }

    // =========================================================
    // Calendario (misma lógica, pero con cache)
    // =========================================================
    private static function snapInicioAlCalendario(string $calendarioId, Carbon $fechaInicio): ?Carbon
    {
        $lines = self::getCalendarioLines($calendarioId);

        // Simula: where FechaFin > $fechaInicio orderBy FechaInicio first()
        $ts = $fechaInicio->getTimestamp();
        $line = null;
        foreach ($lines as $l) {
            if ($l['fin_ts'] > $ts) { $line = $l; break; }
        }

        if (!$line) return null;

        $ini = $line['ini']; // Carbon ya parseado
        $fin = $line['fin'];

        if ($fechaInicio->gte($ini) && $fechaInicio->lt($fin)) return $fechaInicio->copy();

        return $ini->copy();
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
        $formulas = [];

        try {
            $vel = (float) ($programa->VelocidadSTD ?? 0);
            $efic = (float) ($programa->EficienciaSTD ?? 0);
            $cantidad = self::sanitizeNumber($programa->SaldoPedido ?? $programa->Produccion ?? $programa->TotalPedido ?? 0);
            $pesoCrudo = (float) ($programa->PesoCrudo ?? 0);

            $m = self::getModeloParams($programa->TamanoClave ?? null, $programa);

            if ($efic > 1) $efic = $efic / 100;

            $inicio = Carbon::parse($programa->FechaInicio);
            $fin    = Carbon::parse($programa->FechaFinal);
            $diffSeg = abs($fin->getTimestamp() - $inicio->getTimestamp());
            $diffDias = $diffSeg / 86400;

            // StdToaHra
            $stdToaHra = 0;
            if ($m['no_tiras'] > 0 && $m['total'] > 0 && $m['luchaje'] > 0 && $m['repeticiones'] > 0 && $vel > 0) {
                $parte1 = $m['total'];
                $parte2 = (($m['luchaje'] * 0.5) / 0.0254) / $m['repeticiones'];
                $den = ($parte1 + $parte2) / $vel;
                if ($den > 0) {
                    $stdToaHra = ($m['no_tiras'] * 60) / $den;
                    $formulas['StdToaHra'] = (float) round($stdToaHra, 2);
                }
            }

            // PesoGRM2
            $largoToalla = (float) ($programa->LargoToalla ?? 0);
            $anchoToalla = (float) ($programa->AnchoToalla ?? 0);
            if ($pesoCrudo > 0 && $largoToalla > 0 && $anchoToalla > 0) {
                $formulas['PesoGRM2'] = (float) round(($pesoCrudo * 10000) / ($largoToalla * $anchoToalla), 2);
            }

            if ($diffDias > 0) $formulas['DiasEficiencia'] = (float) round($diffDias, 2);

            if ($stdToaHra > 0 && $efic > 0) {
                $stdDia = $stdToaHra * $efic * 24;
                $formulas['StdDia'] = (float) round($stdDia, 2);

                if ($pesoCrudo > 0) {
                    $formulas['ProdKgDia'] = (float) round(($stdDia * $pesoCrudo) / 1000, 2);
                }
            }

            if ($diffDias > 0) {
                $stdHrsEfect = ($cantidad / $diffDias) / 24;
                $formulas['StdHrsEfect'] = (float) round($stdHrsEfect, 2);

                if ($pesoCrudo > 0) {
                    $formulas['ProdKgDia2'] = (float) round((($pesoCrudo * $stdHrsEfect) * 24) / 1000, 2);
                }
            }

            if ($stdToaHra > 0 && $efic > 0) {
                $horasProd = $cantidad / ($stdToaHra * $efic);
                $formulas['HorasProd'] = (float) round($horasProd, 2);
                $formulas['DiasJornada'] = (float) round($horasProd / 24, 2);
            }

        } catch (\Throwable $e) {
            Log::warning('BalancearTejido: Error al calcular fórmulas', [
                'error' => $e->getMessage(),
                'programa_id' => $programa->Id ?? null,
            ]);
        }

        return $formulas;
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
        if ($value === null) return 0.0;
        if (is_numeric($value)) return (float)$value;
        $clean = str_replace([',', ' '], '', (string)$value);
        return is_numeric($clean) ? (float)$clean : 0.0;
    }

    // =========================================================
    // BALANCEO AUTOMÁTICO CON FECHA OBJETIVO
    // =========================================================
    public static function balancearAutomatico(Request $request)
    {
        $request->validate([
            'ord_compartida' => 'required|integer',
            'fecha_fin_objetivo' => 'required|date',
        ]);

        $ordCompartida = (int)$request->input('ord_compartida');
        $fechaFinObjetivo = Carbon::parse($request->input('fecha_fin_objetivo'));

        // Obtener todos los registros del grupo ordenados por FechaInicio
        $registros = ReqProgramaTejido::where('OrdCompartida', $ordCompartida)
            ->orderBy('FechaInicio', 'asc')
            ->orderBy('Id', 'asc')
            ->get();

        if ($registros->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontraron registros para balancear',
            ], 404);
        }

        // Warm caches
        self::warmCachesFromProgramas($registros);

        // Obtener fecha de inicio del primer registro
        $primerRegistro = $registros->first();
        if (empty($primerRegistro->FechaInicio)) {
            return response()->json([
                'success' => false,
                'message' => 'El primer registro no tiene fecha de inicio',
            ], 400);
        }

        $fechaInicio = Carbon::parse($primerRegistro->FechaInicio);

        // Snap al calendario si tiene
        if (!empty($primerRegistro->CalendarioId)) {
            $snap = self::snapInicioAlCalendario($primerRegistro->CalendarioId, $fechaInicio);
            if ($snap) $fechaInicio = $snap;
        }

        // Calcular horas disponibles hasta la fecha objetivo (usando calendario si aplica)
        $horasDisponibles = self::calcularHorasDisponiblesHastaFecha(
            $primerRegistro->CalendarioId ?? null,
            $fechaInicio,
            $fechaFinObjetivo
        );

        // Calcular total de pedido actual
        $totalPedidoActual = $registros->sum(fn($r) => (float)($r->TotalPedido ?? 0));

        // Calcular horas necesarias con pedidos actuales y eficiencia de cada registro
        $horasNecesariasActuales = 0;
        $datosRegistros = [];

        foreach ($registros as $reg) {
            $produccion = (float)($reg->Produccion ?? 0);
            $pedidoActual = (float)($reg->TotalPedido ?? 0);
            $saldo = max(0, $pedidoActual - $produccion);

            // Calcular horas necesarias con el pedido actual
            $regTemp = clone $reg;
            $regTemp->SaldoPedido = $saldo;
            $horas = self::calcularHorasProd($regTemp);

            // Calcular eficiencia (horas por unidad de pedido)
            $eficienciaHoras = $pedidoActual > 0 && $horas > 0 ? $horas / $pedidoActual : 0;

            $datosRegistros[] = [
                'registro' => $reg,
                'pedido_actual' => $pedidoActual,
                'produccion' => $produccion,
                'saldo' => $saldo,
                'horas' => $horas,
                'eficiencia_horas' => $eficienciaHoras
            ];

            $horasNecesariasActuales += $horas;
        }

        // =========================================================
        // ALGORITMO PRECISO: Calcular pedidos para llegar exactamente a fecha objetivo
        // PRIORIDAD: Los primeros registros deben llegar a la fecha objetivo
        // =========================================================
        $nuevosPedidos = [];
        $ultimoIdx = count($datosRegistros) - 1;
        $totalRegistros = count($datosRegistros);

        // Si solo hay un registro, calcular exactamente para llegar a fecha objetivo
        if ($totalRegistros === 1) {
            $reg = $datosRegistros[0]['registro'];
            $produccion = $datosRegistros[0]['produccion'];

            // Calcular pedido exacto para llegar a fecha objetivo
            $pedidoExacto = self::calcularPedidoParaFechaObjetivo(
                $reg,
                $fechaInicio,
                $fechaFinObjetivo,
                $produccion
            );

            $nuevosPedidos[$reg->Id] = [
                'id' => (int)$reg->Id,
                'total_pedido' => max($produccion, round($pedidoExacto)),
                'modo' => 'total'
            ];
        } elseif ($totalRegistros === 2) {
            // CASO ESPECIAL: 2 registros - el primero DEBE llegar exactamente a la fecha objetivo
            $primerDatos = $datosRegistros[0];
            $segundoDatos = $datosRegistros[1];
            $primerReg = $primerDatos['registro'];
            $segundoReg = $segundoDatos['registro'];
            $produccionPrimero = $primerDatos['produccion'];
            $produccionSegundo = $segundoDatos['produccion'];

            $cursorFecha = $fechaInicio->copy();

            // Snap inicial al calendario
            if (!empty($primerReg->CalendarioId)) {
                $snap = self::snapInicioAlCalendario($primerReg->CalendarioId, $cursorFecha);
                if ($snap) $cursorFecha = $snap;
            }

            // PRIMER REGISTRO: Calcular exactamente para llegar a fecha objetivo
            $pedidoPrimero = self::calcularPedidoParaFechaObjetivo(
                $primerReg,
                $cursorFecha,
                $fechaFinObjetivo,
                $produccionPrimero
            );

            $pedidoPrimeroFinal = max($produccionPrimero, round($pedidoPrimero));

            $nuevosPedidos[$primerReg->Id] = [
                'id' => (int)$primerReg->Id,
                'total_pedido' => $pedidoPrimeroFinal,
                'modo' => 'total'
            ];

            // Calcular fecha final del primero para usar como inicio del segundo
            $regTemp = clone $primerReg;
            $regTemp->TotalPedido = $pedidoPrimeroFinal;
            $regTemp->SaldoPedido = max(0, $pedidoPrimeroFinal - $produccionPrimero);
            $horasPrimero = self::calcularHorasProd($regTemp);

            if ($horasPrimero > 0) {
                if (!empty($primerReg->CalendarioId)) {
                    $fin = self::calcularFechaFinalDesdeInicio($primerReg->CalendarioId, $cursorFecha, $horasPrimero);
                    if ($fin) {
                        $cursorFecha = $fin->copy();
                    } else {
                        $cursorFecha->addSeconds((int)round($horasPrimero * 3600));
                    }
                } else {
                    $cursorFecha->addSeconds((int)round($horasPrimero * 3600));
                }
            }

            // SEGUNDO REGISTRO: Calcular lo que quede (puede desbalancearse)
            $sumaPedidos = $pedidoPrimeroFinal;
            $diferencia = $totalPedidoActual - $sumaPedidos;
            $pedidoSegundo = max($produccionSegundo, round($diferencia));

            $nuevosPedidos[$segundoReg->Id] = [
                'id' => (int)$segundoReg->Id,
                'total_pedido' => $pedidoSegundo,
                'modo' => 'total'
            ];
        } else {
            // Para múltiples registros: calcular secuencialmente
            $cursorFecha = $fechaInicio->copy();

            // Snap inicial al calendario
            if (!empty($primerRegistro->CalendarioId)) {
                $snap = self::snapInicioAlCalendario($primerRegistro->CalendarioId, $cursorFecha);
                if ($snap) $cursorFecha = $snap;
            }

            // Calcular pedidos para todos excepto el último
            // PRIORIDAD: Cada registro debe llegar lo más cerca posible a la fecha objetivo
            for ($idx = 0; $idx < $ultimoIdx; $idx++) {
                $datos = $datosRegistros[$idx];
                $reg = $datos['registro'];
                $produccion = $datos['produccion'];

                // Determinar fecha objetivo para este registro
                // Si es el penúltimo, debe llegar exactamente a la fecha objetivo
                // Si es anterior, debe llegar lo más cerca posible
                $fechaObjetivoRegistro = ($idx === $ultimoIdx - 1)
                    ? $fechaFinObjetivo
                    : self::calcularFechaObjetivoIntermedia($fechaInicio, $fechaFinObjetivo, $idx, $ultimoIdx);

                // Snap al calendario si tiene
                if (!empty($reg->CalendarioId)) {
                    $snap = self::snapInicioAlCalendario($reg->CalendarioId, $cursorFecha);
                    if ($snap) $cursorFecha = $snap;
                }

                // Calcular pedido exacto para llegar a la fecha objetivo de este registro
                $pedidoExacto = self::calcularPedidoParaFechaObjetivo(
                    $reg,
                    $cursorFecha,
                    $fechaObjetivoRegistro,
                    $produccion
                );

                $pedidoFinal = max($produccion, round($pedidoExacto));

                $nuevosPedidos[$reg->Id] = [
                    'id' => (int)$reg->Id,
                    'total_pedido' => $pedidoFinal,
                    'modo' => 'total'
                ];

                // Calcular fecha final real de este registro para usar como inicio del siguiente
                $regTemp = clone $reg;
                $regTemp->TotalPedido = $pedidoFinal;
                $regTemp->SaldoPedido = max(0, $pedidoFinal - $produccion);
                $horas = self::calcularHorasProd($regTemp);

                if ($horas > 0) {
                    if (!empty($reg->CalendarioId)) {
                        $fin = self::calcularFechaFinalDesdeInicio($reg->CalendarioId, $cursorFecha, $horas);
                        if ($fin) {
                            $cursorFecha = $fin->copy();
                        } else {
                            $cursorFecha->addSeconds((int)round($horas * 3600));
                        }
                    } else {
                        $cursorFecha->addSeconds((int)round($horas * 3600));
                    }
                }
            }

            // Último registro: calcular lo que quede (puede desbalancearse)
            $ultimoReg = $datosRegistros[$ultimoIdx]['registro'];
            $sumaPedidos = array_sum(array_column($nuevosPedidos, 'total_pedido'));
            $diferencia = $totalPedidoActual - $sumaPedidos;
            $produccionUltimo = (float)($ultimoReg->Produccion ?? 0);

            $pedidoUltimo = max($produccionUltimo, round($diferencia));

            $nuevosPedidos[$ultimoReg->Id] = [
                'id' => (int)$ultimoReg->Id,
                'total_pedido' => $pedidoUltimo,
                'modo' => 'total'
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Balanceo calculado correctamente',
            'cambios' => array_values($nuevosPedidos),
            'horas_disponibles' => $horasDisponibles,
            'horas_necesarias_originales' => $horasNecesariasActuales,
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
        float $produccion
    ): float {
        // Calcular horas disponibles hasta fecha objetivo
        $horasDisponibles = self::calcularHorasDisponiblesHastaFecha(
            $reg->CalendarioId ?? null,
            $fechaInicio,
            $fechaObjetivo
        );

        if ($horasDisponibles <= 0) {
            // No hay horas disponibles, devolver producción mínima
            return $produccion;
        }

        // Calcular eficiencia del registro (horas por unidad de pedido)
        $regTemp = clone $reg;
        $regTemp->TotalPedido = 1000; // Valor de prueba
        $regTemp->SaldoPedido = max(0, 1000 - $produccion);
        $horasPrueba = self::calcularHorasProd($regTemp);

        if ($horasPrueba <= 0) {
            // No se puede calcular eficiencia, usar pedido actual
            return (float)($reg->TotalPedido ?? $produccion);
        }

        $eficienciaHoras = $horasPrueba / 1000.0; // horas por unidad

        // Estimación inicial basada en eficiencia
        $pedidoEstimado = $produccion + ($horasDisponibles / max($eficienciaHoras, 0.0001));

        // Búsqueda binaria para encontrar el pedido exacto
        $minPedido = $produccion;
        $maxPedido = $pedidoEstimado * 3; // Límite superior amplio
        $mejorPedido = $pedidoEstimado;
        $mejorDiferencia = PHP_FLOAT_MAX;

        $maxIteraciones = 50; // Más iteraciones para mayor precisión
        $toleranciaSegundos = 60; // 1 minuto de tolerancia

        for ($iter = 0; $iter < $maxIteraciones; $iter++) {
            $pedidoPrueba = ($minPedido + $maxPedido) / 2.0;

            // Calcular fecha final con este pedido
            $regTemp = clone $reg;
            $regTemp->TotalPedido = $pedidoPrueba;
            $regTemp->SaldoPedido = max(0, $pedidoPrueba - $produccion);
            $horas = self::calcularHorasProd($regTemp);

            if ($horas <= 0) {
                $minPedido = $pedidoPrueba;
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
                $mejorPedido = $pedidoPrueba;
            }

            if ($diferenciaSegundos <= $toleranciaSegundos) {
                // Llegamos a la tolerancia, salir
                break;
            }

            // Ajustar búsqueda binaria
            if ($fechaFinal->getTimestamp() < $fechaObjetivo->getTimestamp()) {
                // Necesitamos más pedido
                $minPedido = $pedidoPrueba;
            } else {
                // Tenemos demasiado pedido
                $maxPedido = $pedidoPrueba;
            }

            // Si el rango es muy pequeño, salir
            if (($maxPedido - $minPedido) < 0.1) {
                break;
            }
        }

        return $mejorPedido;
    }

    /**
     * Calcula una fecha objetivo intermedia para registros que no son el penúltimo
     * Distribuye el tiempo proporcionalmente
     */
    private static function calcularFechaObjetivoIntermedia(
        Carbon $fechaInicio,
        Carbon $fechaFinObjetivo,
        int $indiceActual,
        int $totalRegistros
    ): Carbon {
        // Calcular proporción del tiempo total
        $totalSegundos = $fechaFinObjetivo->getTimestamp() - $fechaInicio->getTimestamp();
        $proporcion = ($indiceActual + 1) / (float)$totalRegistros;

        $segundosIntermedios = (int)round($totalSegundos * $proporcion);
        return $fechaInicio->copy()->addSeconds($segundosIntermedios);
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
