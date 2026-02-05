<?php

namespace App\Http\Controllers\Planeacion\ProgramaTejido\funciones;

use App\Models\Planeacion\ReqProgramaTejido;
use App\Observers\ReqProgramaTejidoObserver;
use App\Http\Controllers\Planeacion\ProgramaTejido\helper\TejidoHelpers;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB as DBFacade;
use Illuminate\Support\Facades\Log as LogFacade;
use App\Models\Planeacion\ReqModelosCodificados;
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

        // Guardar y restaurar dispatcher para no romper otros flujos (igual que DuplicarTejido)
        $dispatcher = ReqProgramaTejido::getEventDispatcher();
        ReqProgramaTejido::unsetEventDispatcher();

        DBFacade::beginTransaction();
        LogFacade::info('DividirTejido::dividir INICIO', ['salon' => $salonOrigen, 'telar' => $telarOrigen, 'destinos_count' => count($destinos)]);

        try {
            // Si es redistribuci├│n, usar l├│gica diferente
            if ($esRedistribucion) {
                LogFacade::info('DividirTejido: usando redistribuirGrupoExistente');
                return self::redistribuirGrupoExistente($request, $ordCompartidaExistente, $destinos, $salonDestino, $hilo, $dispatcher);
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
            // ⚡ MEJORA: Calcular cantidad original total desde SaldoPedido o TotalPedido
            // Usar TotalPedido como base (es el pedido completo sin restar producción)
            $totalPedidoOriginal = (float) ($registroOriginal->TotalPedido ?? 0);
            $saldoPedidoOriginal = (float) ($registroOriginal->SaldoPedido ?? 0);
            // Si no hay TotalPedido, usar SaldoPedido como fallback
            $cantidadOriginalTotal = $totalPedidoOriginal > 0 ? $totalPedidoOriginal : $saldoPedidoOriginal;
            $cantidadParaOriginal = 0;
            $cantidadesNuevos = [];

            // Procesar destinos: el primero es el original (mantener), los dem├ís son nuevos
            $observacionesOriginal = null;
            $porcentajeSegundosOriginal = null;

            foreach ($destinos as $index => $destino) {
                // Normalizar pedido (quitar comas de miles para que (float) no trunque: "1,000" -> 1000)
                $rawPedido = isset($destino['pedido']) && $destino['pedido'] !== '' ? (string) $destino['pedido'] : '';
                $pedidoDestino = $rawPedido !== '' ? (float) str_replace(',', '', $rawPedido) : 0;
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
                    // ⚡ MEJORA: Incluir tamano_clave y otros campos específicos de cada destino
                    $destinosNuevos[] = [
                        'salon_destino' => $salonDestinoItem,
                        'telar' => $destino['telar'],
                        'pedido' => $pedidoDestino,
                        'pedido_tempo' => $pedidoTempoDestino,
                        'observaciones' => $observacionesDestino,
                        'porcentaje_segundos' => $porcentajeSegundosDestino,
                        'tamano_clave' => $destino['tamano_clave'] ?? null,
                        'producto' => $destino['producto'] ?? null,
                        'flog' => $destino['flog'] ?? null,
                        'descripcion' => $destino['descripcion'] ?? null,
                        'custName' => $destino['custName'] ?? null,
                        'itemId' => $destino['itemId'] ?? null,
                        'inventSizeId' => $destino['inventSizeId'] ?? null,
                        // Incluir todos los campos técnicos del modelo
                        'cuentaRizo' => $destino['cuentaRizo'] ?? null,
                        'calibreRizo' => $destino['calibreRizo'] ?? null,
                        'calibreRizo2' => $destino['calibreRizo2'] ?? null,
                        'ancho' => $destino['ancho'] ?? null,
                        'calibrePie' => $destino['calibrePie'] ?? null,
                        'calibrePie2' => $destino['calibrePie2'] ?? null,
                        'rasurado' => $destino['rasurado'] ?? null,
                        'noTiras' => $destino['noTiras'] ?? null,
                        'peine' => $destino['peine'] ?? null,
                        'luchaje' => $destino['luchaje'] ?? null,
                        'pesoCrudo' => $destino['pesoCrudo'] ?? null,
                        'calibreTrama' => $destino['calibreTrama'] ?? null,
                        'calibreTrama2' => $destino['calibreTrama2'] ?? null,
                        'fibraTrama' => $destino['fibraTrama'] ?? null,
                        'dobladilloId' => $destino['dobladilloId'] ?? null,
                        'pasadasTrama' => $destino['pasadasTrama'] ?? null,
                        'pasadasComb1' => $destino['pasadasComb1'] ?? null,
                        'pasadasComb2' => $destino['pasadasComb2'] ?? null,
                        'pasadasComb3' => $destino['pasadasComb3'] ?? null,
                        'pasadasComb4' => $destino['pasadasComb4'] ?? null,
                        'pasadasComb5' => $destino['pasadasComb5'] ?? null,
                        'anchoToalla' => $destino['anchoToalla'] ?? null,
                        'codColorTrama' => $destino['codColorTrama'] ?? null,
                        'colorTrama' => $destino['colorTrama'] ?? null,
                        'calibreComb1' => $destino['calibreComb1'] ?? null,
                        'calibreComb12' => $destino['calibreComb12'] ?? null,
                        'fibraComb1' => $destino['fibraComb1'] ?? null,
                        'codColorComb1' => $destino['codColorComb1'] ?? null,
                        'nombreCC1' => $destino['nombreCC1'] ?? null,
                        'calibreComb2' => $destino['calibreComb2'] ?? null,
                        'calibreComb22' => $destino['calibreComb22'] ?? null,
                        'fibraComb2' => $destino['fibraComb2'] ?? null,
                        'codColorComb2' => $destino['codColorComb2'] ?? null,
                        'nombreCC2' => $destino['nombreCC2'] ?? null,
                        'calibreComb3' => $destino['calibreComb3'] ?? null,
                        'calibreComb32' => $destino['calibreComb32'] ?? null,
                        'fibraComb3' => $destino['fibraComb3'] ?? null,
                        'codColorComb3' => $destino['codColorComb3'] ?? null,
                        'nombreCC3' => $destino['nombreCC3'] ?? null,
                        'calibreComb4' => $destino['calibreComb4'] ?? null,
                        'calibreComb42' => $destino['calibreComb42'] ?? null,
                        'fibraComb4' => $destino['fibraComb4'] ?? null,
                        'codColorComb4' => $destino['codColorComb4'] ?? null,
                        'nombreCC4' => $destino['nombreCC4'] ?? null,
                        'calibreComb5' => $destino['calibreComb5'] ?? null,
                        'calibreComb52' => $destino['calibreComb52'] ?? null,
                        'fibraComb5' => $destino['fibraComb5'] ?? null,
                        'codColorComb5' => $destino['codColorComb5'] ?? null,
                        'nombreCC5' => $destino['nombreCC5'] ?? null,
                        'medidaPlano' => $destino['medidaPlano'] ?? null,
                        'cuentaPie' => $destino['cuentaPie'] ?? null,
                        'largoToalla' => $destino['largoToalla'] ?? null, // ⚡ MEJORA: LargoCrudo se obtiene de LargoToalla
                        'aplicacion' => $destino['aplicacion'] ?? null,
                    ];
                    $cantidadesNuevos[] = $pedidoDestino;
                }
            }

            // ⚡ MEJORA: Ajustar cantidades - calcular correctamente el TotalPedido del registro original
            // El TotalPedido del original debe ser: TotalPedido original - suma de pedidos nuevos
            $sumaNuevos = array_sum($cantidadesNuevos);

            // Si no se especificó cantidad para el original, calcularla restando los nuevos
            if ($cantidadParaOriginal <= 0) {
                $cantidadParaOriginal = max(0, $cantidadOriginalTotal - $sumaNuevos);
            }

            // Si tampoco hubo nuevos, mantener el total original en el registro base
            if ($cantidadParaOriginal <= 0 && $sumaNuevos <= 0) {
                $cantidadParaOriginal = $cantidadOriginalTotal;
            }

            // ⚡ MEJORA: Validar que la suma de original + nuevos no exceda el total original
            $sumaTotal = $cantidadParaOriginal + $sumaNuevos;
            if ($sumaTotal > $cantidadOriginalTotal) {
                // Ajustar proporcionalmente si excede
                $factor = $cantidadOriginalTotal / $sumaTotal;
                $cantidadParaOriginal = max(0, round($cantidadParaOriginal * $factor));
                foreach ($cantidadesNuevos as $idx => $cantidad) {
                    $cantidadesNuevos[$idx] = max(0, round($cantidad * $factor));
                    $destinosNuevos[$idx]['pedido'] = $cantidadesNuevos[$idx];
                }
                $sumaNuevos = array_sum($cantidadesNuevos);
            }

            $idsParaObserver = [];
            $registrosParaObserver = []; // Modelos con datos en memoria para generar ReqProgramaTejidoLine
            $totalDivididos = 0;

            // === PASO 1: Actualizar el registro original ===
            $registroOriginal->OrdCompartida = $nuevoOrdCompartida;

            // ⚡ MEJORA: Actualizar TotalPedido y SaldoPedido restando los pedidos de los nuevos registros
            // TotalPedido = cantidad original - suma de pedidos nuevos
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
            $registrosParaObserver[] = $registroOriginal;
            $totalDivididos++;

            // Datos de cada registro nuevo para la respuesta (evita depender de find() tras commit, que puede fallar en SQL Server)
            $registrosDatosParaRespuesta = [];

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
                $nuevo->ProgramarProd = null;   // Day Scheduling en null
                $nuevo->SaldoMarbete = null;    // Saldo marbetes en null

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

                // ⚡ MEJORA: Leer tamano_clave específico de cada destino (permite diferentes claves modelo por fila)
                $tamanoClaveDestino = isset($destino['tamano_clave']) && $destino['tamano_clave'] !== null && $destino['tamano_clave'] !== ''
                    ? trim((string) $destino['tamano_clave'])
                    : null;
                $productoDestino = isset($destino['producto']) && $destino['producto'] !== null && $destino['producto'] !== ''
                    ? trim((string) $destino['producto'])
                    : null;
                $flogDestino = isset($destino['flog']) && $destino['flog'] !== null && $destino['flog'] !== ''
                    ? trim((string) $destino['flog'])
                    : null;
                $descripcionDestino = isset($destino['descripcion']) && $destino['descripcion'] !== null && $destino['descripcion'] !== ''
                    ? trim((string) $destino['descripcion'])
                    : null;
                $custnameDestino = isset($destino['custName']) && $destino['custName'] !== null && $destino['custName'] !== ''
                    ? trim((string) $destino['custName'])
                    : null;
                $itemIdDestino = isset($destino['itemId']) && $destino['itemId'] !== null && $destino['itemId'] !== ''
                    ? trim((string) $destino['itemId'])
                    : null;
                $inventSizeIdDestino = isset($destino['inventSizeId']) && $destino['inventSizeId'] !== null && $destino['inventSizeId'] !== ''
                    ? trim((string) $destino['inventSizeId'])
                    : null;

                // ⚡ MEJORA: Usar valores específicos del destino si existen, sino usar valores globales
                // IMPORTANTE: Si hay tamano_clave específico, usarlo (incluso si es diferente al original)
                if ($tamanoClaveDestino) {
                    $nuevo->TamanoClave = $tamanoClaveDestino;
                }
                if ($productoDestino) {
                    $nuevo->NombreProducto = $productoDestino;
                }
                if ($flogDestino) {
                    $nuevo->FlogsId = $flogDestino;
                }
                if ($descripcionDestino) {
                    $nuevo->NombreProyecto = $descripcionDestino;
                }
                if ($custnameDestino) {
                    $nuevo->CustName = $custnameDestino;
                }
                if ($itemIdDestino) {
                    $nuevo->ItemId = $itemIdDestino;
                }
                if ($inventSizeIdDestino) {
                    $nuevo->InventSizeId = $inventSizeIdDestino;
                }

                // Actualizar otros campos si se proporcionan (fallback a valores globales)
                if (!$itemIdDestino && $codArticulo) $nuevo->ItemId = $codArticulo;
                if (!$productoDestino && $producto) $nuevo->NombreProducto = $producto;
                if (!$flogDestino && $flog) $nuevo->FlogsId = $flog;
                if (!$descripcionDestino && $descripcion) $nuevo->NombreProyecto = $descripcion;
                if (!$custnameDestino && $custname) $nuevo->CustName = $custname;
                if (!$inventSizeIdDestino && $inventSizeId) $nuevo->InventSizeId = $inventSizeId;
                if ($hilo) $nuevo->FibraRizo = $hilo;
                if ($aplicacion) $nuevo->AplicacionId = $aplicacion;

                // ⚡ MEJORA: Aplicar campos técnicos del modelo cuando hay tamano_clave específico diferente
                // Si hay tamano_clave específico diferente, aplicar todos los campos técnicos del modelo
                if ($tamanoClaveDestino && $tamanoClaveDestino !== $registroOriginal->TamanoClave) {
                    // Aplicar modelo codificado con la clave modelo específica
                    self::aplicarModeloCodificadoPorSalon($nuevo, $salonDestinoItem, $tamanoClaveDestino);

                    // Aplicar campos técnicos desde los datos del destino (ya vienen del frontend)
                    if (isset($destino['cuentaRizo']) && $destino['cuentaRizo'] !== null) $nuevo->CuentaRizo = $destino['cuentaRizo'];
                    if (isset($destino['calibreRizo']) && $destino['calibreRizo'] !== null) $nuevo->CalibreRizo = $destino['calibreRizo'];
                    if (isset($destino['calibreRizo2']) && $destino['calibreRizo2'] !== null) $nuevo->CalibreRizo2 = $destino['calibreRizo2'];
                    if (isset($destino['ancho']) && $destino['ancho'] !== null) $nuevo->Ancho = $destino['ancho'];
                    if (isset($destino['calibrePie']) && $destino['calibrePie'] !== null) $nuevo->CalibrePie = $destino['calibrePie'];
                    if (isset($destino['calibrePie2']) && $destino['calibrePie2'] !== null) $nuevo->CalibrePie2 = $destino['calibrePie2'];
                    if (isset($destino['rasurado']) && $destino['rasurado'] !== null) $nuevo->Rasurado = $destino['rasurado'];
                    if (isset($destino['noTiras']) && $destino['noTiras'] !== null) $nuevo->NoTiras = $destino['noTiras'];
                    if (isset($destino['peine']) && $destino['peine'] !== null) $nuevo->Peine = $destino['peine'];
                    if (isset($destino['luchaje']) && $destino['luchaje'] !== null) $nuevo->Luchaje = $destino['luchaje'];
                    if (isset($destino['pesoCrudo']) && $destino['pesoCrudo'] !== null) $nuevo->PesoCrudo = $destino['pesoCrudo'];
                    // ⚡ CORRECCIÓN: CalibreTrama se invierte al aplicar desde el modelo
                    // CalibreTrama del modelo -> CalibreTrama2 del registro
                    // CalibreTrama2 del modelo -> CalibreTrama del registro
                    // Pero si viene del destino directamente, usarlo tal cual
                    if (isset($destino['calibreTrama']) && $destino['calibreTrama'] !== null) $nuevo->CalibreTrama = $destino['calibreTrama'];
                    if (isset($destino['calibreTrama2']) && $destino['calibreTrama2'] !== null) $nuevo->CalibreTrama2 = $destino['calibreTrama2'];

                    // ⚡ MEJORA: LargoCrudo se obtiene de LargoToalla del modelo o del destino
                    if (isset($destino['largoToalla']) && $destino['largoToalla'] !== null) {
                        $nuevo->LargoCrudo = (float) $destino['largoToalla'];
                    }
                    if (isset($destino['fibraTrama']) && $destino['fibraTrama'] !== null) $nuevo->FibraTrama = $destino['fibraTrama'];
                    if (isset($destino['dobladilloId']) && $destino['dobladilloId'] !== null) $nuevo->DobladilloId = $destino['dobladilloId'];
                    if (isset($destino['pasadasTrama']) && $destino['pasadasTrama'] !== null) $nuevo->PasadasTrama = $destino['pasadasTrama'];
                    if (isset($destino['pasadasComb1']) && $destino['pasadasComb1'] !== null) $nuevo->PasadasComb1 = $destino['pasadasComb1'];
                    if (isset($destino['pasadasComb2']) && $destino['pasadasComb2'] !== null) $nuevo->PasadasComb2 = $destino['pasadasComb2'];
                    if (isset($destino['pasadasComb3']) && $destino['pasadasComb3'] !== null) $nuevo->PasadasComb3 = $destino['pasadasComb3'];
                    if (isset($destino['pasadasComb4']) && $destino['pasadasComb4'] !== null) $nuevo->PasadasComb4 = $destino['pasadasComb4'];
                    if (isset($destino['pasadasComb5']) && $destino['pasadasComb5'] !== null) $nuevo->PasadasComb5 = $destino['pasadasComb5'];
                    if (isset($destino['anchoToalla']) && $destino['anchoToalla'] !== null) $nuevo->AnchoToalla = $destino['anchoToalla'];
                    if (isset($destino['codColorTrama']) && $destino['codColorTrama'] !== null) $nuevo->CodColorTrama = $destino['codColorTrama'];
                    if (isset($destino['colorTrama']) && $destino['colorTrama'] !== null) $nuevo->ColorTrama = $destino['colorTrama'];
                    if (isset($destino['calibreComb1']) && $destino['calibreComb1'] !== null) $nuevo->CalibreComb1 = $destino['calibreComb1'];
                    if (isset($destino['calibreComb12']) && $destino['calibreComb12'] !== null) $nuevo->CalibreComb12 = $destino['calibreComb12'];
                    if (isset($destino['fibraComb1']) && $destino['fibraComb1'] !== null) $nuevo->FibraComb1 = $destino['fibraComb1'];
                    if (isset($destino['codColorComb1']) && $destino['codColorComb1'] !== null) $nuevo->CodColorComb1 = $destino['codColorComb1'];
                    if (isset($destino['nombreCC1']) && $destino['nombreCC1'] !== null) $nuevo->NombreCC1 = $destino['nombreCC1'];
                    if (isset($destino['calibreComb2']) && $destino['calibreComb2'] !== null) $nuevo->CalibreComb2 = $destino['calibreComb2'];
                    if (isset($destino['calibreComb22']) && $destino['calibreComb22'] !== null) $nuevo->CalibreComb22 = $destino['calibreComb22'];
                    if (isset($destino['fibraComb2']) && $destino['fibraComb2'] !== null) $nuevo->FibraComb2 = $destino['fibraComb2'];
                    if (isset($destino['codColorComb2']) && $destino['codColorComb2'] !== null) $nuevo->CodColorComb2 = $destino['codColorComb2'];
                    if (isset($destino['nombreCC2']) && $destino['nombreCC2'] !== null) $nuevo->NombreCC2 = $destino['nombreCC2'];
                    if (isset($destino['calibreComb3']) && $destino['calibreComb3'] !== null) $nuevo->CalibreComb3 = $destino['calibreComb3'];
                    if (isset($destino['calibreComb32']) && $destino['calibreComb32'] !== null) $nuevo->CalibreComb32 = $destino['calibreComb32'];
                    if (isset($destino['fibraComb3']) && $destino['fibraComb3'] !== null) $nuevo->FibraComb3 = $destino['fibraComb3'];
                    if (isset($destino['codColorComb3']) && $destino['codColorComb3'] !== null) $nuevo->CodColorComb3 = $destino['codColorComb3'];
                    if (isset($destino['nombreCC3']) && $destino['nombreCC3'] !== null) $nuevo->NombreCC3 = $destino['nombreCC3'];
                    if (isset($destino['calibreComb4']) && $destino['calibreComb4'] !== null) $nuevo->CalibreComb4 = $destino['calibreComb4'];
                    if (isset($destino['calibreComb42']) && $destino['calibreComb42'] !== null) $nuevo->CalibreComb42 = $destino['calibreComb42'];
                    if (isset($destino['fibraComb4']) && $destino['fibraComb4'] !== null) $nuevo->FibraComb4 = $destino['fibraComb4'];
                    if (isset($destino['codColorComb4']) && $destino['codColorComb4'] !== null) $nuevo->CodColorComb4 = $destino['codColorComb4'];
                    if (isset($destino['nombreCC4']) && $destino['nombreCC4'] !== null) $nuevo->NombreCC4 = $destino['nombreCC4'];
                    if (isset($destino['calibreComb5']) && $destino['calibreComb5'] !== null) $nuevo->CalibreComb5 = $destino['calibreComb5'];
                    if (isset($destino['calibreComb52']) && $destino['calibreComb52'] !== null) $nuevo->CalibreComb52 = $destino['calibreComb52'];
                    if (isset($destino['fibraComb5']) && $destino['fibraComb5'] !== null) $nuevo->FibraComb5 = $destino['fibraComb5'];
                    if (isset($destino['codColorComb5']) && $destino['codColorComb5'] !== null) $nuevo->CodColorComb5 = $destino['codColorComb5'];
                    if (isset($destino['nombreCC5']) && $destino['nombreCC5'] !== null) $nuevo->NombreCC5 = $destino['nombreCC5'];
                    if (isset($destino['medidaPlano']) && $destino['medidaPlano'] !== null) $nuevo->MedidaPlano = $destino['medidaPlano'];
                    if (isset($destino['cuentaPie']) && $destino['cuentaPie'] !== null) $nuevo->CuentaPie = $destino['cuentaPie'];
                    // ⚡ MEJORA: LargoCrudo se obtiene de LargoToalla del destino o del modelo
                    if (isset($destino['largoToalla']) && $destino['largoToalla'] !== null) {
                        $nuevo->LargoCrudo = (float) $destino['largoToalla'];
                    }
                } elseif ($salonDestinoItem !== $salonOrigen) {
                    // Si solo cambió de salón (sin cambiar clave modelo), aplicar modelo del nuevo salón
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

                // Asignar posición consecutiva para este telar
                $nuevo->Posicion = TejidoHelpers::obtenerSiguientePosicionDisponible($salonDestinoItem, $telarDestino);

                $nuevo->CreatedAt = now();
                $nuevo->UpdatedAt = now();
                $nuevo->save();

                $idsParaObserver[] = $nuevo->Id;
                $registrosDatosParaRespuesta[(string) $nuevo->Id] = $nuevo->toArray();
                $registrosParaObserver[] = $nuevo;
                $totalDivididos++;
            }

            // ===== ORDCOMPARTIDALIDER: Asignar al registro con fecha inicio más antigua =====
            // Obtener todos los registros con este OrdCompartida (incluyendo el original)
            $registrosConOrdCompartida = ReqProgramaTejido::where('OrdCompartida', $nuevoOrdCompartida)
                ->get();

            if ($registrosConOrdCompartida->count() > 0) {
                // Ordenar por FechaInicio (más antigua primero)
                $registrosOrdenados = $registrosConOrdCompartida->sortBy(function ($registro) {
                    return $registro->FechaInicio ? Carbon::parse($registro->FechaInicio)->timestamp : PHP_INT_MAX;
                });

                // El primero es el líder (fecha más antigua)
                $idLider = $registrosOrdenados->first()->Id;

                // Quitar OrdCompartidaLider de todos
                ReqProgramaTejido::where('OrdCompartida', $nuevoOrdCompartida)
                    ->update([
                        'OrdCompartidaLider' => null,
                        'UpdatedAt' => now()
                    ]);

                // Asignar OrdCompartidaLider = 1 solo al registro con fecha más antigua
                ReqProgramaTejido::where('Id', $idLider)
                    ->update([
                        'OrdCompartidaLider' => 1,
                        'UpdatedAt' => now()
                    ]);

                // Actualizar OrdPrincipal con el ItemId del líder en todos los registros compartidos
                \App\Http\Controllers\Planeacion\ProgramaTejido\funciones\VincularTejido::actualizarOrdPrincipalPorOrdCompartida($nuevoOrdCompartida);
            }

            // Asegurar que los registros divididos mantengan EnProceso=0 (dentro de la misma transacción)
            if (!empty($idsParaObserver)) {
                $idsNuevos = array_slice($idsParaObserver, 1);
                if (!empty($idsNuevos)) {
                    ReqProgramaTejido::whereIn('Id', $idsNuevos)->update(['EnProceso' => 0]);
                }
            }

            DBFacade::commit();
            LogFacade::info('DividirTejido::dividir COMMIT realizado', ['ids_para_observer' => $idsParaObserver, 'total_divididos' => $totalDivididos]);

            // Restaurar dispatcher y re-habilitar observer
            if ($dispatcher) {
                ReqProgramaTejido::setEventDispatcher($dispatcher);
            }
            ReqProgramaTejido::observe(ReqProgramaTejidoObserver::class);

            // Generar ReqProgramaTejidoLine tras el commit, con las instancias en memoria (evita reconsulta que no ve el registro en SQL Server)
            if (!empty($registrosParaObserver)) {
                $observer = new ReqProgramaTejidoObserver();
                foreach ($registrosParaObserver as $registro) {
                    if (!$registro || !$registro->Id) continue;
                    try {
                        $observer->saved($registro);
                    } catch (\Throwable $e) {
                        LogFacade::error('DividirTejido: error generando líneas tras commit', [
                            'programa_id' => $registro->Id,
                            'message' => $e->getMessage(),
                        ]);
                        throw $e;
                    }
                }
            }

            // Primer registro nuevo para respuesta (desde memoria; find() tras commit puede fallar en SQL Server)
            $primerNuevoCreado = count($registrosParaObserver) > 1 ? $registrosParaObserver[1] : $registrosParaObserver[0];

            // IDs de registros nuevos creados (excluyendo el original)
            $idsNuevosCreados = array_slice($idsParaObserver, 1);

            // Usar datos capturados justo después de save(); find() tras commit puede no encontrar el registro en SQL Server
            $registrosDatos = $registrosDatosParaRespuesta;

            return response()->json([
                'success' => true,
                'message' => "Registro dividido correctamente. OrdCompartida: {$nuevoOrdCompartida}. Se crearon/actualizaron {$totalDivididos} registro(s).",
                'registros_divididos' => $totalDivididos,
                'ord_compartida' => $nuevoOrdCompartida,
                'registro_id' => $primerNuevoCreado?->Id,
                'registros_ids' => $idsNuevosCreados,
                'registros_datos' => $registrosDatos,
                'registro_id_original' => $registroOriginal->Id,
                'registro_original' => [
                    'TotalPedido' => $registroOriginal->TotalPedido,
                    'SaldoPedido' => $registroOriginal->SaldoPedido,
                    'FechaInicio' => $registroOriginal->FechaInicio,
                    'FechaFinal' => $registroOriginal->FechaFinal,
                    'EntregaProduc' => $registroOriginal->EntregaProduc,
                    'EntregaPT' => $registroOriginal->EntregaPT,
                    'EntregaCte' => $registroOriginal->EntregaCte,
                    'ProgramarProd' => $registroOriginal->ProgramarProd,
                    'HorasProd' => $registroOriginal->HorasProd ?? null,
                    'DiasJornada' => $registroOriginal->DiasJornada ?? null,
                    'StdDia' => $registroOriginal->StdDia ?? null,
                    'ProdKgDia' => $registroOriginal->ProdKgDia ?? null,
                ],
                'salon_destino' => $primerNuevoCreado?->SalonTejidoId,
                'telar_destino' => $primerNuevoCreado?->NoTelarId,
                'modo' => 'dividir'
            ]);

        } catch (\Throwable $e) {
            DBFacade::rollBack();
            if (isset($dispatcher) && $dispatcher) {
                ReqProgramaTejido::setEventDispatcher($dispatcher);
            }
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

    private static function aplicarModeloCodificadoPorSalon(ReqProgramaTejido $registro, string $salonDestino, ?string $tamanoClave = null): void
    {
        // ⚡ MEJORA: Usar tamanoClave específico si se proporciona, sino usar el del registro
        $claveParaBuscar = $tamanoClave ?? $registro->TamanoClave;
        $modelo = self::obtenerModeloCodificadoPorSalon($claveParaBuscar, $salonDestino);
        if (!$modelo) {
            return;
        }

        // Si se proporcionó tamanoClave específico, actualizarlo en el registro
        if ($tamanoClave) {
            $registro->TamanoClave = trim((string) $tamanoClave);
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
        // ⚡ CORRECCIÓN: CalibreTrama se invierte al aplicar desde el modelo
        // CalibreTrama del modelo -> CalibreTrama2 del registro
        // CalibreTrama2 del modelo -> CalibreTrama del registro
        if ($modelo->CalibreTrama !== null) {
            $registro->CalibreTrama2 = (float) $modelo->CalibreTrama;
        }
        if ($modelo->CalibreTrama2 !== null) {
            $registro->CalibreTrama = (float) $modelo->CalibreTrama2;
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
        // ⚡ MEJORA: LargoCrudo se obtiene de LargoToalla del modelo codificado
        if ($modelo->LargoToalla !== null) {
            $registro->LargoCrudo = (float) $modelo->LargoToalla;
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
    private static function redistribuirGrupoExistente(Request $request, $ordCompartida, $destinos, $salonDestino, $hilo = null, $dispatcher = null)
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
            $registrosParaObserver = [];
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
                        $registrosParaObserver[] = $registro;
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
                $nuevo->ProgramarProd = null;   // Day Scheduling en null
                $nuevo->SaldoMarbete = null;    // Saldo marbetes en null

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

                // Asignar posición consecutiva para este telar
                $nuevo->Posicion = TejidoHelpers::obtenerSiguientePosicionDisponible($salonDestinoItem, $telarDestino);

                $nuevo->CreatedAt = now();
                $nuevo->UpdatedAt = now();
                $nuevo->save();

                $idsParaObserver[] = $nuevo->Id;
                $registrosParaObserver[] = $nuevo;
                $totalCreados++;
            }

            DBFacade::commit();

            // Restaurar dispatcher y re-habilitar observer (igual que DuplicarTejido)
            if ($dispatcher) {
                ReqProgramaTejido::setEventDispatcher($dispatcher);
            }
            ReqProgramaTejido::observe(ReqProgramaTejidoObserver::class);

            // Generar ReqProgramaTejidoLine tras el commit, con las instancias en memoria (evita reconsulta que no ve el registro en SQL Server)
            if (!empty($registrosParaObserver)) {
                $observer = new ReqProgramaTejidoObserver();
                foreach ($registrosParaObserver as $registro) {
                    if (!$registro || !$registro->Id) continue;
                    try {
                        $observer->saved($registro);
                    } catch (\Throwable $e) {
                        LogFacade::error('DividirTejido::redistribuirGrupoExistente: error generando líneas tras commit', [
                            'programa_id' => $registro->Id,
                            'message' => $e->getMessage(),
                        ]);
                    }
                }
            }

            // ===== ORDCOMPARTIDALIDER: Asignar al registro con fecha inicio más antigua =====
            // Obtener todos los registros con este OrdCompartida (incluyendo los actualizados y nuevos)
            $registrosConOrdCompartida = ReqProgramaTejido::where('OrdCompartida', $ordCompartida)
                ->get();

            if ($registrosConOrdCompartida->count() > 0) {
                // Ordenar por FechaInicio (más antigua primero)
                $registrosOrdenados = $registrosConOrdCompartida->sortBy(function ($registro) {
                    return $registro->FechaInicio ? Carbon::parse($registro->FechaInicio)->timestamp : PHP_INT_MAX;
                });

                // El primero es el líder (fecha más antigua)
                $idLider = $registrosOrdenados->first()->Id;

                // Quitar OrdCompartidaLider de todos
                ReqProgramaTejido::where('OrdCompartida', $ordCompartida)
                    ->update([
                        'OrdCompartidaLider' => null,
                        'UpdatedAt' => now()
                    ]);

                // Asignar OrdCompartidaLider = 1 solo al registro con fecha más antigua
                ReqProgramaTejido::where('Id', $idLider)
                    ->update([
                        'OrdCompartidaLider' => 1,
                        'UpdatedAt' => now()
                    ]);
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
            if ($dispatcher) {
                ReqProgramaTejido::setEventDispatcher($dispatcher);
            }
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
