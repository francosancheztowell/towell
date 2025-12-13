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
    private static array $modeloCache = [];

    /** si el cambio de fin es menor a esto, NO cascada (evita “rebotes”) */
    private const CASCADE_THRESHOLD_SECONDS = 300; // 5 min

    public static function actualizarPedidos(Request $request)
    {
        $dispatcher = ReqProgramaTejido::getEventDispatcher();

        try {
            $request->validate([
                'cambios' => 'required|array',
                'cambios.*.id' => 'required|integer',
                'cambios.*.total_pedido' => 'required|numeric|min:0', // (en UI puede venir saldo o total)
                'cambios.*.fecha_final' => 'nullable|string',
                'cambios.*.modo' => 'nullable|string|in:saldo,total', // opcional
                'ord_compartida' => 'required|integer',
            ]);

            $cambios = $request->input('cambios');

            ReqProgramaTejido::unsetEventDispatcher();

            $idsAfectados = [];
            $porTelar = [];

            DB::beginTransaction();

            foreach ($cambios as $cambio) {
                $registro = ReqProgramaTejido::find($cambio['id']);
                if (!$registro) continue;

                $produccion = (float)($registro->Produccion ?? 0);

                // OJO: puede ser saldo o total dependiendo de tu UI
                $input = (float)$cambio['total_pedido'];

                [$total, $saldo, $modoDetectado] = self::resolverTotalSaldo(
                    $input,
                    $produccion,
                    $cambio['modo'] ?? null
                );

                $registro->TotalPedido = $total;
                $registro->SaldoPedido = $saldo;

                $fechaFinalAntes = $registro->FechaFinal;
                $fechaFinalCambiada = false;

                // 1) Si el usuario mandó fecha_final: respetar
                $fechaFinalManual = isset($cambio['fecha_final']) ? trim((string)$cambio['fecha_final']) : '';
                if ($fechaFinalManual !== '') {
                    $fin = self::parseFecha($fechaFinalManual);
                    if ($fin) {
                        $registro->FechaFinal = $fin->format('Y-m-d H:i:s');
                    } else {
                        Log::warning('BalancearTejido: fecha_final inválida, se ignora', [
                            'id' => $registro->Id,
                            'fecha_final_raw' => $fechaFinalManual,
                        ]);
                    }
                }

                // 2) Si NO mandó fecha_final: auto-recalcular por horas/calendario
                if ($fechaFinalManual === '' && !empty($registro->FechaInicio)) {
                    $inicio = Carbon::parse($registro->FechaInicio);

                    if (!empty($registro->CalendarioId)) {
                        $snap = self::snapInicioAlCalendario($registro->CalendarioId, $inicio);
                        if ($snap) {
                            $inicio = $snap;
                            $registro->FechaInicio = $inicio->format('Y-m-d H:i:s');
                        }
                    }

                    $horasNecesarias = self::calcularHorasProd($registro);

                    if ($horasNecesarias > 0) {
                        $finCalc = null;

                        if (!empty($registro->CalendarioId)) {
                            $finCalc = self::calcularFechaFinalDesdeInicio($registro->CalendarioId, $inicio, $horasNecesarias);
                        }

                        if (!$finCalc) {
                            $finCalc = $inicio->copy()->addSeconds((int)ceil($horasNecesarias * 3600)); // ceil evita “subestimar”
                        }

                        $registro->FechaFinal = $finCalc->format('Y-m-d H:i:s');

                        Log::info('BalancearTejido: auto FechaFinal', [
                            'id' => $registro->Id,
                            'modo_input' => $modoDetectado,
                            'produccion' => $produccion,
                            'input' => $input,
                            'total' => $total,
                            'saldo' => $saldo,
                            'horas' => $horasNecesarias,
                            'inicio' => $registro->FechaInicio,
                            'fin' => $registro->FechaFinal,
                            'calendario_id' => $registro->CalendarioId ?? null,
                        ]);
                    } else {
                        Log::warning('BalancearTejido: horasNecesarias=0 (datos incompletos modelo/STD)', [
                            'id' => $registro->Id,
                            'modo_input' => $modoDetectado,
                            'vel' => $registro->VelocidadSTD ?? null,
                            'efic' => $registro->EficienciaSTD ?? null,
                            'tamano' => $registro->TamanoClave ?? null,
                            'saldo' => $registro->SaldoPedido ?? null,
                        ]);
                    }
                }

                // 3) Fórmulas
                if (!empty($registro->FechaInicio) && !empty($registro->FechaFinal)) {
                    $formulas = self::calcularFormulasEficiencia($registro);
                    foreach ($formulas as $campo => $valor) {
                        $registro->{$campo} = $valor;
                    }
                }

                $registro->save();
                $idsAfectados[] = $registro->Id;

                // ¿cambió FechaFinal “de verdad”? (umbral)
                $delta = self::diffSecondsNullable($fechaFinalAntes, $registro->FechaFinal);

                if ($delta === null) {
                    // si antes no había fecha, sí consideramos cambio
                    $fechaFinalCambiada = !empty($registro->FechaFinal);
                } else {
                    $fechaFinalCambiada = ($delta >= self::CASCADE_THRESHOLD_SECONDS);
                }

                if ($fechaFinalCambiada) {
                    $key = trim((string)$registro->SalonTejidoId) . '|' . trim((string)$registro->NoTelarId);
                    $porTelar[$key][] = $registro->Id;

                    Log::info('BalancearTejido: marcar cascada', [
                        'id' => $registro->Id,
                        'delta_seg' => $delta,
                        'threshold' => self::CASCADE_THRESHOLD_SECONDS,
                        'telar' => $key,
                    ]);
                }
            }

            // 4) Cascada por telar
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

            // 5) Regenerar líneas diarias
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

    /**
     * Resuelve si input viene como SALDO o TOTAL.
     * - Si mandas modo='saldo' o 'total' lo respeta.
     * - Si no, auto:
     *    - si Produccion>0 y input < Produccion => input es saldo
     *    - si no => input es total
     */
    private static function resolverTotalSaldo(float $input, float $produccion, ?string $modo): array
    {
        $input = max(0, $input);
        $produccion = max(0, $produccion);

        if ($modo === 'saldo') {
            $saldo = $input;
            $total = $produccion + $saldo;
            return [$total, $saldo, 'saldo_explicit'];
        }

        if ($modo === 'total') {
            $total = $input;
            $saldo = max(0, $total - $produccion);
            return [$total, $saldo, 'total_explicit'];
        }

        // auto
        if ($produccion > 0 && $input < $produccion) {
            $saldo = $input;
            $total = $produccion + $saldo;
            return [$total, $saldo, 'auto_saldo'];
        }

        $total = $input;
        $saldo = max(0, $total - $produccion);
        return [$total, $saldo, 'auto_total'];
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
            $fin = null;

            if ($horas > 0) {
                if (!empty($r->CalendarioId)) {
                    $fin = self::calcularFechaFinalDesdeInicio($r->CalendarioId, $inicio, $horas);
                }
                if (!$fin) {
                    $fin = $inicio->copy()->addSeconds((int)ceil($horas * 3600));
                }
            } else {
                $fin = $inicio->copy();
            }

            $r->FechaFinal = $fin->format('Y-m-d H:i:s');

            $formulas = self::calcularFormulasEficiencia($r);
            foreach ($formulas as $campo => $valor) {
                $r->{$campo} = $valor;
            }

            $r->save();
            $ids[] = $r->Id;

            $cursor = $fin->copy();

            Log::info('BalancearTejido: cascada', [
                'id' => $r->Id,
                'inicio' => $r->FechaInicio,
                'fin' => $r->FechaFinal,
                'horas' => $horas,
                'saldo' => $r->SaldoPedido ?? null,
                'calendario_id' => $r->CalendarioId ?? null,
            ]);
        }

        return $ids;
    }

    private static function calcularHorasProd(ReqProgramaTejido $p): float
    {
        $vel  = (float)($p->VelocidadSTD ?? 0);
        $efic = (float)($p->EficienciaSTD ?? 0);
        if ($efic > 1) $efic = $efic / 100;

        $cant = (float)($p->SaldoPedido ?? $p->Produccion ?? $p->TotalPedido ?? 0);

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

        if ($stdToaHra > 0 && $efic > 0 && $cant > 0) {
            return $cant / ($stdToaHra * $efic);
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

    public static function calcularFechaFinalDesdeInicio(string $calendarioId, Carbon $fechaInicio, float $horasNecesarias): ?Carbon
    {
        $segundosRestantes = (int)max(0, ceil($horasNecesarias * 3600));
        if ($segundosRestantes <= 0) return $fechaInicio->copy();

        $cursor = $fechaInicio->copy();

        while ($segundosRestantes > 0) {
            $lineas = ReqCalendarioLine::where('CalendarioId', $calendarioId)
                ->where('FechaFin', '>', $cursor)
                ->orderBy('FechaInicio')
                ->limit(5000)
                ->get();

            if ($lineas->isEmpty()) {
                return null;
            }

            foreach ($lineas as $linea) {
                if ($segundosRestantes <= 0) break;

                $ini = Carbon::parse($linea->FechaInicio);
                $fin = Carbon::parse($linea->FechaFin);

                if ($cursor->lt($ini)) {
                    // gap real del calendario (aquí es donde “salta” días si no hay líneas)
                    $cursor = $ini->copy();
                }
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
            if ($cursor->lt($ultimaFin)) {
                $cursor = $ultimaFin->copy();
            }
        }

        return $cursor;
    }

    private static function calcularFormulasEficiencia(ReqProgramaTejido $programa): array
    {
        // (la dejo igual que la tienes, está bien para cálculo/estadísticos)
        // ... pega tu misma implementación aquí sin cambios ...
        return [];
    }

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

    private static function parseFecha(string $raw): ?Carbon
    {
        try {
            return Carbon::parse($raw);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
