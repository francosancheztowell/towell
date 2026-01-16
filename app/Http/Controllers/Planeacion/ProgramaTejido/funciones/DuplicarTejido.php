<?php

namespace App\Http\Controllers\Planeacion\ProgramaTejido\funciones;

use App\Helpers\StringTruncator;
use App\Http\Controllers\Planeacion\ProgramaTejido\funciones\BalancearTejido;
use App\Models\Planeacion\ReqModelosCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Observers\ReqProgramaTejidoObserver;
use App\Http\Controllers\Planeacion\ProgramaTejido\helper\TejidoHelpers;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB as DBFacade;
use Illuminate\Support\Facades\Log as LogFacade;
use App\Http\Controllers\Planeacion\ProgramaTejido\helper\UpdateHelpers;

class DuplicarTejido
{
    /** Cache de modelo para no pegar a ReqModelosCodificados por cada registro */
    private static array $modeloCache = [];

    /** Cache para datos completos del modelo codificado */
    private static array $datosModeloCache = [];

    public static function duplicar(Request $request)
    {
        $data = $request->validate([
            'salon_tejido_id' => 'required|string',
            'no_telar_id'     => 'required|string',
            'destinos'        => 'required|array|min:1',
            'destinos.*.telar'  => 'required|string',
            'destinos.*.pedido' => 'nullable|string',
            'destinos.*.pedido_tempo' => 'nullable|string',
            'destinos.*.saldo' => 'nullable|string',
            'destinos.*.observaciones' => 'nullable|string|max:500',
            'destinos.*.porcentaje_segundos' => 'nullable|numeric|min:0',
            'destinos.*.tamano_clave' => 'nullable|string|max:100',
            'destinos.*.producto' => 'nullable|string|max:255',
            'destinos.*.flog' => 'nullable|string|max:100',
            'destinos.*.descripcion' => 'nullable|string|max:500',
            'destinos.*.aplicacion' => 'nullable|string|max:255',

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
            'registro_id_original' => 'nullable|integer',
        ]);

        $salonOrigen  = $data['salon_tejido_id'];
        $telarOrigen  = $data['no_telar_id'];
        $salonDestino = $data['salon_destino'] ?? $salonOrigen;
        $destinos     = $data['destinos'];

        $pedidoGlobal   = self::sanitizeNullableNumber($data['pedido'] ?? null);
        $inventSizeId   = $data['invent_size_id'] ?? null;
        $tamanoClave    = $data['tamano_clave'] ?? null;
        $codArticulo    = $data['cod_articulo'] ?? null;
        $producto       = $data['producto'] ?? null;
        $custname       = $data['custname'] ?? null;
        $hilo           = $data['hilo'] ?? null;
        $flog           = $data['flog'] ?? null;
        $aplicacion     = $data['aplicacion'] ?? null;
        $descripcion    = $data['descripcion'] ?? null;
        $registroIdOriginal = $data['registro_id_original'] ?? null;

        // Guardar y restaurar dispatcher para no romper otros flujos
        $dispatcher = ReqProgramaTejido::getEventDispatcher();
        ReqProgramaTejido::unsetEventDispatcher();

        DBFacade::beginTransaction();

        try {
            // Obtener registro específico o fallback al último del telar
            $original = null;
            if (!empty($registroIdOriginal)) {
                $original = ReqProgramaTejido::find($registroIdOriginal);
                if ($original && ($original->SalonTejidoId !== $salonOrigen || $original->NoTelarId !== $telarOrigen)) {
                    $original = null;
                }
            }
            if (!$original) {
                $original = self::obtenerUltimoRegistroTelar($salonOrigen, $telarOrigen);
            }

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
                $pedidoTempoDestino = $destino['pedido_tempo'] ?? null;
                $saldoDestinoRaw = $destino['saldo'] ?? null;
                $observacionesDestino = $destino['observaciones'] ?? null;

                $porcentajeSegundosDestino = isset($destino['porcentaje_segundos']) && $destino['porcentaje_segundos'] !== null && $destino['porcentaje_segundos'] !== ''
                    ? (float)$destino['porcentaje_segundos']
                    : null;

                // Último del destino (para FechaInicio)
                $ultimoDestino = ReqProgramaTejido::query()
                    ->salon($salonDestino)
                    ->telar($telarDestino)
                    ->orderBy('FechaInicio', 'desc')
                    ->first();

                // Asegurar un solo "Ultimo"
                ReqProgramaTejido::where('SalonTejidoId', $salonDestino)
                    ->where('NoTelarId', $telarDestino)
                    ->where('Ultimo', 1)
                    ->update(['Ultimo' => 0]);

                // FechaInicio base = FechaFinal del último programa del destino (o fallback)
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

                // Limpiar campos que siempre deben resetearse en duplicación
                $nuevo->Produccion    = null;
                $nuevo->Programado    = null;
                $nuevo->NoProduccion  = null;
                $nuevo->OrdCompartida = null;
                // Limpiar campos de combinaciones


                $nuevo->ProgramarProd = Carbon::now()->format('Y-m-d');

                // ===== Overrides por destino (específicos de cada fila) o globales =====
                // Valores específicos del destino tienen prioridad sobre los valores globales
                $tamanoClaveDestino = $destino['tamano_clave'] ?? null;
                $productoDestino = $destino['producto'] ?? null;
                $flogDestino = $destino['flog'] ?? null;
                $descripcionDestino = $destino['descripcion'] ?? null;
                $custNameDestino = $destino['custName'] ?? null;

                // DETECTAR si hay cambio REAL de clave modelo
                $hayCambioClaveModelo = $tamanoClaveDestino && $tamanoClaveDestino !== $original->TamanoClave;

                // IMPORTANTE: Si viene tamano_clave en el destino, SIEMPRE aplicar datos del modelo
                // incluso si es igual al original, porque puede haber sido cambiado en el modal
                $aplicarDatosModelo = $tamanoClaveDestino && trim($tamanoClaveDestino) !== '';

                // SI HAY CAMBIO DE CLAVE MODELO O SI VIENE tamano_clave EN EL DESTINO, APLICAR DATOS DEL MODELO
                if ($aplicarDatosModelo) {
                    // IMPORTANTE: Limpiar ItemId e InventSizeId para que se apliquen desde el nuevo modelo
                    $nuevo->ItemId = null;
                    $nuevo->InventSizeId = null;

                    // Limpiar campos técnicos que deben venir del modelo codificado nuevo
                    $nuevo->CuentaRizo = null;
                    $nuevo->CalibreRizo = null;
                    $nuevo->CalibreRizo2 = null;
                    $nuevo->FibraRizo = null;
                    $nuevo->CalibrePie = null;
                    $nuevo->CalibrePie2 = null;
                    $nuevo->CalibreTrama = null;
                    $nuevo->CalibreTrama2 = null;
                    $nuevo->FibraTrama = null;
                    $nuevo->NoTiras = null;
                    $nuevo->Peine = null;
                    $nuevo->Luchaje = null;
                    $nuevo->PesoCrudo = null;
                    $nuevo->DobladilloId = null;
                    $nuevo->PasadasTrama = null;
                    $nuevo->AnchoToalla = null;
                    $nuevo->CodColorTrama = null;
                    $nuevo->ColorTrama = null;
                    $nuevo->MedidaPlano = null;
                    $nuevo->CuentaPie = null;
                    $nuevo->Rasurado = null;
                    $nuevo->LargoCrudo = null;
                    // Limpiar campos de combinaciones que deben venir del modelo
                    $nuevo->PasadasComb1 = null;
                    $nuevo->PasadasComb2 = null;
                    $nuevo->PasadasComb3 = null;
                    $nuevo->PasadasComb4 = null;
                    $nuevo->PasadasComb5 = null;
                    $nuevo->CalibreComb1 = null;
                    $nuevo->CalibreComb12 = null;
                    $nuevo->CalibreComb2 = null;
                    $nuevo->CalibreComb22 = null;
                    $nuevo->CalibreComb3 = null;
                    $nuevo->CalibreComb32 = null;
                    $nuevo->CalibreComb4 = null;
                    $nuevo->CalibreComb42 = null;
                    $nuevo->CalibreComb5 = null;
                    $nuevo->CalibreComb52 = null;
                    $nuevo->FibraComb1 = null;
                    $nuevo->FibraComb2 = null;
                    $nuevo->FibraComb3 = null;
                    $nuevo->FibraComb4 = null;
                    $nuevo->FibraComb5 = null;
                    $nuevo->CodColorComb1 = null;
                    $nuevo->CodColorComb2 = null;
                    $nuevo->CodColorComb3 = null;
                    $nuevo->CodColorComb4 = null;
                    $nuevo->CodColorComb5 = null;
                    $nuevo->NombreCC1 = null;
                    $nuevo->NombreCC2 = null;
                    $nuevo->NombreCC3 = null;
                    $nuevo->NombreCC4 = null;
                    $nuevo->NombreCC5 = null;

                    self::aplicarDatosModeloCodificado($nuevo, $tamanoClaveDestino, $salonDestino);
                }

                // Los campos del modelo ya se aplicaron arriba si hubo cambio de clave
                // Si no hubo cambio, aplicaremos campos específicos del destino si existen

                // AplicacionId: SIEMPRE viene del select del modal, no del modelo
                // Independientemente de si cambió la clave modelo o no
                $aplicacionDestino = $destino['aplicacion'] ?? null;

                if ($aplicacionDestino && $aplicacionDestino !== '' && $aplicacionDestino !== 'NA') {
                    $nuevo->AplicacionId = StringTruncator::truncate('AplicacionId', $aplicacionDestino);
                } elseif ($aplicacion && $aplicacion !== '' && $aplicacion !== 'NA') {
                    $nuevo->AplicacionId = StringTruncator::truncate('AplicacionId', $aplicacion);
                } else {
                    // Si no hay aplicación específica, copiar del registro original si existe
                    if (!empty($original->AplicacionId)) {
                        $nuevo->AplicacionId = $original->AplicacionId;
                    }
                }

                // Aplicar FibraRizo del hilo del modal (este campo NO viene del modelo)
                $nuevo->FibraRizo = $hilo ?: (!empty($original->FibraRizo) ? $original->FibraRizo : null);

                // Eficiencia y Velocidad: Si cambió clave modelo, ya se aplicaron desde el modelo
                // Si no cambió, se aplicarán desde catálogos más abajo

                // Maquina construida desde salón y telar
                if (isset($destino['maquina']) && $destino['maquina'] !== '') {
                    $nuevo->Maquina = StringTruncator::truncate('Maquina', $destino['maquina']);
                } else {
                    // Construir Maquina si no viene
                    $nuevo->Maquina = self::construirMaquina($original->Maquina ?? null, $salonDestino, $telarDestino);
                }

                // TamanoClave: priorizar valor del destino (fila)
                if ($tamanoClaveDestino) {
                    $nuevo->TamanoClave = StringTruncator::truncate('TamanoClave', $tamanoClaveDestino);
                } elseif ($tamanoClave) {
                    $nuevo->TamanoClave = $tamanoClave;
                }

                // ItemId e InventSizeId: Si se aplicaron datos del modelo, ya están correctos
                // NO sobrescribir con valores del destino (que pueden ser del modelo anterior)
                if (!$aplicarDatosModelo) {
                    // Si NO hubo cambio, entonces sí usar valores del destino o globales
                    // InventSizeId: priorizar valor del destino (fila)
                    if (isset($destino['inventSizeId']) && $destino['inventSizeId'] !== '') {
                        $nuevo->InventSizeId = StringTruncator::truncate('InventSizeId', $destino['inventSizeId']);
                    } elseif ($inventSizeId) {
                        $nuevo->InventSizeId = $inventSizeId;
                    }

                    // ItemId: priorizar valor del destino (fila)
                    if (isset($destino['itemId']) && $destino['itemId'] !== '') {
                        $nuevo->ItemId = StringTruncator::truncate('ItemId', $destino['itemId']);
                    } elseif ($codArticulo) {
                        $nuevo->ItemId = $codArticulo;
                    }
                }
                // Si hubo cambio de Clave Modelo, ItemId e InventSizeId ya están aplicados desde aplicarDatosModeloCodificado
                if ($productoDestino) {
                    // Usar valor específico del destino (fila)
                    $nuevo->NombreProducto = StringTruncator::truncate('NombreProducto', $productoDestino);
                } elseif ($producto) {
                    // Fallback a valor global
                    $nuevo->NombreProducto = StringTruncator::truncate('NombreProducto', $producto);
                }

                // FlogsId: Si se aplicaron datos del modelo, ya está aplicado
                // Si no se aplicaron, usar valor del destino o global
                if (!$aplicarDatosModelo) {
                    if ($flogDestino) {
                        // Usar valor específico del destino (fila)
                        $nuevo->FlogsId = StringTruncator::truncate('FlogsId', $flogDestino);
                    } elseif ($flog) {
                        // Fallback a valor global
                        $nuevo->FlogsId = StringTruncator::truncate('FlogsId', $flog);
                    }
                }
                // Si hubo cambio de Clave Modelo, FlogsId ya está aplicado desde aplicarDatosModeloCodificado

                // Aplicar TipoPedido basado en las primeras 2 letras del Flog (CE, RS, etc.)
                UpdateHelpers::applyFlogYTipoPedido($nuevo, $nuevo->FlogsId);

                // NombreProyecto: Si se aplicaron datos del modelo, ya está aplicado
                // Si no se aplicaron, usar valor del destino o global
                if (!$aplicarDatosModelo) {
                    if ($descripcionDestino) {
                        // Usar valor específico del destino (fila)
                        $nuevo->NombreProyecto = StringTruncator::truncate('NombreProyecto', $descripcionDestino);
                    } elseif ($descripcion) {
                        // Fallback a valor global
                        $nuevo->NombreProyecto = StringTruncator::truncate('NombreProyecto', $descripcion);
                    }
                }
                // Si hubo cambio de Clave Modelo, NombreProyecto ya está aplicado desde aplicarDatosModeloCodificado

                // CustName: Si se aplicaron datos del modelo, obtener desde el nuevo Flog
                // Si no se aplicaron, usar valor del destino o global
                if ($aplicarDatosModelo && !empty($nuevo->FlogsId)) {
                    // Obtener CustName desde TwFlogsTable usando el nuevo FlogsId
                    try {
                        $flogsIdParaCustName = trim((string)$nuevo->FlogsId);

                        LogFacade::info('DuplicarTejido: Obteniendo CustName desde Flog', [
                            'flogs_id' => $flogsIdParaCustName,
                            'tamano_clave' => $tamanoClaveDestino ?? $tamanoClave,
                            'hay_cambio_clave_modelo' => $hayCambioClaveModelo
                        ]);

                        $row = DBFacade::connection('sqlsrv_ti')
                            ->table('dbo.TwFlogsTable as ft')
                            ->select('ft.CUSTNAME as CustName')
                            ->where('ft.IDFLOG', $flogsIdParaCustName)
                            ->first();

                        if ($row && !empty($row->CustName)) {
                            $custNameObtenido = trim((string)$row->CustName);
                            $nuevo->CustName = StringTruncator::truncate('CustName', $custNameObtenido);

                            LogFacade::info('DuplicarTejido: CustName obtenido desde Flog', [
                                'flogs_id' => $flogsIdParaCustName,
                                'cust_name' => $custNameObtenido
                            ]);
                        } else {
                            LogFacade::warning('DuplicarTejido: No se encontró CustName para el Flog', [
                                'flogs_id' => $flogsIdParaCustName
                            ]);
                        }
                    } catch (\Throwable $e) {
                        // Si falla, continuar sin CustName
                        LogFacade::warning('DuplicarTejido: Error al obtener CustName desde Flog', [
                            'flogs_id' => $nuevo->FlogsId ?? 'NULL',
                            'error' => $e->getMessage()
                        ]);
                    }
                } else {
                    // Si NO hubo cambio de Clave Modelo, usar valor del destino o global
                    if ($custNameDestino) {
                        // Usar valor específico del destino (fila)
                        $nuevo->CustName = StringTruncator::truncate('CustName', $custNameDestino);
                    } elseif ($custname) {
                        $nuevo->CustName = StringTruncator::truncate('CustName', $custname);
                    }
                }

                // ===== PedidoTempo / Observaciones / Segundos =====
                if ($pedidoTempoDestino !== null && $pedidoTempoDestino !== '') {
                    $nuevo->PedidoTempo = $pedidoTempoDestino;
                }
                if ($observacionesDestino !== null && $observacionesDestino !== '') {
                    $nuevo->Observaciones = StringTruncator::truncate('Observaciones', $observacionesDestino);
                }
                if ($porcentajeSegundosDestino !== null) {
                    $nuevo->PorcentajeSegundos = $porcentajeSegundosDestino;
                }

                // ===== TotalPedido / SaldoPedido =====
                // TotalPedido = pedido (sin % de segundas)
                $pedidoDestino = ($pedidoDestinoRaw !== null && $pedidoDestinoRaw !== '') ? self::sanitizeNumber($pedidoDestinoRaw) : null;

                if ($pedidoDestino !== null) {
                    $nuevo->TotalPedido = $pedidoDestino;
                } elseif ($pedidoGlobal !== null) {
                    $nuevo->TotalPedido = $pedidoGlobal;
                } elseif (!empty($original->TotalPedido)) {
                    $nuevo->TotalPedido = self::sanitizeNumber($original->TotalPedido);
                } elseif (!empty($original->SaldoPedido)) {
                    $nuevo->TotalPedido = self::sanitizeNumber($original->SaldoPedido);
                } else {
                    $nuevo->TotalPedido = 0;
                }

                // SaldoPedido = saldo (con % de segundas aplicado)
                $saldoDestino = ($saldoDestinoRaw !== null && $saldoDestinoRaw !== '') ? self::sanitizeNumber($saldoDestinoRaw) : null;

                if ($saldoDestino !== null) {
                    $nuevo->SaldoPedido = $saldoDestino;
                } else {
                    // Si no viene saldo, calcularlo basado en TotalPedido y % de segundas
                    $porcentajeSegundos = $porcentajeSegundosDestino ?? 0;
                    $nuevo->SaldoPedido = $nuevo->TotalPedido * (1 + $porcentajeSegundos / 100);
                }

                // ===== FORZAR STD DESDE CATÁLOGOS (SMITH/JACQUARD + Normal/Alta) =====
                // IMPORTANTE: Si NO se aplicaron datos del modelo, aplicar desde catálogos
                // Si se aplicaron, ya se aplicaron desde el modelo codificado
                if (!$aplicarDatosModelo) {
                    self::aplicarStdDesdeCatalogos($nuevo);
                }


                // ===== FECHA INICIO: SIEMPRE la FechaFinal del último registro del telar destino =====
                // NO hacer snap al calendario, usar exactamente la fecha final del último registro
                $nuevo->FechaInicio = $fechaInicioBase->format('Y-m-d H:i:s');
                $inicio = $fechaInicioBase->copy();

                // ===== CALCULAR FECHA FINAL desde la fecha inicio exacta =====
                $horasNecesarias = self::calcularHorasProd($nuevo);

                // fallback proporcional si por alguna razón horas=0 pero existe HorasProd en original
                if ($horasNecesarias <= 0 && !empty($original->HorasProd)) {
                    $cantOrig = self::sanitizeNumber($original->SaldoPedido ?? $original->TotalPedido ?? 0);
                    $cantNew  = self::sanitizeNumber($nuevo->SaldoPedido ?? $nuevo->TotalPedido ?? 0);
                    if ($cantOrig > 0 && $cantNew > 0) {
                        $horasNecesarias = (float)$original->HorasProd * ($cantNew / $cantOrig);
                    }
                }

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



                if (!empty($nuevo->FechaFinal) && !empty($nuevo->FechaInicio)) {
                    if (Carbon::parse($nuevo->FechaFinal)->lt(Carbon::parse($nuevo->FechaInicio))) {
                        $nuevo->FechaFinal = $nuevo->FechaInicio;
                    }
                }

                // ===== CambioHilo =====
                if ($ultimoDestino) {
                    $nuevo->CambioHilo = (trim((string)$nuevo->FibraRizo) !== trim((string)$ultimoDestino->FibraRizo)) ? '1' : '0';
                }

                // ===== Fórmulas =====
                if (!empty($nuevo->FechaInicio) && !empty($nuevo->FechaFinal)) {
                    $formulas = self::calcularFormulasEficiencia($nuevo);
                    foreach ($formulas as $campo => $valor) {
                        $nuevo->{$campo} = $valor;
                    }
                }

                // Truncar strings
                foreach ([
                    'Maquina','NombreProyecto','CustName','AplicacionId','NombreProducto',
                    'TipoPedido','Observaciones','FibraTrama','FibraComb1','FibraComb2',
                    'FibraComb3','FibraComb4','FibraComb5','FibraPie','SalonTejidoId','NoTelarId',
                    'Rasurado','TamanoClave','ItemId','InventSizeId'
                ] as $campoStr) {
                    if (isset($nuevo->{$campoStr})) {
                        $nuevo->{$campoStr} = StringTruncator::truncate($campoStr, $nuevo->{$campoStr});
                    }
                }

                // Log ANTES de guardar para verificar valores finales
                LogFacade::info('DuplicarTejido: Valores ANTES de guardar', [
                    'id' => $nuevo->Id ?? 'nuevo',
                    'tamano_clave' => $nuevo->TamanoClave,
                    'ItemId' => $nuevo->ItemId,
                    'InventSizeId' => $nuevo->InventSizeId,
                    'CustName' => $nuevo->CustName,
                    'FlogsId' => $nuevo->FlogsId,
                    'hay_cambio_clave_modelo' => $hayCambioClaveModelo,
                    'aplicar_datos_modelo' => $aplicarDatosModelo,
                    'tamano_clave_destino' => $tamanoClaveDestino,
                    'tamano_clave_original' => $original->TamanoClave
                ]);

                $nuevo->CreatedAt = now();
                $nuevo->UpdatedAt = now();
                $nuevo->save();

                // Log DESPUÉS de guardar para verificar que se guardó correctamente
                $nuevo->refresh();
                LogFacade::info('DuplicarTejido: Valores DESPUÉS de guardar (desde BD)', [
                    'id' => $nuevo->Id,
                    'tamano_clave' => $nuevo->TamanoClave,
                    'ItemId' => $nuevo->ItemId,
                    'InventSizeId' => $nuevo->InventSizeId,
                    'CustName' => $nuevo->CustName,
                    'FlogsId' => $nuevo->FlogsId
                ]);

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

            ReqProgramaTejido::whereIn('Id', $idsParaObserver)->update(['EnProceso' => 0]);

            $primer = !empty($idsParaObserver) ? ReqProgramaTejido::find($idsParaObserver[0]) : null;

            return response()->json([
                'success' => true,
                'message' => "Telar duplicado correctamente. Se crearon {$totalDuplicados} registro(s).",
                'registros_duplicados' => $totalDuplicados,
                'registro_id' => $primer?->Id,
                'registros_ids' => $idsParaObserver, // Devolver TODOS los IDs de los registros creados
                'salon_destino' => $primer?->SalonTejidoId,
                'telar_destino' => $primer?->NoTelarId,
            ]);

        } catch (\Throwable $e) {
            DBFacade::rollBack();

            if ($dispatcher) {
                ReqProgramaTejido::setEventDispatcher($dispatcher);
            }
            ReqProgramaTejido::observe(ReqProgramaTejidoObserver::class);



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

        if (!isset(self::$modeloCache[$key])) {
            $m = ReqModelosCodificados::where('TamanoClave', $key)->first();
            self::$modeloCache[$key] = $m ? [
                'total' => (float)($m->Total ?? 0),
                'no_tiras' => (float)($m->NoTiras ?? 0),
                'luchaje' => (float)($m->Luchaje ?? 0),
                'repeticiones' => (float)($m->Repeticiones ?? 0),
            ] : [
                'total' => 0.0,
                'no_tiras' => 0.0,
                'luchaje' => 0.0,
                'repeticiones' => 0.0,
            ];
        }

        $base = self::$modeloCache[$key];

        return [
            'total' => (float)($base['total'] ?? 0),
            'no_tiras' => $noTiras > 0 ? $noTiras : (float)($base['no_tiras'] ?? 0),
            'luchaje' => $luchaje > 0 ? $luchaje : (float)($base['luchaje'] ?? 0),
            'repeticiones' => $rep > 0 ? $rep : (float)($base['repeticiones'] ?? 0),
        ];
    }

    // =========================
    // STD DESDE CATÁLOGOS
    // =========================

    private static function aplicarStdDesdeCatalogos(ReqProgramaTejido $p): void
    {
        TejidoHelpers::aplicarStdDesdeCatalogos($p);
    }

    public static function calcularFormulasEficiencia(ReqProgramaTejido $programa): array
    {
        try {
            $m = self::getModeloParams($programa->TamanoClave ?? null, $programa);
            return TejidoHelpers::calcularFormulasEficiencia($programa, $m, true, true, true);
        } catch (\Throwable $e) {
            LogFacade::warning('DuplicarTejido: Error al calcular formulas', [
                'error' => $e->getMessage(),
                'programa_id' => $programa->Id ?? null,
            ]);
        }

        return [];
    }

    // =========================
    // HELPERS CONSTRUIDOS EN TEJIDOHELPERS
    // =========================

    private static function construirMaquina(?string $maquinaBase, ?string $salon, $telar): string
    {
        return TejidoHelpers::construirMaquinaConBase($maquinaBase, $salon, $telar);
    }

    private static function sanitizeNumber($value): float
    {
        return TejidoHelpers::sanitizeNumber($value);
    }

    private static function sanitizeNullableNumber($value): ?float
    {
        return TejidoHelpers::sanitizeNullableNumber($value);
    }

    /**
     * Obtiene todos los datos del modelo codificado para un tamano_clave específico
     * Similar a getDatosRelacionados pero para uso interno en duplicación
     */
    private static function getDatosModeloCodificado(string $tamanoClave, string $salon): ?array
    {
        $cacheKey = $salon . '|' . $tamanoClave;

        if (isset(self::$datosModeloCache[$cacheKey])) {
            return self::$datosModeloCache[$cacheKey];
        }

        // Usar solo columnas que existen en ReqModelosCodificados
        // NOTA: VelocidadSTD y EficienciaSTD no vienen del modelo, vienen de tablas separadas
        $selectCols = [
            'TamanoClave', 'SalonTejidoId', 'FlogsId', 'NombreProyecto', 'InventSizeId', 'ItemId', 'Nombre',
            'AnchoToalla', 'LargoToalla', 'CuentaPie', 'MedidaPlano', 'PesoCrudo',
            'NoTiras', 'Luchaje', 'Repeticiones', 'Total', 'CalibreTrama', 'CalibreTrama2',
            'FibraId', 'CalibreRizo', 'CalibreRizo2', 'CuentaRizo', 'CalibrePie', 'CalibrePie2',
            'Peine', 'Rasurado', 'CodColorTrama', 'ColorTrama', 'DobladilloId',
            'PasadasTramaFondoC1', 'FibraTramaFondoC1',
            'PasadasComb1', 'PasadasComb2', 'PasadasComb3', 'PasadasComb4', 'PasadasComb5',
            'CalibreComb1', 'CalibreComb12', 'FibraComb1', 'CodColorC1', 'NomColorC1',
            'CalibreComb2', 'CalibreComb22', 'FibraComb2', 'CodColorC2', 'NomColorC2',
            'CalibreComb3', 'CalibreComb32', 'FibraComb3', 'CodColorC3', 'NomColorC3',
            'CalibreComb4', 'CalibreComb42', 'FibraComb4', 'CodColorC4', 'NomColorC4',
            'CalibreComb5', 'CalibreComb52', 'FibraComb5', 'CodColorC5', 'NomColorC5'
        ];

        $tam = trim($tamanoClave);
        if ($tam) {
            $tam = preg_replace('/\s+/', ' ', $tam);
        }

        $qBase = ReqModelosCodificados::where('SalonTejidoId', $salon);

        // Intento exacto
        $datos = (clone $qBase)
            ->whereRaw("REPLACE(UPPER(LTRIM(RTRIM(TamanoClave))), '  ', ' ') = ?", [strtoupper($tam)])
            ->select($selectCols)
            ->first();

        // Prefijo
        if (!$datos) {
            $datos = (clone $qBase)
                ->whereRaw('UPPER(TamanoClave) like ?', [strtoupper($tam) . '%'])
                ->select($selectCols)
                ->first();
        }

        // Contiene
        if (!$datos) {
            $datos = (clone $qBase)
                ->whereRaw('UPPER(TamanoClave) like ?', ['%' . strtoupper($tam) . '%'])
                ->select($selectCols)
                ->first();
        }

        if ($datos) {
            $resultado = $datos->toArray();

            // Mapear campos específicos
            $resultado['PasadasTrama'] = $resultado['PasadasTramaFondoC1'] ?? null;
            $resultado['FibraTrama'] = $resultado['FibraTramaFondoC1'] ?? $resultado['FibraId'] ?? null;

            // Limpiar campos que no queremos
            unset($resultado['PasadasTramaFondoC1'], $resultado['FibraTramaFondoC1']);

            self::$datosModeloCache[$cacheKey] = $resultado;
            return $resultado;
        }

        self::$datosModeloCache[$cacheKey] = null;
        return null;
    }

    /**
     * Aplica todos los datos del modelo codificado a un registro cuando cambia el tamano clave
     */
    private static function aplicarDatosModeloCodificado(ReqProgramaTejido $nuevo, string $tamanoClave, string $salon): void
    {
        $datosModelo = self::getDatosModeloCodificado($tamanoClave, $salon);

        if (!$datosModelo) {
            LogFacade::warning('DuplicarTejido: No se encontraron datos para el modelo', [
                'tamano_clave' => $tamanoClave,
                'salon' => $salon
            ]);
            return;
        }

        // Log para verificar ItemId e InventSizeId antes de aplicar
        LogFacade::info('DuplicarTejido: Datos del modelo antes de aplicar', [
            'tamano_clave' => $tamanoClave,
            'salon' => $salon,
            'ItemId_en_modelo' => $datosModelo['ItemId'] ?? 'NO EXISTE',
            'InventSizeId_en_modelo' => $datosModelo['InventSizeId'] ?? 'NO EXISTE',
            'ItemId_tipo' => isset($datosModelo['ItemId']) ? gettype($datosModelo['ItemId']) : 'N/A',
            'InventSizeId_tipo' => isset($datosModelo['InventSizeId']) ? gettype($datosModelo['InventSizeId']) : 'N/A',
            'ItemId_antes' => $nuevo->ItemId ?? 'NULL',
            'InventSizeId_antes' => $nuevo->InventSizeId ?? 'NULL'
        ]);



        // Aplicar campos del modelo usando StringTruncator
        // NOTA: VelocidadSTD y EficienciaSTD NO vienen del modelo, vienen de tablas separadas ReqVelocidadStd/ReqEficienciaStd
        $camposAAplicar = [
            'FlogsId' => 'FlogsId',
            'NombreProyecto' => 'NombreProyecto',
            'InventSizeId' => 'InventSizeId',
            'ItemId' => 'ItemId',
            'Nombre' => 'NombreProducto',
            'AnchoToalla' => 'AnchoToalla',
            'LargoToalla' => 'LargoCrudo', // Largo del modelo -> LargoCrudo del registro
            'CuentaPie' => 'CuentaPie',
            'MedidaPlano' => 'MedidaPlano',
            'PesoCrudo' => 'PesoCrudo',
            'NoTiras' => 'NoTiras',
            'Luchaje' => 'Luchaje',
            'Repeticiones' => 'Repeticiones',
            'CalibreTrama' => 'CalibreTrama2', // Invertido: CalibreTrama del modelo -> CalibreTrama2 del registro
            'CalibreTrama2' => 'CalibreTrama', // Invertido: CalibreTrama2 del modelo -> CalibreTrama del registro
            'CalibreRizo' => 'CalibreRizo',
            'CalibreRizo2' => 'CalibreRizo2',
            'CuentaRizo' => 'CuentaRizo',
            'CalibrePie' => 'CalibrePie',
            'CalibrePie2' => 'CalibrePie2',
            'Peine' => 'Peine',
            'Rasurado' => 'Rasurado',
            'CodColorTrama' => 'CodColorTrama',
            'ColorTrama' => 'ColorTrama',
            'DobladilloId' => 'DobladilloId',
            'PasadasTrama' => 'PasadasTrama',
            'FibraTrama' => 'FibraTrama',
            'PasadasComb1' => 'PasadasComb1',
            'PasadasComb2' => 'PasadasComb2',
            'PasadasComb3' => 'PasadasComb3',
            'PasadasComb4' => 'PasadasComb4',
            'PasadasComb5' => 'PasadasComb5',
            'CalibreComb1' => 'CalibreComb1',
            'CalibreComb12' => 'CalibreComb12',
            'FibraComb1' => 'FibraComb1',
            'CodColorC1' => 'CodColorComb1',
            'NomColorC1' => 'NombreCC1',
            'CalibreComb2' => 'CalibreComb2',
            'CalibreComb22' => 'CalibreComb22',
            'FibraComb2' => 'FibraComb2',
            'CodColorC2' => 'CodColorComb2',
            'NomColorC2' => 'NombreCC2',
            'CalibreComb3' => 'CalibreComb3',
            'CalibreComb32' => 'CalibreComb32',
            'FibraComb3' => 'FibraComb3',
            'CodColorC3' => 'CodColorComb3',
            'NomColorC3' => 'NombreCC3',
            'CalibreComb4' => 'CalibreComb4',
            'CalibreComb42' => 'CalibreComb42',
            'FibraComb4' => 'FibraComb4',
            'CodColorC4' => 'CodColorComb4',
            'NomColorC4' => 'NombreCC4',
            'CalibreComb5' => 'CalibreComb5',
            'CalibreComb52' => 'CalibreComb52',
            'FibraComb5' => 'FibraComb5',
            'CodColorC5' => 'CodColorComb5',
            'NomColorC5' => 'NombreCC5'
        ];

        foreach ($camposAAplicar as $campoModelo => $campoRegistro) {
            // Para ItemId e InventSizeId, permitir valores incluso si están vacíos (pueden ser strings vacíos válidos)
            $esCampoEspecial = in_array($campoModelo, ['ItemId', 'InventSizeId']);

            if (isset($datosModelo[$campoModelo])) {
                $valorRaw = $datosModelo[$campoModelo];

                // Para campos especiales (ItemId, InventSizeId), permitir valores null o vacíos
                // Para otros campos, requerir que no sean null ni vacíos
                if (!$esCampoEspecial && ($valorRaw === null || $valorRaw === '')) {
                    continue;
                }

                $valor = $valorRaw;

                // Aplicar conversión de tipos para campos numéricos
                if (in_array($campoModelo, ['VelocidadSTD', 'EficienciaSTD', 'AnchoToalla', 'LargoToalla', 'CuentaPie', 'MedidaPlano', 'PesoCrudo',
                    'NoTiras', 'Luchaje', 'Repeticiones', 'CalibreTrama', 'CalibreTrama2', 'CalibreRizo', 'CalibreRizo2',
                    'CuentaRizo', 'CalibrePie', 'CalibrePie2', 'Peine', 'PasadasTrama', 'PasadasComb1', 'PasadasComb2',
                    'PasadasComb3', 'PasadasComb4', 'PasadasComb5', 'CalibreComb1', 'CalibreComb12', 'CalibreComb2',
                    'CalibreComb22', 'CalibreComb3', 'CalibreComb32', 'CalibreComb4', 'CalibreComb42', 'CalibreComb5', 'CalibreComb52'])) {
                    $valor = is_numeric($valor) ? (float)$valor : null;
                } elseif (in_array($campoModelo, ['NoTiras', 'Peine', 'Luchaje', 'Repeticiones', 'PasadasTrama', 'PasadasComb1', 'PasadasComb2',
                    'PasadasComb3', 'PasadasComb4', 'PasadasComb5'])) {
                    $valor = is_numeric($valor) ? (int)$valor : null;
                } else {
                    // Campos string - para ItemId e InventSizeId, trim y aplicar truncate
                    if ($esCampoEspecial) {
                        $valor = trim((string)$valor);
                    }
                    $valor = StringTruncator::truncate($campoRegistro, $valor);
                }

                $nuevo->{$campoRegistro} = $valor;

                // Log para debugging ItemId e InventSizeId
                if ($esCampoEspecial) {
                    LogFacade::info('DuplicarTejido: Aplicando campo del modelo', [
                        'campo_modelo' => $campoModelo,
                        'campo_registro' => $campoRegistro,
                        'valor_original' => $valorRaw,
                        'valor_aplicado' => $valor,
                        'tamano_clave' => $tamanoClave
                    ]);
                }
            }
        }

        // Log para verificar ItemId e InventSizeId después de aplicar
        LogFacade::info('DuplicarTejido: Datos del modelo después de aplicar', [
            'tamano_clave' => $tamanoClave,
            'ItemId_despues' => $nuevo->ItemId ?? 'NULL',
            'InventSizeId_despues' => $nuevo->InventSizeId ?? 'NULL'
        ]);
    }
}
