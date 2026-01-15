<?php

namespace App\Http\Controllers\Planeacion\ProgramaTejido\helper;

use App\Models\Planeacion\ReqCalendarioLine;
use App\Models\Planeacion\ReqModelosCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Observers\ReqProgramaTejidoObserver;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DateHelpers
{
    /** Cache simple para no pegarle a ReqModelosCodificados por cada cálculo */
    private static array $totalModeloCache = [];

    public static function setSafeDate(ReqProgramaTejido $r, string $attr, $value): void
    {
        try {
            $r->{$attr} = Carbon::parse($value);
        } catch (\Throwable $e) {
            // silencioso
        }
    }

    /**
     * Recalcular fechas de una secuencia de registros usando CALENDARIO REAL
     * (igual que DuplicarTejido).
     *
     * Regla:
     * - Inicio = fin del anterior (snap al calendario si cae en gap)
     * - Fin = consumir horas (HorasProd) a través de líneas reales del calendario
     * - CambioHilo compara FibraRizo vs anterior
     * - EnProceso = 1 primer registro, 0 los demás (como tu versión)
     * - Ultimo = 1 último registro, 0 los demás
     *
     * @return array{0: array<int,array<string,mixed>>, 1: array<int,array<string,mixed>>}
     */
    public static function recalcularFechasSecuencia(
        Collection $registrosOrdenados,
        Carbon $inicioOriginal,
        bool $respetarInicioPrimerRegistro = false
    ): array
    {
        $updates  = [];
        $detalles = [];
        $now      = now();
        $n        = $registrosOrdenados->count();

        if ($n < 1) return [[], []];

        $cursor = $inicioOriginal->copy();

        foreach ($registrosOrdenados->values() as $i => $r) {
            /** @var ReqProgramaTejido $r */

            // 1) Inicio base = cursor
            $nuevoInicio = $cursor->copy();

            // snap al calendario si cae en gap
            if (!empty($r->CalendarioId) && !($respetarInicioPrimerRegistro && $i === 0)) {
                $nuevoInicio = self::snapInicioAlCalendario($r->CalendarioId, $nuevoInicio) ?? $nuevoInicio;
            }

            // 2) Horas necesarias (FUENTE DE VERDAD)
            $metricas = self::calcularMetricasBase($r);
            $horasNecesarias = (float)($metricas['HorasProdRaw'] ?? 0);

            // fallback: si no se pudieron calcular, usa HorasProd guardadas
            if ($horasNecesarias <= 0 && !empty($r->HorasProd)) {
                $horasNecesarias = (float)$r->HorasProd;
            }

            // 3) Fin con calendario real (o fallback continuo)
            $nuevoFin = $nuevoInicio->copy();

            if ($horasNecesarias > 0) {
                if (!empty($r->CalendarioId)) {
                    $finCalc = self::calcularFechaFinalDesdeInicio($r->CalendarioId, $nuevoInicio, $horasNecesarias);
                    if ($finCalc) {
                        $nuevoFin = $finCalc;
                    } else {
                        // sin líneas suficientes => continuo
                        $nuevoFin = $nuevoInicio->copy()->addSeconds((int)round($horasNecesarias * 3600));
                    }
                } else {
                    $nuevoFin = $nuevoInicio->copy()->addSeconds((int)round($horasNecesarias * 3600));
                }
            } else {
                // último fallback: conservar duración previa si existía, si no 30 días
                if (!empty($r->FechaInicio) && !empty($r->FechaFinal)) {
                    try {
                        $iniOld = Carbon::parse($r->FechaInicio);
                        $finOld = Carbon::parse($r->FechaFinal);
                        $dur = $iniOld->diff($finOld);
                        $nuevoFin = (clone $nuevoInicio)->add($dur);
                    } catch (\Throwable $e) {
                        $nuevoFin = $nuevoInicio->copy()->addDays(30);
                    }
                } else {
                    $nuevoFin = $nuevoInicio->copy()->addDays(30);
                }
            }

            // 4) CambioHilo
            $cambioHilo = '0';
            if ($i > 0) {
                $prev = $registrosOrdenados->values()[$i - 1];
                $fibraAct  = trim((string)$r->FibraRizo);
                $fibraPrev = trim((string)$prev->FibraRizo);
                $cambioHilo = ($fibraAct !== $fibraPrev) ? '1' : '0';
            }

            // 5) Fórmulas: calculadas con diffDias = (FechaFinal - FechaInicio)
            $tmp = clone $r;
            $tmp->FechaInicio = $nuevoInicio->format('Y-m-d H:i:s');
            $tmp->FechaFinal  = $nuevoFin->format('Y-m-d H:i:s');

            // Reusar StdToaHra / HorasProdRaw si ya lo calculamos
            $formulas = self::calcularFormulasEficiencia($tmp, $metricas);

            $updates[(int)$r->Id] = array_merge([
                'FechaInicio' => $tmp->FechaInicio,
                'FechaFinal'  => $tmp->FechaFinal,
                'EnProceso'   => $i === 0 ? 1 : 0,
                'Ultimo'      => $i === ($n - 1) ? '1' : '0',
                'CambioHilo'  => $cambioHilo,
                'UpdatedAt'   => $now,
            ], $formulas);

            $detalles[] = [
                'Id'               => (int)$r->Id,
                'NoTelar'          => $r->NoTelarId,
                'Posicion'         => $i,
                'FechaInicio_nueva'=> $updates[(int)$r->Id]['FechaInicio'],
                'FechaFinal_nueva' => $updates[(int)$r->Id]['FechaFinal'],
                'EnProceso_nuevo'  => $updates[(int)$r->Id]['EnProceso'],
                'Ultimo_nuevo'     => $updates[(int)$r->Id]['Ultimo'],
                'CambioHilo_nuevo' => $cambioHilo,
                'CalendarioId'     => $r->CalendarioId ?? null,
                'HorasProd_calc'   => $updates[(int)$r->Id]['HorasProd'] ?? null,
            ];

            // siguiente inicio
            $cursor = $nuevoFin->copy();
        }

        return [$updates, $detalles];
    }

    /**
     * Cascade: recalcula registros posteriores al actualizado,
     * usando la misma lógica de calendario real.
     */
    public static function cascadeFechas(ReqProgramaTejido $registroActualizado)
    {
        $dispatcher = ReqProgramaTejido::getEventDispatcher();

        DB::beginTransaction();
        try {
            $salon = $registroActualizado->SalonTejidoId;
            $telar = $registroActualizado->NoTelarId;

            $finActual = Carbon::parse($registroActualizado->FechaFinal);

            $todos = ReqProgramaTejido::query()
                ->salon($salon)
                ->telar($telar)
                ->orderBy('FechaInicio', 'asc')
                ->lockForUpdate()
                ->get()
                ->values();

            $idx = $todos->search(fn($r) => $r->Id === $registroActualizado->Id);
            if ($idx === false) {
                DB::commit();
                return [];
            }

            $detalles = [];
            $idsActualizados = [];

            // deshabilitar eventos
            ReqProgramaTejido::unsetEventDispatcher();

            $cursor = $finActual->copy();

            // Fibra para CambioHilo: parte desde el registro actualizado
            $fibraPrev = trim((string)$registroActualizado->FibraRizo);

            for ($i = $idx + 1; $i < $todos->count(); $i++) {
                /** @var ReqProgramaTejido $row */
                $row = $todos[$i];

                // inicio
                $nuevoInicio = $cursor->copy();
                if (!empty($row->CalendarioId)) {
                    $nuevoInicio = self::snapInicioAlCalendario($row->CalendarioId, $nuevoInicio) ?? $nuevoInicio;
                }

                // horas
                $metricas = self::calcularMetricasBase($row);
                $horasNecesarias = (float)($metricas['HorasProdRaw'] ?? 0);
                if ($horasNecesarias <= 0 && !empty($row->HorasProd)) {
                    $horasNecesarias = (float)$row->HorasProd;
                }

                // fin
                $nuevoFin = $nuevoInicio->copy();
                if ($horasNecesarias > 0) {
                    if (!empty($row->CalendarioId)) {
                        $finCalc = self::calcularFechaFinalDesdeInicio($row->CalendarioId, $nuevoInicio, $horasNecesarias);
                        $nuevoFin = $finCalc ?: $nuevoInicio->copy()->addSeconds((int)round($horasNecesarias * 3600));
                    } else {
                        $nuevoFin = $nuevoInicio->copy()->addSeconds((int)round($horasNecesarias * 3600));
                    }
                } else {
                    // fallback: conservar duración previa si existía
                    if (!empty($row->FechaInicio) && !empty($row->FechaFinal)) {
                        try {
                            $iniOld = Carbon::parse($row->FechaInicio);
                            $finOld = Carbon::parse($row->FechaFinal);
                            $dur = $iniOld->diff($finOld);
                            $nuevoFin = (clone $nuevoInicio)->add($dur);
                        } catch (\Throwable $e) {
                            $nuevoFin = $nuevoInicio->copy()->addDays(30);
                        }
                    } else {
                        $nuevoFin = $nuevoInicio->copy()->addDays(30);
                    }
                }

                // CambioHilo vs previo
                $fibraAct = trim((string)$row->FibraRizo);
                $cambioHilo = ($fibraAct !== $fibraPrev) ? '1' : '0';
                $fibraPrev = $fibraAct;

                // fórmulas
                $tmp = clone $row;
                $tmp->FechaInicio = $nuevoInicio->format('Y-m-d H:i:s');
                $tmp->FechaFinal  = $nuevoFin->format('Y-m-d H:i:s');

                $formulas = self::calcularFormulasEficiencia($tmp, $metricas);

                // Ultimo flag
                $ultimo = ($i === ($todos->count() - 1)) ? '1' : '0';

                DB::table('ReqProgramaTejido')->where('Id', $row->Id)->update(array_merge([
                    'FechaInicio' => $tmp->FechaInicio,
                    'FechaFinal'  => $tmp->FechaFinal,
                    'EnProceso'   => 0,
                    'Ultimo'      => $ultimo,
                    'CambioHilo'  => $cambioHilo,
                    'UpdatedAt'   => now(),
                ], $formulas));

                $idsActualizados[] = (int)$row->Id;

                $detalles[] = [
                    'Id'                => (int)$row->Id,
                    'NoTelar'           => $row->NoTelarId,
                    'FechaInicio_nueva' => $tmp->FechaInicio,
                    'FechaFinal_nueva'  => $tmp->FechaFinal,
                    'CambioHilo_nuevo'  => $cambioHilo,
                    'Ultimo_nuevo'      => $ultimo,
                    'HorasProd_calc'    => $formulas['HorasProd'] ?? null,
                ];

                $cursor = $nuevoFin->copy();
            }

            DB::commit();

            // restaurar dispatcher
            ReqProgramaTejido::setEventDispatcher($dispatcher);

            // regenerar líneas en batch (evita N+1)
            if (!empty($idsActualizados)) {
                $observer = new ReqProgramaTejidoObserver();
                $modelos = ReqProgramaTejido::whereIn('Id', $idsActualizados)->get();
                foreach ($modelos as $m) {
                    $observer->saved($m);
                }
            }

            return $detalles;

        } catch (\Throwable $e) {
            DB::rollBack();
            if ($dispatcher) {
                ReqProgramaTejido::setEventDispatcher($dispatcher);
            }

            Log::error('cascadeFechas error', [
                'id'    => $registroActualizado->Id ?? null,
                'msg'   => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /* =========================================================
     *  CALENDARIO (igual que DuplicarTejido)
     * =======================================================*/

    public static function snapInicioAlCalendario(string $calendarioId, Carbon $fechaInicio): ?Carbon
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

                // gap
                if ($cursor->lt($ini)) {
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

                if ($cursor->gte($fin)) {
                    $cursor = $fin->copy();
                }
            }

            $ultimaFin = Carbon::parse($lineas->last()->FechaFin);
            if ($cursor->lt($ultimaFin)) {
                $cursor = $ultimaFin->copy();
            }
        }

        return $cursor;
    }

    /* =========================================================
     *  MÉTRICAS / FÓRMULAS (compatibles con DuplicarTejido)
     * =======================================================*/

    /**
     * Calcula StdToaHra y HorasProdRaw sin depender de fechas.
     * (Esto es lo que debe gobernar la duración real).
     */
    private static function calcularMetricasBase(ReqProgramaTejido $p): array
    {
        $vel   = (float)($p->VelocidadSTD ?? 0);
        $efic  = (float)($p->EficienciaSTD ?? 0);
        $cant  = self::sanitizeNumber($p->SaldoPedido ?? $p->Produccion ?? $p->TotalPedido ?? 0);

        $noTiras = (float)($p->NoTiras ?? 0);
        $luchaje = (float)($p->Luchaje ?? 0);
        $rep     = (float)($p->Repeticiones ?? 0);

        if ($efic > 1) $efic = $efic / 100;

        $total = self::obtenerTotalModelo($p->TamanoClave ?? null);

        $stdToaHra = 0.0;
        if ($noTiras > 0 && $total > 0 && $luchaje > 0 && $rep > 0 && $vel > 0) {
            $parte1 = $total;
            $parte2 = (($luchaje * 0.5) / 0.0254) / $rep;
            $den = ($parte1 + $parte2) / $vel;
            if ($den > 0) {
                $stdToaHra = ($noTiras * 60) / $den;
            }
        }

        $horasProdRaw = 0.0;
        if ($stdToaHra > 0 && $efic > 0 && $cant > 0) {
            $horasProdRaw = $cant / ($stdToaHra * $efic);
        }

        return [
            'StdToaHra'    => $stdToaHra,
            'HorasProdRaw' => $horasProdRaw,
            'Efic'         => $efic,
            'Cant'         => $cant,
            'TotalModelo'  => $total,
        ];
    }

    /**
     * Fórmulas: diffDias = (FechaFinal - FechaInicio) como en tu Duplicar.
     * Puedes pasar $metricasBase para evitar recálculo.
     */
    private static function calcularFormulasEficiencia(ReqProgramaTejido $programa, ?array $metricasBase = null): array
    {
        $formulas = [];

        try {
            $metricasBase = $metricasBase ?: self::calcularMetricasBase($programa);

            $stdToaHra = (float)($metricasBase['StdToaHra'] ?? 0);
            $horasProdRaw = (float)($metricasBase['HorasProdRaw'] ?? 0);
            $efic = (float)($metricasBase['Efic'] ?? 0);
            $cantidad = (float)($metricasBase['Cant'] ?? self::sanitizeNumber($programa->SaldoPedido ?? $programa->Produccion ?? $programa->TotalPedido ?? 0));

            $pesoCrudo = (float)($programa->PesoCrudo ?? 0);

            $inicio = Carbon::parse($programa->FechaInicio);
            $fin    = Carbon::parse($programa->FechaFinal);
            $diffSeg  = abs($fin->getTimestamp() - $inicio->getTimestamp());
            $diffDias = $diffSeg / 86400;

            if ($stdToaHra > 0) {
                $formulas['StdToaHra'] = (float)round($stdToaHra, 2);
            }

            // PesoGRM2
            $largoToalla = (float)($programa->LargoToalla ?? 0);
            $anchoToalla = (float)($programa->AnchoToalla ?? 0);
            if ($pesoCrudo > 0 && $largoToalla > 0 && $anchoToalla > 0) {
                $formulas['PesoGRM2'] = (float)round(($pesoCrudo * 10000) / ($largoToalla * $anchoToalla), 2);
            }

            // DiasEficiencia
            if ($diffDias > 0) {
                $formulas['DiasEficiencia'] = (float)round($diffDias, 2);
            }

            // StdDia / ProdKgDia
            if ($stdToaHra > 0 && $efic > 0) {
                $stdDia = $stdToaHra * $efic * 24;
                $formulas['StdDia'] = (float)round($stdDia, 2);

                if ($pesoCrudo > 0) {
                    $formulas['ProdKgDia'] = (float)round(($stdDia * $pesoCrudo) / 1000, 2);
                }
            }

            // StdHrsEfect / ProdKgDia2
            if ($diffDias > 0) {
                $stdHrsEfect = ($cantidad / $diffDias) / 24;
                $formulas['StdHrsEfect'] = (float)round($stdHrsEfect, 2);

                if ($pesoCrudo > 0) {
                    $formulas['ProdKgDia2'] = (float)round((($pesoCrudo * $stdHrsEfect) * 24) / 1000, 2);
                }
            }

            // HorasProd / DiasJornada (usa horasProdRaw)
            if ($horasProdRaw > 0) {
                $formulas['HorasProd'] = (float)round($horasProdRaw, 2);
                $formulas['DiasJornada'] = (float)round($horasProdRaw / 24, 2);
            }

            // EntregaCte = FechaFinal + 12/16 dias
            $diasEntrega = TejidoHelpers::resolverDiasEntrega($programa);
            $entregaCteCalculada = null;
            $entregaPT = null;
            if (!empty($programa->FechaFinal)) {
                try {
                    $fechaFinal = Carbon::parse($programa->FechaFinal);
                    $entregaCteCalculada = $fechaFinal->copy()->addDays($diasEntrega);
                    $formulas['EntregaCte'] = $entregaCteCalculada->format('Y-m-d H:i:s');
                    $entregaPT = $fechaFinal->copy()->day(15);
                    $formulas['EntregaPT'] = $entregaPT->format('Y-m-d');
                } catch (\Throwable $e) {
                    // Si hay error al parsear, no establecer EntregaCte
                }
            }

            // PTvsCte = EntregaCte - EntregaPT (diferencia en días)
            if (!$entregaPT && !empty($programa->EntregaPT)) {
                try {
                    $entregaPT = Carbon::parse($programa->EntregaPT);
                } catch (\Throwable $e) {
                    $entregaPT = null;
                }
            }

            if ($entregaPT) {
                $formulas['EntregaProduc'] = $entregaPT->copy()->subDays($diasEntrega)->format('Y-m-d');
            }

            if ($entregaPT) {
                // Usar EntregaCte calculada si existe, sino usar la del programa si existe
                $entregaCteParaCalcular = $entregaCteCalculada
                    ?: (!empty($programa->EntregaCte) ? Carbon::parse($programa->EntregaCte) : null);

                if ($entregaCteParaCalcular) {
                    $diferenciaDias = $entregaCteParaCalcular->diffInDays($entregaPT, false);
                    $formulas['PTvsCte'] = (float)round($diferenciaDias, 2);
                }
            }

        } catch (\Throwable $e) {
            Log::warning('DateHelpers: Error al calcular fórmulas', [
                'error' => $e->getMessage(),
                'programa_id' => $programa->Id ?? null,
            ]);
        }

        return $formulas;
    }

    private static function obtenerTotalModelo(?string $tamanoClave): float
    {
        $key = trim((string)$tamanoClave);
        if ($key === '') return 0.0;

        if (isset(self::$totalModeloCache[$key])) {
            return self::$totalModeloCache[$key];
        }

        $modelo = ReqModelosCodificados::where('TamanoClave', $key)->first();
        $total = $modelo ? (float)($modelo->Total ?? 0) : 0.0;

        self::$totalModeloCache[$key] = $total;
        return $total;
    }

    private static function sanitizeNumber($value): float
    {
        if ($value === null) return 0.0;
        if (is_numeric($value)) return (float)$value;

        $clean = str_replace([',', ' '], '', (string)$value);
        return is_numeric($clean) ? (float)$clean : 0.0;
    }
}
