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

class VincularTejido
{
    /** Cache simple para no pegarle a ReqModelosCodificados por cada cálculo */
    private static array $totalModeloCache = [];

    /**
     * Vincular tejidos nuevos desde cero con un OrdCompartida
     * Permite crear registros nuevos y asignarles un OrdCompartida existente o crear uno nuevo
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public static function vincular(Request $request)
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
            'ord_compartida_existente' => 'nullable|integer|min:1', // OrdCompartida existente para vincular (solo si es > 0)
            'registro_id_original' => 'nullable|integer',
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
        $ordCompartidaExistente = $data['ord_compartida_existente'] ?? null;
        $registroIdOriginal = $data['registro_id_original'] ?? null;

        // Guardar y restaurar dispatcher para no romper otros flujos
        $dispatcher = ReqProgramaTejido::getEventDispatcher();
        ReqProgramaTejido::unsetEventDispatcher();

        DBFacade::beginTransaction();

        try {
            // Determinar el OrdCompartida a usar
            // En modo "vincular" siempre se debe crear uno nuevo, no usar existente
            $ordCompartidaAVincular = null;

            // Normalizar el valor: convertir strings vacíos a null
            $ordCompartidaExistente = ($ordCompartidaExistente === '' || $ordCompartidaExistente === null)
                ? null
                : $ordCompartidaExistente;

            if ($ordCompartidaExistente !== null && $ordCompartidaExistente > 0) {
                // Verificar que el OrdCompartida existente realmente exista en la BD
                $existe = ReqProgramaTejido::where('OrdCompartida', (int)$ordCompartidaExistente)->exists();
                if (!$existe) {
                    DBFacade::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "El OrdCompartida {$ordCompartidaExistente} no existe en la base de datos.",
                    ], 404);
                }
                // Usar OrdCompartida existente
                $ordCompartidaAVincular = (int) $ordCompartidaExistente;
            } else {
                // Crear un nuevo OrdCompartida verificando que no esté en uso
                $ordCompartidaAVincular = self::obtenerNuevoOrdCompartidaDisponible();
            }
            // Obtener el registro específico a vincular, o como fallback el último del telar
            $original = null;
            if ($registroIdOriginal) {
                $original = ReqProgramaTejido::find($registroIdOriginal);
                // Verificar que el registro encontrado pertenece al telar y salón correctos
                if ($original && ($original->SalonTejidoId !== $salonOrigen || $original->NoTelarId !== $telarOrigen)) {
                    $original = null; // No es del telar correcto, usar fallback
                }
            }

            // Fallback: obtener el último registro del telar
            if (!$original) {
                $original = self::obtenerUltimoRegistroTelar($salonOrigen, $telarOrigen);
            }

            if (!$original) {
                DBFacade::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron registros para vincular',
                ], 404);
            }

            $idsParaObserver = [];
            $totalVinculados = 0;

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

                // ===== ORDCOMPARTIDA: Asignar el OrdCompartida (existente o nuevo) =====
                $nuevo->OrdCompartida = $ordCompartidaAVincular;

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
                            LogFacade::warning('VincularTejido: No se pudo calcular fecha final con calendario (sin líneas suficientes). Fallback continuo.', [
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

                // (opcional) log para verificar
                LogFacade::info('VincularTejido: resumen fechas/horas', [
                    'telar_destino' => $telarDestino,
                    'calendario_id' => $nuevo->CalendarioId ?? null,
                    'fecha_inicio'  => $nuevo->FechaInicio,
                    'fecha_final'   => $nuevo->FechaFinal,
                    'ord_compartida' => $nuevo->OrdCompartida,
                    'dias_ef'       => $nuevo->DiasEficiencia ?? null,
                    'horas_prod'    => $nuevo->HorasProd ?? null,
                    'dias_jornada'  => $nuevo->DiasJornada ?? null,
                ]);

                // Truncar strings
                foreach ([
                    'Maquina','NombreProyecto','CustName','AplicacionId','NombreProducto',
                    'FlogsId','TipoPedido','Observaciones','FibraTrama','FibraComb1','FibraComb2',
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
                $totalVinculados++;
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
                'message' => "Telar vinculado correctamente. Se crearon {$totalVinculados} registro(s) con OrdCompartida: {$ordCompartidaAVincular}.",
                'registros_vinculados' => $totalVinculados,
                'ord_compartida' => $ordCompartidaAVincular,
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

            LogFacade::error('VincularTejido error', [
                'salon' => $salonOrigen,
                'telar' => $telarOrigen,
                'msg'   => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al vincular el telar: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtiene un nuevo OrdCompartida disponible verificando que no esté en uso
     *
     * @return int
     */
    private static function obtenerNuevoOrdCompartidaDisponible(): int
    {
        // Obtener el máximo OrdCompartida existente
        $maxOrdCompartida = ReqProgramaTejido::max('OrdCompartida') ?? 0;

        // Empezar desde el siguiente número
        $candidato = $maxOrdCompartida + 1;

        // Verificar que no esté en uso (buscar hasta encontrar uno disponible)
        // Límite de seguridad para evitar loops infinitos
        $intentos = 0;
        $maxIntentos = 1000;

        while ($intentos < $maxIntentos) {
            // Verificar si el OrdCompartida candidato ya existe
            $existe = ReqProgramaTejido::where('OrdCompartida', $candidato)->exists();

            if (!$existe) {
                // Este OrdCompartida está disponible
                LogFacade::info('VincularTejido: Nuevo OrdCompartida asignado', [
                    'ord_compartida' => $candidato,
                    'max_existente' => $maxOrdCompartida,
                ]);
                return $candidato;
            }

            // Si existe, probar el siguiente
            $candidato++;
            $intentos++;
        }

        // Si llegamos aquí, algo está mal (muchos gaps en la secuencia)
        // Usar el máximo + 1 de todas formas y loggear advertencia
        LogFacade::warning('VincularTejido: No se encontró OrdCompartida disponible después de múltiples intentos', [
            'max_ord_compartida' => $maxOrdCompartida,
            'candidato_final' => $candidato,
        ]);

        return $candidato;
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
    private static function calcularFormulasEficiencia(ReqProgramaTejido $programa): array
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
            LogFacade::warning('VincularTejido: Error al calcular fórmulas', [
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

