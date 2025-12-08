<?php

namespace App\Http\Controllers\ProgramaTejido\funciones;

use App\Models\ReqProgramaTejido;
use App\Observers\ReqProgramaTejidoObserver;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB as DBFacade;
use Illuminate\Support\Facades\Log as LogFacade;
use App\Models\ReqModelosCodificados;

class DividirTejido
{
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
            'destinos.*.pedido' => 'nullable|string',
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

        // Verificar si es una redistribución de un grupo existente
        $ordCompartidaExistente = $request->input('ord_compartida_existente');
        $registroIdOriginal = $request->input('registro_id_original');
        $esRedistribucion = !empty($ordCompartidaExistente) && $ordCompartidaExistente !== '0';

        DBFacade::beginTransaction();
        ReqProgramaTejido::unsetEventDispatcher();

        try {
            // Si es redistribución, usar lógica diferente
            if ($esRedistribucion) {
                return self::redistribuirGrupoExistente($request, $ordCompartidaExistente, $destinos, $salonDestino);
            }

            // Obtener el último registro del telar original (el que se va a dividir)
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
            foreach ($destinos as $index => $destino) {
                $pedidoDestino = isset($destino['pedido']) && $destino['pedido'] !== ''
                    ? (float) $destino['pedido']
                    : 0;

                if ($index === 0) {
                    // Primer registro = el original, se actualiza con la nueva cantidad
                    $cantidadParaOriginal = $pedidoDestino;
                } else {
                    // Nuevos registros a crear
                    $destinosNuevos[] = [
                        'telar' => $destino['telar'],
                        'pedido' => $pedidoDestino
                    ];
                    $cantidadesNuevos[] = $pedidoDestino;
                }
            }

            // Validar que la suma de cantidades no exceda el original
            $sumaCantidades = $cantidadParaOriginal + array_sum($cantidadesNuevos);
            if ($sumaCantidades > $cantidadOriginalTotal) {
                DBFacade::rollBack();
                ReqProgramaTejido::observe(ReqProgramaTejidoObserver::class);
                return response()->json([
                    'success' => false,
                    'message' => "La suma de cantidades ({$sumaCantidades}) excede el saldo original ({$cantidadOriginalTotal})"
                ], 400);
            }

            $idsParaObserver = [];
            $totalDivididos = 0;

            // Guardar fechas originales ANTES de modificar el registro
            $fechaInicioOriginal = $registroOriginal->FechaInicio ? Carbon::parse($registroOriginal->FechaInicio) : null;
            $fechaFinalOriginal = $registroOriginal->FechaFinal ? Carbon::parse($registroOriginal->FechaFinal) : null;
            $duracionOriginalSegundos = ($fechaInicioOriginal && $fechaFinalOriginal)
                ? abs($fechaFinalOriginal->getTimestamp() - $fechaInicioOriginal->getTimestamp())
                : 0;

            // === PASO 1: Actualizar el registro original ===
            $registroOriginal->OrdCompartida = $nuevoOrdCompartida;
            $registroOriginal->TotalPedido = $cantidadParaOriginal;
            // SaldoPedido = TotalPedido - Produccion (si hay producción)
            $produccionOriginal = (float) ($registroOriginal->Produccion ?? 0);
            $registroOriginal->SaldoPedido = max(0, $cantidadParaOriginal - $produccionOriginal);
            $registroOriginal->UpdatedAt = now();

            // Recalcular fechas proporcionalmente usando la duración original guardada
            if ($cantidadOriginalTotal > 0 && $cantidadParaOriginal > 0 && $duracionOriginalSegundos > 0 && $fechaInicioOriginal) {
                $factor = $cantidadParaOriginal / $cantidadOriginalTotal;
                $nuevaDuracion = $duracionOriginalSegundos * $factor;
                $registroOriginal->FechaFinal = $fechaInicioOriginal->copy()->addSeconds((int) round($nuevaDuracion))->format('Y-m-d H:i:s');
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

                // Obtener el último registro del telar destino para determinar fecha de inicio
                $ultimoRegistroDestino = ReqProgramaTejido::query()
                    ->salon($salonDestino)
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
                $nuevo->SalonTejidoId = $salonDestino;
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
                    $salonDestino,
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

                // Calcular fechas proporcionalmente
                $nuevoInicio = $fechaInicioBase->copy();
                $nuevo->FechaInicio = $nuevoInicio->format('Y-m-d H:i:s');

                // Calcular duración proporcional usando la duración original guardada al inicio
                if ($duracionOriginalSegundos > 0 && $cantidadOriginalTotal > 0) {
                    $factor = $pedidoDestino / $cantidadOriginalTotal;
                    $duracionNueva = $duracionOriginalSegundos * $factor;
                    $nuevo->FechaFinal = $nuevoInicio->copy()->addSeconds((int) round($duracionNueva))->format('Y-m-d H:i:s');
                } else {
                    $nuevo->FechaFinal = $nuevoInicio->copy()->addDays(30)->format('Y-m-d H:i:s');
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
                $modelo = ReqModelosCodificados::where('TamanoClave', $programa->TamanoClave)->first();
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

        } catch (\Throwable $e) {
            LogFacade::warning('DividirTejido: Error al calcular fórmulas de eficiencia', [
                'error' => $e->getMessage(),
                'programa_id' => $programa->Id ?? null,
            ]);
        }

        return $formulas;
    }

    /**
     * Redistribuir cantidades en un grupo existente de OrdCompartida
     * Actualiza registros existentes y crea nuevos si es necesario
     */
    private static function redistribuirGrupoExistente(Request $request, $ordCompartida, $destinos, $salonDestino)
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

                    if ($nuevaCantidad > 0) {
                        $registro->TotalPedido = $nuevaCantidad;
                        $produccion = (float) ($registro->Produccion ?? 0);
                        $registro->SaldoPedido = max(0, $nuevaCantidad - $produccion);

                        // Recalcular fecha final proporcionalmente
                        if ($segundosPorUnidad > 0) {
                            $nuevaDuracion = $segundosPorUnidad * $nuevaCantidad;
                            $fechaInicioRegistro = $registro->FechaInicio ? Carbon::parse($registro->FechaInicio) : $fechaInicioBase;
                            $registro->FechaFinal = $fechaInicioRegistro->copy()->addSeconds((int) round($nuevaDuracion))->format('Y-m-d H:i:s');
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

                if (empty($telarDestino) || $pedidoDestino <= 0) {
                    continue;
                }

                // Obtener el último registro del telar destino
                $ultimoRegistroDestino = ReqProgramaTejido::query()
                    ->salon($salonDestino)
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
                $nuevo->SalonTejidoId = $salonDestino;
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
                    $salonDestino,
                    $telarDestino
                );

                // Fechas
                $nuevo->FechaInicio = $fechaInicioNuevo->format('Y-m-d H:i:s');

                // Calcular duración proporcional
                if ($segundosPorUnidad > 0) {
                    $duracionNueva = $segundosPorUnidad * $pedidoDestino;
                    $nuevo->FechaFinal = $fechaInicioNuevo->copy()->addSeconds((int) round($duracionNueva))->format('Y-m-d H:i:s');
                } else {
                    $nuevo->FechaFinal = $fechaInicioNuevo->copy()->addDays(30)->format('Y-m-d H:i:s');
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
        $prefijo = null;

        if ($maquinaBase && preg_match('/^([A-Za-z]+)\s*\d*/', trim($maquinaBase), $matches)) {
            $prefijo = $matches[1];
        }

        if (!$prefijo && $salon) {
            $prefijo = substr($salon, 0, 4);
            $prefijo = rtrim($prefijo, '0123456789');
        }

        if (!$prefijo) {
            $prefijo = 'TEL';
        }

        return trim($prefijo) . ' ' . trim((string) $telar);
    }
}

