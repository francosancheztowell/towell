<?php

namespace App\Http\Controllers;

use App\Models\TejInventarioTelares;
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
     * Guardar o actualizar un registro de inventario de telares
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

            // Buscar registro existente activo por telar+tipo
            $existente = TejInventarioTelares::where('no_telar', $validated['no_telar'])
                ->where('tipo', $validated['tipo'])
                ->where('status', 'Activo')
                ->first();

            if ($existente) {
                // Actualizar registro existente
                $existente->update([
                    'cuenta' => $validated['cuenta'],
                    'calibre' => $validated['calibre'],
                    'fecha' => $validated['fecha'],
                    'turno' => $validated['turno'],
                    'salon' => $validated['salon'],
                    'hilo' => $validated['hilo'] ?? $existente->hilo,
                    'no_orden' => $validated['no_orden'] ?? $existente->no_orden,
                ]);
                $registro = $existente;
            } else {
                // Crear nuevo registro
                $registro = TejInventarioTelares::create([
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
                    'no_orden' => $validated['no_orden'] ?? null,
                ]);
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
}

