<?php

namespace App\Http\Controllers\ProgramaTejido\funciones;

use App\Models\ReqProgramaTejido;
use App\Models\ReqCalendarioLine;
use App\Observers\ReqProgramaTejidoObserver;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB as DBFacade;
use Illuminate\Support\Facades\Log as LogFacade;
use App\Models\ReqModelosCodificados;
class DuplicarTejido
{
    /**
     * Duplicar todos los registros de un telar a uno o más telares destino
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public static function duplicar(Request $request)
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
        $salonDestino = $request->input('salon_destino', $salonOrigen); // Usar salón destino o el mismo si no se especifica
        $destinos = $request->input('destinos', []);
        $codArticulo = $request->input('cod_articulo');
        $producto = $request->input('producto');
        $hilo = $request->input('hilo');
        $pedido = $request->input('pedido');
        $flog = $request->input('flog');
        $aplicacion = $request->input('aplicacion');
        $descripcion = $request->input('descripcion');
        $custname = $request->input('custname');
        $inventSizeId = $request->input('invent_size_id');

        DBFacade::beginTransaction();
        ReqProgramaTejido::unsetEventDispatcher();

        try {
            // Obtener SOLO el último registro del telar original
            // Se intenta primero por campo Ultimo=1 y, si no existe, por la última FechaInicio
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
                    'message' => 'No se encontraron registros para duplicar'
                ], 404);
            }

            // Validar calendario antes de duplicar
            // Si el registro original tiene CalendarioId, validar que haya fechas disponibles
            // para el período que tendrá el programa duplicado
            if (!empty($registroOriginal->CalendarioId)) {
                // Calcular las fechas que tendrá el programa duplicado
                // Primero necesitamos determinar la fecha de inicio y fin estimadas
                $fechaInicioEstimada = null;
                $fechaFinEstimada = null;

                // Obtener el último registro del primer telar destino para calcular fecha inicio
                $primerDestino = $destinos[0] ?? null;
                if ($primerDestino) {
                    $telarDestino = $primerDestino['telar'];
                    $ultimoRegistroDestino = ReqProgramaTejido::query()
                        ->salon($salonDestino)
                        ->telar($telarDestino)
                        ->orderBy('FechaInicio', 'desc')
                        ->first();

                    $fechaInicioEstimada = $ultimoRegistroDestino && $ultimoRegistroDestino->FechaFinal
                        ? Carbon::parse($ultimoRegistroDestino->FechaFinal)
                        : ($registroOriginal->FechaInicio
                            ? Carbon::parse($registroOriginal->FechaInicio)
                            : Carbon::now());

                    // Calcular fecha fin estimada (usando la duración del original)
                    $iniOriginal = $registroOriginal->FechaInicio ? Carbon::parse($registroOriginal->FechaInicio) : null;
                    $finOriginal = $registroOriginal->FechaFinal ? Carbon::parse($registroOriginal->FechaFinal) : null;

                    if ($iniOriginal && $finOriginal) {
                        $duracionOriginalSegundos = abs($finOriginal->getTimestamp() - $iniOriginal->getTimestamp());
                        $fechaFinEstimada = $fechaInicioEstimada->copy()->addSeconds($duracionOriginalSegundos);
                    } else {
                        // Fallback: fecha final = fecha inicio + 30 días
                        $fechaFinEstimada = $fechaInicioEstimada->copy()->addDays(30);
                    }
                } else {
                    // Si no hay destinos, usar fechas del original
                    $fechaInicioEstimada = $registroOriginal->FechaInicio ? Carbon::parse($registroOriginal->FechaInicio) : Carbon::now();
                    $fechaFinEstimada = $registroOriginal->FechaFinal ? Carbon::parse($registroOriginal->FechaFinal) : $fechaInicioEstimada->copy()->addDays(30);
                }

                // VALIDAR CALENDARIO: Calcular horas reales de trabajo disponibles
                // Los días que NO están en el calendario se consideran días de descanso
                // Si faltan horas, se calculará cuántos días adicionales se necesitan
                if ($fechaInicioEstimada && $fechaFinEstimada) {
                    // Calcular horas necesarias del programa (usando HorasProd del original si existe)
                    $horasNecesarias = 0;
                    if (!empty($registroOriginal->HorasProd)) {
                        $horasNecesarias = (float) $registroOriginal->HorasProd;
                    } else {
                        // Si no hay HorasProd, calcular basado en la duración del período original
                        $iniOriginal = $registroOriginal->FechaInicio ? Carbon::parse($registroOriginal->FechaInicio) : null;
                        $finOriginal = $registroOriginal->FechaFinal ? Carbon::parse($registroOriginal->FechaFinal) : null;
                        if ($iniOriginal && $finOriginal) {
                            // Usar la duración original como estimación de horas necesarias
                            $horasNecesarias = abs($finOriginal->getTimestamp() - $iniOriginal->getTimestamp()) / 3600.0;
                        }
                    }

                    // Calcular horas reales de trabajo disponibles en el calendario
                    $resultadoValidacion = self::calcularHorasDisponiblesEnCalendario(
                        $registroOriginal->CalendarioId,
                        $fechaInicioEstimada,
                        $fechaFinEstimada,
                        $horasNecesarias
                    );

                    if (!$resultadoValidacion['hay_suficientes_horas']) {
                        // No hay suficientes horas disponibles en el período estimado
                        // Calcular cuántos días adicionales se necesitan
                        $horasFaltantes = $resultadoValidacion['horas_faltantes'];
                        $horasPromedioPorDia = $resultadoValidacion['horas_promedio_por_dia'];

                        if ($horasPromedioPorDia > 0) {
                            // Calcular días adicionales necesarios
                            $diasAdicionales = ceil($horasFaltantes / $horasPromedioPorDia);
                            $fechaFinAjustada = $fechaFinEstimada->copy()->addDays($diasAdicionales);

                            // Validar que con la fecha extendida haya suficientes horas
                            $resultadoAjustado = self::calcularHorasDisponiblesEnCalendario(
                                $registroOriginal->CalendarioId,
                                $fechaInicioEstimada,
                                $fechaFinAjustada,
                                $horasNecesarias
                            );

                            if ($resultadoAjustado['hay_suficientes_horas']) {
                                // Con la fecha extendida hay suficientes horas, permitir duplicación
                                // La fecha final se ajustará durante la duplicación
                                LogFacade::info('DuplicarTejido: Fecha extendida para compensar días de descanso', [
                                    'calendario_id' => $registroOriginal->CalendarioId,
                                    'fecha_inicio' => $fechaInicioEstimada->format('Y-m-d H:i:s'),
                                    'fecha_fin_original' => $fechaFinEstimada->format('Y-m-d H:i:s'),
                                    'fecha_fin_ajustada' => $fechaFinAjustada->format('Y-m-d H:i:s'),
                                    'dias_adicionales' => $diasAdicionales,
                                    'horas_faltantes' => $horasFaltantes
                                ]);
                            } else {
                                // Aún faltan horas incluso extendiendo, verificar si hay fechas en el calendario
                                if ($resultadoAjustado['horas_disponibles'] <= 0) {
                                    // No hay fechas en el calendario para este período
                                    $mensaje = "No se puede duplicar el programa porque no hay fechas disponibles en el calendario '{$registroOriginal->CalendarioId}' para el período estimado (Inicio: {$fechaInicioEstimada->format('Y-m-d H:i')}, Fin: {$fechaFinAjustada->format('Y-m-d H:i')}). Por favor, agregue fechas al calendario antes de duplicar.";

                                    LogFacade::warning('DuplicarTejido: No hay fechas en calendario', [
                                        'calendario_id' => $registroOriginal->CalendarioId,
                                        'fecha_inicio' => $fechaInicioEstimada->format('Y-m-d H:i:s'),
                                        'fecha_fin' => $fechaFinAjustada->format('Y-m-d H:i:s'),
                                        'mensaje' => $mensaje
                                    ]);

                                    return response()->json([
                                        'success' => false,
                                        'message' => $mensaje,
                                        'tipo_error' => 'calendario_sin_fechas',
                                        'calendario_id' => $registroOriginal->CalendarioId,
                                        'fecha_inicio' => $fechaInicioEstimada->format('Y-m-d H:i:s'),
                                        'fecha_fin' => $fechaFinAjustada->format('Y-m-d H:i:s')
                                    ], 422);
                                }
                            }
                        } else {
                            // No se puede calcular promedio (no hay fechas en el calendario)
                            $mensaje = "No se puede duplicar el programa porque no hay fechas disponibles en el calendario '{$registroOriginal->CalendarioId}' para el período estimado (Inicio: {$fechaInicioEstimada->format('Y-m-d H:i')}, Fin: {$fechaFinEstimada->format('Y-m-d H:i')}). Por favor, agregue fechas al calendario antes de duplicar.";

                            return response()->json([
                                'success' => false,
                                'message' => $mensaje,
                                'tipo_error' => 'calendario_sin_fechas',
                                'calendario_id' => $registroOriginal->CalendarioId,
                                'fecha_inicio' => $fechaInicioEstimada->format('Y-m-d H:i:s'),
                                'fecha_fin' => $fechaFinEstimada->format('Y-m-d H:i:s')
                            ], 422);
                        }
                    }
                }
            }

            $totalDuplicados = 0;
            $idsParaObserver = []; // Guardar IDs para disparar observer después

            // Procesar cada destino (telar)
            foreach ($destinos as $destino) {
                $telarDestino = $destino['telar'];
                $pedidoDestino = $destino['pedido'] ?? null;

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

                // Determinar fecha de inicio: usar la fecha final del último registro o la fecha del primer registro original
                $fechaInicioBase = $ultimoRegistroDestino && $ultimoRegistroDestino->FechaFinal
                    ? Carbon::parse($ultimoRegistroDestino->FechaFinal)
                    : ($registroOriginal->FechaInicio
                        ? Carbon::parse($registroOriginal->FechaInicio)
                        : Carbon::now());

                // Duplicar SOLO el último registro del telar original
                $registrosDuplicados = [];
                $original = $registroOriginal;
                if ($original) {
                    $nuevo = $original->replicate();

                    // Actualizar campos básicos
                    $nuevo->SalonTejidoId = $salonDestino;
                    $nuevo->NoTelarId = $telarDestino;
                    $nuevo->EnProceso = 0;    // El duplicado NO está en proceso
                    $nuevo->Ultimo = 1;       // El duplicado ES el último del telar destino
                    $nuevo->CambioHilo = 0;

                    // Limpiar campos que deben ir vacíos
                    $nuevo->Produccion = null;
                    $nuevo->Programado = null;  // inn debe ir en null
                    $nuevo->NoProduccion = null;
                    $nuevo->OrdCompartida = null; // El duplicado NO hereda la orden compartida

                    // Day Scheduling = fecha de hoy
                    $nuevo->ProgramarProd = Carbon::now()->format('Y-m-d');

                    // InventSizeId: si se proporciona uno nuevo (cambió clave modelo), usarlo
                    // Si no se proporciona, mantener el del original (ya viene del replicate)
                    if ($inventSizeId) {
                        $nuevo->InventSizeId = $inventSizeId;
                    }
                    // Si no hay inventSizeId nuevo, el valor del original se mantiene por el replicate()

                    // Actualizar campos con nuevos valores si se proporcionan
                    if ($codArticulo) {
                        $nuevo->ItemId = $codArticulo;
                    }
                    if ($producto) {
                        $nuevo->NombreProducto = $producto;
                    }
                    if ($hilo) {
                        $nuevo->FibraRizo = $hilo;
                    }
                    // Actualizar TotalPedido: usar el valor proporcionado o el del original
                    // TotalPedido = valor proporcionado (si Produccion es null, TotalPedido es el valor directo)
                    if ($pedidoDestino && $pedidoDestino !== '') {
                        $nuevo->TotalPedido = is_numeric($pedidoDestino) ? (float)$pedidoDestino : (float)$pedidoDestino;
                    } elseif ($pedido && $pedido !== '') {
                        $nuevo->TotalPedido = is_numeric($pedido) ? (float)$pedido : (float)$pedido;
                    } elseif (!empty($original->TotalPedido)) {
                        // Si no se proporciona un nuevo pedido, mantener el TotalPedido del original
                        $nuevo->TotalPedido = (float)$original->TotalPedido;
                    } elseif (!empty($original->SaldoPedido)) {
                        // Fallback: usar SaldoPedido del original si TotalPedido está vacío
                        $nuevo->TotalPedido = (float)$original->SaldoPedido;
                    } else {
                        // Último fallback: 0 si no hay ningún valor
                        $nuevo->TotalPedido = 0;
                    }

                    // Asegurar que SaldoPedido sea igual a TotalPedido (ya que Produccion es null)
                    $nuevo->SaldoPedido = $nuevo->TotalPedido;
                    if ($flog) {
                        $nuevo->FlogsId = $flog;
                    }
                    if ($descripcion) {
                        $nuevo->NombreProyecto = $descripcion;
                    }
                    if ($custname) {
                        $nuevo->CustName = $custname;
                    }
                    if ($aplicacion) {
                        $nuevo->AplicacionId = $aplicacion;
                    }

                    // Calcular FechaInicio y FechaFinal del nuevo registro
                    // FechaInicio = FechaFinal del último registro del telar destino (o fecha original si no hay)
                    // FechaFinal = Se calcula proporcionalmente a la cantidad

                    // Nuevo inicio = fecha base (FechaFinal del último registro destino)
                    $nuevoInicio = $fechaInicioBase->copy();
                    $nuevo->FechaInicio = $nuevoInicio->format('Y-m-d H:i:s');

                    // Obtener duración original en segundos
                    $iniOriginal = $original->FechaInicio ? Carbon::parse($original->FechaInicio) : null;
                    $finOriginal = $original->FechaFinal ? Carbon::parse($original->FechaFinal) : null;

                    if ($iniOriginal && $finOriginal) {
                        $duracionOriginalSegundos = abs($finOriginal->getTimestamp() - $iniOriginal->getTimestamp());

                        // Obtener cantidad original y nueva
                        $cantidadOriginal = (float) ($original->TotalPedido ?? $original->SaldoPedido ?? 0);
                        $cantidadNueva = (float) ($nuevo->TotalPedido ?? $nuevo->SaldoPedido ?? 0);

                        // Si las cantidades son diferentes, ajustar duración proporcionalmente
                        // Si cantidad nueva es mayor, durará más; si es menor, durará menos
                        if ($cantidadOriginal > 0 && $cantidadNueva > 0) {
                            $factor = $cantidadNueva / $cantidadOriginal;
                            $duracionNuevaSegundos = $duracionOriginalSegundos * $factor;
                        } else {
                            // Si no hay cantidades válidas, mantener duración original
                            $duracionNuevaSegundos = $duracionOriginalSegundos;
                        }

                        //  CALCULAR FECHA FINAL: Si hay calendario, calcular desde fecha inicio usando horas del calendario
                        // Si no hay calendario, usar duración proporcional
                        if (!empty($nuevo->CalendarioId)) {
                            // IMPORTANTE: Si el registro original tiene DiasEficiencia, usarlo para calcular la fecha final
                            // Esto asegura que el duplicado tenga el mismo DiasEficiencia que el original
                            $diasEficienciaOriginal = !empty($original->DiasEficiencia) ? (float) $original->DiasEficiencia : null;

                            // Primero calcular las fórmulas para obtener HorasProd
                            // Necesitamos calcular HorasProd antes de calcular la fecha final
                            $formulasTemporales = self::calcularFormulasEficiencia($nuevo);

                            // Calcular horas necesarias
                            // IMPORTANTE: Usar siempre HorasProd del original para mantener consistencia
                            // El DiasEficiencia se forzará después para que coincida con el original
                            $horasNecesariasPrograma = 0;
                            if (!empty($original->HorasProd)) {
                                // Usar HorasProd del original para mantener la misma cantidad de horas
                                $horasNecesariasPrograma = (float) $original->HorasProd;

                                LogFacade::info('DuplicarTejido: Usando HorasProd del original para calcular fecha final', [
                                    'horas_prod_original' => $horasNecesariasPrograma,
                                    'dias_eficiencia_original' => $diasEficienciaOriginal ?? 'N/A'
                                ]);
                            } elseif (!empty($formulasTemporales['HorasProd'])) {
                                $horasNecesariasPrograma = (float) $formulasTemporales['HorasProd'];
                            } elseif (!empty($nuevo->HorasProd)) {
                                $horasNecesariasPrograma = (float) $nuevo->HorasProd;
                            } else {
                                // Si no hay HorasProd, usar la duración calculada
                                $horasNecesariasPrograma = $duracionNuevaSegundos / 3600.0;
                            }

                            // Calcular fecha final desde fecha inicio recorriendo líneas del calendario
                            // Esto asegura que se salten los días de descanso automáticamente
                            $nuevoFin = self::calcularFechaFinalDesdeInicio(
                                $nuevo->CalendarioId,
                                $nuevoInicio,
                                $horasNecesariasPrograma
                            );

                            if (!$nuevoFin) {
                                // Si no se pudo calcular, usar fecha base
                                $nuevoFin = $nuevoInicio->copy()->addSeconds((int) round($duracionNuevaSegundos));
                                LogFacade::warning('DuplicarTejido: No se pudo calcular fecha final con calendario, usando fecha base', [
                                    'calendario_id' => $nuevo->CalendarioId,
                                    'fecha_inicio' => $nuevoInicio->format('Y-m-d H:i:s'),
                                    'fecha_fin_base' => $nuevoFin->format('Y-m-d H:i:s')
                                ]);
                            } else {
                                LogFacade::info('DuplicarTejido: Fecha final calculada desde inicio usando calendario', [
                                    'programa_id' => $nuevo->Id ?? 'nuevo',
                                    'calendario_id' => $nuevo->CalendarioId,
                                    'fecha_inicio' => $nuevoInicio->format('Y-m-d H:i:s'),
                                    'fecha_fin_calculada' => $nuevoFin->format('Y-m-d H:i:s'),
                                    'horas_necesarias' => $horasNecesariasPrograma,
                                    'dias_eficiencia_esperados' => $nuevo->DiasEficiencia ?? 'N/A',
                                    'dias_calculados' => $nuevoInicio->diffInDays($nuevoFin)
                                ]);
                            }
                        } else {
                            // Sin calendario, usar duración proporcional
                            $nuevoFin = $nuevoInicio->copy()->addSeconds((int) round($duracionNuevaSegundos));
                        }

                        $nuevo->FechaFinal = $nuevoFin->format('Y-m-d H:i:s');
                    } else {
                        // Fallback: fecha final = fecha inicio + 30 días
                        $nuevo->FechaFinal = $nuevoInicio->copy()->addDays(30)->format('Y-m-d H:i:s');
                    }

                    // Calcular CambioHilo comparando con el último registro del telar destino
                    if ($ultimoRegistroDestino) {
                        $fibraRizoNuevo = trim((string) $nuevo->FibraRizo);
                        $fibraRizoAnterior = trim((string) $ultimoRegistroDestino->FibraRizo);
                        $nuevo->CambioHilo = ($fibraRizoNuevo !== $fibraRizoAnterior) ? '1' : '0';
                    }

                    // Calcular campos calculables (igual que en create)
                    // Esto debe hacerse DESPUÉS de establecer las fechas
                    if ($nuevo->FechaInicio && $nuevo->FechaFinal) {
                        $formulas = self::calcularFormulasEficiencia($nuevo);

                        // IMPORTANTE: Si el registro original tiene DiasEficiencia, forzarlo para mantener consistencia
                        // Esto asegura que el duplicado tenga el mismo DiasEficiencia que el original
                        if (!empty($original->DiasEficiencia)) {
                            $diasEficienciaOriginal = (float) $original->DiasEficiencia;
                            $formulas['DiasEficiencia'] = $diasEficienciaOriginal;

                            // IMPORTANTE: Recalcular StdHrsEfect y ProdKgDia2 usando el DiasEficiencia del original
                            // porque estas fórmulas dependen de DiasEficiencia
                            $cantidad = (float) ($nuevo->SaldoPedido ?? $nuevo->Produccion ?? $nuevo->TotalPedido ?? 0);
                            $pesoCrudo = (float) ($nuevo->PesoCrudo ?? 0);

                            if ($diasEficienciaOriginal > 0) {
                                // Recalcular StdHrsEfect usando el DiasEficiencia del original
                                $stdHrsEfect = ($cantidad / $diasEficienciaOriginal) / 24;
                                $formulas['StdHrsEfect'] = (float) round($stdHrsEfect, 2);

                                // Recalcular ProdKgDia2 usando el StdHrsEfect recalculado
                                if ($pesoCrudo > 0) {
                                    $formulas['ProdKgDia2'] = (float) round((($pesoCrudo * $stdHrsEfect) * 24) / 1000, 2);
                                }

                                LogFacade::info('DuplicarTejido: Forzando DiasEficiencia del original y recalculando fórmulas dependientes', [
                                    'programa_id' => $nuevo->Id ?? 'nuevo',
                                    'dias_eficiencia_original' => $diasEficienciaOriginal,
                                    'std_hrs_efect_recalculado' => $formulas['StdHrsEfect'] ?? 'N/A',
                                    'prod_kg_dia2_recalculado' => $formulas['ProdKgDia2'] ?? 'N/A'
                                ]);
                            }
                        }

                        // Aplicar las fórmulas calculadas al registro
                        foreach ($formulas as $campo => $valor) {
                            $nuevo->{$campo} = $valor;
                        }
                    }

                    $nuevo->CreatedAt = now();
                    $nuevo->UpdatedAt = now();
                    $nuevo->save();

                    $registrosDuplicados[] = $nuevo;
                    $idsParaObserver[] = $nuevo->Id;
                }

                $totalDuplicados += count($registrosDuplicados);
            }

            DBFacade::commit();

            // Re-habilitar observer
            ReqProgramaTejido::observe(ReqProgramaTejidoObserver::class);

            // Disparar observer manualmente para generar las líneas de cada registro duplicado
            // Capturar errores relacionados con calendarios para mostrar alertas al usuario
            $observer = new ReqProgramaTejidoObserver();
            $erroresCalendario = [];
            $programasConErrores = [];

            foreach ($idsParaObserver as $idDuplicado) {
                $registro = ReqProgramaTejido::find($idDuplicado);
                if ($registro) {
                    try {
                        $observer->saved($registro);
                    } catch (\Exception $e) {
                        // Capturar errores del Observer, especialmente relacionados con calendarios
                        $mensajeError = $e->getMessage();

                        // Verificar si el error es por falta de fechas en el calendario
                        $esErrorCalendario = strpos($mensajeError, 'No hay fechas disponibles') !== false ||
                                           strpos($mensajeError, 'no hay líneas de calendario') !== false ||
                                           strpos($mensajeError, 'no tienen HorasTurno') !== false;

                        if ($esErrorCalendario) {
                            $calendarioId = $registro->CalendarioId ?? 'N/A';
                            $fechaInicio = $registro->FechaInicio ?? 'N/A';
                            $fechaFin = $registro->FechaFinal ?? 'N/A';

                            $erroresCalendario[] = [
                                'programa_id' => $idDuplicado,
                                'calendario_id' => $calendarioId,
                                'fecha_inicio' => $fechaInicio,
                                'fecha_fin' => $fechaFin,
                                'mensaje' => $mensajeError
                            ];

                            $programasConErrores[] = $idDuplicado;

                            LogFacade::warning('DuplicarTejido: Error de calendario al generar líneas', [
                                'programa_id' => $idDuplicado,
                                'calendario_id' => $calendarioId,
                                'error' => $mensajeError
                            ]);
                        } else {
                            // Otro tipo de error, también registrarlo
                            LogFacade::error('DuplicarTejido: Error al generar líneas del programa duplicado', [
                                'programa_id' => $idDuplicado,
                                'error' => $mensajeError,
                                'trace' => $e->getTraceAsString()
                            ]);
                        }
                    }
                }
            }

            // Asegurar que los registros duplicados mantengan EnProceso=0 y Ultimo=1
            // (por si el observer o algún trigger lo cambió)
            if (!empty($idsParaObserver)) {
                ReqProgramaTejido::whereIn('Id', $idsParaObserver)
                    ->update(['EnProceso' => 0]);
            }

            // Obtener el primer registro creado para redirigir
            $primerRegistroCreado = !empty($idsParaObserver)
                ? ReqProgramaTejido::find($idsParaObserver[0])
                : null;

            // Construir mensaje de respuesta
            $mensaje = "Telar duplicado correctamente. Se crearon {$totalDuplicados} registro(s) en " . count($destinos) . " telar(es).";

            // Si hay errores de calendario, agregar advertencia al mensaje
            if (!empty($erroresCalendario)) {
                $calendariosAfectados = array_unique(array_column($erroresCalendario, 'calendario_id'));
                $mensaje .= " ⚠️ Advertencia: " . count($erroresCalendario) . " programa(s) no pudieron generar líneas diarias porque no hay fechas disponibles en el calendario '" . implode("', '", $calendariosAfectados) . "'.";
            }

            $respuesta = [
                'success' => true,
                'message' => $mensaje,
                'registros_duplicados' => $totalDuplicados,
                'registro_id' => $primerRegistroCreado?->Id,
                'salon_destino' => $primerRegistroCreado?->SalonTejidoId,
                'telar_destino' => $primerRegistroCreado?->NoTelarId
            ];

            // Incluir información de errores de calendario si los hay
            if (!empty($erroresCalendario)) {
                $respuesta['advertencias'] = [
                    'tipo' => 'calendario_sin_fechas',
                    'total_errores' => count($erroresCalendario),
                    'programas_afectados' => $programasConErrores,
                    'detalles' => $erroresCalendario
                ];
            }

            return response()->json($respuesta);
        } catch (\Throwable $e) {
            DBFacade::rollBack();
            ReqProgramaTejido::observe(ReqProgramaTejidoObserver::class);
            LogFacade::error('duplicarTelar error', [
                'salon' => $salonOrigen,
                'telar' => $telarOrigen,
                'msg' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al duplicar el telar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calcular fórmulas de eficiencia (IGUAL QUE EN FRONTEND crud-manager.js)
     *
     * @param ReqProgramaTejido $programa
     * @return array
     */
    private static function calcularFormulasEficiencia(ReqProgramaTejido $programa): array
    {
        $formulas = [];

        try {
            // Parámetros base
            $vel = (float) ($programa->VelocidadSTD ?? 0);
            $efic = (float) ($programa->EficienciaSTD ?? 0);
            $cantidad = (float) ($programa->SaldoPedido ?? $programa->Produccion ?? $programa->TotalPedido ?? 0);
            $pesoCrudo = (float) ($programa->PesoCrudo ?? 0);
            $noTiras = (float) ($programa->NoTiras ?? 0);
            $luchaje = (float) ($programa->Luchaje ?? 0);
            $repeticiones = (float) ($programa->Repeticiones ?? 0);

            // Normalizar eficiencia si viene en porcentaje (ej: 80 -> 0.8)
            if ($efic > 1) {
                $efic = $efic / 100;
            }

            // Obtener 'Total' del modelo codificado si existe
            $total = 0;
            if ($programa->TamanoClave) {
                $modelo = ReqModelosCodificados::where('TamanoClave', $programa->TamanoClave)->first();
                if ($modelo) {
                    $total = (float) ($modelo->Total ?? 0);
                }
            }

            // Fechas
            $inicio = Carbon::parse($programa->FechaInicio);
            $fin = Carbon::parse($programa->FechaFinal);
            $diffSegundos = abs($fin->getTimestamp() - $inicio->getTimestamp());
            $diffDias = $diffSegundos / (60 * 60 * 24); // Días decimales (igual que frontend)

            // === PASO 1: Calcular StdToaHra (fórmula del frontend) ===
            // StdToaHra = (NoTiras * 60) / ((total + ((luchaje * 0.5) / 0.0254) / repeticiones) / velocidad)
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

            // === PASO 2: Calcular PesoGRM2 (frontend usa 10000, no 1000) ===
            // PesoGRM2 = (PesoCrudo * 10000) / (LargoToalla * AnchoToalla)
            $largoToalla = (float) ($programa->LargoToalla ?? 0);
            $anchoToalla = (float) ($programa->AnchoToalla ?? 0);
            if ($pesoCrudo > 0 && $largoToalla > 0 && $anchoToalla > 0) {
                $formulas['PesoGRM2'] = (float) round(($pesoCrudo * 10000) / ($largoToalla * $anchoToalla), 2);
            }

            // === PASO 3: Calcular DiasEficiencia (días decimales como frontend) ===
            if ($diffDias > 0) {
                $formulas['DiasEficiencia'] = (float) round($diffDias, 2);
            }

            // === PASO 4: Calcular StdDia y ProdKgDia ===
            // StdDia = StdToaHra * eficiencia * 24 (frontend incluye eficiencia)
            // ProdKgDia = (StdDia * PesoCrudo) / 1000
            if ($stdToaHra > 0 && $efic > 0) {
                $stdDia = $stdToaHra * $efic * 24;
                $formulas['StdDia'] = (float) round($stdDia, 2);

                if ($pesoCrudo > 0) {
                    $formulas['ProdKgDia'] = (float) round(($stdDia * $pesoCrudo) / 1000, 2);
                }
            }

            // === PASO 5: Calcular StdHrsEfect y ProdKgDia2 ===
            // StdHrsEfect = (TotalPedido / DiasEficiencia) / 24 (frontend divide entre 24)
            // ProdKgDia2 = ((PesoCrudo * StdHrsEfect) * 24) / 1000
            if ($diffDias > 0) {
                $stdHrsEfect = ($cantidad / $diffDias) / 24;
                $formulas['StdHrsEfect'] = (float) round($stdHrsEfect, 2);

                if ($pesoCrudo > 0) {
                    $formulas['ProdKgDia2'] = (float) round((($pesoCrudo * $stdHrsEfect) * 24) / 1000, 2);
                }
            }

            // === PASO 6: Calcular HorasProd ===
            // HorasProd = TotalPedido / (StdToaHra * EficienciaSTD)
            $horasProd = 0;
            if ($stdToaHra > 0 && $efic > 0) {
                $horasProd = $cantidad / ($stdToaHra * $efic);
                $formulas['HorasProd'] = (float) round($horasProd, 2);
            }

            // === PASO 7: Calcular DiasJornada ===
            // DiasJornada = HorasProd / 24 (frontend usa horasProd, no velocidad)
            if ($horasProd > 0) {
                $formulas['DiasJornada'] = (float) round($horasProd / 24, 2);
            }

        } catch (\Throwable $e) {
            LogFacade::warning('DuplicarTejido: Error al calcular fórmulas de eficiencia', [
                'error' => $e->getMessage(),
                'programa_id' => $programa->Id ?? null,
            ]);
        }

        return $formulas;
    }

    /**
     * Calcular el promedio de horas por día en el calendario
     *
     * Este método calcula el promedio de horas de trabajo por día en el calendario,
     * considerando solo los días que tienen líneas de calendario (días de trabajo).
     *
     * @param string $calendarioId ID del calendario
     * @param Carbon $fechaInicio Fecha de inicio para calcular el promedio
     * @return float Promedio de horas por día de trabajo
     */
    private static function calcularHorasPromedioPorDiaCalendario(
        string $calendarioId,
        Carbon $fechaInicio
    ): float {
        try {
            // Obtener líneas del calendario desde la fecha de inicio hasta 60 días adelante
            // para tener una muestra representativa
            $fechaFinMuestra = $fechaInicio->copy()->addDays(60);

            $lineasCalendario = ReqCalendarioLine::where('CalendarioId', $calendarioId)
                ->where('FechaInicio', '>=', $fechaInicio)
                ->where('FechaInicio', '<=', $fechaFinMuestra)
                ->get();

            if ($lineasCalendario->isEmpty()) {
                LogFacade::warning('calcularHorasPromedioPorDiaCalendario: No hay líneas en el calendario', [
                    'calendario_id' => $calendarioId,
                    'fecha_inicio' => $fechaInicio->format('Y-m-d H:i:s')
                ]);
                return 0;
            }

            // Calcular el total de horas y el número de días únicos con trabajo
            $totalHoras = 0;
            $diasUnicos = [];

            foreach ($lineasCalendario as $linea) {
                $horasTurno = (float) ($linea->HorasTurno ?? 0);
                $totalHoras += $horasTurno;

                // Obtener el día de la línea
                $fechaLinea = Carbon::parse($linea->FechaInicio);
                $diaKey = $fechaLinea->format('Y-m-d');

                if (!isset($diasUnicos[$diaKey])) {
                    $diasUnicos[$diaKey] = true;
                }
            }

            $numDiasTrabajo = count($diasUnicos);

            if ($numDiasTrabajo > 0) {
                $promedio = $totalHoras / $numDiasTrabajo;

                LogFacade::info('calcularHorasPromedioPorDiaCalendario: Promedio calculado', [
                    'calendario_id' => $calendarioId,
                    'total_horas' => $totalHoras,
                    'num_dias_trabajo' => $numDiasTrabajo,
                    'promedio_horas_por_dia' => $promedio
                ]);

                return $promedio;
            }

            return 0;
        } catch (\Throwable $e) {
            LogFacade::error('Error al calcular horas promedio por día del calendario', [
                'calendario_id' => $calendarioId,
                'fecha_inicio' => $fechaInicio->format('Y-m-d H:i:s'),
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Calcular horas reales de trabajo disponibles en el calendario para un período
     *
     * Este método suma las HorasTurno de todas las líneas de calendario que cubren
     * el período. Los días que NO están en el calendario se consideran días de descanso
     * y no se cuentan como horas de trabajo.
     *
     * @param string $calendarioId ID del calendario
     * @param Carbon $fechaInicio Fecha de inicio del período
     * @param Carbon $fechaFin Fecha de fin del período
     * @param float $horasNecesarias Horas de trabajo necesarias para el programa
     * @return array Con información sobre horas disponibles, horas faltantes, promedio por día, etc.
     */
    private static function calcularHorasDisponiblesEnCalendario(
        string $calendarioId,
        Carbon $fechaInicio,
        Carbon $fechaFin,
        float $horasNecesarias
    ): array {
        try {
            // Buscar todas las líneas del calendario que cubren el período
            $lineasCalendario = ReqCalendarioLine::where('CalendarioId', $calendarioId)
                ->where(function($query) use ($fechaInicio, $fechaFin) {
                    // Líneas que se solapan con el período
                    $query->where(function($q) use ($fechaInicio, $fechaFin) {
                        $q->where('FechaInicio', '<=', $fechaFin)
                          ->where('FechaFin', '>=', $fechaInicio);
                    });
                })
                ->orderBy('FechaInicio')
                ->get();

            // Si no hay líneas, no hay horas disponibles
            if ($lineasCalendario->isEmpty()) {
                return [
                    'hay_suficientes_horas' => false,
                    'horas_disponibles' => 0,
                    'horas_necesarias' => $horasNecesarias,
                    'horas_faltantes' => $horasNecesarias,
                    'horas_promedio_por_dia' => 0,
                    'dias_con_trabajo' => 0,
                    'total_lineas' => 0
                ];
            }

            // Sumar las HorasTurno de todas las líneas que cubren el período
            // Los días que NO están en el calendario se consideran días de descanso
            // Solo sumamos las HorasTurno de las líneas que se solapan con el período
            $horasDisponibles = 0;
            $diasConTrabajo = [];

            foreach ($lineasCalendario as $linea) {
                $lineaInicio = Carbon::parse($linea->FechaInicio);
                $lineaFin = Carbon::parse($linea->FechaFin);
                $horasTurno = (float) ($linea->HorasTurno ?? 0);

                if ($horasTurno <= 0) {
                    continue;
                }

                // Calcular la intersección entre la línea y el período del programa
                $interseccionInicio = max($lineaInicio->timestamp, $fechaInicio->timestamp);
                $interseccionFin = min($lineaFin->timestamp, $fechaFin->timestamp);

                // Si hay intersección, sumar las HorasTurno completas del turno
                // (no calcular porcentajes, usar las horas completas del turno)
                if ($interseccionInicio < $interseccionFin) {
                    // Sumar las HorasTurno completas del turno
                    // Esto representa las horas reales de trabajo disponibles en ese turno
                    $horasDisponibles += $horasTurno;

                    // Registrar días con trabajo (usar fecha de inicio de la línea para agrupar por día)
                    // Agrupar por día para calcular el promedio de horas por día
                    $fechaDiaInicio = Carbon::createFromTimestamp($lineaInicio->timestamp)->format('Y-m-d');
                    $fechaDiaFin = Carbon::createFromTimestamp($lineaFin->timestamp)->format('Y-m-d');

                    // Agregar todos los días que cubre esta línea
                    $fechaActual = Carbon::createFromTimestamp($lineaInicio->timestamp)->startOfDay();
                    $fechaFinLinea = Carbon::createFromTimestamp($lineaFin->timestamp)->startOfDay();

                    while ($fechaActual->lte($fechaFinLinea)) {
                        $fechaDia = $fechaActual->format('Y-m-d');
                        if (!in_array($fechaDia, $diasConTrabajo)) {
                            $diasConTrabajo[] = $fechaDia;
                        }
                        $fechaActual->addDay();
                    }
                }
            }

            // Calcular promedio de horas por día de trabajo
            // Solo considerar días que tienen líneas de calendario (días de trabajo)
            // Los días sin líneas son días de descanso y no se cuentan
            $horasPromedioPorDia = 0;
            if (count($diasConTrabajo) > 0) {
                // Calcular horas totales por día (puede haber múltiples turnos en un día)
                $horasPorDia = [];
                foreach ($lineasCalendario as $linea) {
                    $lineaInicio = Carbon::parse($linea->FechaInicio);
                    $lineaFin = Carbon::parse($linea->FechaFin);
                    $horasTurno = (float) ($linea->HorasTurno ?? 0);

                    if ($horasTurno <= 0) continue;

                    // Verificar si esta línea se solapa con el período
                    $interseccionInicio = max($lineaInicio->timestamp, $fechaInicio->timestamp);
                    $interseccionFin = min($lineaFin->timestamp, $fechaFin->timestamp);

                    if ($interseccionInicio < $interseccionFin) {
                        // Agregar las horas de este turno a los días que cubre
                        $fechaActual = Carbon::createFromTimestamp($lineaInicio->timestamp)->startOfDay();
                        $fechaFinLinea = Carbon::createFromTimestamp($lineaFin->timestamp)->startOfDay();

                        while ($fechaActual->lte($fechaFinLinea)) {
                            $fechaDia = $fechaActual->format('Y-m-d');
                            if (!isset($horasPorDia[$fechaDia])) {
                                $horasPorDia[$fechaDia] = 0;
                            }
                            $horasPorDia[$fechaDia] += $horasTurno;
                            $fechaActual->addDay();
                        }
                    }
                }

                // Calcular promedio: sumar todas las horas y dividir entre días con trabajo
                $totalHoras = array_sum($horasPorDia);
                $diasConTrabajoUnicos = count($horasPorDia);
                $horasPromedioPorDia = $diasConTrabajoUnicos > 0 ? ($totalHoras / $diasConTrabajoUnicos) : 0;
            }

            // Verificar si hay suficientes horas
            $horasFaltantes = max(0, $horasNecesarias - $horasDisponibles);
            $haySuficientesHoras = $horasDisponibles >= $horasNecesarias;

            return [
                'hay_suficientes_horas' => $haySuficientesHoras,
                'horas_disponibles' => $horasDisponibles,
                'horas_necesarias' => $horasNecesarias,
                'horas_faltantes' => $horasFaltantes,
                'horas_promedio_por_dia' => $horasPromedioPorDia,
                'dias_con_trabajo' => count($diasConTrabajo),
                'total_lineas' => $lineasCalendario->count()
            ];

        } catch (\Throwable $e) {
            LogFacade::error('Error al calcular horas disponibles en calendario', [
                'calendario_id' => $calendarioId,
                'fecha_inicio' => $fechaInicio->format('Y-m-d H:i:s'),
                'fecha_fin' => $fechaFin->format('Y-m-d H:i:s'),
                'error' => $e->getMessage()
            ]);

            // En caso de error, retornar que no hay horas disponibles
            return [
                'hay_suficientes_horas' => false,
                'horas_disponibles' => 0,
                'horas_necesarias' => $horasNecesarias,
                'horas_faltantes' => $horasNecesarias,
                'horas_promedio_por_dia' => 0,
                'dias_con_trabajo' => 0,
                'total_lineas' => 0
            ];
        }
    }

    /**
     * Calcular fecha final desde fecha inicio recorriendo líneas del calendario
     *
     * Este método recorre las líneas del calendario desde la fecha de inicio,
     * sumando las HorasTurno de cada línea hasta alcanzar las horas necesarias.
     * Los días que NO están en el calendario se saltan automáticamente (días de descanso).
     *
     * Similar a la lógica del frontend en calendario-manager.js _sumarHorasConLineasReales
     *
     * @param string $calendarioId ID del calendario
     * @param Carbon $fechaInicio Fecha de inicio del programa
     * @param float $horasNecesarias Horas de trabajo necesarias (HorasProd)
     * @return Carbon|null Fecha final calculada, o null si no hay fechas en el calendario
     */
    private static function calcularFechaFinalDesdeInicio(
        string $calendarioId,
        Carbon $fechaInicio,
        float $horasNecesarias
    ): ?Carbon {
        try {
            // Obtener TODAS las líneas del calendario ordenadas por fecha
            // No limitar la consulta, necesitamos todas las líneas para calcular correctamente
            $lineasCalendario = ReqCalendarioLine::where('CalendarioId', $calendarioId)
                ->orderBy('FechaInicio')
                ->orderBy('FechaFin')
                ->get();

            if ($lineasCalendario->isEmpty()) {
                LogFacade::warning('calcularFechaFinalDesdeInicio: No hay líneas en el calendario', [
                    'calendario_id' => $calendarioId
                ]);
                return null;
            }

            // Empezar desde la fecha de inicio exacta
            $fechaActual = $fechaInicio->copy();

            // Buscar la línea que contiene la fecha de inicio o la primera línea después
            $indiceInicio = -1;
            for ($i = 0; $i < $lineasCalendario->count(); $i++) {
                $linea = $lineasCalendario[$i];
                $lineaInicio = Carbon::parse($linea->FechaInicio);
                $lineaFin = Carbon::parse($linea->FechaFin);

                // Si la fecha está dentro de este período, empezar desde aquí
                if ($fechaActual->gte($lineaInicio) && $fechaActual->lte($lineaFin)) {
                    $indiceInicio = $i;
                    break;
                }

                // Si la fecha es anterior a esta línea, empezar desde esta línea
                // (esto significa que hay días de descanso antes de esta línea)
                // IMPORTANTE: Ajustar la fecha al inicio de esta línea para saltar los días de descanso
                // Esto EXTENDE la fecha hacia adelante, no la recorta
                if ($fechaActual->lt($lineaInicio)) {
                    $indiceInicio = $i;
                    $fechaActual = $lineaInicio->copy(); // Saltar al inicio de la siguiente línea disponible
                    break;
                }
            }

            if ($indiceInicio === -1) {
                LogFacade::warning('calcularFechaFinalDesdeInicio: No se encontró línea que contenga la fecha de inicio', [
                    'calendario_id' => $calendarioId,
                    'fecha_inicio' => $fechaInicio->format('Y-m-d H:i:s')
                ]);
                return null;
            }

            // Recorrer las líneas sumando horas hasta alcanzar las horas necesarias
            $horasRestantes = $horasNecesarias;
            $horasTotalesProcesadas = 0; // Contador para verificar que se están sumando todas las horas

            LogFacade::info('calcularFechaFinalDesdeInicio: Iniciando cálculo', [
                'calendario_id' => $calendarioId,
                'fecha_inicio' => $fechaInicio->format('Y-m-d H:i:s'),
                'horas_necesarias' => $horasNecesarias,
                'indice_inicio' => $indiceInicio,
                'total_lineas' => $lineasCalendario->count(),
                'fecha_actual_inicial' => $fechaActual->format('Y-m-d H:i:s')
            ]);

            for ($i = $indiceInicio; $i < $lineasCalendario->count() && $horasRestantes > 0.0001; $i++) {
                $linea = $lineasCalendario[$i];
                $lineaInicio = Carbon::parse($linea->FechaInicio);
                $lineaFin = Carbon::parse($linea->FechaFin);
                $horasTurno = (float) ($linea->HorasTurno ?? 0);

                // Si la fecha actual está antes del inicio de esta línea, avanzar al inicio
                // (esto salta los días de descanso entre líneas - EXTENDIENDO hacia adelante)
                // IMPORTANTE: Esto extiende la fecha hacia adelante para compensar días de descanso
                if ($fechaActual->lt($lineaInicio)) {
                    $fechaActual = $lineaInicio->copy();
                    LogFacade::debug('calcularFechaFinalDesdeInicio: Saltando días de descanso', [
                        'fecha_anterior' => $fechaActual->copy()->subDays(1)->format('Y-m-d H:i:s'),
                        'fecha_nueva' => $fechaActual->format('Y-m-d H:i:s'),
                        'linea_inicio' => $lineaInicio->format('Y-m-d H:i:s'),
                        'dias_saltados' => $fechaActual->diffInDays($lineaInicio->copy()->subDays(1))
                    ]);
                }

                // Si la fecha actual está después del fin de esta línea, saltar a la siguiente
                // (esto puede pasar si hay líneas que ya pasaron)
                if ($fechaActual->gt($lineaFin)) {
                    continue;
                }

                // Calcular horas disponibles en este período
                $horasDisponiblesEnEstePeriodo = 0;

                if ($horasTurno > 0) {
                    // Calcular qué porcentaje del turno queda desde fechaActual hasta finLinea
                    // IMPORTANTE: Si la fecha actual está dentro de la línea, calcular las horas restantes
                    // Si la fecha actual está antes del inicio de la línea, usar todas las horas del turno
                    if ($fechaActual->gte($lineaInicio) && $fechaActual->lte($lineaFin)) {
                        // La fecha está dentro de esta línea, calcular horas restantes proporcionalmente
                        $duracionTotalTurno = $lineaFin->diffInSeconds($lineaInicio, absolute: true) / 3600.0;
                        $horasDesdeInicio = abs($fechaActual->diffInSeconds($lineaInicio, absolute: true)) / 3600.0;

                        if ($duracionTotalTurno > 0 && $horasDesdeInicio >= 0) {
                            $porcentajeConsumido = min(1.0, $horasDesdeInicio / $duracionTotalTurno);
                            $horasDisponiblesEnEstePeriodo = $horasTurno * (1 - $porcentajeConsumido);

                            // IMPORTANTE: Si la fecha actual está muy cerca del inicio (menos de 1 minuto),
                            // considerar que está al inicio y usar todas las horas del turno
                            if ($horasDesdeInicio < 0.0167) { // Menos de 1 minuto
                                $horasDisponiblesEnEstePeriodo = $horasTurno;
                            }
                        } else {
                            // Si la duración es 0 o hay un problema, usar las horas completas del turno
                            $horasDisponiblesEnEstePeriodo = $horasTurno;
                        }
                    } else {
                        // La fecha está al inicio de la línea (saltó días de descanso), usar todas las horas
                        $horasDisponiblesEnEstePeriodo = $horasTurno;
                    }
                } else {
                    // Si no hay HorasTurno definido, usar la diferencia de tiempo real
                    $horasDisponiblesEnEstePeriodo = $fechaActual->diffInHours($lineaFin, absolute: true);
                }

                if ($horasRestantes <= $horasDisponiblesEnEstePeriodo) {
                    // Las horas caben en este período
                    // Calcular la fecha final proporcionalmente
                    if ($horasDisponiblesEnEstePeriodo > 0 && $horasRestantes > 0) {
                        $porcentajeUsado = $horasRestantes / $horasDisponiblesEnEstePeriodo;
                        $tiempoDisponible = $fechaActual->diffInSeconds($lineaFin, absolute: true);
                        $tiempoUsado = $tiempoDisponible * $porcentajeUsado;
                        $fechaActual->addSeconds((int) round($tiempoUsado));
                        $horasTotalesProcesadas += $horasRestantes; // Sumar las horas usadas
                    }
                    $horasRestantes = 0;
                } else {
                    // Consumir todo este período y pasar al siguiente
                    $horasRestantes -= $horasDisponiblesEnEstePeriodo;
                    $horasTotalesProcesadas += $horasDisponiblesEnEstePeriodo; // Sumar las horas consumidas
                    $fechaActual = $lineaFin->copy();
                }

                // Log detallado para depuración
                if ($i % 10 == 0 || $horasRestantes < 10) {
                    LogFacade::debug('calcularFechaFinalDesdeInicio: Procesando línea', [
                        'indice' => $i,
                        'linea_inicio' => $lineaInicio->format('Y-m-d H:i:s'),
                        'linea_fin' => $lineaFin->format('Y-m-d H:i:s'),
                        'horas_turno' => $horasTurno,
                        'horas_disponibles_periodo' => $horasDisponiblesEnEstePeriodo,
                        'horas_restantes' => $horasRestantes,
                        'fecha_actual' => $fechaActual->format('Y-m-d H:i:s')
                    ]);
                }
            }

            LogFacade::info('calcularFechaFinalDesdeInicio: Terminó bucle principal', [
                'calendario_id' => $calendarioId,
                'horas_necesarias' => $horasNecesarias,
                'horas_totales_procesadas' => $horasTotalesProcesadas,
                'horas_restantes' => $horasRestantes,
                'fecha_actual' => $fechaActual->format('Y-m-d H:i:s'),
                'lineas_procesadas' => $i - $indiceInicio,
                'diferencia_horas' => $horasNecesarias - $horasTotalesProcesadas
            ]);

            // Si aún quedan horas después de recorrer todas las líneas,
            // buscar más líneas del calendario que estén después de la última línea procesada
            if ($horasRestantes > 0.0001) {
                LogFacade::info('calcularFechaFinalDesdeInicio: Quedan horas después de recorrer todas las líneas, buscando más líneas', [
                    'calendario_id' => $calendarioId,
                    'horas_restantes' => $horasRestantes,
                    'fecha_actual' => $fechaActual->format('Y-m-d H:i:s')
                ]);

                // Buscar líneas adicionales del calendario que estén después de la fecha actual
                $lineasAdicionales = ReqCalendarioLine::where('CalendarioId', $calendarioId)
                    ->where('FechaInicio', '>', $fechaActual)
                    ->orderBy('FechaInicio')
                    ->get();

                // Continuar recorriendo las líneas adicionales
                foreach ($lineasAdicionales as $linea) {
                    if ($horasRestantes <= 0.0001) {
                        break;
                    }

                    $lineaInicio = Carbon::parse($linea->FechaInicio);
                    $lineaFin = Carbon::parse($linea->FechaFin);
                    $horasTurno = (float) ($linea->HorasTurno ?? 0);

                    // Si la fecha actual está antes del inicio de esta línea, avanzar al inicio
                    // (esto salta los días de descanso entre líneas)
                    if ($fechaActual->lt($lineaInicio)) {
                        $fechaActual = $lineaInicio->copy();
                    }

                    // Si la fecha actual está después del fin de esta línea, saltar a la siguiente
                    if ($fechaActual->gt($lineaFin)) {
                        continue;
                    }

                    // Calcular horas disponibles en este período
                    $horasDisponiblesEnEstePeriodo = 0;

                    if ($horasTurno > 0) {
                        // Calcular qué porcentaje del turno queda desde fechaActual hasta finLinea
                        $duracionTotalTurno = $lineaFin->diffInSeconds($lineaInicio, absolute: true) / 3600.0;
                        $horasDesdeInicio = $fechaActual->diffInSeconds($lineaInicio, absolute: true) / 3600.0;

                        if ($duracionTotalTurno > 0) {
                            $porcentajeConsumido = $horasDesdeInicio / $duracionTotalTurno;
                            $horasDisponiblesEnEstePeriodo = $horasTurno * (1 - $porcentajeConsumido);
                        } else {
                            $horasDisponiblesEnEstePeriodo = $horasTurno;
                        }
                    } else {
                        $horasDisponiblesEnEstePeriodo = $fechaActual->diffInHours($lineaFin, absolute: true);
                    }

                    if ($horasRestantes <= $horasDisponiblesEnEstePeriodo) {
                        // Las horas caben en este período
                        if ($horasDisponiblesEnEstePeriodo > 0 && $horasRestantes > 0) {
                            $porcentajeUsado = $horasRestantes / $horasDisponiblesEnEstePeriodo;
                            $tiempoDisponible = $fechaActual->diffInSeconds($lineaFin, absolute: true);
                            $tiempoUsado = $tiempoDisponible * $porcentajeUsado;
                            $fechaActual->addSeconds((int) round($tiempoUsado));
                        }
                        $horasRestantes = 0;
                    } else {
                        // Consumir todo este período y pasar al siguiente
                        $horasRestantes -= $horasDisponiblesEnEstePeriodo;
                        $fechaActual = $lineaFin->copy();
                    }
                }

                // Si aún quedan horas después de buscar todas las líneas adicionales,
                // extender día por día hasta alcanzar las horas restantes
                if ($horasRestantes > 0.0001) {
                    LogFacade::warning('calcularFechaFinalDesdeInicio: Aún quedan horas después de buscar líneas adicionales', [
                        'calendario_id' => $calendarioId,
                        'horas_restantes' => $horasRestantes,
                        'fecha_actual' => $fechaActual->format('Y-m-d H:i:s')
                    ]);

                    // Extender la fecha final día por día hasta alcanzar las horas restantes
                    while ($horasRestantes > 0.0001) {
                        $fechaActual->addDay();

                        // Verificar si este día tiene líneas de calendario (es día de trabajo)
                        $fechaDiaInicio = $fechaActual->copy()->startOfDay();
                        $fechaDiaFin = $fechaActual->copy()->endOfDay();

                        $lineasDelDia = ReqCalendarioLine::where('CalendarioId', $calendarioId)
                            ->where(function($query) use ($fechaDiaInicio, $fechaDiaFin) {
                                $query->where('FechaInicio', '<=', $fechaDiaFin)
                                      ->where('FechaFin', '>=', $fechaDiaInicio);
                            })
                            ->get();

                        // Si hay líneas en este día, restar las horas
                        if ($lineasDelDia->isNotEmpty()) {
                            $horasDelDia = 0;
                            foreach ($lineasDelDia as $linea) {
                                $horasDelDia += (float) ($linea->HorasTurno ?? 0);
                            }
                            $horasRestantes -= $horasDelDia;
                        }
                        // Si no hay líneas, es día de descanso y no se restan horas, pero se avanza el día

                        // Límite de seguridad
                        if ($fechaActual->diffInDays($fechaInicio) > 365) {
                            break;
                        }
                    }
                }
            }

            return $fechaActual;

        } catch (\Throwable $e) {
            LogFacade::error('Error al calcular fecha final desde inicio', [
                'calendario_id' => $calendarioId,
                'fecha_inicio' => $fechaInicio->format('Y-m-d H:i:s'),
                'horas_necesarias' => $horasNecesarias,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }
}

