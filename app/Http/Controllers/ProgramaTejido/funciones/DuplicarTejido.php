<?php

namespace App\Http\Controllers\ProgramaTejido\funciones;

use App\Helpers\StringTruncator;
use App\Models\ReqCalendarioLine;
use App\Models\ReqModelosCodificados;
use App\Models\ReqProgramaTejido;
use App\Observers\ReqProgramaTejidoObserver;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB as DBFacade;
use Illuminate\Support\Facades\Log as LogFacade;

class DuplicarTejido
{
    /** Cache simple para no pegarle a ReqModelosCodificados por cada cálculo */
    private static array $totalModeloCache = [];

    public static function duplicar(Request $request)
    {
        $data = $request->validate([
            'salon_tejido_id' => 'required|string',
            'no_telar_id'     => 'required|string',
            'destinos'        => 'required|array|min:1',
            'destinos.*.telar'  => 'required|string',
            'destinos.*.pedido' => 'nullable|string',
            'destinos.*.observaciones' => 'nullable|string|max:500',
            'destinos.*.porcentaje_segundos' => 'nullable|numeric|min:0',

            'tamano_clave'   => 'nullable|string|max:100',
            'invent_size_id' => 'nullable|string|max:100',
            'cod_articulo'   => 'nullable|string|max:100',
            'producto'       => 'nullable|string|max:255',
            'custname'       => 'nullable|string|max:255',

            'salon_destino' => 'nullable|string',
            'hilo'          => 'nullable|string',
            'pedido'        => 'nullable|string',
            'flog'          => 'nullable|string',
            'aplicacion'    => 'nullable|string',
            'descripcion'   => 'nullable|string',
        ]);

        $salonOrigen  = $data['salon_tejido_id'];
        $telarOrigen  = $data['no_telar_id'];
        $salonDestino = $data['salon_destino'] ?? $salonOrigen;

        $destinos     = $data['destinos'];

        $pedidoGlobal   = self::sanitizeNumber($data['pedido'] ?? null);
        $inventSizeId   = $data['invent_size_id'] ?? null;
        $tamanoClave    = $data['tamano_clave'] ?? null;
        $codArticulo    = $data['cod_articulo'] ?? null;
        $producto       = $data['producto'] ?? null;
        $custname       = $data['custname'] ?? null;
        $hilo           = $data['hilo'] ?? null;
        $flog           = $data['flog'] ?? null;
        $aplicacion     = $data['aplicacion'] ?? null;
        $descripcion    = $data['descripcion'] ?? null;

        // Guardar y restaurar dispatcher para no romper otros flujos
        $dispatcher = ReqProgramaTejido::getEventDispatcher();
        ReqProgramaTejido::unsetEventDispatcher();

        DBFacade::beginTransaction();

        try {
            $original = self::obtenerUltimoRegistroTelar($salonOrigen, $telarOrigen);

            if (!$original) {
                DBFacade::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron registros para duplicar',
                ], 404);
            }

            $idsParaObserver = [];
            $totalDuplicados = 0;

            foreach ($destinos as $destino) {
                $telarDestino = $destino['telar'];
                $pedidoDestinoRaw = $destino['pedido'] ?? null;
                $observacionesDestino = $destino['observaciones'] ?? null;
                $porcentajeSegundosDestino = isset($destino['porcentaje_segundos']) && $destino['porcentaje_segundos'] !== null && $destino['porcentaje_segundos'] !== ''
                    ? (float)$destino['porcentaje_segundos']
                    : null;

                $ultimoDestino = ReqProgramaTejido::query()
                    ->salon($salonDestino)
                    ->telar($telarDestino)
                    ->orderBy('FechaInicio', 'desc')
                    ->first();

                if ($ultimoDestino && (int)$ultimoDestino->Ultimo === 1) {
                    ReqProgramaTejido::where('Id', $ultimoDestino->Id)->update(['Ultimo' => 0]);
                }

                // FechaInicio = EXACTAMENTE FechaFinal del último programa del telar destino
                $fechaInicioBase = $ultimoDestino && $ultimoDestino->FechaFinal
                    ? Carbon::parse($ultimoDestino->FechaFinal)
                    : ($original->FechaInicio ? Carbon::parse($original->FechaInicio) : Carbon::now());

                $nuevo = $original->replicate();

                // ===== Básicos =====
                $nuevo->SalonTejidoId = $salonDestino;
                $nuevo->NoTelarId     = $telarDestino;
                $nuevo->EnProceso     = 0;
                $nuevo->Ultimo        = 1;
                $nuevo->CambioHilo    = 0;

                $nuevo->Maquina = self::construirMaquina($original->Maquina ?? null, $salonDestino, $telarDestino);

                // Limpiar campos
                $nuevo->Produccion    = null;
                $nuevo->Programado    = null;
                $nuevo->NoProduccion  = null;
                $nuevo->OrdCompartida = null;

                $nuevo->ProgramarProd = Carbon::now()->format('Y-m-d');

                // ===== Overrides =====
                if ($inventSizeId) $nuevo->InventSizeId = $inventSizeId;
                if ($tamanoClave)  $nuevo->TamanoClave  = $tamanoClave;
                if ($codArticulo)  $nuevo->ItemId       = $codArticulo;
                if ($producto)     $nuevo->NombreProducto = StringTruncator::truncate('NombreProducto', $producto);
                if ($custname)     $nuevo->CustName = StringTruncator::truncate('CustName', $custname);
                if ($hilo)         $nuevo->FibraRizo = $hilo;
                if ($flog)         $nuevo->FlogsId = $flog;
                if ($aplicacion)   $nuevo->AplicacionId = StringTruncator::truncate('AplicacionId', $aplicacion);
                if ($descripcion)  $nuevo->NombreProyecto = StringTruncator::truncate('NombreProyecto', $descripcion);

                // ===== Observaciones y PorcentajeSegundos =====
                if ($observacionesDestino !== null && $observacionesDestino !== '') {
                    $nuevo->Observaciones = StringTruncator::truncate('Observaciones', $observacionesDestino);
                }
                if ($porcentajeSegundosDestino !== null) {
                    $nuevo->PorcentajeSegundos = $porcentajeSegundosDestino;
                }

                // ===== TotalPedido / SaldoPedido =====
                $pedidoDestino = ($pedidoDestinoRaw !== null && $pedidoDestinoRaw !== '') ? self::sanitizeNumber($pedidoDestinoRaw) : null;

                if ($pedidoDestino !== null) {
                    $nuevo->TotalPedido = $pedidoDestino;
                } elseif (!empty($pedidoGlobal)) {
                    $nuevo->TotalPedido = $pedidoGlobal;
                } elseif (!empty($original->TotalPedido)) {
                    $nuevo->TotalPedido = self::sanitizeNumber($original->TotalPedido);
                } elseif (!empty($original->SaldoPedido)) {
                    $nuevo->TotalPedido = self::sanitizeNumber($original->SaldoPedido);
                } else {
                    $nuevo->TotalPedido = 0;
                }

                $nuevo->SaldoPedido = $nuevo->TotalPedido;

                // ===== FechaInicio (snap al calendario si cae en gap) =====
                $inicio = $fechaInicioBase->copy();
                if (!empty($nuevo->CalendarioId)) {
                    $inicio = self::snapInicioAlCalendario($nuevo->CalendarioId, $inicio) ?? $inicio;
                }
                $nuevo->FechaInicio = $inicio->format('Y-m-d H:i:s');

                // ===== HORAS a programar (FUENTE DE VERDAD) =====
                // Nota: esto NO depende de FechaFinal.
                $horasNecesarias = self::calcularHorasProd($nuevo);

                // Fallbacks si faltan datos del modelo
                if ($horasNecesarias <= 0 && !empty($original->HorasProd)) {
                    $cantOrig = self::sanitizeNumber($original->SaldoPedido ?? $original->TotalPedido ?? 0);
                    $cantNew  = self::sanitizeNumber($nuevo->SaldoPedido ?? $nuevo->TotalPedido ?? 0);
                    if ($cantOrig > 0 && $cantNew > 0) {
                        $horasNecesarias = (float)$original->HorasProd * ($cantNew / $cantOrig);
                    }
                }

                if ($horasNecesarias <= 0) {
                    // último fallback: 30 días
                    $nuevo->FechaFinal = $inicio->copy()->addDays(30)->format('Y-m-d H:i:s');
                } else {
                    // ===== FechaFinal desde calendario por DURACIÓN REAL de líneas =====
                    if (!empty($nuevo->CalendarioId)) {
                        $fin = self::calcularFechaFinalDesdeInicio($nuevo->CalendarioId, $inicio, $horasNecesarias);

                if (!$fin) {
                            // si no hay líneas suficientes, fallback continuo
                            LogFacade::warning('DuplicarTejido: No se pudo calcular fecha final con calendario (sin líneas suficientes). Fallback continuo.', [
                                'calendario_id' => $nuevo->CalendarioId,
                                'fecha_inicio'  => $inicio->format('Y-m-d H:i:s'),
                                'horas'         => $horasNecesarias,
                            ]);
                            $fin = $inicio->copy()->addSeconds((int)($horasNecesarias * 3600));
                }

                $nuevo->FechaFinal = $fin->format('Y-m-d H:i:s');
                    } else {
                        // sin calendario, continuo
                        $nuevo->FechaFinal = $inicio->copy()->addSeconds((int)($horasNecesarias * 3600))->format('Y-m-d H:i:s');
                    }
                }

                // ===== CambioHilo =====
                if ($ultimoDestino) {
                    $nuevo->CambioHilo = (trim((string)$nuevo->FibraRizo) !== trim((string)$ultimoDestino->FibraRizo)) ? '1' : '0';
                }

                // ===== Fórmulas (diffDias se queda como FechaFinal-FechaInicio) =====
                $formulas = self::calcularFormulasEficiencia($nuevo);
                foreach ($formulas as $campo => $valor) {
                    $nuevo->{$campo} = $valor;
                }

                // (opcional) log para verificar el bug original:
                LogFacade::info('DuplicarTejido: resumen fechas/horas', [
                    'telar_destino' => $telarDestino,
                    'calendario_id' => $nuevo->CalendarioId ?? null,
                    'fecha_inicio'  => $nuevo->FechaInicio,
                    'fecha_final'   => $nuevo->FechaFinal,
                    'dias_ef'       => $nuevo->DiasEficiencia ?? null,
                    'horas_prod'    => $nuevo->HorasProd ?? null,
                    'dias_jornada'  => $nuevo->DiasJornada ?? null,
                ]);

                // Truncar strings (FlogsId se asigna directamente y no necesita truncamiento)
                foreach ([
                    'Maquina','NombreProyecto','CustName','AplicacionId','NombreProducto',
                    'TipoPedido','Observaciones','FibraTrama','FibraComb1','FibraComb2',
                    'FibraComb3','FibraComb4','FibraComb5','FibraPie','SalonTejidoId','NoTelarId',
                    'Rasurado','TamanoClave'
                ] as $campoStr) {
                    if (isset($nuevo->{$campoStr})) {
                        $nuevo->{$campoStr} = StringTruncator::truncate($campoStr, $nuevo->{$campoStr});
                    }
                }

                $nuevo->CreatedAt = now();
                $nuevo->UpdatedAt = now();
                $nuevo->save();

                $idsParaObserver[] = $nuevo->Id;
                $totalDuplicados++;
            }

            DBFacade::commit();

            // Restaurar dispatcher y observer
            if ($dispatcher) {
            ReqProgramaTejido::setEventDispatcher($dispatcher);
            }
            ReqProgramaTejido::observe(ReqProgramaTejidoObserver::class);

            // Disparar observer manualmente
            $observer = new ReqProgramaTejidoObserver();
            foreach ($idsParaObserver as $id) {
                if ($registro = ReqProgramaTejido::find($id)) {
                    $observer->saved($registro);
                }
            }

            // asegurarse
                ReqProgramaTejido::whereIn('Id', $idsParaObserver)->update(['EnProceso' => 0]);

            $primer = !empty($idsParaObserver) ? ReqProgramaTejido::find($idsParaObserver[0]) : null;

            return response()->json([
                'success' => true,
                'message' => "Telar duplicado correctamente. Se crearon {$totalDuplicados} registro(s) en " . count($destinos) . " telar(es).",
                'registros_duplicados' => $totalDuplicados,
                'registro_id' => $primer?->Id,
                'salon_destino' => $primer?->SalonTejidoId,
                'telar_destino' => $primer?->NoTelarId,
            ]);

        } catch (\Throwable $e) {
            DBFacade::rollBack();

            if ($dispatcher) {
            ReqProgramaTejido::setEventDispatcher($dispatcher);
            }
            ReqProgramaTejido::observe(ReqProgramaTejidoObserver::class);

            LogFacade::error('DuplicarTejido error', [
                'salon' => $salonOrigen,
                'telar' => $telarOrigen,
                'msg'   => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al duplicar el telar: ' . $e->getMessage(),
            ], 500);
        }
    }

    private static function obtenerUltimoRegistroTelar(string $salon, string $telar): ?ReqProgramaTejido
    {
        return ReqProgramaTejido::query()
            ->salon($salon)
            ->telar($telar)
            ->where('Ultimo', 1)
            ->orderBy('FechaInicio', 'desc')
            ->first()
            ?? ReqProgramaTejido::query()
                ->salon($salon)
                ->telar($telar)
                ->orderBy('FechaInicio', 'desc')
                ->first();
    }

    /**
     * Si la fecha cae en un "gap" (día no trabajado / sin líneas),
     * brinca al inicio de la siguiente línea disponible.
     */
    private static function snapInicioAlCalendario(string $calendarioId, Carbon $fechaInicio): ?Carbon
    {
        $linea = ReqCalendarioLine::where('CalendarioId', $calendarioId)
            ->where('FechaFin', '>', $fechaInicio) // incluye línea que lo contiene o la siguiente
            ->orderBy('FechaInicio')
            ->first();

        if (!$linea) {
            return null;
        }

        $ini = Carbon::parse($linea->FechaInicio);
        $fin = Carbon::parse($linea->FechaFin);

        // Si ya está dentro, no mover
        if ($fechaInicio->gte($ini) && $fechaInicio->lt($fin)) {
            return $fechaInicio->copy();
        }

        // Si estaba en gap, brincar al inicio de la línea
        return $ini->copy();
    }

    /**
     * FechaFinal recorriendo líneas reales (FechaInicio/FechaFin).
     * No usa HorasTurno: usa la DURACIÓN REAL de cada línea.
     */
    public static function calcularFechaFinalDesdeInicio(string $calendarioId, Carbon $fechaInicio, float $horasNecesarias): ?Carbon
    {
        $segundosRestantes = (int) (max(0, $horasNecesarias) * 3600);
        if ($segundosRestantes <= 0) {
            return $fechaInicio->copy();
        }

        $cursor = $fechaInicio->copy();

        // Procesar en batches por si el calendario es grande
        while ($segundosRestantes > 0) {
            $lineas = ReqCalendarioLine::where('CalendarioId', $calendarioId)
                ->where('FechaFin', '>', $cursor) // trae desde donde estamos
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

                // Gap: brincar (esto mete días no trabajados automáticamente)
                if ($cursor->lt($ini)) {
                    $cursor = $ini->copy();
                }

                // Si ya pasó esta línea
                if ($cursor->gte($fin)) {
                continue;
            }

                // Segundos disponibles reales en esta línea
                $disponibles = $cursor->diffInSeconds($fin, true);
                if ($disponibles <= 0) {
                    $cursor = $fin->copy();
                continue;
            }

                $usar = min($disponibles, $segundosRestantes);
                $cursor->addSeconds($usar);
                $segundosRestantes -= $usar;

                if ($segundosRestantes <= 0) {
                    return $cursor;
                }

                // si consumimos toda la línea, cursor queda en fin y seguirá a la siguiente
                if ($cursor->gte($fin)) {
                    $cursor = $fin->copy();
                }
            }

            // evitar loop pegado si el batch ya no avanza
            $ultimaFin = Carbon::parse($lineas->last()->FechaFin);
            if ($cursor->lt($ultimaFin)) {
                $cursor = $ultimaFin->copy();
            }
        }

        return $cursor;
    }

    /**
     * HorasProd (NO depende de fechas). Esto debe ser la base para FechaFinal.
     */
    private static function calcularHorasProd(ReqProgramaTejido $p): float
    {
        $vel   = (float) ($p->VelocidadSTD ?? 0);
        $efic  = (float) ($p->EficienciaSTD ?? 0);
        $cant  = self::sanitizeNumber($p->SaldoPedido ?? $p->Produccion ?? $p->TotalPedido ?? 0);
        $noTiras = (float) ($p->NoTiras ?? 0);
        $luchaje = (float) ($p->Luchaje ?? 0);
        $rep     = (float) ($p->Repeticiones ?? 0);

        if ($efic > 1) $efic = $efic / 100;

        $total = self::obtenerTotalModelo($p->TamanoClave ?? null);

        // StdToaHra igual a tu fórmula
        $stdToaHra = 0.0;
        if ($noTiras > 0 && $total > 0 && $luchaje > 0 && $rep > 0 && $vel > 0) {
            $parte1 = $total;
            $parte2 = (($luchaje * 0.5) / 0.0254) / $rep;
            $den = ($parte1 + $parte2) / $vel;
            if ($den > 0) {
                $stdToaHra = ($noTiras * 60) / $den;
            }
        }

        if ($stdToaHra > 0 && $efic > 0 && $cant > 0) {
            return $cant / ($stdToaHra * $efic);
        }

        return 0.0;
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

    /**
     * Fórmulas: DEJAMOS diffDias = (FechaFinal - FechaInicio) como tú quieres.
     */
    public static function calcularFormulasEficiencia(ReqProgramaTejido $programa): array
    {
        $formulas = [];

        try {
            $vel = (float) ($programa->VelocidadSTD ?? 0);
            $efic = (float) ($programa->EficienciaSTD ?? 0);
            $cantidad = self::sanitizeNumber($programa->SaldoPedido ?? $programa->Produccion ?? $programa->TotalPedido ?? 0);
            $pesoCrudo = (float) ($programa->PesoCrudo ?? 0);
            $noTiras = (float) ($programa->NoTiras ?? 0);
            $luchaje = (float) ($programa->Luchaje ?? 0);
            $repeticiones = (float) ($programa->Repeticiones ?? 0);

            if ($efic > 1) $efic = $efic / 100;

            $total = self::obtenerTotalModelo($programa->TamanoClave ?? null);

            $inicio = Carbon::parse($programa->FechaInicio);
            $fin    = Carbon::parse($programa->FechaFinal);
            $diffSeg = abs($fin->getTimestamp() - $inicio->getTimestamp());
            $diffDias = $diffSeg / 86400;

            // StdToaHra
            $stdToaHra = 0;
            if ($noTiras > 0 && $total > 0 && $luchaje > 0 && $repeticiones > 0 && $vel > 0) {
                $parte1 = $total;
                $parte2 = (($luchaje * 0.5) / 0.0254) / $repeticiones;
                $den = ($parte1 + $parte2) / $vel;
                if ($den > 0) {
                    $stdToaHra = ($noTiras * 60) / $den;
                    $formulas['StdToaHra'] = (float) round($stdToaHra, 2);
                }
            }

            // PesoGRM2
            $largoToalla = (float) ($programa->LargoToalla ?? 0);
            $anchoToalla = (float) ($programa->AnchoToalla ?? 0);
            if ($pesoCrudo > 0 && $largoToalla > 0 && $anchoToalla > 0) {
                $formulas['PesoGRM2'] = (float) round(($pesoCrudo * 10000) / ($largoToalla * $anchoToalla), 2);
            }

            // DiasEficiencia (tu regla)
            if ($diffDias > 0) {
                $formulas['DiasEficiencia'] = (float) round($diffDias, 2);
            }

            // StdDia / ProdKgDia
            if ($stdToaHra > 0 && $efic > 0) {
                $stdDia = $stdToaHra * $efic * 24;
                $formulas['StdDia'] = (float) round($stdDia, 2);

                if ($pesoCrudo > 0) {
                    $formulas['ProdKgDia'] = (float) round(($stdDia * $pesoCrudo) / 1000, 2);
                }
            }

            // StdHrsEfect / ProdKgDia2 (depende de diffDias)
            if ($diffDias > 0) {
                $stdHrsEfect = ($cantidad / $diffDias) / 24;
                $formulas['StdHrsEfect'] = (float) round($stdHrsEfect, 2);

                if ($pesoCrudo > 0) {
                    $formulas['ProdKgDia2'] = (float) round((($pesoCrudo * $stdHrsEfect) * 24) / 1000, 2);
                }
            }

            // HorasProd / DiasJornada
            if ($stdToaHra > 0 && $efic > 0) {
                $horasProd = $cantidad / ($stdToaHra * $efic);
                $formulas['HorasProd'] = (float) round($horasProd, 2);

                $formulas['DiasJornada'] = (float) round($horasProd / 24, 2);
            }

        } catch (\Throwable $e) {
            LogFacade::warning('DuplicarTejido: Error al calcular fórmulas', [
                'error' => $e->getMessage(),
                'programa_id' => $programa->Id ?? null,
            ]);
        }

        return $formulas;
    }

    private static function construirMaquina(?string $maquinaBase, ?string $salon, $telar): string
    {
        $prefijo = null;

        if ($maquinaBase && preg_match('/^([A-Za-z]+)\s*\d*/', trim($maquinaBase), $m)) {
            $prefijo = $m[1];
        }

        if (!$prefijo && $salon) {
            $prefijo = rtrim(substr($salon, 0, 4), '0123456789');
        }

        if (!$prefijo) $prefijo = 'TEL';

        return trim($prefijo) . ' ' . trim((string)$telar);
    }

    private static function sanitizeNumber($value): float
    {
        if ($value === null) return 0.0;
        if (is_numeric($value)) return (float)$value;

        $clean = str_replace([',', ' '], '', (string)$value);
        return is_numeric($clean) ? (float)$clean : 0.0;
    }
}
