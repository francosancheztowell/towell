<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\TelTelaresOperador;
use App\Models\TejInventarioTelares;
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

            // Buscar orden activa en ReqProgramaTejido para el telar
            $ordenActiva = DB::table('ReqProgramaTejido')
                ->where('NoTelarId', $noTelar)
                ->where('EnProceso', 1) // Orden activa/en proceso
                ->select('NoProduccion', 'NoTelarId', 'SalonTejidoId')
                ->first();

            if (!$ordenActiva) {
                return response()->json([
                    'error' => 'No se encontró orden de producción activa para este telar'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'orden' => $ordenActiva
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener orden de producción: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener datos de producción desde TOW_PRO y validar marbetes
     */
    public function getDatosProduccion(Request $request)
    {
        try {
            $noProduccion = $request->query('no_produccion');

            if (!$noProduccion) {
                return response()->json(['error' => 'No se proporcionó el número de producción'], 400);
            }

            // Consultar ProdTable e InventDim en TOW_PRO
            $datosProduccion = DB::connection('sqlsrv_tow_pro')
                ->table('ProdTable as p')
                ->join('InventDim as i', 'p.InventDimId', '=', 'i.InventDimId')
                ->select(
                    'p.ProdId',
                    'p.ItemId',
                    'i.InventSizeId',
                    'i.inventBatchId as PurchBarCode',
                    'i.inventBatchId as InventBatchId',
                    'p.QtySched',
                    'i.WMSLocationId'
                )
                ->where('p.impreso', 'SI')
                ->where('p.ProdStatus', 0)
                ->where('p.DATAAREAID', 'PRO')
                ->where('i.InventDimId', DB::raw('p.inventDimId'))
                ->where('i.WMSLocationId', DB::raw('SUBSTRING(p.ItemId, 1, 1)'))
                ->where('i.inventBatchId', $noProduccion) // Filtrar por NoProduccion (Orden Activa)
                ->where('i.DATAAREAID', 'PRO')
                ->get();

            if ($datosProduccion->isEmpty()) {
                return response()->json([
                    'error' => 'No se encontraron datos de producción para esta orden'
                ], 404);
            }

            // Validar cada marbete contra TelMarbeteLiberado
            $datosValidados = [];
            foreach ($datosProduccion as $dato) {
                $marbeteValido = DB::table('TelMarbeteLiberado')
                    ->where('PurchBarCode', $dato->PurchBarCode)
                    ->exists();

                if ($marbeteValido) {
                    $datosValidados[] = [
                        'Marbete' => $dato->PurchBarCode,
                        'Articulo' => $dato->ItemId,
                        'Tamaño' => $dato->InventSizeId,
                        'Orden' => $dato->InventBatchId,
                        'Telar' => '', // Se puede agregar si está disponible
                        'Piezas' => $dato->QtySched,
                        'Salon' => $dato->WMSLocationId,
                        'valido' => true
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'datos' => $datosValidados,
                'total' => count($datosValidados)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener datos de producción: ' . $e->getMessage()
            ], 500);
        }
    }
}
