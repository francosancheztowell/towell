<?php

namespace App\Http\Controllers\ProgramaTejido\funciones;

use App\Models\ReqProgramaTejido;
use App\Observers\ReqProgramaTejidoObserver;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB as DBFacade;
use Illuminate\Support\Facades\Log as LogFacade;
use App\Models\ReqModelosCodificados;
use App\Models\ReqCalendarioLine;
use App\Models\ReqVelocidadStd;
use App\Models\ReqEficienciaStd;
use App\Helpers\StringTruncator;

class DividirTejido
{
    /** Valores válidos en catálogo */
    private const DENSIDAD_NORMAL = 'Normal';
    private const DENSIDAD_ALTA   = 'Alta';
    /**
     * Dividir un registro de telar entre múltiples telares destino
     * El registro original se mantiene pero con cantidad reducida
     * Se crean nuevos registros con el saldo dividido
     * Todos comparten el mismo OrdCompartida para identificarlos
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public static function dividir(Request $request)
    {
        $request->validate([
            'salon_tejido_id' => 'required|string',
            'no_telar_id' => 'required|string',
            'destinos' => 'required|array|min:1',
            'destinos.*.telar' => 'required|string',
            'destinos.*.salon_destino' => 'nullable|string',
            'destinos.*.pedido' => 'nullable|string',
            'destinos.*.pedido_tempo' => 'nullable|string',
            'destinos.*.observaciones' => 'nullable|string|max:500',
            'destinos.*.porcentaje_segundos' => 'nullable|numeric|min:0',
            'registro_id_original' => 'nullable|integer',
        ]);

        $salonOrigen = $request->input('salon_tejido_id');
        $telarOrigen = $request->input('no_telar_id');
        $salonDestino = $request->input('salon_destino', $salonOrigen);
        $destinos = $request->input('destinos', []);
        $codArticulo = $request->input('cod_articulo');
        $producto = $request->input('producto');
        $hilo = $request->input('hilo');
        $flog = $request->input('flog');
        $aplicacion = $request->input('aplicacion');
        $descripcion = $request->input('descripcion');
        $custname = $request->input('custname');
        $inventSizeId = $request->input('invent_size_id');
        $registroIdOriginal = $request->input('registro_id_original');

        // Verificar si es una redistribución de un grupo existente
        $ordCompartidaExistente = $request->input('ord_compartida_existente');
        $esRedistribucion = !empty($ordCompartidaExistente) && $ordCompartidaExistente !== '0';

        DBFacade::beginTransaction();
        ReqProgramaTejido::unsetEventDispatcher();

        try {
            // Si es redistribución, usar lógica diferente
            if ($esRedistribucion) {
                return self::redistribuirGrupoExistente($request, $ordCompartidaExistente, $destinos, $salonDestino, $hilo);
            }

            // Obtener el registro específico a dividir:
            // 1) Si viene registro_id_original, usar ese.
            // 2) Si no, usar el último del telar (fallback anterior).
            if (!empty($registroIdOriginal)) {
                $registroOriginal = ReqProgramaTejido::find($registroIdOriginal);
                // Verificar que el registro encontrado pertenece al telar y salón correctos
                if ($registroOriginal && ($registroOriginal->SalonTejidoId !== $salonOrigen || $registroOriginal->NoTelarId !== $telarOrigen)) {
                    $registroOriginal = null; // No es del telar correcto, usar fallback
                }
            }

            // Fallback: obtener el último registro del telar
            if (!$registroOriginal) {
                $registroOriginal = ReqProgramaTejido::query()
                    ->salon($salonOrigen)
                    ->telar($telarOrigen)
                    ->where('Ultimo', 1)
                    ->orderBy('FechaInicio', 'desc')
                    ->first()
                    ?? ReqProgramaTejido::query()
                        ->salon($salonOrigen)
                        ->telar($telarOrigen)
                        ->orderBy('FechaInicio', 'desc')
                        ->first();
            }

            if (!$registroOriginal) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró el registro para dividir'
                ], 404);
            }

            // Obtener el siguiente número consecutivo de OrdCompartida
            $maxOrdCompartida = ReqProgramaTejido::max('OrdCompartida') ?? 0;
            $nuevoOrdCompartida = $maxOrdCompartida + 1;

            // El primer destino es el registro original (ya viene bloqueado en el modal)
            // Los demás destinos son los telares donde se dividirá
            $destinosNuevos = [];
            $cantidadOriginalTotal = (float) ($registroOriginal->SaldoPedido ?? $registroOriginal->TotalPedido ?? 0);
            $cantidadParaOriginal = 0;
            $cantidadesNuevos = [];

            // Procesar destinos: el primero es el original (mantener), los demás son nuevos
            $observacionesOriginal = null;
            $porcentajeSegundosOriginal = null;

            foreach ($destinos as $index => $destino) {
                $pedidoDestino = isset($destino['pedido']) && $destino['pedido'] !== ''
                    ? (float) $destino['pedido']
                    : 0;
                $pedidoTempoDestino = $destino['pedido_tempo'] ?? null;
                $observacionesDestino = $destino['observaciones'] ?? null;
                $porcentajeSegundosDestino = isset($destino['porcentaje_segundos']) && $destino['porcentaje_segundos'] !== null && $destino['porcentaje_segundos'] !== ''
                    ? (float)$destino['porcentaje_segundos']
                    : null;

                if ($index === 0) {
                    // Primer registro = el original, se actualiza con la nueva cantidad
                    $cantidadParaOriginal = $pedidoDestino;
                    $observacionesOriginal = $observacionesDestino;
                    $porcentajeSegundosOriginal = $porcentajeSegundosDestino;
                } else {
                    $salonDestinoItem = $destino['salon_destino'] ?? $salonDestino;
                    // Nuevos registros a crear
                    $destinosNuevos[] = [
                        'salon_destino' => $salonDestinoItem,
                        'telar' => $destino['telar'],
                        'pedido' => $pedidoDestino,
                        'observaciones' => $observacionesDestino,
                        'porcentaje_segundos' => $porcentajeSegundosDestino
                    ];
                    $cantidadesNuevos[] = $pedidoDestino;
                }
            }

            // Ajustar cantidades: si no se dio cantidad al original, usar la diferencia
            $sumaNuevos = array_sum($cantidadesNuevos);
            if ($cantidadParaOriginal <= 0) {
                $cantidadParaOriginal = max(0, $cantidadOriginalTotal - $sumaNuevos);
            }
            // Si tampoco hubo nuevos, mantener el total original en el registro base
            if ($cantidadParaOriginal <= 0 && $sumaNuevos <= 0) {
                $cantidadParaOriginal = $cantidadOriginalTotal;
            }

            $idsParaObserver = [];
            $totalDivididos = 0;

            // === PASO 1: Actualizar el registro original ===
            $registroOriginal->OrdCompartida = $nuevoOrdCompartida;
            $registroOriginal->TotalPedido = $cantidadParaOriginal;
            // SaldoPedido = TotalPedido - Produccion (si hay producción)
            $produccionOriginal = (float) ($registroOriginal->Produccion ?? 0);
            $registroOriginal->SaldoPedido = max(0, $cantidadParaOriginal - $produccionOriginal);

            // Actualizar PedidoTempo, Observaciones y PorcentajeSegundos del registro original
            if ($pedidoTempoDestino !== null && $pedidoTempoDestino !== '') {
                $registroOriginal->PedidoTempo = $pedidoTempoDestino;
            }
            if ($observacionesOriginal !== null && $observacionesOriginal !== '') {
                $registroOriginal->Observaciones = StringTruncator::truncate('Observaciones', $observacionesOriginal);
            }
            if ($porcentajeSegundosOriginal !== null) {
                $registroOriginal->PorcentajeSegundos = $porcentajeSegundosOriginal;
            }
            // Ajustar Maquina al telar origen seleccionado
            $registroOriginal->Maquina = self::construirMaquina(
                $registroOriginal->Maquina ?? null,
                $salonOrigen,
                $telarOrigen
            );

            // ===== FORZAR STD DESDE CATÁLOGOS (SMITH/JACQUARD + Normal/Alta) =====
            self::aplicarStdDesdeCatalogos($registroOriginal);

            $registroOriginal->UpdatedAt = now();

            // ===== RECALCULAR FECHA FINAL desde la fecha inicio existente (sin cambiar fecha inicio) =====
            if (!empty($registroOriginal->FechaInicio)) {
                $inicio = Carbon::parse($registroOriginal->FechaInicio);
                $horasNecesarias = self::calcularHorasProd($registroOriginal);

                if ($horasNecesarias <= 0) {
                    $registroOriginal->FechaFinal = $inicio->copy()->addDays(30)->format('Y-m-d H:i:s');
                } else {
                    if (!empty($registroOriginal->CalendarioId)) {
                        $fin = BalancearTejido::calcularFechaFinalDesdeInicio($registroOriginal->CalendarioId, $inicio, $horasNecesarias);
                        if (!$fin) {
                            $fin = $inicio->copy()->addSeconds((int)($horasNecesarias * 3600));
                        }
                        $registroOriginal->FechaFinal = $fin->format('Y-m-d H:i:s');
                    } else {
                        $registroOriginal->FechaFinal = $inicio->copy()->addSeconds((int)($horasNecesarias * 3600))->format('Y-m-d H:i:s');
                    }
                }

                LogFacade::info('DividirTejido: recalculo exacto registro original', [
                    'id' => $registroOriginal->Id,
                    'cantidad' => $cantidadParaOriginal,
                    'horas' => $horasNecesarias,
                    'inicio' => $registroOriginal->FechaInicio,
                    'fin' => $registroOriginal->FechaFinal,
                    'calendario_id' => $registroOriginal->CalendarioId ?? null,
                ]);
            }

            // Recalcular fórmulas del registro original
            if ($registroOriginal->FechaInicio && $registroOriginal->FechaFinal) {
                $formulas = self::calcularFormulasEficiencia($registroOriginal);
                foreach ($formulas as $campo => $valor) {
                    $registroOriginal->{$campo} = $valor;
                }
            }

            $registroOriginal->save();
            $idsParaObserver[] = $registroOriginal->Id;
            $totalDivididos++;

            // === PASO 2: Crear los nuevos registros para los telares destino ===
            foreach ($destinosNuevos as $destino) {
                $telarDestino = $destino['telar'];
                $pedidoDestino = $destino['pedido'];
                $salonDestinoItem = $destino['salon_destino'] ?? $salonDestino;

                // Obtener el último registro del telar destino para determinar fecha de inicio
                $ultimoRegistroDestino = ReqProgramaTejido::query()
                    ->salon($salonDestinoItem)
                    ->telar($telarDestino)
                    ->orderBy('FechaInicio', 'desc')
                    ->first();

                // Quitar Ultimo=1 del registro anterior del telar destino (si existe)
                if ($ultimoRegistroDestino && $ultimoRegistroDestino->Ultimo == 1) {
                    ReqProgramaTejido::where('Id', $ultimoRegistroDestino->Id)
                        ->update(['Ultimo' => 0]);
                }

                // Determinar fecha de inicio
                $fechaInicioBase = $ultimoRegistroDestino && $ultimoRegistroDestino->FechaFinal
                    ? Carbon::parse($ultimoRegistroDestino->FechaFinal)
                    : ($registroOriginal->FechaInicio
                        ? Carbon::parse($registroOriginal->FechaInicio)
                        : Carbon::now());

                // Crear nuevo registro basado en el original
                $nuevo = $registroOriginal->replicate();

                // Campos básicos
                $nuevo->SalonTejidoId = $salonDestinoItem;
                $nuevo->NoTelarId = $telarDestino;
                $nuevo->EnProceso = 0;
                $nuevo->Ultimo = 1;
                $nuevo->CambioHilo = 0;
                $nuevo->Produccion = null;
                $nuevo->Programado = null;
                $nuevo->NoProduccion = null;
                $nuevo->ProgramarProd = Carbon::now()->format('Y-m-d');

                // OrdCompartida - mismo número que el original para relacionarlos
                $nuevo->OrdCompartida = $nuevoOrdCompartida;

                // Cantidad del nuevo registro
                // Los nuevos registros no tienen producción aún, así que SaldoPedido = TotalPedido
                $nuevo->TotalPedido = $pedidoDestino;
                $nuevo->SaldoPedido = $pedidoDestino; // Sin producción inicial

                // Ajustar Maquina al telar destino (prefijo del salón + número de telar)
                $nuevo->Maquina = self::construirMaquina(
                    $registroOriginal->Maquina ?? null,
                    $salonDestinoItem,
                    $telarDestino
                );

                // Actualizar otros campos si se proporcionan
                if ($inventSizeId) $nuevo->InventSizeId = $inventSizeId;
                if ($codArticulo) $nuevo->ItemId = $codArticulo;
                if ($producto) $nuevo->NombreProducto = $producto;
                if ($hilo) $nuevo->FibraRizo = $hilo;
                if ($flog) $nuevo->FlogsId = $flog;
                if ($descripcion) $nuevo->NombreProyecto = $descripcion;
                if ($custname) $nuevo->CustName = $custname;
                if ($aplicacion) $nuevo->AplicacionId = $aplicacion;

                if ($salonDestinoItem !== $salonOrigen) {
                    self::aplicarModeloCodificadoPorSalon($nuevo, $salonDestinoItem);
                }

                // ===== FORZAR STD DESDE CATÁLOGOS (SMITH/JACQUARD + Normal/Alta) =====
                self::aplicarStdDesdeCatalogos($nuevo);

                // PedidoTempo, Observaciones y PorcentajeSegundos del destino
                $pedidoTempoDestinoNuevo = $destino['pedido_tempo'] ?? null;
                $observacionesDestino = $destino['observaciones'] ?? null;
                $porcentajeSegundosDestino = $destino['porcentaje_segundos'] ?? null;
                if ($pedidoTempoDestinoNuevo !== null && $pedidoTempoDestinoNuevo !== '') {
                    $nuevo->PedidoTempo = $pedidoTempoDestinoNuevo;
                }
                if ($observacionesDestino !== null && $observacionesDestino !== '') {
                    $nuevo->Observaciones = StringTruncator::truncate('Observaciones', $observacionesDestino);
                }
                if ($porcentajeSegundosDestino !== null && $porcentajeSegundosDestino !== '') {
                    $nuevo->PorcentajeSegundos = (float)$porcentajeSegundosDestino;
                }

                // ===== FECHA INICIO: SIEMPRE la FechaFinal del último registro del telar destino =====
                // NO hacer snap al calendario, usar exactamente la fecha final del último registro
                $nuevo->FechaInicio = $fechaInicioBase->format('Y-m-d H:i:s');
                $inicio = $fechaInicioBase->copy();

                // ===== CALCULAR FECHA FINAL desde la fecha inicio exacta =====
                $horasNecesarias = self::calcularHorasProd($nuevo);

                if ($horasNecesarias <= 0) {
                    $nuevo->FechaFinal = $inicio->copy()->addDays(30)->format('Y-m-d H:i:s');
                } else {
                    // Calcular FechaFinal desde la fecha inicio exacta (sin snap)
                    if (!empty($nuevo->CalendarioId)) {
                        $fin = BalancearTejido::calcularFechaFinalDesdeInicio($nuevo->CalendarioId, $inicio, $horasNecesarias);
                        if (!$fin) {
                            $fin = $inicio->copy()->addSeconds((int)($horasNecesarias * 3600));
                        }
                        $nuevo->FechaFinal = $fin->format('Y-m-d H:i:s');
                    } else {
                        $nuevo->FechaFinal = $inicio->copy()->addSeconds((int)($horasNecesarias * 3600))->format('Y-m-d H:i:s');
                    }
                }

                LogFacade::info('DividirTejido: recalculo exacto nuevo registro', [
                    'id' => $nuevo->Id ?? 'nuevo',
                    'cantidad' => $pedidoDestino,
                    'horas' => $horasNecesarias,
                    'inicio' => $nuevo->FechaInicio,
                    'fin' => $nuevo->FechaFinal,
                    'calendario_id' => $nuevo->CalendarioId ?? null,
                ]);

                // CambioHilo
                if ($ultimoRegistroDestino) {
                    $fibraRizoNuevo = trim((string) $nuevo->FibraRizo);
                    $fibraRizoAnterior = trim((string) $ultimoRegistroDestino->FibraRizo);
                    $nuevo->CambioHilo = ($fibraRizoNuevo !== $fibraRizoAnterior) ? '1' : '0';
                }

                // Calcular fórmulas
                if ($nuevo->FechaInicio && $nuevo->FechaFinal) {
                    $formulas = self::calcularFormulasEficiencia($nuevo);
                    foreach ($formulas as $campo => $valor) {
                        $nuevo->{$campo} = $valor;
                    }
                }

                // Eliminar Repeticiones si existe (no es una columna de la tabla)
                unset($nuevo->Repeticiones);

                $nuevo->CreatedAt = now();
                $nuevo->UpdatedAt = now();
                $nuevo->save();

                $idsParaObserver[] = $nuevo->Id;
                $totalDivididos++;
            }

            DBFacade::commit();

            // Re-habilitar observer
            ReqProgramaTejido::observe(ReqProgramaTejidoObserver::class);

            // Disparar observer manualmente para generar las líneas
            $observer = new ReqProgramaTejidoObserver();
            foreach ($idsParaObserver as $idDividido) {
                $registro = ReqProgramaTejido::find($idDividido);
                if ($registro) {
                    $observer->saved($registro);
                }
            }

            // Asegurar que los registros divididos mantengan EnProceso=0
            if (!empty($idsParaObserver)) {
                // El original mantiene su estado, los nuevos son EnProceso=0
                $idsNuevos = array_slice($idsParaObserver, 1);
                if (!empty($idsNuevos)) {
                    ReqProgramaTejido::whereIn('Id', $idsNuevos)
                        ->update(['EnProceso' => 0]);
                }
            }

            // Obtener el primer registro creado (nuevo) para redirigir
            $primerNuevoCreado = count($idsParaObserver) > 1
                ? ReqProgramaTejido::find($idsParaObserver[1])
                : ReqProgramaTejido::find($idsParaObserver[0]);

            return response()->json([
                'success' => true,
                'message' => "Registro dividido correctamente. OrdCompartida: {$nuevoOrdCompartida}. Se crearon/actualizaron {$totalDivididos} registro(s).",
                'registros_divididos' => $totalDivididos,
                'ord_compartida' => $nuevoOrdCompartida,
                'registro_id' => $primerNuevoCreado?->Id,
                'salon_destino' => $primerNuevoCreado?->SalonTejidoId,
                'telar_destino' => $primerNuevoCreado?->NoTelarId
            ]);

        } catch (\Throwable $e) {
            DBFacade::rollBack();
            ReqProgramaTejido::observe(ReqProgramaTejidoObserver::class);
            LogFacade::error('dividirTelar error', [
                'salon' => $salonOrigen,
                'telar' => $telarOrigen,
                'msg' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al dividir el telar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calcular fórmulas de eficiencia (IGUAL QUE EN DuplicarTejido)
     */
    private static function calcularFormulasEficiencia(ReqProgramaTejido $programa): array
    {
        $formulas = [];

        try {
            $vel = (float) ($programa->VelocidadSTD ?? 0);
            $efic = (float) ($programa->EficienciaSTD ?? 0);
            $cantidad = (float) ($programa->SaldoPedido ?? $programa->Produccion ?? $programa->TotalPedido ?? 0);
            $pesoCrudo = (float) ($programa->PesoCrudo ?? 0);
            $noTiras = (float) ($programa->NoTiras ?? 0);
            $luchaje = (float) ($programa->Luchaje ?? 0);
            $repeticiones = (float) ($programa->Repeticiones ?? 0);

            if ($efic > 1) {
                $efic = $efic / 100;
            }

            $total = 0;
            if ($programa->TamanoClave) {
                $modelo = self::obtenerModeloCodificadoPorSalon($programa->TamanoClave, $programa->SalonTejidoId);
                if ($modelo) {
                    $total = (float) ($modelo->Total ?? 0);
                }
            }

            $inicio = Carbon::parse($programa->FechaInicio);
            $fin = Carbon::parse($programa->FechaFinal);
            $diffSegundos = abs($fin->getTimestamp() - $inicio->getTimestamp());
            $diffDias = $diffSegundos / (60 * 60 * 24);

            $stdToaHra = 0;
            if ($noTiras > 0 && $total > 0 && $luchaje > 0 && $repeticiones > 0 && $vel > 0) {
                $parte1 = $total / 1;
                $parte2 = (($luchaje * 0.5) / 0.0254) / $repeticiones;
                $denominador = ($parte1 + $parte2) / $vel;
                if ($denominador > 0) {
                    $stdToaHra = ($noTiras * 60) / $denominador;
                    $formulas['StdToaHra'] = (float) round($stdToaHra, 2);
                }
            }

            $largoToalla = (float) ($programa->LargoToalla ?? 0);
            $anchoToalla = (float) ($programa->AnchoToalla ?? 0);
            if ($pesoCrudo > 0 && $largoToalla > 0 && $anchoToalla > 0) {
                $formulas['PesoGRM2'] = (float) round(($pesoCrudo * 10000) / ($largoToalla * $anchoToalla), 2);
            }

            if ($diffDias > 0) {
                $formulas['DiasEficiencia'] = (float) round($diffDias, 2);
            }

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

            $horasProd = 0;
            if ($stdToaHra > 0 && $efic > 0) {
                $horasProd = $cantidad / ($stdToaHra * $efic);
                $formulas['HorasProd'] = (float) round($horasProd, 2);
            }

            if ($horasProd > 0) {
                $formulas['DiasJornada'] = (float) round($horasProd / 24, 2);
            }

            // EntregaCte = FechaFinal + 12 días
            $entregaCteCalculada = null;
            if (!empty($programa->FechaFinal)) {
                try {
                    $fechaFinal = Carbon::parse($programa->FechaFinal);
                    $entregaCteCalculada = $fechaFinal->copy()->addDays(12);
                    $formulas['EntregaCte'] = $entregaCteCalculada->format('Y-m-d H:i:s');
                } catch (\Throwable $e) {
                    // Si hay error al parsear, no establecer EntregaCte
                }
            }

            // PTvsCte = EntregaCte - EntregaPT (diferencia en días)
            if (!empty($programa->EntregaPT)) {
                try {
                    $entregaPT = Carbon::parse($programa->EntregaPT);
                    // Usar EntregaCte calculada si existe, sino usar la del programa si existe
                    $entregaCteParaCalcular = $entregaCteCalculada
                        ?: (!empty($programa->EntregaCte) ? Carbon::parse($programa->EntregaCte) : null);

                    if ($entregaCteParaCalcular) {
                        $diferenciaDias = $entregaCteParaCalcular->diffInDays($entregaPT, false);
                        $formulas['PTvsCte'] = (float) round($diferenciaDias, 2);
                    }
                } catch (\Throwable $e) {
                    // Si hay error al parsear, no establecer PTvsCte
                }
            }

        } catch (\Throwable $e) {
            LogFacade::warning('DividirTejido: Error al calcular fórmulas de eficiencia', [
                'error' => $e->getMessage(),
                'programa_id' => $programa->Id ?? null,
            ]);
        }

        return $formulas;
    }

    private static function obtenerModeloCodificadoPorSalon(?string $tamanoClave, ?string $salonTejidoId): ?ReqModelosCodificados
    {
        $clave = trim((string) $tamanoClave);
        if ($clave === '') {
            return null;
        }

        $salon = trim((string) $salonTejidoId);

        $q = ReqModelosCodificados::query()
            ->where(function ($builder) use ($clave) {
                $builder->where('TamanoClave', $clave)
                    ->orWhere('ClaveModelo', $clave);
            });
        if ($salon !== '') {
            $q->where('SalonTejidoId', $salon);
        }

        $modelo = $q->orderByDesc('FechaTejido')->first();
        if ($modelo || $salon === '') {
            return $modelo;
        }

        return ReqModelosCodificados::query()
            ->where(function ($builder) use ($clave) {
                $builder->where('TamanoClave', $clave)
                    ->orWhere('ClaveModelo', $clave);
            })
            ->orderByDesc('FechaTejido')
            ->first();
    }

    private static function aplicarModeloCodificadoPorSalon(ReqProgramaTejido $registro, string $salonDestino): void
    {
        $modelo = self::obtenerModeloCodificadoPorSalon($registro->TamanoClave, $salonDestino);
        if (!$modelo) {
            return;
        }

        if (!empty($modelo->ItemId)) {
            $registro->ItemId = (string) $modelo->ItemId;
        }
        if (!empty($modelo->InventSizeId)) {
            $registro->InventSizeId = (string) $modelo->InventSizeId;
        }
        if (!empty($modelo->Nombre)) {
            $registro->NombreProducto = StringTruncator::truncate('NombreProducto', (string) $modelo->Nombre);
        }
        if (!empty($modelo->NombreProyecto)) {
            $registro->NombreProyecto = StringTruncator::truncate('NombreProyecto', (string) $modelo->NombreProyecto);
        }
        if (!empty($modelo->FlogsId)) {
            $registro->FlogsId = StringTruncator::truncate('FlogsId', (string) $modelo->FlogsId);
        }

        // Solo asignar FibraRizo del modelo si no hay un valor ya asignado (respetar el hilo del usuario)
        if (empty($registro->FibraRizo)) {
            $fibraRizo = $modelo->FibraRizo ?? $modelo->FibraId ?? null;
            if (!empty($fibraRizo)) {
                $registro->FibraRizo = (string) $fibraRizo;
            }
        }

        if ($modelo->CalibreRizo !== null) {
            $registro->CalibreRizo = (float) $modelo->CalibreRizo;
        }
        if ($modelo->CalibreRizo2 !== null) {
            $registro->CalibreRizo2 = (float) $modelo->CalibreRizo2;
        }
        if ($modelo->CalibrePie !== null) {
            $registro->CalibrePie = (float) $modelo->CalibrePie;
        }
        if ($modelo->CalibrePie2 !== null) {
            $registro->CalibrePie2 = (float) $modelo->CalibrePie2;
        }
        // Usar CalibreTrama del modelo codificado (no CalibreTrama2)
        if ($modelo->CalibreTrama !== null) {
            $registro->CalibreTrama = (float) $modelo->CalibreTrama2;
        }
        if ($modelo->CalibreTrama2 !== null) {
            $registro->CalibreTrama2 = (float) $modelo->CalibreTrama;
        }

        if ($modelo->NoTiras !== null) {
            $registro->NoTiras = (float) $modelo->NoTiras;
        }
        if ($modelo->Luchaje !== null) {
            $registro->Luchaje = (float) $modelo->Luchaje;
        }
        // Repeticiones no existe en la tabla ReqProgramaTejido, se elimina la asignación
        // if ($modelo->Repeticiones !== null) {
        //     $registro->Repeticiones = (float) $modelo->Repeticiones;
        // }
        if ($modelo->PesoCrudo !== null) {
            $registro->PesoCrudo = (float) $modelo->PesoCrudo;
        }
        if ($modelo->MedidaPlano !== null) {
            $registro->MedidaPlano = (int) $modelo->MedidaPlano;
        }
        if ($modelo->Peine !== null) {
            $registro->Peine = (int) $modelo->Peine;
        }
        if ($modelo->AnchoToalla !== null) {
            $registro->AnchoToalla = (float) $modelo->AnchoToalla;
            $registro->Ancho = (float) $modelo->AnchoToalla;
        }

        if ($modelo->FibraTrama !== null) {
            $registro->FibraTrama = (string) $modelo->FibraTrama;
        }
        if ($modelo->FibraPie !== null) {
            $registro->FibraPie = (string) $modelo->FibraPie;
        }
        if ($modelo->CuentaRizo !== null) {
            $registro->CuentaRizo = (string) $modelo->CuentaRizo;
        }
        if ($modelo->CuentaPie !== null) {
            $registro->CuentaPie = (string) $modelo->CuentaPie;
        }
        if ($modelo->CodColorTrama !== null) {
            $registro->CodColorTrama = (string) $modelo->CodColorTrama;
        }
        if ($modelo->ColorTrama !== null) {
            $registro->ColorTrama = (string) $modelo->ColorTrama;
        }
    }

    /**
     * Redistribuir cantidades en un grupo existente de OrdCompartida
     * Actualiza registros existentes y crea nuevos si es necesario
     */
    private static function redistribuirGrupoExistente(Request $request, $ordCompartida, $destinos, $salonDestino, $hilo = null)
    {
        try {
            // Obtener todos los registros del grupo
            $registrosExistentes = ReqProgramaTejido::where('OrdCompartida', $ordCompartida)
                ->orderBy('FechaInicio')
                ->get();

            if ($registrosExistentes->isEmpty()) {
                DBFacade::rollBack();
                ReqProgramaTejido::observe(ReqProgramaTejidoObserver::class);
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron registros para el grupo OrdCompartida: ' . $ordCompartida
                ], 404);
            }

            // Calcular la duración total original (usaremos el primer registro como base)
            $primerRegistro = $registrosExistentes->first();
            $fechaInicioBase = $primerRegistro->FechaInicio ? Carbon::parse($primerRegistro->FechaInicio) : Carbon::now();

            // Calcular cantidad total del grupo para proporciones
            $cantidadTotalGrupo = $registrosExistentes->sum('TotalPedido');

            // Calcular duración promedio por unidad (basado en el primer registro)
            $fechaFinalPrimer = $primerRegistro->FechaFinal ? Carbon::parse($primerRegistro->FechaFinal) : null;
            $duracionPrimerSegundos = ($fechaInicioBase && $fechaFinalPrimer)
                ? abs($fechaFinalPrimer->getTimestamp() - $fechaInicioBase->getTimestamp())
                : 0;
            $cantidadPrimer = (float) ($primerRegistro->TotalPedido ?? 1);
            $segundosPorUnidad = $cantidadPrimer > 0 ? $duracionPrimerSegundos / $cantidadPrimer : 0;

            $idsParaObserver = [];
            $totalActualizados = 0;
            $totalCreados = 0;

            // Mapear destinos por registro_id para actualizaciones
            $destinosPorId = [];
            $destinosNuevos = [];

            foreach ($destinos as $destino) {
                $registroId = $destino['registro_id'] ?? '';
                $esExistente = isset($destino['es_existente']) && $destino['es_existente'];
                $esNuevo = isset($destino['es_nuevo']) && $destino['es_nuevo'];

                if ($registroId && $esExistente) {
                    $destinosPorId[$registroId] = $destino;
                } elseif ($esNuevo || !$registroId) {
                    $destinosNuevos[] = $destino;
                }
            }

            // Actualizar registros existentes
            foreach ($registrosExistentes as $registro) {
                $registroId = (string) $registro->Id;

                if (isset($destinosPorId[$registroId])) {
                    $destino = $destinosPorId[$registroId];
                    $nuevaCantidad = (float) ($destino['pedido'] ?? 0);
                    $pedidoTempoDestino = $destino['pedido_tempo'] ?? null;
                    $observacionesDestino = $destino['observaciones'] ?? null;
                    $porcentajeSegundosDestino = isset($destino['porcentaje_segundos']) && $destino['porcentaje_segundos'] !== null && $destino['porcentaje_segundos'] !== ''
                        ? (float)$destino['porcentaje_segundos']
                        : null;

                    if ($nuevaCantidad > 0) {
                        $registro->TotalPedido = $nuevaCantidad;
                        $produccion = (float) ($registro->Produccion ?? 0);
                        $registro->SaldoPedido = max(0, $nuevaCantidad - $produccion);

                        // PedidoTempo, Observaciones y PorcentajeSegundos
                        if ($pedidoTempoDestino !== null && $pedidoTempoDestino !== '') {
                            $registro->PedidoTempo = $pedidoTempoDestino;
                        }
                        if ($observacionesDestino !== null && $observacionesDestino !== '') {
                            $registro->Observaciones = StringTruncator::truncate('Observaciones', $observacionesDestino);
                        }
                        if ($porcentajeSegundosDestino !== null) {
                            $registro->PorcentajeSegundos = $porcentajeSegundosDestino;
                        }

                        // Ajustar Maquina al telar (si se recibe telar en destino existente)
                        $telarDestino = $destino['telar'] ?? $registro->NoTelarId;
                        $salonDestinoItem = $registro->SalonTejidoId ?? $salonDestino;
                        $registro->Maquina = self::construirMaquina(
                            $registro->Maquina ?? null,
                            $salonDestinoItem,
                            $telarDestino
                        );

                        // ===== FORZAR STD DESDE CATÁLOGOS (SMITH/JACQUARD + Normal/Alta) =====
                        self::aplicarStdDesdeCatalogos($registro);

                        // ===== RECALCULAR FECHA FINAL desde la fecha inicio existente (sin cambiar fecha inicio) =====
                        if (!empty($registro->FechaInicio)) {
                            $inicio = Carbon::parse($registro->FechaInicio);
                            $horasNecesarias = self::calcularHorasProd($registro);

                            if ($horasNecesarias <= 0) {
                                $registro->FechaFinal = $inicio->copy()->addDays(30)->format('Y-m-d H:i:s');
                            } else {
                                if (!empty($registro->CalendarioId)) {
                                    $fin = BalancearTejido::calcularFechaFinalDesdeInicio($registro->CalendarioId, $inicio, $horasNecesarias);
                                    if (!$fin) {
                                        $fin = $inicio->copy()->addSeconds((int)($horasNecesarias * 3600));
                                    }
                                    $registro->FechaFinal = $fin->format('Y-m-d H:i:s');
                                } else {
                                    $registro->FechaFinal = $inicio->copy()->addSeconds((int)($horasNecesarias * 3600))->format('Y-m-d H:i:s');
                                }
                            }
                        }

                        // Recalcular fórmulas
                        if ($registro->FechaInicio && $registro->FechaFinal) {
                            $formulas = self::calcularFormulasEficiencia($registro);
                            foreach ($formulas as $campo => $valor) {
                                $registro->{$campo} = $valor;
                            }
                        }

                        $registro->UpdatedAt = now();
                        $registro->save();
                        $idsParaObserver[] = $registro->Id;
                        $totalActualizados++;
                    }
                }
            }

            // Crear nuevos registros
            foreach ($destinosNuevos as $destino) {
                $telarDestino = $destino['telar'] ?? '';
                $pedidoDestino = (float) ($destino['pedido'] ?? 0);
                $pedidoTempoDestino = $destino['pedido_tempo'] ?? null;
                $observacionesDestino = $destino['observaciones'] ?? null;
                $porcentajeSegundosDestino = isset($destino['porcentaje_segundos']) && $destino['porcentaje_segundos'] !== null && $destino['porcentaje_segundos'] !== ''
                    ? (float)$destino['porcentaje_segundos']
                    : null;
                $salonDestinoItem = $destino['salon_destino'] ?? $salonDestino;

                if (empty($telarDestino) || $pedidoDestino <= 0) {
                    continue;
                }

                // Obtener el último registro del telar destino
                $ultimoRegistroDestino = ReqProgramaTejido::query()
                    ->salon($salonDestinoItem)
                    ->telar($telarDestino)
                    ->orderBy('FechaInicio', 'desc')
                    ->first();

                // Quitar Ultimo=1 del registro anterior del telar destino
                if ($ultimoRegistroDestino && $ultimoRegistroDestino->Ultimo == 1) {
                    ReqProgramaTejido::where('Id', $ultimoRegistroDestino->Id)
                        ->update(['Ultimo' => 0]);
                }

                // Determinar fecha de inicio
                $fechaInicioNuevo = $ultimoRegistroDestino && $ultimoRegistroDestino->FechaFinal
                    ? Carbon::parse($ultimoRegistroDestino->FechaFinal)
                    : $fechaInicioBase->copy();

                // Crear nuevo registro basado en el primero del grupo
                $nuevo = $primerRegistro->replicate();

                // Campos básicos
                $nuevo->SalonTejidoId = $salonDestinoItem;
                $nuevo->NoTelarId = $telarDestino;
                $nuevo->EnProceso = 0;
                $nuevo->Ultimo = 1;
                $nuevo->CambioHilo = 0;
                $nuevo->Produccion = null;
                $nuevo->Programado = null;
                $nuevo->NoProduccion = null;
                $nuevo->ProgramarProd = Carbon::now()->format('Y-m-d');

                // OrdCompartida - mismo número que el grupo
                $nuevo->OrdCompartida = (int) $ordCompartida;

                // Cantidad del nuevo registro
                $nuevo->TotalPedido = $pedidoDestino;
                $nuevo->SaldoPedido = $pedidoDestino;

                // Ajustar Maquina al telar destino
                $nuevo->Maquina = self::construirMaquina(
                    $primerRegistro->Maquina ?? null,
                    $salonDestinoItem,
                    $telarDestino
                );

                // Asignar hilo del request si se proporciona (antes de aplicar modelo codificado)
                if ($hilo) {
                    $nuevo->FibraRizo = $hilo;
                }

                self::aplicarModeloCodificadoPorSalon($nuevo, $salonDestinoItem);

                // ===== FORZAR STD DESDE CATÁLOGOS (SMITH/JACQUARD + Normal/Alta) =====
                self::aplicarStdDesdeCatalogos($nuevo);

                // PedidoTempo, Observaciones y PorcentajeSegundos
                if ($pedidoTempoDestino !== null && $pedidoTempoDestino !== '') {
                    $nuevo->PedidoTempo = $pedidoTempoDestino;
                }
                if ($observacionesDestino !== null && $observacionesDestino !== '') {
                    $nuevo->Observaciones = StringTruncator::truncate('Observaciones', $observacionesDestino);
                }
                if ($porcentajeSegundosDestino !== null) {
                    $nuevo->PorcentajeSegundos = $porcentajeSegundosDestino;
                }

                // ===== FECHA INICIO: SIEMPRE la FechaFinal del último registro del telar destino =====
                // NO hacer snap al calendario, usar exactamente la fecha final del último registro
                $nuevo->FechaInicio = $fechaInicioNuevo->format('Y-m-d H:i:s');
                $inicio = $fechaInicioNuevo->copy();

                // ===== CALCULAR FECHA FINAL desde la fecha inicio exacta =====
                $horasNecesarias = self::calcularHorasProd($nuevo);

                if ($horasNecesarias <= 0) {
                    $nuevo->FechaFinal = $inicio->copy()->addDays(30)->format('Y-m-d H:i:s');
                } else {
                    // Calcular FechaFinal desde la fecha inicio exacta (sin snap)
                    if (!empty($nuevo->CalendarioId)) {
                        $fin = BalancearTejido::calcularFechaFinalDesdeInicio($nuevo->CalendarioId, $inicio, $horasNecesarias);
                        if (!$fin) {
                            $fin = $inicio->copy()->addSeconds((int)($horasNecesarias * 3600));
                        }
                        $nuevo->FechaFinal = $fin->format('Y-m-d H:i:s');
                    } else {
                        $nuevo->FechaFinal = $inicio->copy()->addSeconds((int)($horasNecesarias * 3600))->format('Y-m-d H:i:s');
                    }
                }

                // CambioHilo
                if ($ultimoRegistroDestino) {
                    $fibraRizoNuevo = trim((string) $nuevo->FibraRizo);
                    $fibraRizoAnterior = trim((string) $ultimoRegistroDestino->FibraRizo);
                    $nuevo->CambioHilo = ($fibraRizoNuevo !== $fibraRizoAnterior) ? '1' : '0';
                }

                // Calcular fórmulas
                if ($nuevo->FechaInicio && $nuevo->FechaFinal) {
                    $formulas = self::calcularFormulasEficiencia($nuevo);
                    foreach ($formulas as $campo => $valor) {
                        $nuevo->{$campo} = $valor;
                    }
                }

                // Eliminar Repeticiones si existe (no es una columna de la tabla)
                unset($nuevo->Repeticiones);

                $nuevo->CreatedAt = now();
                $nuevo->UpdatedAt = now();
                $nuevo->save();

                $idsParaObserver[] = $nuevo->Id;
                $totalCreados++;
            }

            DBFacade::commit();

            // Re-habilitar observer
            ReqProgramaTejido::observe(ReqProgramaTejidoObserver::class);

            // Disparar observer manualmente para generar las líneas
            $observer = new ReqProgramaTejidoObserver();
            foreach ($idsParaObserver as $idActualizado) {
                $registro = ReqProgramaTejido::find($idActualizado);
                if ($registro) {
                    $observer->saved($registro);
                }
            }

            // Obtener el primer registro nuevo creado para redirigir (si hay)
            $primerNuevoCreado = $totalCreados > 0 && !empty($idsParaObserver)
                ? ReqProgramaTejido::find(end($idsParaObserver))
                : $registrosExistentes->first();

            return response()->json([
                'success' => true,
                'message' => "Redistribución completada. Actualizados: {$totalActualizados}, Nuevos: {$totalCreados}.",
                'registros_actualizados' => $totalActualizados,
                'registros_creados' => $totalCreados,
                'ord_compartida' => $ordCompartida,
                'registro_id' => $primerNuevoCreado?->Id,
                'salon_destino' => $primerNuevoCreado?->SalonTejidoId,
                'telar_destino' => $primerNuevoCreado?->NoTelarId
            ]);

        } catch (\Throwable $e) {
            DBFacade::rollBack();
            ReqProgramaTejido::observe(ReqProgramaTejidoObserver::class);
            LogFacade::error('redistribuirGrupoExistente error', [
                'ord_compartida' => $ordCompartida,
                'msg' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al redistribuir el grupo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Construye el valor de Maquina usando un prefijo del salón o del valor base y el número de telar
     */
    private static function construirMaquina(?string $maquinaBase, ?string $salon, $telar): string
    {
        $salonNorm = strtoupper(trim((string) $salon));
        $prefijo = null;

        if ($salonNorm !== '') {
            if (preg_match('/SMI(T)?/i', $salonNorm)) {
                $prefijo = 'SMI';
            } elseif (preg_match('/JAC/i', $salonNorm)) {
                $prefijo = 'JAC';
            }
        }

        if (!$prefijo && $maquinaBase && preg_match('/^([A-Za-z]+)/', trim($maquinaBase), $matches)) {
            $prefijo = $matches[1];
        }

        if (!$prefijo && $salonNorm !== '') {
            $prefijo = substr($salonNorm, 0, 4);
            $prefijo = rtrim($prefijo, '0123456789');
        }

        if (!$prefijo) {
            $prefijo = 'TEL';
        }

        return trim($prefijo) . ' ' . trim((string) $telar);
    }

    // =========================================================
    // Métodos para cálculo exacto de fechas con calendario
    // (copiados de BalancearTejido para consistencia)
    // =========================================================

    /**
     * Calcular inicio y fin exactos usando calendario (igual que BalancearTejido)
     */
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
            $fin = BalancearTejido::calcularFechaFinalDesdeInicio($r->CalendarioId, $inicio, $horasNecesarias);
            if (!$fin) {
                $fin = $inicio->copy()->addSeconds((int)($horasNecesarias * 3600));
            }
            return [$inicio, $fin, $horasNecesarias];
        }

        // sin calendario, continuo
        $fin = $inicio->copy()->addSeconds((int)($horasNecesarias * 3600));
        return [$inicio, $fin, $horasNecesarias];
    }

    /**
     * Calcular horas de producción necesarias (igual que BalancearTejido)
     */
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

    /**
     * Snap fecha inicio al calendario (igual que BalancearTejido)
     */
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
     * Obtener parámetros del modelo (igual que BalancearTejido)
     */
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

        $m = self::obtenerModeloCodificadoPorSalon($key, $p->SalonTejidoId);
        if (!$m) {
            return [
                'total' => 0.0,
                'no_tiras' => $noTiras,
                'luchaje' => $luchaje,
                'repeticiones' => $rep,
            ];
        }

        return [
            'total' => (float)($m->Total ?? 0),
            'no_tiras' => $noTiras > 0 ? $noTiras : (float)($m->NoTiras ?? 0),
            'luchaje' => $luchaje > 0 ? $luchaje : (float)($m->Luchaje ?? 0),
            'repeticiones' => $rep > 0 ? $rep : (float)($m->Repeticiones ?? 0),
        ];
    }

    /**
     * Sanitizar número (igual que BalancearTejido)
     */
    private static function sanitizeNumber($value): float
    {
        if ($value === null) return 0.0;
        if (is_numeric($value)) return (float)$value;
        $clean = str_replace([',', ' '], '', (string)$value);
        return is_numeric($clean) ? (float)$clean : 0.0;
    }

    // =========================
    // STD DESDE CATÁLOGOS
    // =========================

    private static function aplicarStdDesdeCatalogos(ReqProgramaTejido $p): void
    {
        $tipoTelar = self::resolverTipoTelarStd($p->Maquina ?? null, $p->SalonTejidoId ?? null);
        $telar     = trim((string)($p->NoTelarId ?? ''));
        $fibraId   = trim((string)($p->FibraRizo ?? ''));

        // Default: Normal
        $densidad  = self::resolverDensidadStd($p); // "Normal" o "Alta"

        if ($telar === '' || $fibraId === '') {
            LogFacade::warning('STD: telar o fibra vacíos, no se puede aplicar', [
                'tipoTelar' => $tipoTelar,
                'telar' => $telar,
                'fibra' => $fibraId,
                'programa_id' => $p->Id ?? null,
            ]);
            return;
        }

        $velRow = self::buscarStdVelocidad($tipoTelar, $telar, $fibraId, $densidad);
        $efiRow = self::buscarStdEficiencia($tipoTelar, $telar, $fibraId, $densidad);

        $oldVel = $p->VelocidadSTD ?? null;
        $oldEfi = $p->EficienciaSTD ?? null;

        if ($velRow) {
            $p->VelocidadSTD = (float)$velRow->Velocidad;
        } else {
            LogFacade::warning('STD: No se encontró velocidad', [
                'tipoTelar' => $tipoTelar,
                'telar' => $telar,
                'fibra' => $fibraId,
                'densidad' => $densidad,
                'velocidad_actual' => $oldVel,
            ]);
        }

        if ($efiRow) {
            $efi = (float)$efiRow->Eficiencia;
            if ($efi > 1) $efi = $efi / 100; // 78 -> 0.78
            $p->EficienciaSTD = $efi;
        } else {
            LogFacade::warning('STD: No se encontró eficiencia', [
                'tipoTelar' => $tipoTelar,
                'telar' => $telar,
                'fibra' => $fibraId,
                'densidad' => $densidad,
                'eficiencia_actual' => $oldEfi,
            ]);
        }

        if ((string)$oldVel !== (string)($p->VelocidadSTD ?? null) || (string)$oldEfi !== (string)($p->EficienciaSTD ?? null)) {
            LogFacade::info('STD aplicado', [
                'tipoTelar' => $tipoTelar,
                'telar' => $telar,
                'fibra' => $fibraId,
                'densidad' => $densidad,
                'vel_old' => $oldVel,
                'vel_new' => $p->VelocidadSTD ?? null,
                'efi_old' => $oldEfi,
                'efi_new' => $p->EficienciaSTD ?? null,
            ]);
        }
    }

    /**
     * Tipo de telar para catálogo:
     * - "SMI ..." -> SMITH
     * - "JAC ..." -> JACQUARD
     * - fallback por SalonTejidoId
     */
    private static function resolverTipoTelarStd(?string $maquina, ?string $salonTejidoId): string
    {
        $m = strtoupper(trim((string)$maquina));
        $s = strtoupper(trim((string)$salonTejidoId));

        if ($m !== '') {
            if (str_contains($m, 'SMI')) return 'SMITH';
            if (str_contains($m, 'JAC')) return 'JACQUARD';
        }

        if ($s === 'SMIT' || $s === 'SMITH') return 'SMITH';
        if ($s === 'JAC' || $s === 'JACQ' || $s === 'JACQUARD') return 'JACQUARD';

        return $s !== '' ? $s : 'SMITH';
    }

    /**
     * Densidad del catálogo es texto: "Normal" o "Alta"
     * Default: Normal (como me indicas que usan casi siempre)
     */
    private static function resolverDensidadStd(ReqProgramaTejido $p): string
    {
        if (isset($p->Densidad) && $p->Densidad !== null && $p->Densidad !== '') {
            $d = trim((string)$p->Densidad);
            if (strcasecmp($d, self::DENSIDAD_ALTA) === 0)   return self::DENSIDAD_ALTA;
            if (strcasecmp($d, self::DENSIDAD_NORMAL) === 0) return self::DENSIDAD_NORMAL;
        }
        return self::DENSIDAD_NORMAL;
    }

    private static function buscarStdVelocidad(string $tipoTelar, string $telar, string $fibraId, string $densidad): ?ReqVelocidadStd
    {
        $q = ReqVelocidadStd::query()
            ->where('SalonTejidoId', $tipoTelar) // SMITH/JACQUARD
            ->where('NoTelarId', $telar)
            ->where('FibraId', $fibraId);

        // 1) match exacto densidad (Normal/Alta)
        $row = (clone $q)->where('Densidad', $densidad)->orderBy('Id', 'desc')->first();
        if ($row) return $row;

        // 2) fallback densidad NULL
        $rowNull = (clone $q)->whereNull('Densidad')->orderBy('Id', 'desc')->first();
        if ($rowNull) return $rowNull;

        // 3) fallback cualquiera
        return (clone $q)->orderBy('Id', 'desc')->first();
    }

    private static function buscarStdEficiencia(string $tipoTelar, string $telar, string $fibraId, string $densidad): ?ReqEficienciaStd
    {
        $q = ReqEficienciaStd::query()
            ->where('SalonTejidoId', $tipoTelar) // SMITH/JACQUARD
            ->where('NoTelarId', $telar)
            ->where('FibraId', $fibraId);

        $row = (clone $q)->where('Densidad', $densidad)->orderBy('Id', 'desc')->first();
        if ($row) return $row;

        $rowNull = (clone $q)->whereNull('Densidad')->orderBy('Id', 'desc')->first();
        if ($rowNull) return $rowNull;

        return (clone $q)->orderBy('Id', 'desc')->first();
    }
}
