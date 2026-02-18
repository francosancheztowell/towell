<?php

namespace App\Http\Controllers\Tejedores\NotificarMontadoRollo;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Tejedores\TelTelaresOperador;
use App\Models\Tejido\TejInventarioTelares;
use App\Models\Tejedores\TelMarbeteLiberadoModel;
use App\Models\Planeacion\ReqProgramaTejido;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class NotificarMontRollosController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        // Obtener los registros de telares asignados al usuario actual
        $telaresUsuario = TelTelaresOperador::where('numero_empleado', $user->numero_empleado)
            ->select('NoTelarId', 'numero_empleado', 'nombreEmpl')
            ->orderBy('NoTelarId')
            ->get();

        // Obtener array de IDs de telares
        $telaresOperador = $telaresUsuario->pluck('NoTelarId')->toArray();

        // Determinar el telar seleccionado (por parámetro o el primero)
        $telarSeleccionado = $request->query('telar') ?? ($telaresUsuario->first()->NoTelarId ?? null);

        // Si es una petición AJAX
        if ($request->ajax() || $request->wantsJson()) {
            if ($request->has('listado')) {
                $tipoFiltro = $request->query('tipo');

                $query = TejInventarioTelares::select('no_telar', 'tipo')
                    ->distinct()
                    ->orderBy('no_telar')
                    ->whereIn('no_telar', $telaresOperador);

                if ($tipoFiltro && in_array($tipoFiltro, ['rizo', 'pie'])) {
                    $query->where('tipo', $tipoFiltro);
                }

                return response()->json([
                    'telares' => $telaresUsuario->pluck('NoTelarId')->values(),
                    'telaresDetalle' => $query->get()
                ]);
            }

            // Si se solicita detalle de un telar específico con tipo
            if ($request->has('no_telar') && $request->has('tipo')) {
                $detalles = TejInventarioTelares::where('no_telar', $request->no_telar)
                    ->where('tipo', $request->tipo)
                    ->whereIn('no_telar', $telaresOperador)
                    ->select('id', 'no_telar', 'cuenta', 'calibre', 'tipo', 'tipo_atado', 'no_orden', 'no_rollo', 'metros', 'horaParo')
                    ->first();

                return response()->json(['detalles' => $detalles]);
            }

            return response()->json(['error' => 'Parámetros inválidos'], 400);
        }

        // Si no es AJAX, devolver vista
        $tipo = $request->query('tipo');

        // Filtrar por telar seleccionado si existe
        $query = TejInventarioTelares::select('no_telar', 'tipo')
            ->distinct()
            ->orderBy('no_telar');

        if ($telarSeleccionado) {
            $query->where('no_telar', $telarSeleccionado);
        } else {
            $query->whereIn('no_telar', $telaresOperador);
        }

        if ($tipo && in_array($tipo, ['rizo', 'pie'])) {
            $query->where('tipo', $tipo);
        }

        $telares = $query->get();

        return view('modulos.tejedores.notificar-mont-rollos.index', compact('telares', 'tipo', 'telaresUsuario', 'telarSeleccionado'));
    }

    public function telares(Request $request)
    {
        $user = Auth::user();

        $telaresUsuario = TelTelaresOperador::where('numero_empleado', $user->numero_empleado)
            ->select('NoTelarId')
            ->orderBy('NoTelarId')
            ->get();

        $telaresOperador = $telaresUsuario->pluck('NoTelarId')->toArray();

        $telares = TejInventarioTelares::select('no_telar', 'tipo')
            ->distinct()
            ->whereIn('no_telar', $telaresOperador)
            ->orderBy('no_telar')
            ->get();

        return response()->json([
            'telares' => $telares
        ]);
    }

    public function detalle(Request $request)
    {
        $user = Auth::user();
        $noTelar = $request->query('no_telar');

        if (!$noTelar) {
            return response()->json(['error' => 'No se proporciono el numero de telar'], 400);
        }

        $telaresOperador = TelTelaresOperador::where('numero_empleado', $user->numero_empleado)
            ->pluck('NoTelarId')
            ->toArray();

        $detalles = TejInventarioTelares::where('no_telar', $noTelar)
            ->whereIn('no_telar', $telaresOperador)
            ->select('id', 'no_telar', 'cuenta', 'calibre', 'tipo', 'tipo_atado', 'no_orden', 'no_rollo', 'metros', 'horaParo')
            ->first();

        if (!$detalles) {
            return response()->json(['error' => 'Telar no encontrado'], 404);
        }

        return response()->json(['detalles' => $detalles]);
    }

    public function notificar(Request $request)
    {
        try {
            $registro = TejInventarioTelares::find($request->id);

            if (!$registro) {
                return response()->json(['error' => 'Registro no encontrado'], 404);
            }

            // Actualizar horaParo con la hora actual
            $horaActual = Carbon::now()->format('H:i:s');
            $registro->horaParo = $horaActual;
            $registro->save();

            return response()->json([
                'success' => true,
                'horaParo' => $horaActual,
                'message' => 'Notificación de rollo registrada correctamente'
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtener TODAS las órdenes en proceso de un telar
     * Un telar puede tener múltiples órdenes con EnProceso = 1
     */
    public function obtenerOrdenesEnProceso($telarId)
    {
        try {
            $user = Auth::user();
            
            // Validar que el telar pertenece al operador
            $telaresOperador = TelTelaresOperador::where('numero_empleado', $user->numero_empleado)
                ->pluck('NoTelarId')
                ->toArray();

            if (!in_array($telarId, $telaresOperador)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tiene permisos para ver este telar'
                ], 403);
            }

            // Obtener TODAS las órdenes EN PROCESO (EnProceso = 1)
            $ordenesEnProceso = ReqProgramaTejido::where('NoTelarId', $telarId)
                ->where('EnProceso', 1)
                ->whereNotNull('NoProduccion')
                ->where('NoProduccion', '!=', '')
                ->select('SalonTejidoId', 'NoProduccion', 'FechaInicio', 'TamanoClave', 'NombreProducto')
                ->distinct()
                ->orderBy('FechaInicio', 'asc')
                ->get();

            Log::info('obtenerOrdenesEnProceso - Cortado de Rollo', [
                'telarId' => $telarId,
                'count' => $ordenesEnProceso->count(),
                'ordenes' => $ordenesEnProceso->pluck('NoProduccion')->toArray(),
            ]);

            return response()->json([
                'success' => true,
                'ordenes' => $ordenesEnProceso
            ]);
        } catch (\Exception $e) {
            Log::error('Error en obtenerOrdenesEnProceso - Cortado de Rollo', [
                'error' => $e->getMessage(),
                'telarId' => $telarId
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las órdenes en proceso: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener orden de producción activa desde ReqProgramaTejido
     */
    public function getOrdenProduccion(Request $request)
    {
        try {
            $noTelar = $request->query('no_telar');

            if (!$noTelar) {
                return response()->json(['error' => 'No se proporcionó el número de telar'], 400);
            }

            // Probar conexión a TOW_PRO
            try {
                $testConexion = DB::connection('sqlsrv_ti')->select('SELECT @@VERSION as version');
                $conexionTowPro = 'OK - ' . ($testConexion[0]->version ?? 'Conectado');
            } catch (\Exception $e) {
                $conexionTowPro = 'ERROR: ' . $e->getMessage();
            }

            // Buscar orden activa en ReqProgramaTejido para el telar
            $ordenActiva = DB::table('ReqProgramaTejido')
                ->where('NoTelarId', $noTelar)
                ->where('EnProceso', 1) // Orden activa/en proceso
                ->select('NoProduccion', 'NoTelarId', 'SalonTejidoId')
                ->first();

            if (!$ordenActiva) {
                return response()->json([
                    'error' => 'No se encontró orden de producción activa para este telar',
                    'debug' => [
                        'telar_buscado' => $noTelar,
                        'conexion_tow_pro' => $conexionTowPro
                    ]
                ], 404);
            }

            return response()->json([
                'success' => true,
                'orden' => $ordenActiva,
                'debug' => [
                    'conexion_tow_pro' => $conexionTowPro
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener orden de producción: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener datos de producción desde TI_PRO para mostrar en modal
     */
    public function getDatosProduccion(Request $request)
    {
        try {
            $noProduccion = $request->query('no_produccion');
            $noTelar = $request->query('no_telar');
            $salon = $request->query('salon');

            if (!$noProduccion || !$noTelar) {
                return response()->json(['error' => 'Faltan parámetros requeridos (no_produccion, no_telar)'], 400);
            }

            // Consultar TOW_PRO con el INNER JOIN correcto
            $datosProduccion = DB::connection('sqlsrv_ti')
                ->table('ProdTable as P')
                ->join('InventDim as I', 'P.InventDimId', '=', 'I.InventDimId')
                ->select(
                    'P.PurchBarCode',
                    'P.ItemId',
                    'P.QtySched',
                    'P.CUANTAS',
                    'I.InventSizeId',
                    'I.InventBatchId',
                    'I.WMSLocationId'
                )
                ->where('P.impreso', 'SI')
                ->where('P.ProdStatus', 0)
                ->where('P.DATAAREAID', 'PRO')
                ->where('I.InventBatchId', $noProduccion)
                ->where('I.WMSLocationId', $noTelar)
                ->where('I.DATAAREAID', 'PRO')
                ->get();

            if ($datosProduccion->isEmpty()) {
                return response()->json([
                    'error' => 'No se encontraron datos de producción',
                    'debug' => [
                        'no_produccion' => $noProduccion,
                        'no_telar' => $noTelar,
                        'query' => 'ProdTable INNER JOIN InventDim con filtros: impreso=SI, ProdStatus=0'
                    ]
                ], 404);
            }

            // Obtener los PurchBarCode que ya están liberados en TelMarbeteLiberado
            $marbetesLiberados = TelMarbeteLiberadoModel::pluck('PurchBarCode')->toArray();

            // Formatear datos para el modal, excluyendo los ya liberados
            $datosFormateados = [];
            $excluidos = 0;
            foreach ($datosProduccion as $dato) {
                // Saltar este registro si ya fue liberado
                if (in_array($dato->PurchBarCode, $marbetesLiberados)) {
                    $excluidos++;
                    continue;
                }

                $datosFormateados[] = [
                    'PurchBarCode' => $dato->PurchBarCode,
                    'ItemId' => $dato->ItemId,
                    'QtySched' => $dato->QtySched,
                    'CUANTAS' => $dato->CUANTAS,
                    'InventSizeId' => $dato->InventSizeId,
                    'InventBatchId' => $dato->InventBatchId,
                    'WMSLocationId' => $dato->WMSLocationId,
                    'Salon' => $salon
                ];
            }

            // Si no quedan registros después de filtrar
            if (empty($datosFormateados)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Todos los marbetes ya han sido liberados',
                    'mensaje' => "Se encontraron {$datosProduccion->count()} registros, pero todos ya fueron liberados anteriormente.",
                    'debug' => [
                        'total_encontrados' => $datosProduccion->count(),
                        'excluidos' => $excluidos
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'datos' => $datosFormateados,
                'total' => count($datosFormateados),
                'mensaje' => "Se encontraron " . count($datosFormateados) . " marbetes disponibles" . ($excluidos > 0 ? " ({$excluidos} ya liberados)" : "")
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener datos de producción: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Insertar registros seleccionados en TelMarbeteLiberado
     */
    public function insertarMarbetes(Request $request)
    {
        try {
            $marbetesSeleccionados = $request->input('marbetes');

            if (empty($marbetesSeleccionados)) {
                return response()->json(['error' => 'No se proporcionaron marbetes para insertar'], 400);
            }

            $insertados = 0;
            $yaExistian = 0;
            $errores = [];

            foreach ($marbetesSeleccionados as $marbete) {
                try {
                    // Verificar si ya existe
                    $marbeteExistente = TelMarbeteLiberadoModel::where('PurchBarCode', $marbete['PurchBarCode'])->first();

                    if ($marbeteExistente) {
                        // Si ya existe, solo contamos pero no actualizamos
                        $yaExistian++;
                        continue;
                    }

                    // Solo insertar si no existe
                    $datosAGuardar = [
                        'PurchBarCode' => $marbete['PurchBarCode'],
                        'ItemId' => $marbete['ItemId'],
                        'InventSizeId' => $marbete['InventSizeId'],
                        'InventBatchId' => $marbete['InventBatchId'],
                        'WMSLocationId' => $marbete['WMSLocationId'],
                        'QtySched' => $marbete['QtySched'],
                        'Salon' => $marbete['Salon'] ?? '',
                        'CUANTAS' => $marbete['CUANTAS'] ?? null,
                    ];

                    TelMarbeteLiberadoModel::create($datosAGuardar);
                    $insertados++;
                } catch (\Exception $e) {
                    $errores[] = "Error en marbete {$marbete['PurchBarCode']}: " . $e->getMessage();
                }
            }

            $mensaje = "Marbete liberado correctamente";
            if ($yaExistian > 0) {
                $mensaje .= " (Ya existía en la base de datos)";
            }

            return response()->json([
                'success' => true,
                'insertados' => $insertados,
                'yaExistian' => $yaExistian,
                'errores' => $errores,
                'mensaje' => $mensaje
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al insertar marbetes: ' . $e->getMessage()
            ], 500);
        }
    }
}
