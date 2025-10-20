<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\TejTrama;
use App\Models\TejTramaConsumos;

class ConsultarRequerimientoController extends Controller
{
    /**
     * Muestra la vista de consultar requerimientos con datos de TejTrama y TejTramaConsumos
     */
    public function index(Request $request)
    {
        // Obtener filtros del request
        $folioFiltro = $request->input('folio');
        $fechaInicio = $request->input('fecha_inicio');
        $fechaFin = $request->input('fecha_fin');
        $statusFiltro = $request->input('status');
        $turnoFiltro = $request->input('turno');

        // Query base para TejTrama
        $query = TejTrama::query();

        // Aplicar filtros si existen
        if ($folioFiltro) {
            $query->where('Folio', 'like', "%{$folioFiltro}%");
        }

        if ($fechaInicio) {
            $query->whereDate('Fecha', '>=', $fechaInicio);
        }

        if ($fechaFin) {
            $query->whereDate('Fecha', '<=', $fechaFin);
        }

        if ($statusFiltro) {
            $query->where('Status', $statusFiltro);
        }

        if ($turnoFiltro) {
            $query->where('Turno', $turnoFiltro);
        }

        // Obtener requerimientos ordenados por fecha descendente
        $requerimientos = $query->orderBy('Fecha', 'desc')
                                ->orderBy('Folio', 'desc')
                                ->get();

        // Para cada requerimiento, obtener sus consumos
        $requerimientosConConsumos = $requerimientos->map(function ($req) {
            $consumos = TejTramaConsumos::where('Folio', $req->Folio)->get();
            // Agregar dinámicamente la propiedad consumos al objeto
            $req->consumos = $consumos; // @phpstan-ignore-line
            return $req;
        });

        return view('modulos.consultar-requerimiento', [
            'requerimientos' => $requerimientosConConsumos,
            'filtros' => [
                'folio' => $folioFiltro,
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                'status' => $statusFiltro,
                'turno' => $turnoFiltro,
            ]
        ]);
    }

    /**
     * Obtiene el detalle de un requerimiento específico (AJAX)
     */
    public function show($folio)
    {
        $requerimiento = TejTrama::where('Folio', $folio)->first();

        if (!$requerimiento) {
            return response()->json([
                'success' => false,
                'message' => 'Requerimiento no encontrado'
            ], 404);
        }

        $consumos = TejTramaConsumos::where('Folio', $folio)->get();

        return response()->json([
            'success' => true,
            'requerimiento' => $requerimiento,
            'consumos' => $consumos
        ]);
    }

    /**
     * Actualiza el status de un requerimiento
     */
    public function updateStatus(Request $request, $folio)
    {
        try {
            Log::info('UpdateStatus - Request recibido', [
                'folio' => $folio,
                'request_data' => $request->all(),
                'headers' => $request->headers->all()
            ]);

            $request->validate([
                'status' => 'required|in:En Proceso,Solicitado,Surtido,Cancelado,Creado'
            ]);

            $requerimiento = TejTrama::where('Folio', $folio)->first();

            if (!$requerimiento) {
                Log::warning('UpdateStatus - Requerimiento no encontrado', ['folio' => $folio]);
                return response()->json([
                    'success' => false,
                    'message' => 'Requerimiento no encontrado'
                ], 404);
            }

            $statusActual = $requerimiento->Status;
            $nuevoStatus = $request->status;

            Log::info('UpdateStatus - Estados', [
                'folio' => $folio,
                'status_actual' => $statusActual,
                'nuevo_status' => $nuevoStatus
            ]);

            // Validar transiciones de estado permitidas
            $transicionesPermitidas = [
                'En Proceso' => ['Solicitado', 'Cancelado'],
                'Solicitado' => ['Surtido', 'Cancelado'],
                'Surtido' => [], // No puede cambiar
                'Cancelado' => [], // No puede cambiar
                'Creado' => ['En Proceso', 'Cancelado']
            ];

            if (!in_array($nuevoStatus, $transicionesPermitidas[$statusActual] ?? [])) {
                Log::warning('UpdateStatus - Transición no permitida', [
                    'folio' => $folio,
                    'status_actual' => $statusActual,
                    'nuevo_status' => $nuevoStatus
                ]);
                return response()->json([
                    'success' => false,
                    'message' => "No se puede cambiar de '$statusActual' a '$nuevoStatus'"
                ], 400);
            }

            $requerimiento->Status = $nuevoStatus;
            $requerimiento->save();

            Log::info('UpdateStatus - Status actualizado exitosamente', [
                'folio' => $folio,
                'status_anterior' => $statusActual,
                'status_nuevo' => $nuevoStatus
            ]);

            return response()->json([
                'success' => true,
                'message' => "Status cambiado de '$statusActual' a '$nuevoStatus' correctamente"
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('UpdateStatus - Error de validación', [
                'folio' => $folio,
                'errors' => $e->errors(),
                'request_data' => $request->all()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error de validación: ' . implode(', ', collect($e->errors())->flatten()->toArray())
            ], 422);
        } catch (\Exception $e) {
            Log::error('UpdateStatus - Error general', [
                'folio' => $folio,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar resumen de artículos de un folio
     */
    public function resumen($folio)
    {
        $requerimiento = TejTrama::where('Folio', $folio)->first();

        if (!$requerimiento) {
            abort(404, 'Requerimiento no encontrado');
        }

        $consumos = TejTramaConsumos::where('Folio', $folio)->get();

        // Agrupar por salón y preparar datos para la vista
        $consumosPorSalon = $consumos->groupBy('SalonTejidoId');

        return view('modulos.resumen-articulos', [
            'requerimiento' => $requerimiento,
            'consumosPorSalon' => $consumosPorSalon,
            'totalConsumos' => $consumos->count()
        ]);
    }
}
