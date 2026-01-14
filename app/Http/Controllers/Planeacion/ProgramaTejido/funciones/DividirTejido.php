<?php

namespace App\Http\Controllers\Planeacion\ProgramaTejido\funciones;

use App\Models\ReqProgramaTejido;
use App\Observers\ReqProgramaTejidoObserver;
use App\Http\Controllers\Planeacion\ProgramaTejido\helper\TejidoHelpers;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB as DBFacade;
use Illuminate\Support\Facades\Log as LogFacade;
use App\Models\ReqModelosCodificados;
use App\Helpers\StringTruncator;

class DividirTejido
{
    /** Valores v├ílidos en cat├ílogo */
    /**
     * Dividir un registro de telar entre m├║ltiples telares destino
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

        // Verificar si es una redistribuci├│n de un grupo existente
        $ordCompartidaExistente = $request->input('ord_compartida_existente');
        $esRedistribucion = !empty($ordCompartidaExistente) && $ordCompartidaExistente !== '0';

        DBFacade::beginTransaction();
        ReqProgramaTejido::unsetEventDispatcher();

        try {
            // Si es redistribuci├│n, usar l├│gica diferente
            if ($esRedistribucion) {
                return self::redistribuirGrupoExistente($request, $ordCompartidaExistente, $destinos, $salonDestino, $hilo);
            }

            // Obtener el registro espec├¡fico a dividir:
            // 1) Si viene registro_id_original, usar ese.
            // 2) Si no, usar el ├║ltimo del telar (fallback anterior).
            if (!empty($registroIdOriginal)) {
                $registroOriginal = ReqProgramaTejido::find($registroIdOriginal);
                // Verificar que el registro encontrado pertenece al telar y sal├│n correctos
                if ($registroOriginal && ($registroOriginal->SalonTejidoId !== $salonOrigen || $registroOriginal->NoTelarId !== $telarOrigen)) {
                    $registroOriginal = null; // No es del telar correcto, usar fallback
                }
            }

            // Fallback: obtener el ├║ltimo registro del telar
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
                    'message' => 'No se encontr├│ el registro para dividir'
                ], 404);
            }

            // Obtener el siguiente n├║mero consecutivo de OrdCompartida
            $maxOrdCompartida = ReqProgramaTejido::max('OrdCompartida') ?? 0;
            $nuevoOrdCompartida = $maxOrdCompartida + 1;

            // El primer destino es el registro original (ya viene bloqueado en el modal)
            // Los dem├ís destinos son los telares donde se dividir├í
            $destinosNuevos = [];
            $cantidadOriginalTotal = (float) ($registroOriginal->SaldoPedido ?? $registroOriginal->TotalPedido ?? 0);
            $cantidadParaOriginal = 0;
            $cantidadesNuevos = [];

            // Procesar destinos: el primero es el original (mantener), los dem├ís son nuevos
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
            // SaldoPedido = TotalPedido - Produccion (si hay producci├│n)
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

            // ===== FORZAR STD DESDE CAT├üLOGOS (SMITH/JACQUARD + Normal/Alta) =====
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

            // Recalcular f├│rmulas del registro original
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

                // Obtener el ├║ltimo registro del telar destino para determinar fecha de inicio
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

                // Campos b├ísicos
                $nuevo->SalonTejidoId = $salonDestinoItem;
                $nuevo->NoTelarId = $telarDestino;
                $nuevo->EnProceso = 0;
                $nuevo->Ultimo = 1;
                $nuevo->CambioHilo = 0;
                $nuevo->Produccion = null;
                $nuevo->Programado = null;
                $nuevo->NoProduccion = null;
                $nuevo->ProgramarProd = Carbon::now()->format('Y-m-d');

                // OrdCompartida - mismo n├║mero que el original para relacionarlos
                $nuevo->OrdCompartida = $nuevoOrdCompartida;

                // Cantidad del nuevo registro
                // Los nuevos registros no tienen producci├│n a├║n, as├¡ que SaldoPedido = TotalPedido
                $nuevo->TotalPedido = $pedidoDestino;
                $nuevo->SaldoPedido = $pedidoDestino; // Sin producci├│n inicial

                // Ajustar Maquina al telar destino (prefijo del sal├│n + n├║mero de telar)
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

                // ===== FORZAR STD DESDE CAT├üLOGOS (SMITH/JACQUARD + Normal/Alta) =====
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

                // ===== FECHA INICIO: SIEMPRE la FechaFinal del ├║ltimo registro del telar destino =====
                // NO hacer snap al calendario, usar exactamente la fecha final del ├║ltimo registro
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

                // Calcular f├│rmulas
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

            // Disparar observer manualmente para generar las l├¡neas
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
     * Calcular fórmulas de eficiencia usando el helper unificado
     */
    private static function calcularFormulasEficiencia(ReqProgramaTejido $programa): array
    {
        $modeloParams = TejidoHelpers::obtenerModeloParams($programa, function($tamanoClave, $salonTejidoId) {
            return self::obtenerModeloCodificadoPorSalon($tamanoClave, $salonTejidoId);
        });

        return TejidoHelpers::calcularFormulasEficiencia(
            $programa,
            $modeloParams,
            true,  // includeEntregaCte
            true,  // includePTvsCte
            true   // fallbackEntregaCteFromProgram
        );
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
        // Repeticiones no existe en la tabla ReqProgramaTejido, se elimina la asignaci├│n
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

            // Calcular la duraci├│n total original (usaremos el primer registro como base)
            $primerRegistro = $registrosExistentes->first();
            $fechaInicioBase = $primerRegistro->FechaInicio ? Carbon::parse($primerRegistro->FechaInicio) : Carbon::now();

            // Calcular cantidad total del grupo para proporciones
            $cantidadTotalGrupo = $registrosExistentes->sum('TotalPedido');

            // Calcular duraci├│n promedio por unidad (basado en el primer registro)
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

                        // ===== FORZAR STD DESDE CAT├üLOGOS (SMITH/JACQUARD + Normal/Alta) =====
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

                        // Recalcular f├│rmulas
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

                // Obtener el ├║ltimo registro del telar destino
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

                // Campos b├ísicos
                $nuevo->SalonTejidoId = $salonDestinoItem;
                $nuevo->NoTelarId = $telarDestino;
                $nuevo->EnProceso = 0;
                $nuevo->Ultimo = 1;
                $nuevo->CambioHilo = 0;
                $nuevo->Produccion = null;
                $nuevo->Programado = null;
                $nuevo->NoProduccion = null;
                $nuevo->ProgramarProd = Carbon::now()->format('Y-m-d');

                // OrdCompartida - mismo n├║mero que el grupo
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

                // ===== FORZAR STD DESDE CAT├üLOGOS (SMITH/JACQUARD + Normal/Alta) =====
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

                // ===== FECHA INICIO: SIEMPRE la FechaFinal del ├║ltimo registro del telar destino =====
                // NO hacer snap al calendario, usar exactamente la fecha final del ├║ltimo registro
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

                // Calcular f├│rmulas
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

            // Disparar observer manualmente para generar las l├¡neas
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
                'message' => "Redistribuci├│n completada. Actualizados: {$totalActualizados}, Nuevos: {$totalCreados}.",
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
     * Construye el valor de Maquina usando un prefijo del sal├│n o del valor base y el n├║mero de telar
     */
    private static function construirMaquina(?string $maquinaBase, ?string $salon, $telar): string
    {
        return TejidoHelpers::construirMaquinaConSalon($maquinaBase, $salon, $telar);
    }

    /**
     * Calcular horas de producci├│n necesarias (igual que BalancearTejido)
     */
    private static function calcularHorasProd(ReqProgramaTejido $p): float
    {
        $vel   = (float) ($p->VelocidadSTD ?? 0);
        $efic  = (float) ($p->EficienciaSTD ?? 0);
        $cantidad = self::sanitizeNumber($p->SaldoPedido ?? $p->Produccion ?? $p->TotalPedido ?? 0);

        $m = self::getModeloParams($p->TamanoClave ?? null, $p);

        return TejidoHelpers::calcularHorasProd(
            $vel,
            $efic,
            $cantidad,
            (float)($m['no_tiras'] ?? 0),
            (float)($m['total'] ?? 0),
            (float)($m['luchaje'] ?? 0),
            (float)($m['repeticiones'] ?? 0)
        );
    }

    /**
     * Obtener par├ímetros del modelo (igual que BalancearTejido)
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
     * Sanitizar n├║mero (igual que BalancearTejido)
     */
    private static function sanitizeNumber($value): float
    {
        return TejidoHelpers::sanitizeNumber($value);
    }

    // =========================
    // STD DESDE CAT├üLOGOS
    // =========================

    private static function aplicarStdDesdeCatalogos(ReqProgramaTejido $p): void
    {
        TejidoHelpers::aplicarStdDesdeCatalogos($p);
    }

    public static function calcularTotalesDividir(Request $request)
    {
        $request->validate([
            'pedido' => 'required|numeric|min:0',
            'porcentaje_segundos' => 'nullable|numeric|min:0',
            'produccion' => 'nullable|numeric|min:0'
        ]);

        $pedido = (float) $request->input('pedido', 0);
        $porcentajeSegundos = (float) $request->input('porcentaje_segundos', 0);
        $produccion = (float) $request->input('produccion', 0);

        // Calcular TotalPedido: Pedido * (1 + PorcentajeSegundos / 100)
        $totalPedido = $pedido * (1 + $porcentajeSegundos / 100);

        // Calcular SaldoTotal = max(0, TotalPedido - Produccion)
        $saldoTotal = max(0, $totalPedido - $produccion);

        return response()->json([
            'success' => true,
            'total_pedido' => round($totalPedido, 2),
            'saldo_total' => round($saldoTotal, 2),
            'pedido' => round($pedido, 2),
            'porcentaje_segundos' => round($porcentajeSegundos, 2),
            'produccion' => round($produccion, 2)
        ]);
    }
}
