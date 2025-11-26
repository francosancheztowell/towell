<?php

namespace App\Http\Controllers\ProgramaTejido\funciones;

use App\Models\ReqProgramaTejido;
use App\Observers\ReqProgramaTejidoObserver;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB as DBFacade;
use Illuminate\Support\Facades\Log as LogFacade;

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

                        // Calcular fecha final
                        $nuevoFin = $nuevoInicio->copy()->addSeconds((int) round($duracionNuevaSegundos));
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
            $observer = new ReqProgramaTejidoObserver();
            foreach ($idsParaObserver as $idDuplicado) {
                $registro = ReqProgramaTejido::find($idDuplicado);
                if ($registro) {
                    $observer->saved($registro);
                }
            }

            // Asegurar que los registros duplicados mantengan EnProceso=0 y Ultimo=1
            // (por si el observer o algún trigger lo cambió)
            if (!empty($idsParaObserver)) {
                ReqProgramaTejido::whereIn('Id', $idsParaObserver)
                    ->update(['EnProceso' => 0]);
            }

            return response()->json([
                'success' => true,
                'message' => "Telar duplicado correctamente. Se crearon {$totalDuplicados} registro(s) en " . count($destinos) . " telar(es).",
                'registros_duplicados' => $totalDuplicados
            ]);
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
     * Calcular fórmulas de eficiencia (igual que ReqProgramaTejidoObserver)
     *
     * @param ReqProgramaTejido $programa
     * @return array
     */
    private static function calcularFormulasEficiencia(ReqProgramaTejido $programa): array
    {
        $formulas = [];

        try {
            // Parámetros base (mismos valores por defecto que el observer)
            $vel = (float) ($programa->VelocidadSTD ?? 0);
            $efic = (float) ($programa->EficienciaSTD ?? 0);
            $cantidad = (float) ($programa->SaldoPedido ?? $programa->Produccion ?? $programa->TotalPedido ?? 0);
            $pesoCrudo = (float) ($programa->PesoCrudo ?? 0);
            $noTiras = (float) ($programa->NoTiras ?? 0);
            $luchaje = (float) ($programa->Luchaje ?? 0);
            $repeticiones = (float) ($programa->Repeticiones ?? 0);

            // Fechas
            $inicio = Carbon::parse($programa->FechaInicio);
            $fin = Carbon::parse($programa->FechaFinal);
            $diffSegundos = abs($fin->getTimestamp() - $inicio->getTimestamp());
            $diffHoras = $diffSegundos / 3600; // Horas decimales

            // === PASO 1: Calcular StdToaHra ===
            // StdToaHra = (NoTiras * 60) / (Luchaje * VelocidadSTD / 10000)
            if ($noTiras > 0 && $luchaje > 0 && $vel > 0) {
                $stdToaHra = ($noTiras * 60) / ($luchaje * $vel / 10000);
                $formulas['StdToaHra'] = (float) round($stdToaHra, 6);
            }

            $stdToaHra = $formulas['StdToaHra'] ?? 0;

            // === PASO 2: Calcular PesoGRM2 ===
            // PesoGRM2 = (PesoCrudo * 1000) / (LargoToalla * AnchoToalla)
            $largoToalla = (float) ($programa->LargoToalla ?? 0);
            $anchoToalla = (float) ($programa->AnchoToalla ?? 0);
            if ($pesoCrudo > 0 && $largoToalla > 0 && $anchoToalla > 0) {
                $formulas['PesoGRM2'] = (float) round(($pesoCrudo * 1000) / ($largoToalla * $anchoToalla), 6);
            }

            // === PASO 3: Calcular DiasEficiencia (en formato d.HH) ===
            // DiasEficiencia: días.horas (formato d.HH)
            if ($diffHoras > 0) {
                $diasEnteros = (int) floor($diffHoras / 24);
                $horasRestantes = $diffHoras % 24;
                $horasEnteras = (int) floor($horasRestantes);
                $diasEficienciaDH = (float) ("$diasEnteros.$horasEnteras");

                $formulas['DiasEficiencia'] = (float) round($diasEficienciaDH, 2);
            }

            // === PASO 4: Calcular StdDia y ProdKgDia ===
            // StdDia = StdToaHra * 24
            // ProdKgDia = (StdDia * PesoCrudo) / 1000
            if ($stdToaHra > 0) {
                $stdDia = $stdToaHra * 24;
                $formulas['StdDia'] = (float) round($stdDia, 6);

                if ($pesoCrudo > 0) {
                    $formulas['ProdKgDia'] = (float) round(($stdDia * $pesoCrudo) / 1000, 6);
                }
            }

            // === PASO 5: Calcular StdHrsEfect y ProdKgDia2 ===
            // StdHrsEfect = TotalPedido / HorasDiferencia
            // ProdKgDia2 = ((PesoCrudo * StdHrsEfect) * 24) / 1000
            if ($diffHoras > 0) {
                $stdHrsEfect = $cantidad / $diffHoras;
                $formulas['StdHrsEfect'] = (float) round($stdHrsEfect, 6);

                if ($pesoCrudo > 0) {
                    $formulas['ProdKgDia2'] = (float) round((($pesoCrudo * $stdHrsEfect) * 24) / 1000, 6);
                }
            }

            // === PASO 6: Calcular DiasJornada ===
            // DiasJornada = VelocidadSTD / 24
            $formulas['DiasJornada'] = (float) round($vel / 24, 6);

            // === PASO 7: Calcular HorasProd ===
            // HorasProd = TotalPedido / (StdToaHra * EficienciaSTD)
            if ($stdToaHra > 0 && $efic > 0) {
                $formulas['HorasProd'] = (float) round($cantidad / ($stdToaHra * $efic), 6);
            }

        } catch (\Throwable $e) {
            LogFacade::warning('DuplicarTejido: Error al calcular fórmulas de eficiencia', [
                'error' => $e->getMessage(),
                'programa_id' => $programa->Id ?? null,
            ]);
        }

        return $formulas;
    }
}

