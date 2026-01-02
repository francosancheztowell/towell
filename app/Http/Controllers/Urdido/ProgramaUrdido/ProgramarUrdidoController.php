<?php

namespace App\Http\Controllers\Urdido\ProgramaUrdido;

use App\Http\Controllers\Controller;
use App\Models\UrdProgramaUrdido;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;

class ProgramarUrdidoController extends Controller
{
    /**
     * Mostrar la vista de programar urdido
     */
    public function index()
    {
        return view('modulos.urdido.programar-urdido');
    }

    /**
     * Extraer número de MC Coy del campo MaquinaId
     *
     * @param string|null $maquinaId
     * @return int|null
     */
    private function extractMcCoyNumber(?string $maquinaId): ?int
    {
        if (empty($maquinaId)) {
            return null;
        }

        // Buscar patrón "Mc Coy X" (case insensitive, permite espacios variables)
        if (preg_match('/mc\s*coy\s*(\d+)/i', $maquinaId, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Obtener órdenes de urdido agrupadas por MC Coy
     * Extrae el MC Coy del campo MaquinaId (ej: "Mc Coy 1" -> 1)
     * Ordena por CreatedAt ascendente (más antiguas primero) dentro de cada grupo
     *
     * @return JsonResponse
     */
    public function getOrdenes(): JsonResponse
    {
        try {
            $ordenes = UrdProgramaUrdido::select([
                'Id',
                'Folio',
                'RizoPie as tipo',
                'Cuenta',
                'Calibre',
                'Metros',
                'MaquinaId',
                'Status',
                'FechaProg',
                'CreatedAt',
            ])
            ->whereIn('Status', ['Programado', 'En Proceso'])
            ->whereNotNull('MaquinaId')
            ->get();

            // Agrupar por MC Coy (extraído de MaquinaId)
            $ordenesPorMcCoy = [
                1 => [],
                2 => [],
                3 => [],
                4 => [],
            ];

            foreach ($ordenes as $orden) {
                $mcCoy = $this->extractMcCoyNumber($orden->MaquinaId);

                // Solo incluir si el MC Coy es válido (1-4)
                if ($mcCoy !== null && isset($ordenesPorMcCoy[$mcCoy])) {
                    $ordenesPorMcCoy[$mcCoy][] = [
                        'id' => $orden->Id,
                        'folio' => $orden->Folio,
                        'tipo' => $orden->tipo,
                        'cuenta' => $orden->Cuenta,
                        'calibre' => $orden->Calibre,
                        'metros' => $orden->Metros,
                        'mccoy' => $mcCoy,
                        'status' => $orden->Status ?? null,
                        'created_at' => $orden->CreatedAt ? $orden->CreatedAt->format('Y-m-d H:i:s') : null,
                    ];
                }
            }

            // Ordenar cada grupo por CreatedAt ascendente (más antiguas primero)
            // y agregar número de prioridad consecutivo (empezando desde 1)
            foreach ($ordenesPorMcCoy as $key => $grupo) {
                usort($ordenesPorMcCoy[$key], function ($a, $b) {
                    $dateA = $a['created_at'] ? strtotime($a['created_at']) : 0;
                    $dateB = $b['created_at'] ? strtotime($b['created_at']) : 0;
                    return $dateA - $dateB; // Ascendente (más antiguas primero)
                });

                // Agregar número de prioridad consecutivo (1, 2, 3, ...) para cada grupo
                foreach ($ordenesPorMcCoy[$key] as $index => &$orden) {
                    $orden['prioridad'] = $index + 1; // Prioridad consecutiva empezando desde 1
                }
                unset($orden); // Liberar referencia
            }

            return response()->json([
                'success' => true,
                'data' => $ordenesPorMcCoy,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error al obtener órdenes: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Subir prioridad de una orden
     * Intercambia CreatedAt con la orden inmediatamente anterior en el mismo MC Coy
     * (la orden con CreatedAt más reciente se mueve antes)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function subirPrioridad(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'id' => 'required|integer|exists:UrdProgramaUrdido,Id',
            ]);

            $orden = UrdProgramaUrdido::findOrFail($request->id);
            $mcCoy = $this->extractMcCoyNumber($orden->MaquinaId);

            if ($mcCoy === null) {
                return response()->json([
                    'success' => false,
                    'error' => 'No se pudo determinar el MC Coy de la orden',
                ], 400);
            }

            // Obtener todas las órdenes del mismo MC Coy ordenadas por CreatedAt
            $ordenesMcCoy = UrdProgramaUrdido::whereIn('Status', ['Programado', 'En Proceso'])
                ->whereNotNull('MaquinaId')
                ->get()
                ->filter(function ($item) use ($mcCoy) {
                    return $this->extractMcCoyNumber($item->MaquinaId) === $mcCoy;
                })
                ->sortBy('CreatedAt')
                ->values();

            // Encontrar la posición actual de la orden
            $posicionActual = $ordenesMcCoy->search(function ($item) use ($orden) {
                return $item->Id === $orden->Id;
            });

            if ($posicionActual === false || $posicionActual === 0) {
                // Ya está en la primera posición o no se encontró
                return response()->json([
                    'success' => false,
                    'error' => 'La orden ya está en la primera posición',
                ], 400);
            }

            // Obtener la orden anterior
            $ordenAnterior = $ordenesMcCoy->get($posicionActual - 1);

            if (!$ordenAnterior) {
                return response()->json([
                    'success' => false,
                    'error' => 'No se encontró la orden anterior',
                ], 400);
            }

            DB::beginTransaction();

            // Intercambiar CreatedAt (aumentar la prioridad = CreatedAt más antiguo)
            $createdAtTemp = $orden->CreatedAt;
            $orden->CreatedAt = $ordenAnterior->CreatedAt;
            $ordenAnterior->CreatedAt = $createdAtTemp;

            $orden->save();
            $ordenAnterior->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Prioridad actualizada correctamente',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error de validación: ' . $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => 'Error al subir prioridad: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bajar prioridad de una orden
     * Intercambia CreatedAt con la orden inmediatamente posterior en el mismo MC Coy
     * (la orden con CreatedAt más antiguo se mueve después)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function bajarPrioridad(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'id' => 'required|integer|exists:UrdProgramaUrdido,Id',
            ]);

            $orden = UrdProgramaUrdido::findOrFail($request->id);
            $mcCoy = $this->extractMcCoyNumber($orden->MaquinaId);

            if ($mcCoy === null) {
                return response()->json([
                    'success' => false,
                    'error' => 'No se pudo determinar el MC Coy de la orden',
                ], 400);
            }

            // Obtener todas las órdenes del mismo MC Coy ordenadas por CreatedAt
            $ordenesMcCoy = UrdProgramaUrdido::whereIn('Status', ['Programado', 'En Proceso'])
                ->whereNotNull('MaquinaId')
                ->get()
                ->filter(function ($item) use ($mcCoy) {
                    return $this->extractMcCoyNumber($item->MaquinaId) === $mcCoy;
                })
                ->sortBy('CreatedAt')
                ->values();

            // Encontrar la posición actual de la orden
            $posicionActual = $ordenesMcCoy->search(function ($item) use ($orden) {
                return $item->Id === $orden->Id;
            });

            if ($posicionActual === false || $posicionActual === $ordenesMcCoy->count() - 1) {
                // Ya está en la última posición o no se encontró
                return response()->json([
                    'success' => false,
                    'error' => 'La orden ya está en la última posición',
                ], 400);
            }

            // Obtener la orden posterior
            $ordenPosterior = $ordenesMcCoy->get($posicionActual + 1);

            if (!$ordenPosterior) {
                return response()->json([
                    'success' => false,
                    'error' => 'No se encontró la orden posterior',
                ], 400);
            }

            DB::beginTransaction();

            // Intercambiar CreatedAt (disminuir la prioridad = CreatedAt más reciente)
            $createdAtTemp = $orden->CreatedAt;
            $orden->CreatedAt = $ordenPosterior->CreatedAt;
            $ordenPosterior->CreatedAt = $createdAtTemp;

            $orden->save();
            $ordenPosterior->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Prioridad actualizada correctamente',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error de validación: ' . $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => 'Error al bajar prioridad: ' . $e->getMessage(),
            ], 500);
        }
    }
}

