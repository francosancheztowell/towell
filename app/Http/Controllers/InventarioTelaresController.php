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
                // NO guardar no_orden - siempre establecer como null
                $datosUpdate = [
                    'cuenta' => $validated['cuenta'],
                    'calibre' => $validated['calibre'],
                    'fecha' => $validated['fecha'],
                    'turno' => $validated['turno'],
                    'salon' => $validated['salon'],
                    'hilo' => $validated['hilo'] ?? $existente->hilo,
                    'no_orden' => null, // FORZAR null - no se guarda no_orden
                ];

                Log::info('InventarioTelares - Actualizando registro', [
                    'no_telar' => $validated['no_telar'],
                    'tipo' => $validated['tipo'],
                    'no_orden_enviado' => $validated['no_orden'] ?? 'no enviado',
                    'no_orden_establecido' => null,
                    'datos_update' => $datosUpdate
                ]);

                // Guardar el valor anterior para el log si es necesario
                $noOrdenAnterior = $existente->no_orden;

                $existente->update($datosUpdate);

                // Verificar que se estableció como null
                $existente->refresh();
                if ($existente->no_orden !== null) {
                    // Forzar nuevamente si por alguna razón no se estableció
                    Log::warning('InventarioTelares - no_orden no era null después de update, forzando a null', [
                        'no_telar' => $validated['no_telar'],
                        'no_orden_anterior' => $noOrdenAnterior,
                        'no_orden_actual' => $existente->no_orden
                    ]);
                    $existente->no_orden = null;
                    $existente->save();
                }

                $registro = $existente;
            } else {
                // Crear nuevo registro
                // NO guardar no_orden - siempre establecer como null
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

                Log::info('InventarioTelares - Creando nuevo registro', [
                    'no_telar' => $validated['no_telar'],
                    'tipo' => $validated['tipo'],
                    'no_orden_enviado' => $validated['no_orden'] ?? 'no enviado',
                    'no_orden_establecido' => null,
                    'datos_create' => $datosCreate
                ]);

                $registro = TejInventarioTelares::create($datosCreate);

                // Verificar que se creó con null
                if ($registro->no_orden !== null) {
                    Log::error('InventarioTelares - ERROR: no_orden no es null después de crear', [
                        'no_telar' => $validated['no_telar'],
                        'no_orden_creado' => $registro->no_orden
                    ]);
                    // Forzar a null
                    $registro->no_orden = null;
                    $registro->save();
                }
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

