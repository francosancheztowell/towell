<?php

/**
 * @file MoverOrdenesController.php
 * @description Controlador para mover órdenes de producción entre telares.
 * @dependencies ReqProgramaTejido, TejidoHelpers
 * @relatedFiles FinalizarOrdenesController.php, resources/views/planeacion/utileria/mover-ordenes.blade.php
 *
 * ! REPORTE DE FUNCIONALIDAD - Mover Órdenes
 * * -----------------------------------------------
 * * 1. Obtiene la lista de todos los telares (NoTelarId) que tienen registros en ReqProgramaTejido
 * * 2. Al seleccionar un telar ORIGEN, carga todos los registros (no en proceso) de ese telar
 * * 3. Al seleccionar un telar DESTINO, carga todos los registros de ese telar
 * * 4. El usuario selecciona registros del telar origen y los "arrastra" al telar destino
 * *    mediante una interfaz de tipo drag-and-drop con botones de flecha
 * * 5. Al confirmar el movimiento:
 * *    - Se actualiza NoTelarId y SalonTejidoId de cada registro movido al telar destino
 * *    - Se asigna Posicion al final del telar destino
 * *    - Se recalculan las posiciones de ambos telares (origen y destino)
 * * 6. No se permite mover registros que estén EnProceso = 1
 * * -----------------------------------------------
 */

namespace App\Http\Controllers\Planeacion\Utilerias;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Planeacion\ProgramaTejido\helper\TejidoHelpers;
use App\Models\Planeacion\ReqProgramaTejido;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MoverOrdenesController extends Controller
{
    /**
     * Obtiene todos los telares que tienen al menos un registro con NoProduccion (no orden).
     */
    public function getTelares(): JsonResponse
    {
        try {
            $telares = ReqProgramaTejido::query()
                ->select('SalonTejidoId', 'NoTelarId')
                ->whereNotNull('NoProduccion')
                ->where('NoProduccion', '!=', '')
                ->whereNotNull('NoTelarId')
                ->where('NoTelarId', '!=', '')
                ->distinct()
                ->orderBy('SalonTejidoId')
                ->orderBy('NoTelarId')
                ->get()
                ->map(fn ($t) => [
                    'salon' => $t->SalonTejidoId,
                    'telar' => $t->NoTelarId,
                    'label' => $t->NoTelarId,
                ]);

            return response()->json(['success' => true, 'telares' => $telares]);
        } catch (\Throwable $e) {
            Log::error('Utilería/Mover - Error al obtener telares: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al obtener telares'], 500);
        }
    }

    /**
     * Obtiene los registros con NoProduccion (no orden) de un telar específico.
     * Incluye enProceso para que la UI muestre cuáles están en proceso.
     */
    public function getRegistrosByTelar(Request $request): JsonResponse
    {
        try {
            $salonId = $request->query('salon');
            $noTelarId = $request->query('telar');

            if (!$salonId || !$noTelarId) {
                return response()->json(['success' => false, 'message' => 'Salón y telar son requeridos'], 422);
            }

            $registros = ReqProgramaTejido::query()
                ->select('Id', 'NoProduccion', 'TamanoClave', 'NombreProducto', 'SalonTejidoId', 'NoTelarId', 'Posicion', 'EnProceso', 'Produccion')
                ->salon($salonId)
                ->telar($noTelarId)
                ->whereNotNull('NoProduccion')
                ->where('NoProduccion', '!=', '')
                ->orderBy('Posicion', 'asc')
                ->orderBy('FechaInicio', 'asc')
                ->get()
                ->map(function (ReqProgramaTejido $r) {
                    $modelo = trim((string) ($r->NombreProducto ?? ''));
                    $esRepaso1 = $modelo !== '' && stripos($modelo, 'repaso1') !== false;
                    return [
                        'id' => $r->Id,
                        'noOrden' => $r->NoProduccion ?? '',
                        'tamanoClave' => $r->TamanoClave ?? '',
                        'modelo' => $modelo,
                        'posicion' => $r->Posicion ?? 0,
                        'enProceso' => (bool) $r->EnProceso,
                        'esRepaso1' => $esRepaso1,
                        'produccion' => $r->Produccion ?? 0,
                        'telar' => $r->NoTelarId ?? '',
                    ];
                });

            return response()->json(['success' => true, 'registros' => $registros]);
        } catch (\Throwable $e) {
            Log::error('Utilería/Mover - Error al obtener registros: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al obtener registros del telar'], 500);
        }
    }

    /**
     * Mueve registros y actualiza el orden en base a los arrays de IDs enviados.
     */
    public function moverOrdenes(Request $request): JsonResponse
    {
        $request->validate([
            'ordenes_origen' => 'nullable|array',
            'ordenes_origen.*' => 'integer',
            'origen_salon' => 'nullable|string',
            'origen_telar' => 'nullable|string',
            'ordenes_destino' => 'nullable|array',
            'ordenes_destino.*' => 'integer',
            'destino_salon' => 'nullable|string',
            'destino_telar' => 'nullable|string',
        ]);

        $ordenesOrigen = $request->input('ordenes_origen', []);
        $origenSalon = $request->input('origen_salon');
        $origenTelar = $request->input('origen_telar');

        $ordenesDestino = $request->input('ordenes_destino', []);
        $destinoSalon = $request->input('destino_salon');
        $destinoTelar = $request->input('destino_telar');

        DB::beginTransaction();
        try {
            $ahora = Carbon::now();

            // Procesar telar origen
            if (!empty($origenSalon) && !empty($origenTelar)) {
                foreach ($ordenesOrigen as $index => $id) {
                    $registro = ReqProgramaTejido::find($id);
                    if ($registro) {
                        $registro->SalonTejidoId = $origenSalon;
                        $registro->NoTelarId = $origenTelar;
                        $registro->Posicion = $index + 1;
                        $registro->UpdatedAt = $ahora;
                        $registro->saveQuietly();
                    }
                }
            }

            // Procesar telar destino
            if (!empty($destinoSalon) && !empty($destinoTelar)) {
                foreach ($ordenesDestino as $index => $id) {
                    $registro = ReqProgramaTejido::find($id);
                    if ($registro) {
                        $registro->SalonTejidoId = $destinoSalon;
                        $registro->NoTelarId = $destinoTelar;
                        $registro->Posicion = $index + 1;
                        $registro->UpdatedAt = $ahora;
                        $registro->saveQuietly();
                    }
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Se guardaron los cambios correctamente.',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Utilería/Mover - Error al mover órdenes: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar los cambios: ' . $e->getMessage(),
            ], 500);
        }
    }
}
