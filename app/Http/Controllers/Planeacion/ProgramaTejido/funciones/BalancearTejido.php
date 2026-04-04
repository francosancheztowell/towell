<?php

namespace App\Http\Controllers\Planeacion\ProgramaTejido\funciones;

use App\Http\Controllers\Planeacion\ProgramaTejido\helper\DateHelpers;
use App\Http\Controllers\Planeacion\ProgramaTejido\helper\TejidoHelpers;
use App\Models\Planeacion\ReqCalendarioLine;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Observers\ReqProgramaTejidoObserver;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BalancearTejido
{
    /**
     * Líneas de calendario parseadas en memoria por request/proceso.
     * En workers persistentes (p. ej. Octane) puede desactualizarse si se editan calendarios en BD;
     * llamar clearCalendarioLinesCache() tras modificar ReqCalendarioLine.
     *
     * @var array<string, list<array{ini: Carbon, fin: Carbon, ini_ts: int, fin_ts: int}>>
     */
    private static array $calLinesCache = [];

    /** gaps muy pequeños (ej. 2s entre :29:58 y :30:00) se ignoran */
    private const SMALL_GAP_SECONDS = 5;

    // =========================================================
    // PREVIEW SOLO PARA EL MODAL (CALENDARIO)
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

        $ids = array_values(array_unique(array_map(fn ($c) => (int) $c['id'], $cambios)));
        $regs = ReqProgramaTejido::whereIn('Id', $ids)->get()->keyBy('Id');

        $ordCompartida = (int) $request->input('ord_compartida');
        $membershipError = self::validarCambiosPertenecenOrdCompartida($regs, $ids, $ordCompartida);
        if ($membershipError !== null) {
            return $membershipError;
        }

        // Warm caches (NO cambia resultados, solo evita N+1)
        self::warmCachesFromProgramas($regs->values());

        $resp = [];

        foreach ($cambios as $cambio) {
            $id = (int) $cambio['id'];
            /** @var ReqProgramaTejido|null $r */
            $r = $regs->get($id);
            if (! $r) {
                continue;
            }

            $produccion = (float) ($r->Produccion ?? 0);
            $input = (float) $cambio['total_pedido'];

            [$total, $saldo] = self::resolverTotalSaldo($input, $produccion, $cambio['modo'] ?? 'total');

            // Mutar EN MEMORIA (no save)
            $r->TotalPedido = $total;
            $r->SaldoPedido = $saldo;

            $fechaInicioOriginal = ! empty($r->FechaInicio)
                ? Carbon::parse($r->FechaInicio)
                : null;

            [$inicio, $fin, $horas] = self::calcularInicioFinExactos($r);

            $resp[] = [
                'id' => (int) $r->Id,
                'fecha_inicio' => $fechaInicioOriginal ? $fechaInicioOriginal->format('Y-m-d H:i:s') : null,
                'fecha_final' => $fin ? $fin->format('Y-m-d H:i:s') : null,
                'horas_prod' => $horas,
                'saldo' => $saldo,
                'total' => $total,
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

            $ids = array_values(array_unique(array_map(fn ($c) => (int) $c['id'], $cambios)));
            $regs = ReqProgramaTejido::whereIn('Id', $ids)->get()->keyBy('Id');

            $ordCompartida = (int) $request->input('ord_compartida');
            $membershipError = self::validarCambiosPertenecenOrdCompartida($regs, $ids, $ordCompartida);
            if ($membershipError !== null) {
                return $membershipError;
            }

            // Warm caches (NO cambia resultados, solo evita N+1)
            self::warmCachesFromProgramas($regs->values());

            ReqProgramaTejido::unsetEventDispatcher();

            $idsAfectados = [];
            $porTelar = [];
            $idsActualizados = []; // IDs que ya fueron actualizados manualmente

            DB::beginTransaction();

            // PASO 1: Actualizar TODOS los registros en $cambios primero

            foreach ($cambios as $cambio) {
                $id = (int) $cambio['id'];

                /** @var ReqProgramaTejido|null $registro */
                $registro = $regs->get($id);
                if (! $registro) {
                    Log::warning('BalancearTejido: Registro no encontrado', ['id' => $id]);

                    continue;
                }

                $produccion = (float) ($registro->Produccion ?? 0);
                $input = (float) $cambio['total_pedido'];

                [$total, $saldo] = self::resolverTotalSaldo($input, $produccion, $cambio['modo'] ?? 'total');

                $registro->TotalPedido = $total;
                $registro->SaldoPedido = $saldo;

                $fechaFinalAntes = $registro->FechaFinal;

                // Recalcular fechas exactas SI hay FechaInicio
                if (! empty($registro->FechaInicio)) {
                    [$inicio, $fin, $horas] = self::calcularInicioFinExactos($registro);

                    if ($inicio) {
                        $registro->FechaInicio = $inicio->format('Y-m-d H:i:s');
                    }
                    if ($fin) {
                        $registro->FechaFinal = $fin->format('Y-m-d H:i:s');
                    }
                }

                // Fórmulas exactas
                if (! empty($registro->FechaInicio) && ! empty($registro->FechaFinal)) {
                    $formulas = self::calcularFormulasEficiencia($registro);
                    foreach ($formulas as $campo => $valor) {
                        $registro->{$campo} = $valor;
                    }
                }

                $registro->save();
                $idsAfectados[] = (int) $registro->Id;
                $idsActualizados[(int) $registro->Id] = true; // Marcar como ya actualizado

                // Para cascada: guardar información del telar
                $key = trim((string) $registro->SalonTejidoId).'|'.trim((string) $registro->NoTelarId);
                if (! isset($porTelar[$key])) {
                    $porTelar[$key] = [];
                }
                $porTelar[$key][] = (int) $registro->Id;
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
                    ->orderBy('Posicion', 'asc')
                    ->orderBy('FechaInicio', 'asc')
                    ->orderBy('Id', 'asc')
                    ->get();

                if ($todosRegistrosTelar->isEmpty()) {
                    continue;
                }

                // Warm caches
                self::warmCachesFromProgramas($todosRegistrosTelar);

                // Encontrar el índice del registro más temprano que fue actualizado
                $idxBase = null;
                foreach ($todosRegistrosTelar as $idx => $r) {
                    if (isset($idsActualizados[(int) $r->Id])) {
                        $idxBase = $idx;
                        break;
                    }
                }

                if ($idxBase === null) {
                    Log::warning('BalancearTejido: No se encontró registro base', ['telar_key' => $key]);

                    continue;
                }

                $base = $todosRegistrosTelar[$idxBase];
                $cursor = ! empty($base->FechaFinal)
                    ? Carbon::parse($base->FechaFinal)
                    : Carbon::parse($base->FechaInicio);

                // Continuar desde el siguiente registro después del base
                $cascadaCount = 0;
                foreach ($todosRegistrosTelar->slice($idxBase + 1) as $r) {
                    // Si ya fue actualizado manualmente, usar su FechaFinal como cursor y continuar
                    if (isset($idsActualizados[(int) $r->Id])) {
                        if (! empty($r->FechaFinal)) {
                            $cursor = Carbon::parse($r->FechaFinal);
                        }

                        continue;
                    }

                    // Actualizar este registro en cascada
                    $inicio = $cursor->copy();
                    if (! empty($r->CalendarioId)) {
                        $snap = self::snapInicioAlCalendario($r->CalendarioId, $inicio);
                        if ($snap) {
                            $inicio = $snap;
                        }
                    }

                    $r->FechaInicio = $inicio->format('Y-m-d H:i:s');

                    $horas = TejidoHelpers::calcularHorasProd($r);
                    if ($horas > 0) {
                        $fin = ! empty($r->CalendarioId)
                            ? (self::calcularFechaFinalDesdeInicio($r->CalendarioId, $inicio, $horas) ?: $inicio->copy()->addSeconds((int) round($horas * 3600)))
                            : $inicio->copy()->addSeconds((int) round($horas * 3600));
                    } else {
                        $fin = TejidoHelpers::esRepaso($r) ? $inicio->copy()->addHours(TejidoHelpers::DEFAULT_DURACION_REPASO_HORAS) : $inicio->copy()->addDays(TejidoHelpers::DEFAULT_DURACION_DIAS);
                    }
                    $r->FechaFinal = $fin->format('Y-m-d H:i:s');
                    $formulas = self::calcularFormulasEficiencia($r);
                    foreach ($formulas as $campo => $valor) {
                        $r->{$campo} = $valor;
                    }
                    $r->save();
                    $idsAfectados[] = (int) $r->Id;
                    $cascadaCount++;
                    $cursor = $fin->copy();
                }
            }

            if ($ordCompartida > 0) {
                // Balancear pedidos/fechas no debe reasignar la orden lider del grupo.
                // Solo sincronizamos OrdPrincipal usando el lider ya persistido.
                VincularTejido::actualizarOrdPrincipalPorOrdCompartida($ordCompartida);
            }

            DB::commit();
            // Restaurar dispatcher
            if ($dispatcher) {
                ReqProgramaTejido::setEventDispatcher($dispatcher);
            }
            // Eventos desactivados durante la transacción; se invoca el observer aquí para regenerar líneas diarias.
            $idsAfectados = array_values(array_unique(array_filter($idsAfectados)));
            if (! empty($idsAfectados)) {
                $observer = new ReqProgramaTejidoObserver;
                $regsObs = ReqProgramaTejido::whereIn('Id', $idsAfectados)->get()->keyBy('Id');

                foreach ($idsAfectados as $id) {
                    $reg = $regsObs->get($id);
                    if (! $reg) {
                        continue;
                    }
                    try {
                        $observer->saved($reg);
                    } catch (\Throwable $e) {
                        Log::error('BalancearTejido: Error en observer', [
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
                'registros_ids' => $idsAfectados, // IDs de los registros actualizados para actualizar sin recargar
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();

            if ($dispatcher) {
                ReqProgramaTejido::setEventDispatcher($dispatcher);
            }

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar los pedidos: '.$e->getMessage(),
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
                    'StdDia',
                    'Posicion',
                    'FechaCreacion',
                    'HoraCreacion',
                    'OrdPrincipal',
                ])
                ->where('OrdCompartida', $ordCompartida)
                ->orderBy('Posicion', 'asc')
                ->orderBy('NoTelarId', 'asc')
                ->get();

            $totalOriginal = $registros->sum('TotalPedido');
            $totalSaldo = $registros->sum('SaldoPedido');

            return response()->json([
                'success' => true,
                'registros' => $registros,
                'total_original' => $totalOriginal,
                'total_saldo' => $totalSaldo,
                'cantidad_registros' => $registros->count(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los registros: '.$e->getMessage(),
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

        // Si el registro está EnProceso, usar now() como inicio efectivo
        $esEnProceso = (bool) $r->EnProceso;
        $inicio = $esEnProceso ? Carbon::now() : Carbon::parse($r->FechaInicio);

        // Saldo negativo: FechaFin = now() si EnProceso, si no el mismo día que FechaInicio
        $saldo = TejidoHelpers::sanitizeNumber($r->SaldoPedido ?? $r->Produccion ?? $r->TotalPedido ?? 0);
        if ($saldo < 0) {
            $fin = $esEnProceso
                ? Carbon::now()
                : Carbon::parse($r->FechaInicio)->copy()->endOfDay();

            return [$inicio, $fin, 0.0];
        }

        // Snap a calendario (misma lógica, solo sin query repetido) - no aplicar snap a EnProceso
        if (! $esEnProceso && ! empty($r->CalendarioId)) {
            $snap = self::snapInicioAlCalendario($r->CalendarioId, $inicio);
            if ($snap) {
                $inicio = $snap;
            }
        }

        $horasNecesarias = TejidoHelpers::calcularHorasProd($r);

        if ($horasNecesarias <= 0) {
            $fin = TejidoHelpers::esRepaso($r) ? $inicio->copy()->addHours(TejidoHelpers::DEFAULT_DURACION_REPASO_HORAS) : $inicio->copy()->addDays(TejidoHelpers::DEFAULT_DURACION_DIAS);

            return [$inicio, $fin, 0.0];
        }

        if (! empty($r->CalendarioId)) {
            $fin = self::calcularFechaFinalDesdeInicio($r->CalendarioId, $inicio, $horasNecesarias);
            if (! $fin) {
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

    /**
     * @param  Collection<int|string, ReqProgramaTejido>  $regsById
     * @param  array<int>  $ids
     */
    private static function validarCambiosPertenecenOrdCompartida(Collection $regsById, array $ids, int $ordCompartida): ?JsonResponse
    {
        foreach ($ids as $id) {
            if (! $regsById->has($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Uno o más registros no existen.',
                    'id' => $id,
                ], 422);
            }
            $reg = $regsById->get($id);
            $ordReg = (int) ($reg->OrdCompartida ?? 0);
            if ($ordReg !== $ordCompartida) {
                return response()->json([
                    'success' => false,
                    'message' => 'Todos los registros deben pertenecer al OrdCompartida indicado.',
                    'id' => $id,
                ], 422);
            }
        }

        return null;
    }

    /**
     * Cierra la suma de total_pedido al total objetivo del grupo moviendo solo el último registro
     * (orden Posicion asc, NoTelarId asc): suma faltante (diff > 0) o recorte de exceso (diff < 0).
     *
     * @return array{nuevos_pedidos: array, advertencia_total: ?string, total_diferencia_vs_objetivo: ?float}
     */
    private static function ajustarPedidosAlTotalObjetivo(array $nuevosPedidos, array $registrosArray, float $totalObjetivo): array
    {
        $emptyMeta = [
            'nuevos_pedidos' => $nuevosPedidos,
            'advertencia_total' => null,
            'total_diferencia_vs_objetivo' => null,
        ];

        $totalObjetivo = (float) round($totalObjetivo);
        $totalActual = 0.0;

        foreach ($nuevosPedidos as $item) {
            $totalActual += (float) ($item['total_pedido'] ?? 0);
        }

        $diff = (float) round($totalObjetivo - $totalActual);
        if (abs($diff) < 1) {
            return $emptyMeta;
        }

        $candidatos = array_reverse($registrosArray);
        $ultimo = $candidatos[0] ?? null;
        if (! $ultimo) {
            return $emptyMeta;
        }

        if ($diff > 0) {
            $targetId = (int) $ultimo->Id;
            $actual = isset($nuevosPedidos[$targetId])
                ? (float) $nuevosPedidos[$targetId]['total_pedido']
                : (float) ($ultimo->TotalPedido ?? 0);

            $nuevosPedidos[$targetId] = [
                'id' => $targetId,
                'total_pedido' => (int) round($actual + $diff),
                'modo' => 'total',
            ];

            return [
                'nuevos_pedidos' => $nuevosPedidos,
                'advertencia_total' => null,
                'total_diferencia_vs_objetivo' => null,
            ];
        }

        // diff < 0: recortar solo en el último telar (misma regla que al sumar faltante).
        $restante = abs($diff);
        $id = (int) $ultimo->Id;
        $actual = isset($nuevosPedidos[$id])
            ? (float) $nuevosPedidos[$id]['total_pedido']
            : (float) ($ultimo->TotalPedido ?? 0);
        $minimo = max(1.0, (float) ($ultimo->Produccion ?? 0));
        $reducible = max(0.0, $actual - $minimo);
        $ajuste = min($restante, $reducible);

        $nuevosPedidos[$id] = [
            'id' => $id,
            'total_pedido' => (int) round($actual - $ajuste),
            'modo' => 'total',
        ];

        $sumaDespues = 0.0;
        foreach ($nuevosPedidos as $item) {
            $sumaDespues += (float) ($item['total_pedido'] ?? 0);
        }
        $desvio = (float) round($totalObjetivo - $sumaDespues);
        $advertencia = null;
        if (abs($desvio) >= 1) {
            $advertencia = 'El total del grupo no coincide exactamente con el objetivo: el recorte máximo en el último telar alcanzó el límite de producción mínima.';
        }

        return [
            'nuevos_pedidos' => $nuevosPedidos,
            'advertencia_total' => $advertencia,
            'total_diferencia_vs_objetivo' => abs($desvio) >= 1 ? $desvio : null,
        ];
    }

    /**
     * Recalcular fechas y fórmulas de un registro por cambios en Produccion/SaldoPedido.
     * EnProceso=1: usa now() como inicio y actualiza FechaInicio en BD.
     * Para usar cuando un proceso externo actualice Produccion/SaldoPedido vía SQL directo.
     */
    public static function recalcularRegistroPorProduccion(ReqProgramaTejido $registro): bool
    {
        if (empty($registro->FechaInicio)) {
            return false;
        }

        [$inicio, $fin, $horas] = self::calcularInicioFinExactos($registro);
        if (! $inicio || ! $fin) {
            return false;
        }

        $registro->FechaInicio = $inicio->format('Y-m-d H:i:s');
        $registro->FechaFinal = $fin->format('Y-m-d H:i:s');
        if ($horas > 0) {
            $registro->HorasProd = $horas;
        }

        $formulas = self::calcularFormulasEficiencia($registro);
        foreach ($formulas as $k => $v) {
            $registro->{$k} = $v;
        }

        $registro->saveQuietly();

        $observer = new ReqProgramaTejidoObserver;
        $observer->saved($registro->fresh());

        $ultimo = (int) ($registro->Ultimo ?? 0);
        if ($ultimo !== 1) {
            $registroRefreshed = ReqProgramaTejido::find($registro->Id);
            if ($registroRefreshed) {
                DateHelpers::cascadeFechas($registroRefreshed);
            }
        }

        return true;
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

            $cursor->addSeconds((int) $usar);
            $segundosRestantes -= (int) $usar;
        }

        return $cursor;
    }

    // =========================================================
    // Fórmulas EXACTAS
    // =========================================================
    /**
     * Calculate efficiency formulas for BalancearTejido operations.
     * Uses includePTvsCte=true because balanceo shows difference vs commitment.
     *
     * @param ReqProgramaTejido $programa
     * @return array
     */
    private static function calcularFormulasEficiencia(ReqProgramaTejido $programa): array
    {
        try {
            $m = TejidoHelpers::obtenerModeloParams($programa);

            return TejidoHelpers::calcularFormulasEficiencia($programa, $m, true, true, false);
        } catch (\InvalidArgumentException $e) {
            // Datos inválidos en parámetros (ej: tamanoClave vacío), no recuperable
            Log::error('BalancearTejido: Parámetros inválidos para fórmulas', [
                'error' => $e->getMessage(),
                'programa_id' => $programa->Id ?? null,
            ]);

            return [];
        } catch (\Throwable $e) {
            // Error inesperado en cálculo, log y continue sin fórmulas
            Log::warning('BalancearTejido: Error al calcular fórmulas', [
                'error' => $e->getMessage(),
                'programa_id' => $programa->Id ?? null,
            ]);

            return [];
        }
    }

    // =========================================================
    // Helpers de cache (solo performance, no cambia resultados)
    // =========================================================
    public static function clearCalendarioLinesCache(): void
    {
        self::$calLinesCache = [];
    }

    private static function warmCachesFromProgramas($programas): void
    {
        $calIds = [];
        foreach ($programas as $p) {
            $calId = trim((string) ($p->CalendarioId ?? ''));
            if ($calId !== '') {
                $calIds[] = $calId;
            }
        }
        self::warmCalendarios($calIds);
    }

    private static function warmCalendarios(array $calIds): void
    {
        $calIds = array_values(array_unique(array_filter(array_map(fn ($x) => trim((string) $x), $calIds))));
        if (empty($calIds)) {
            return;
        }

        $missing = [];
        foreach ($calIds as $id) {
            if ($id !== '' && ! isset(self::$calLinesCache[$id])) {
                $missing[] = $id;
                self::$calLinesCache[$id] = []; // inicializa para evitar doble carga
            }
        }
        if (empty($missing)) {
            return;
        }

        $rows = ReqCalendarioLine::query()
            ->whereIn('CalendarioId', $missing)
            ->orderBy('CalendarioId')
            ->orderBy('FechaInicio')
            ->get(['CalendarioId', 'FechaInicio', 'FechaFin']);

        foreach ($rows as $row) {
            $calId = trim((string) $row->CalendarioId);
            if ($calId === '') {
                continue;
            }

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
        $calendarioId = trim((string) $calendarioId);
        if ($calendarioId === '') {
            return [];
        }

        if (! isset(self::$calLinesCache[$calendarioId])) {
            self::warmCalendarios([$calendarioId]);
        }

        return self::$calLinesCache[$calendarioId] ?? [];
    }

    public static function balancearAutomatico(Request $request): JsonResponse
    {
        $request->validate([
            'ord_compartida' => 'required|integer',
            'fecha_fin_objetivo' => 'required|date',
            'cambios' => 'nullable|array',
            'cambios.*.id' => 'required_with:cambios|integer',
            'cambios.*.total_pedido' => 'required_with:cambios|numeric|min:0',
            'cambios.*.modo' => 'nullable|string|in:saldo,total',
            'total_objetivo' => 'nullable|numeric|min:0',
        ]);

        $ordCompartida = (int) $request->input('ord_compartida');
        $fechaFinObjetivo = Carbon::parse($request->input('fecha_fin_objetivo'))->endOfDay();
        $registros = ReqProgramaTejido::where('OrdCompartida', $ordCompartida)
            ->orderBy('Posicion', 'asc')
            ->orderBy('NoTelarId', 'asc')
            ->get();

        if ($registros->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontraron registros para esta orden compartida.',
            ], 422);
        }

        $conFechaInicio = $registros->filter(fn ($r) => ! empty($r->FechaInicio));
        if ($conFechaInicio->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Ningún registro tiene fecha de inicio asignada. No se puede calcular el balanceo.',
            ], 422);
        }

        $fechaInicioMasTemprana = $conFechaInicio->min('FechaInicio');
        if ($fechaInicioMasTemprana && Carbon::parse($fechaInicioMasTemprana)->gt($fechaFinObjetivo)) {
            return response()->json([
                'success' => false,
                'message' => 'La fecha objetivo es anterior a la fecha de inicio más temprana ('.Carbon::parse($fechaInicioMasTemprana)->format('d/m/Y').'). Seleccione una fecha posterior.',
            ], 422);
        }

        // La fecha objetivo (día calendario) no puede ser anterior al día del inicio más tardío del grupo.
        $fechaInicioMasTardia = $conFechaInicio->max('FechaInicio');
        $fechaObjetivoDia = Carbon::parse($request->input('fecha_fin_objetivo'))->startOfDay();
        $inicioTardioDia = Carbon::parse($fechaInicioMasTardia)->startOfDay();
        if ($fechaObjetivoDia->lt($inicioTardioDia)) {
            return response()->json([
                'success' => false,
                'message' => 'La fecha objetivo no puede ser anterior al inicio más tardío del grupo ('.$inicioTardioDia->format('d/m/Y').'). Hay un telar que inicia ese día o después.',
            ], 422);
        }

        self::warmCachesFromProgramas($registros);

        /** @var Collection<int, array<string, mixed>> $cambios */
        $cambios = collect($request->input('cambios', []))
            ->filter(fn ($cambio) => isset($cambio['id']))
            ->keyBy(fn ($cambio) => (int) $cambio['id']);

        $registrosArray = $registros->values()->all();
        $pedidosActuales = self::balancearAutomaticoBuildPedidosActuales($registrosArray, $cambios);

        $totalOriginal = (float) $request->input('total_objetivo', 0);
        if ($totalOriginal <= 0) {
            $totalOriginal = self::balancearAutomaticoSumPedidos($pedidosActuales);
        }

        $totalProduccion = $registros->sum(fn ($r) => (float) ($r->Produccion ?? 0));
        if ($totalProduccion >= $totalOriginal) {
            return response()->json([
                'success' => false,
                'message' => 'La producción acumulada ('.number_format($totalProduccion, 0, '.', ',').') ya alcanzó o superó el pedido total ('.number_format($totalOriginal, 0, '.', ',').'). No hay saldo pendiente para balancear.',
            ], 422);
        }

        $toleranciaSegundos = (int) (0.001 * 3600);
        $clasificacion = self::balancearAutomaticoClasificarRegistros(
            $registrosArray,
            $pedidosActuales,
            $fechaFinObjetivo,
            $toleranciaSegundos
        );

        $computo = self::balancearAutomaticoComputarNuevosPedidos(
            $clasificacion['cumplen'],
            $clasificacion['necesitan_ajuste'],
            $pedidosActuales,
            $fechaFinObjetivo
        );

        $nuevosPedidos = $computo['nuevos_pedidos'];
        $ajusteMeta = self::ajustarPedidosAlTotalObjetivo($nuevosPedidos, $registrosArray, $totalOriginal);
        $nuevosPedidos = $ajusteMeta['nuevos_pedidos'];

        return self::balancearAutomaticoJsonResponse(
            $nuevosPedidos,
            $computo['horas_disponibles_total'],
            $clasificacion['horas_necesarias_originales'],
            $ajusteMeta['advertencia_total'],
            $ajusteMeta['total_diferencia_vs_objetivo']
        );
    }

    /**
     * @param  array<int, ReqProgramaTejido>  $registrosArray
     * @param  Collection<int, array<string, mixed>>  $cambios
     * @return array<int, array{pedido: float, saldo: float}>
     */
    private static function balancearAutomaticoBuildPedidosActuales(array $registrosArray, Collection $cambios): array
    {
        $pedidosActuales = [];
        foreach ($registrosArray as $reg) {
            $produccion = (float) ($reg->Produccion ?? 0);
            $cambio = $cambios->get((int) $reg->Id);
            if ($cambio) {
                [$pedidoActual, $saldoActual] = self::resolverTotalSaldo(
                    (float) ($cambio['total_pedido'] ?? 0),
                    $produccion,
                    $cambio['modo'] ?? 'total'
                );
            } else {
                $pedidoActual = (float) ($reg->TotalPedido ?? 0);
                $saldoActual = max(0, $pedidoActual - $produccion);
            }
            $pedidosActuales[(int) $reg->Id] = [
                'pedido' => $pedidoActual,
                'saldo' => $saldoActual,
            ];
        }

        return $pedidosActuales;
    }

    /**
     * @param  array<int, array{pedido: float, saldo: float}>  $pedidosActuales
     */
    private static function balancearAutomaticoSumPedidos(array $pedidosActuales): float
    {
        $sum = 0.0;
        foreach ($pedidosActuales as $item) {
            $sum += (float) ($item['pedido'] ?? 0);
        }

        return $sum;
    }

    /**
     * @param  array<int, ReqProgramaTejido>  $registrosArray
     * @param  array<int, array{pedido: float, saldo: float}>  $pedidosActuales
     * @return array{
     *     cumplen: list<array{reg: ReqProgramaTejido, pedido: float}>,
     *     necesitan_ajuste: list<array{reg: ReqProgramaTejido}>,
     *     horas_necesarias_originales: float
     * }
     */
    private static function balancearAutomaticoClasificarRegistros(
        array $registrosArray,
        array $pedidosActuales,
        Carbon $fechaFinObjetivo,
        int $toleranciaSegundos
    ): array {
        $cumplen = [];
        $necesitanAjuste = [];
        $horasNecesariasOriginales = 0.0;
        $totalRegistros = count($registrosArray);

        foreach ($registrosArray as $indice => $reg) {
            $produccion = (float) ($reg->Produccion ?? 0);
            $pedidoActualData = $pedidosActuales[(int) $reg->Id] ?? null;
            $pedidoActual = (float) ($pedidoActualData['pedido'] ?? ($reg->TotalPedido ?? 0));
            $saldoOriginal = (float) ($pedidoActualData['saldo'] ?? max(0, $pedidoActual - $produccion));

            $regTemp = clone $reg;
            $regTemp->TotalPedido = $pedidoActual;
            $regTemp->SaldoPedido = $saldoOriginal;
            $horasNecesariasOriginales += TejidoHelpers::calcularHorasProd($regTemp);

            if (empty($reg->FechaInicio)) {
                $cumplen[] = ['reg' => $reg, 'pedido' => $pedidoActual];

                continue;
            }

            $esEnProceso = (bool) $reg->EnProceso;
            $fechaInicioReg = $esEnProceso ? Carbon::now() : Carbon::parse($reg->FechaInicio);
            if (! $esEnProceso && ! empty($reg->CalendarioId)) {
                $snap = self::snapInicioAlCalendario($reg->CalendarioId, $fechaInicioReg);
                if ($snap) {
                    $fechaInicioReg = $snap;
                }
            }

            $regTemp = clone $reg;
            $regTemp->TotalPedido = $pedidoActual;
            $regTemp->SaldoPedido = $saldoOriginal;
            $horasActual = TejidoHelpers::calcularHorasProd($regTemp);
            $fechaFinalActual = null;
            if ($horasActual > 0) {
                $fechaInicioTemp = $fechaInicioReg->copy();
                $fechaFinalActual = ! empty($reg->CalendarioId)
                    ? (self::calcularFechaFinalDesdeInicio($reg->CalendarioId, $fechaInicioTemp, $horasActual) ?: $fechaInicioTemp->copy()->addSeconds((int) round($horasActual * 3600)))
                    : $fechaInicioTemp->copy()->addSeconds((int) round($horasActual * 3600));
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

            // Todos los registros con FechaInicio pasan por búsqueda binaria.
            // ajustarPedidosAlTotalObjetivo (llamado después) siempre ajusta el ÚLTIMO.
            $necesitanAjuste[] = ['reg' => $reg];
        }

        return [
            'cumplen' => $cumplen,
            'necesitan_ajuste' => $necesitanAjuste,
            'horas_necesarias_originales' => $horasNecesariasOriginales,
        ];
    }

    /**
     * @param  list<array{reg: ReqProgramaTejido, pedido: float}>  $cumplen
     * @param  list<array{reg: ReqProgramaTejido}>  $necesitanAjuste
     * @param  array<int, array{pedido: float, saldo: float}>  $pedidosActuales
     * @return array{nuevos_pedidos: array<int, array{id: int, total_pedido: float|int, modo: string}>, horas_disponibles_total: float}
     */
    private static function balancearAutomaticoComputarNuevosPedidos(
        array $cumplen,
        array $necesitanAjuste,
        array $pedidosActuales,
        Carbon $fechaFinObjetivo
    ): array {
        $nuevosPedidos = [];
        foreach ($cumplen as $item) {
            $reg = $item['reg'];
            $nuevosPedidos[(int) $reg->Id] = [
                'id' => (int) $reg->Id,
                'total_pedido' => $item['pedido'],
                'modo' => 'total',
            ];
        }

        $horasDisponiblesTotal = 0.0;
        foreach ($necesitanAjuste as $item) {
            $reg = $item['reg'];
            $produccion = (float) ($reg->Produccion ?? 0);
            $pedidoActualData = $pedidosActuales[(int) $reg->Id] ?? null;
            $pedidoActual = (float) ($pedidoActualData['pedido'] ?? ($reg->TotalPedido ?? 0));
            $saldoActual = (float) ($pedidoActualData['saldo'] ?? max(0, $pedidoActual - $produccion));
            $esEnProceso = (bool) $reg->EnProceso;
            $fechaInicioReg = $esEnProceso ? Carbon::now() : Carbon::parse($reg->FechaInicio);
            if (! $esEnProceso && ! empty($reg->CalendarioId)) {
                $snap = self::snapInicioAlCalendario($reg->CalendarioId, $fechaInicioReg);
                if ($snap) {
                    $fechaInicioReg = $snap;
                }
            }
            $horasDispReg = self::calcularHorasDisponiblesHastaFecha(
                $reg->CalendarioId ?? null,
                $fechaInicioReg,
                $fechaFinObjetivo
            );
            $horasDisponiblesTotal += $horasDispReg;

            $saldoCalculado = self::calcularPedidoParaFechaObjetivo(
                $reg,
                $fechaInicioReg,
                $fechaFinObjetivo,
                $produccion,
                $saldoActual
            );

            $saldoFinal = max(0.0, floor($saldoCalculado));
            $pedidoFinal = $produccion + $saldoFinal;
            $nuevosPedidos[(int) $reg->Id] = [
                'id' => (int) $reg->Id,
                'total_pedido' => $pedidoFinal,
                'modo' => 'total',
            ];
        }

        return [
            'nuevos_pedidos' => $nuevosPedidos,
            'horas_disponibles_total' => $horasDisponiblesTotal,
        ];
    }

    /**
     * @param  array<int, array{id: int, total_pedido: float|int, modo: string}>  $nuevosPedidos
     */
    private static function balancearAutomaticoJsonResponse(
        array $nuevosPedidos,
        float $horasDisponiblesTotal,
        float $horasNecesariasOriginales,
        ?string $advertenciaTotal,
        ?float $totalDiferenciaVsObjetivo
    ): JsonResponse {
        $payload = [
            'success' => true,
            'message' => 'Balanceo calculado correctamente',
            'cambios' => array_values($nuevosPedidos),
            'horas_disponibles_sumadas' => $horasDisponiblesTotal,
            'horas_necesarias_originales' => $horasNecesariasOriginales,
        ];
        if ($advertenciaTotal !== null) {
            $payload['advertencia_total'] = $advertenciaTotal;
        }
        if ($totalDiferenciaVsObjetivo !== null) {
            $payload['total_diferencia_vs_objetivo'] = $totalDiferenciaVsObjetivo;
        }

        return response()->json($payload);
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
        $horasPrueba = TejidoHelpers::calcularHorasProd($regTemp);

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
            $saldoPrueba = ($minSaldo + $maxSaldo) / 2.0;

            // Calcular fecha final con este pedido
            $regTemp = clone $reg;
            $regTemp->TotalPedido = $produccion + $saldoPrueba;
            $regTemp->SaldoPedido = max(0, $saldoPrueba);
            $horas = TejidoHelpers::calcularHorasProd($regTemp);

            if ($horas <= 0) {
                $minSaldo = $saldoPrueba;

                continue;
            }

            // Calcular fecha final
            $fechaInicioTemp = $fechaInicio->copy();
            if (! empty($reg->CalendarioId)) {
                $snap = self::snapInicioAlCalendario($reg->CalendarioId, $fechaInicioTemp);
                if ($snap) {
                    $fechaInicioTemp = $snap;
                }
            }

            $fechaFinal = ! empty($reg->CalendarioId)
                ? (self::calcularFechaFinalDesdeInicio($reg->CalendarioId, $fechaInicioTemp, $horas) ?: $fechaInicioTemp->copy()->addSeconds((int) round($horas * 3600)))
                : $fechaInicioTemp->copy()->addSeconds((int) round($horas * 3600));

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
