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

    /** si el cambio de fin es menor a esto, NO cascada (evita “rebotes”) */
    private const CASCADE_THRESHOLD_SECONDS = 300; // 5 min

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

        // Cargar registros una sola vez
        $ids = array_values(array_unique(array_map(fn($c) => (int)$c['id'], $cambios)));
        $regs = ReqProgramaTejido::whereIn('Id', $ids)->get()->keyBy('Id');

        $resp = [];

        foreach ($cambios as $cambio) {
            $id = (int)$cambio['id'];
            /** @var ReqProgramaTejido|null $r */
            $r = $regs->get($id);
            if (!$r) continue;

            $produccion = (float)($r->Produccion ?? 0);
            $input = (float)$cambio['total_pedido'];

            // Importante: para este modal, SIEMPRE manda modo=total desde JS
            [$total, $saldo] = self::resolverTotalSaldo($input, $produccion, $cambio['modo'] ?? 'total');

            // Mutar EN MEMORIA (no save)
            $r->TotalPedido = $total;
            $r->SaldoPedido = $saldo;

            [$inicio, $fin, $horas] = self::calcularInicioFinExactos($r);

            $resp[] = [
                'id' => (int)$r->Id,
                'fecha_inicio' => $inicio ? $inicio->format('Y-m-d H:i:s') : null,
                'fecha_final'  => $fin ? $fin->format('Y-m-d H:i:s') : null,
                'horas_prod'   => $horas,
                'saldo'        => $saldo,
                'total'        => $total,
                'calendario_id'=> $r->CalendarioId ?? null,
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
                'cambios' => 'required|array',
                'cambios.*.id' => 'required|integer',
                'cambios.*.total_pedido' => 'required|numeric|min:0',
                // aunque venga, lo ignoramos (para evitar fechas falsas del modal)
                'cambios.*.fecha_final' => 'nullable|string',
                'cambios.*.modo' => 'nullable|string|in:saldo,total',
                'ord_compartida' => 'required|integer',
            ]);

            $cambios = $request->input('cambios');

            ReqProgramaTejido::unsetEventDispatcher();

            $idsAfectados = [];
            $porTelar = [];

            DB::beginTransaction();

            foreach ($cambios as $cambio) {
                $registro = ReqProgramaTejido::find((int)$cambio['id']);
                if (!$registro) continue;

                $produccion = (float)($registro->Produccion ?? 0);

                $input = (float)$cambio['total_pedido'];

                // Para el modal: SIEMPRE manda modo=total (aun así dejamos fallback)
                [$total, $saldo] = self::resolverTotalSaldo(
                    $input,
                    $produccion,
                    $cambio['modo'] ?? 'total'
                );

                $registro->TotalPedido = $total;
                $registro->SaldoPedido = $saldo;

                $fechaFinalAntes = $registro->FechaFinal;

                // ===== SIEMPRE recalcular FechaFinal EXACTA con calendario =====
                if (!empty($registro->FechaInicio)) {
                    [$inicio, $fin, $horas] = self::calcularInicioFinExactos($registro);

                    if ($inicio) $registro->FechaInicio = $inicio->format('Y-m-d H:i:s');
                    if ($fin)    $registro->FechaFinal  = $fin->format('Y-m-d H:i:s');

                    Log::info('BalancearTejido: recalculo exacto (save)', [
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

                // ===== Fórmulas EXACTAS (igual que DuplicarTejido) =====
                if (!empty($registro->FechaInicio) && !empty($registro->FechaFinal)) {
                    $formulas = self::calcularFormulasEficiencia($registro);
                    foreach ($formulas as $campo => $valor) {
                        $registro->{$campo} = $valor;
                    }
                }

                $registro->save();
                $idsAfectados[] = $registro->Id;

                // Cascada si cambió FechaFinal "de verdad"
                $delta = self::diffSecondsNullable($fechaFinalAntes, $registro->FechaFinal);
                $fechaFinalCambiada = ($delta === null)
                    ? !empty($registro->FechaFinal)
                    : ($delta >= self::CASCADE_THRESHOLD_SECONDS);

                if ($fechaFinalCambiada) {
                    $key = trim((string)$registro->SalonTejidoId) . '|' . trim((string)$registro->NoTelarId);
                    $porTelar[$key][] = $registro->Id;
                }
            }

            // Cascada por telar (mantener secuencia real)
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

            if ($dispatcher) {
                ReqProgramaTejido::setEventDispatcher($dispatcher);
            }
            ReqProgramaTejido::observe(ReqProgramaTejidoObserver::class);

            // Regenerar líneas diarias (observer)
            $idsAfectados = array_values(array_unique(array_filter($idsAfectados)));
            if (!empty($idsAfectados)) {
                $observer = new ReqProgramaTejidoObserver();
                foreach ($idsAfectados as $id) {
                    $reg = ReqProgramaTejido::find($id);
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
            ReqProgramaTejido::observe(ReqProgramaTejidoObserver::class);

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
        if (empty($r->FechaInicio)) {
            return [null, null, 0.0];
        }

        $inicio = Carbon::parse($r->FechaInicio);

        // Snap a calendario si hay
        if (!empty($r->CalendarioId)) {
            $snap = self::snapInicioAlCalendario($r->CalendarioId, $inicio);
            if ($snap) $inicio = $snap;
        }

        $horasNecesarias = self::calcularHorasProd($r);

        if ($horasNecesarias <= 0) {
            // fallback mínimo, pero mejor que null
            $fin = $inicio->copy()->addDays(30);
            return [$inicio, $fin, 0.0];
        }

        if (!empty($r->CalendarioId)) {
            $fin = self::calcularFechaFinalDesdeInicio($r->CalendarioId, $inicio, $horasNecesarias);
            if (!$fin) {
                $fin = $inicio->copy()->addSeconds((int)($horasNecesarias * 3600));
            }
            return [$inicio, $fin, $horasNecesarias];
        }

        // sin calendario, continuo
        $fin = $inicio->copy()->addSeconds((int)($horasNecesarias * 3600));
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

        // default total
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
            return abs($ca->diffInSeconds($cb, false));
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
                    ? (self::calcularFechaFinalDesdeInicio($r->CalendarioId, $inicio, $horas) ?: $inicio->copy()->addSeconds((int)($horas * 3600)))
                    : $inicio->copy()->addSeconds((int)($horas * 3600));
            } else {
                $fin = $inicio->copy()->addDays(30);
            }

            $r->FechaFinal = $fin->format('Y-m-d H:i:s');

            $formulas = self::calcularFormulasEficiencia($r);
            foreach ($formulas as $campo => $valor) $r->{$campo} = $valor;

            $r->save();
            $ids[] = $r->Id;

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

    private static function snapInicioAlCalendario(string $calendarioId, Carbon $fechaInicio): ?Carbon
    {
        $linea = ReqCalendarioLine::where('CalendarioId', $calendarioId)
            ->where('FechaFin', '>', $fechaInicio)
            ->orderBy('FechaInicio')
            ->first();

        if (!$linea) return null;

        $ini = Carbon::parse($linea->FechaInicio);
        $fin = Carbon::parse($linea->FechaFin);

        if ($fechaInicio->gte($ini) && $fechaInicio->lt($fin)) {
            return $fechaInicio->copy();
        }

        return $ini->copy();
    }

    /**
     * FechaFinal recorriendo líneas reales (FechaInicio/FechaFin).
     * No usa HorasTurno: usa DURACIÓN REAL.
     */
    public static function calcularFechaFinalDesdeInicio(string $calendarioId, Carbon $fechaInicio, float $horasNecesarias): ?Carbon
    {
        $segundosRestantes = (int)(max(0, $horasNecesarias) * 3600);
        if ($segundosRestantes <= 0) return $fechaInicio->copy();

        $cursor = $fechaInicio->copy();

        while ($segundosRestantes > 0) {
            $lineas = ReqCalendarioLine::where('CalendarioId', $calendarioId)
                ->where('FechaFin', '>', $cursor)
                ->orderBy('FechaInicio')
                ->limit(5000)
                ->get();

            if ($lineas->isEmpty()) return null;

            foreach ($lineas as $linea) {
                if ($segundosRestantes <= 0) break;

                $ini = Carbon::parse($linea->FechaInicio);
                $fin = Carbon::parse($linea->FechaFin);

                if ($cursor->lt($ini)) $cursor = $ini->copy();
                if ($cursor->gte($fin)) continue;

                $disponibles = $cursor->diffInSeconds($fin, true);
                if ($disponibles <= 0) {
                    $cursor = $fin->copy();
                    continue;
                }

                $usar = min($disponibles, $segundosRestantes);
                $cursor->addSeconds($usar);
                $segundosRestantes -= $usar;

                if ($segundosRestantes <= 0) return $cursor;

                if ($cursor->gte($fin)) $cursor = $fin->copy();
            }

            $ultimaFin = Carbon::parse($lineas->last()->FechaFin);
            if ($cursor->lt($ultimaFin)) $cursor = $ultimaFin->copy();
        }

        return $cursor;
    }

    // =========================================================
    // Fórmulas EXACTAS (copiadas de DuplicarTejido)
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
    // Modelo params (cache)
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

    private static function sanitizeNumber($value): float
    {
        if ($value === null) return 0.0;
        if (is_numeric($value)) return (float)$value;
        $clean = str_replace([',', ' '], '', (string)$value);
        return is_numeric($clean) ? (float)$clean : 0.0;
    }
}
