<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\TelTelaresOperador;
use App\Models\TejInventarioTelares;
use App\Models\TelMarbeteLiberadoModel;
use Carbon\Carbon;

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
            
        return view('modulos.notificar-montado-rollos.index', compact('telares', 'tipo', 'telaresUsuario', 'telarSeleccionado'));
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
                $testConexion = DB::connection('sqlsrv_tow_pro')->select('SELECT @@VERSION as version');
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
            $datosProduccion = DB::connection('sqlsrv_tow_pro')
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
                    'error' => 'No se encontraron datos de producción en TOW_PRO',
                    'debug' => [
                        'no_produccion' => $noProduccion,
                        'no_telar' => $noTelar,
                        'query' => 'ProdTable INNER JOIN InventDim con filtros: impreso=SI, ProdStatus=0'
                    ]
                ], 404);
            }

            // Formatear datos para el modal (sin insertar aún)
            $datosFormateados = [];
            foreach ($datosProduccion as $dato) {
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

            return response()->json([
                'success' => true,
                'datos' => $datosFormateados,
                'total' => count($datosFormateados),
                'mensaje' => "Se encontraron {$datosProduccion->count()} registros en TI_PRO"
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
            $actualizados = 0;
            $errores = [];

            foreach ($marbetesSeleccionados as $marbete) {
                try {
                    // Verificar si ya existe
                    $marbeteExistente = TelMarbeteLiberadoModel::where('PurchBarCode', $marbete['PurchBarCode'])->first();

                    $datosAGuardar = [
                        'ItemId' => $marbete['ItemId'],
                        'InventSizeId' => $marbete['InventSizeId'],
                        'InventBatchId' => $marbete['InventBatchId'],
                        'WMSLocationId' => $marbete['WMSLocationId'],
                        'QtySched' => $marbete['QtySched'],
                        'Salon' => $marbete['Salon'] ?? '',
                    ];

                    if ($marbeteExistente) {
                        $marbeteExistente->update($datosAGuardar);
                        $actualizados++;
                    } else {
                        $datosAGuardar['PurchBarCode'] = $marbete['PurchBarCode'];
                        TelMarbeteLiberadoModel::create($datosAGuardar);
                        $insertados++;
                    }
                } catch (\Exception $e) {
                    $errores[] = "Error en marbete {$marbete['PurchBarCode']}: " . $e->getMessage();
                }
            }

            return response()->json([
                'success' => true,
                'insertados' => $insertados,
                'actualizados' => $actualizados,
                'errores' => $errores,
                'mensaje' => "Insertados: {$insertados}, Actualizados: {$actualizados}"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al insertar marbetes: ' . $e->getMessage()
            ], 500);
        }
    }
}
