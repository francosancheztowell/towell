<?php

namespace App\Http\Controllers\Tejedores;

use App\Http\Controllers\Controller;
use App\Models\Tejido\TejInventarioTelares;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class InventarioTelaresController extends Controller
{
    /**
     * Obtener todos los registros de inventario de telares
     */
    public function index(): JsonResponse
    {
        try {
            $inventario = TejInventarioTelares::orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $inventario
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener inventario de telares', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener inventario: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Guardar un registro de inventario de telares
     * Permite múltiples registros por telar (uno por fecha/turno/tipo)
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'no_telar' => 'required|string|max:20',
                'tipo' => 'required|string|max:20',
                'cuenta' => 'required|string|max:20',
                'calibre' => 'nullable',
                'fecha' => 'required|date',
                'turno' => 'required|integer|min:1|max:3',
                'salon' => 'required|string|max:50',
                'hilo' => 'nullable|string|max:50',
                'no_orden' => 'nullable|string|max:50',
            ]);

            // Buscar registro existente por telar+tipo+fecha+turno (combinación única)
            $existente = TejInventarioTelares::where('no_telar', $validated['no_telar'])
                ->where('tipo', $validated['tipo'])
                ->where('fecha', $validated['fecha'])
                ->where('turno', $validated['turno'])
                ->where('status', 'Activo')
                ->first();

            if ($existente) {
                // Actualizar registro existente
                $datosUpdate = [
                    'cuenta' => $validated['cuenta'],
                    'calibre' => $validated['calibre'],
                    'salon' => $validated['salon'],
                    'hilo' => $validated['hilo'] ?? $existente->hilo,
                    'no_orden' => null, // FORZAR null - no se guarda no_orden
                ];

                $existente->update($datosUpdate);
                $registro = $existente;
            } else {
                // Crear nuevo registro
                $datosCreate = [
                    'no_telar' => $validated['no_telar'],
                    'status' => 'Activo',
                    'tipo' => $validated['tipo'],
                    'cuenta' => $validated['cuenta'],
                    'calibre' => $validated['calibre'],
                    'fecha' => $validated['fecha'],
                    'turno' => $validated['turno'],
                    'tipo_atado' => 'Normal',
                    'salon' => $validated['salon'],
                    'hilo' => $validated['hilo'] ?? null,
                    'no_orden' => null, // FORZAR null - no se guarda no_orden
                ];

                $registro = TejInventarioTelares::create($datosCreate);
            }

            return response()->json([
                'success' => true,
                'message' => 'Guardado con éxito',
                'data' => $registro,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error al guardar inventario de telares', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al guardar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar un registro de inventario de telares
     */
    public function destroy(Request $request): JsonResponse
    {
        try {
            // Para DELETE, los datos pueden venir en el body o como query params
            $noTelar = $request->input('no_telar') ?? $request->query('no_telar');
            $tipo = $request->input('tipo') ?? $request->query('tipo');
            $fecha = $request->input('fecha') ?? $request->query('fecha');
            $turno = $request->input('turno') ?? $request->query('turno');

            if (!$noTelar || !$tipo || !$fecha || !$turno) {
                return response()->json([
                    'success' => false,
                    'message' => 'Faltan parámetros requeridos: no_telar, tipo, fecha, turno'
                ], 422);
            }

            // Buscar registro por telar+tipo+fecha+turno
            $registro = TejInventarioTelares::where('no_telar', $noTelar)
                ->where('tipo', $tipo)
                ->where('fecha', $fecha)
                ->where('turno', $turno)
                ->where('status', 'Activo')
                ->first();

            if (!$registro) {
                return response()->json([
                    'success' => false,
                    'message' => 'Registro no encontrado'
                ], 404);
            }

            // Eliminar el registro físicamente
            $registro->delete();

            return response()->json([
                'success' => true,
                'message' => 'Registro eliminado con éxito'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al eliminar inventario de telares', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar: ' . $e->getMessage()
            ], 500);
        }
    }
}

