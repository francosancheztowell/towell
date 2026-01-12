<?php

namespace App\Http\Controllers\Planeacion\ProgramaTejido;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Planeacion\ProgramaTejido\OrdenDeCambio\Felpa\OrdenDeCambioFelpaController;
use App\Models\catcodificados\CatCodificados;
use App\Models\ReqProgramaTejido;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReimprimirOrdenesController extends Controller
{
    /**
     * Reimprime una orden específica por ID de CatCodificados
     * Solo reimprime si el registro tiene UsuarioCrea (indica que fue creado)
     */
    public function reimprimir($id)
    {
        try {
            // Buscar el registro en CatCodificados
            $catCodificado = CatCodificados::find($id);

            if (!$catCodificado) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró el registro especificado.',
                ], 404);
            }

            // Verificar que tenga UsuarioCrea (indica que el registro fue creado)
            if (empty($catCodificado->UsuarioCrea) || $catCodificado->UsuarioCrea === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este registro no puede ser reimpreso porque no tiene un usuario de creación asignado.',
                ], 403);
            }

            // Verificar que tenga OrdenTejido
            $ordenTejido = $catCodificado->OrdenTejido;
            if (empty($ordenTejido)) {
                return response()->json([
                    'success' => false,
                    'message' => 'El registro no tiene un número de orden de tejido asignado.',
                ], 400);
            }

            // Buscar el registro correspondiente en ReqProgramaTejido
            $registroReqProgramaTejido = ReqProgramaTejido::query()
                ->where('NoProduccion', $ordenTejido)
                ->whereNotNull('NoProduccion')
                ->where('NoProduccion', '!=', '')
                ->first();

            if (!$registroReqProgramaTejido) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró el registro de producción correspondiente a esta orden.',
                ], 404);
            }

            // Usar el controlador de orden de cambio para generar el Excel
            $ordenCambioController = new OrdenDeCambioFelpaController();
            $response = $ordenCambioController->generarExcelDesdeBD(collect([$registroReqProgramaTejido]));

            // Si la respuesta es un StreamedResponse, retornarla directamente
            if ($response instanceof StreamedResponse) {
                return $response;
            }

            // Si hay error, retornar la respuesta JSON directamente
            return $response;
        } catch (\Exception $e) {
            Log::error('Error al reimprimir orden', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al reimprimir la orden: ' . $e->getMessage(),
            ], 500);
        }
    }
}
