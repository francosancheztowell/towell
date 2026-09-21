<?php

namespace App\Http\Controllers\Tejido\InventarioTrama;

use App\Http\Controllers\Controller;
use App\Models\Tejido\TejTrama;
use App\Models\Tejido\TejTramaConsumos;
use App\Services\Tejido\InventarioTrama\RequerimientoStatusService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ConsultarRequerimientoController extends Controller
{
    public function __construct(private readonly RequerimientoStatusService $statusService) {}

    /**
     * Muestra la vista de consultar requerimientos con datos de TejTrama y TejTramaConsumos
     */
    /**
     * Página contenedora; el listado y el detalle viven en el componente Livewire
     * (consulta perezosa por folio, sin cargar todos los consumos).
     */
    public function index()
    {
        return view('modulos.inventario-trama.consultar-requerimiento');
    }

    /**
     * Obtiene el detalle de un requerimiento específico (AJAX)
     */
    public function show($folio)
    {
        $requerimiento = TejTrama::where('Folio', $folio)->first();

        if (! $requerimiento) {
            return response()->json([
                'success' => false,
                'message' => 'Requerimiento no encontrado',
            ], 404);
        }

        $consumos = TejTramaConsumos::where('Folio', $folio)->get();

        return response()->json([
            'success' => true,
            'requerimiento' => $requerimiento,
            'consumos' => $consumos,
        ]);
    }

    /**
     * Actualiza el status de un requerimiento
     */
    public function updateStatus(Request $request, $folio)
    {
        try {
            $request->validate([
                'status' => 'required|in:'.implode(',', RequerimientoStatusService::estatusValidos()),
            ]);

            $resultado = $this->statusService->cambiar((string) $folio, (string) $request->status);

            return response()->json([
                'success' => $resultado['ok'],
                'message' => $resultado['message'],
            ], $resultado['code']);
        } catch (ValidationException $e) {
            Log::error('UpdateStatus - Error de validación', [
                'folio' => $folio,
                'errors' => $e->errors(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error de validación: '.implode(', ', collect($e->errors())->flatten()->toArray()),
            ], 422);
        } catch (\Exception $e) {
            Log::error('UpdateStatus - Error general', [
                'folio' => $folio,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mostrar resumen de artículos de un folio
     */
    public function resumen($folio)
    {
        $requerimiento = TejTrama::where('Folio', $folio)->first();

        if (! $requerimiento) {
            abort(404, 'Requerimiento no encontrado');
        }

        $consumos = TejTramaConsumos::where('Folio', $folio)->get();

        // Agrupar por salón y preparar datos para la vista
        $consumosPorSalon = $consumos->groupBy('SalonTejidoId');

        return view('modulos.inventario-trama.resumen-articulos', [
            'requerimiento' => $requerimiento,
            'consumosPorSalon' => $consumosPorSalon,
            'totalConsumos' => $consumos->count(),
        ]);
    }
}
