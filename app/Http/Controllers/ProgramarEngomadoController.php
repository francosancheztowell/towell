<?php

namespace App\Http\Controllers;

use App\Models\EngProgramaEngomado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;

class ProgramarEngomadoController extends Controller
{
    /**
     * Mostrar la vista de programar engomado
     */
    public function index()
    {
        return view('modulos.engomado.programar-engomado');
    }

    /**
     * Extraer número de tabla del campo MaquinaEng
     * Busca patrones como "WestPoint 2", "West Point 2", "Tabla 1", "Izquierda", "Derecha", "1", "2", etc.
     *
     * @param string|null $maquinaEng
     * @return int|null
     */
    private function extractTablaNumber(?string $maquinaEng): ?int
    {
        if (empty($maquinaEng)) {
            return null;
        }

        $maquinaEng = trim($maquinaEng);

        // Buscar "WestPoint 2" o "West Point 2" o "WestPoint2" (case insensitive)
        // WestPoint 2 -> tabla 1, WestPoint 3 -> tabla 2
        if (preg_match('/west\s*point\s*(\d+)/i', $maquinaEng, $matches)) {
            $numero = (int) $matches[1];
            if ($numero === 2) return 1; // WestPoint 2 -> tabla 1
            if ($numero === 3) return 2; // WestPoint 3 -> tabla 2
            return null;
        }

        // Buscar "Tabla X" o "tabla X" (case insensitive)
        if (preg_match('/tabla\s*(\d+)/i', $maquinaEng, $matches)) {
            $numero = (int) $matches[1];
            return ($numero >= 1 && $numero <= 2) ? $numero : null;
        }

        // Buscar "Izquierda" o "izquierda" -> tabla 1
        if (preg_match('/izquierda/i', $maquinaEng)) {
            return 1;
        }

        // Buscar "Derecha" o "derecha" -> tabla 2
        if (preg_match('/derecha/i', $maquinaEng)) {
            return 2;
        }

        // Buscar cualquier número al final del string (1 o 2)
        // Útil para casos como "Máquina 1", "Eng 2", etc.
        if (preg_match('/(\d+)\s*$/i', $maquinaEng, $matches)) {
            $numero = (int) $matches[1];
            return ($numero >= 1 && $numero <= 2) ? $numero : null;
        }

        // Buscar solo números (1 o 2)
        if (preg_match('/^(\d+)$/', $maquinaEng, $matches)) {
            $numero = (int) $matches[1];
            return ($numero >= 1 && $numero <= 2) ? $numero : null;
        }

        return null;
    }

    /**
     * Obtener órdenes de engomado agrupadas por tabla (Izquierda/Derecha)
     * Extrae el número de tabla del campo MaquinaEng
     * Ordena por CreatedAt ascendente (más antiguas primero) dentro de cada grupo
     *
     * @return JsonResponse
     */
    public function getOrdenes(): JsonResponse
    {
        try {
            $ordenes = EngProgramaEngomado::select([
                'Id',
                'Folio',
                'RizoPie as tipo',
                'Cuenta',
                'Calibre',
                'Metros',
                'MaquinaEng',
                'Status',
                'FechaProg',
                'BomFormula',
            ])
            ->whereIn('Status', ['Programado', 'En Proceso'])
            ->whereNotNull('MaquinaEng')
            ->where('MaquinaEng', '!=', '')
            ->get();

            // Log para debug (puedes removerlo después)
            Log::info('EngProgramaEngomado - Total órdenes encontradas: ' . $ordenes->count());
            foreach ($ordenes->take(5) as $orden) {
                Log::info('Orden ID: ' . $orden->Id . ', MaquinaEng: ' . $orden->MaquinaEng . ', Tabla extraída: ' . ($this->extractTablaNumber($orden->MaquinaEng) ?? 'null'));
            }

            // Agrupar por tabla (extraído de MaquinaEng)
            $ordenesPorTabla = [
                1 => [], // West Point 2
                2 => [], // West Point 3
            ];

            foreach ($ordenes as $orden) {
                $tabla = $this->extractTablaNumber($orden->MaquinaEng);

                // Solo incluir si la tabla es válida (1-2)
                if ($tabla !== null && isset($ordenesPorTabla[$tabla])) {
                    $ordenesPorTabla[$tabla][] = [
                        'id' => $orden->Id,
                        'folio' => $orden->Folio,
                        'tipo' => $orden->tipo,
                        'cuenta' => $orden->Cuenta,
                        'calibre' => $orden->Calibre,
                        'metros' => $orden->Metros,
                        'tabla' => $tabla,
                        'status' => $orden->Status ?? null,
                        'formula' => $orden->BomFormula ?? null,
                    ];
                }
            }

            // Ordenar cada grupo por CreatedAt ascendente (más antiguas primero)
            // y agregar número de prioridad consecutivo (empezando desde 1)
            foreach ($ordenesPorTabla as $key => $grupo) {
                // Si no hay CreatedAt, usar FechaProg como alternativa
                usort($ordenesPorTabla[$key], function ($a, $b) use ($ordenes) {
                    $ordenA = $ordenes->firstWhere('Id', $a['id']);
                    $ordenB = $ordenes->firstWhere('Id', $b['id']);

                    $dateA = $ordenA && $ordenA->FechaProg ? strtotime($ordenA->FechaProg) : 0;
                    $dateB = $ordenB && $ordenB->FechaProg ? strtotime($ordenB->FechaProg) : 0;

                    return $dateA - $dateB; // Ascendente (más antiguas primero)
                });

                // Agregar número de prioridad consecutivo (1, 2, 3, ...) para cada grupo
                foreach ($ordenesPorTabla[$key] as $index => &$orden) {
                    $orden['prioridad'] = $index + 1; // Prioridad consecutiva empezando desde 1
                }
                unset($orden); // Liberar referencia
            }

            return response()->json([
                'success' => true,
                'data' => $ordenesPorTabla,
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
     * Intercambia FechaProg con la orden inmediatamente anterior en la misma tabla
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function subirPrioridad(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'id' => 'required|integer|exists:EngProgramaEngomado,Id',
            ]);

            $orden = EngProgramaEngomado::findOrFail($request->id);
            $tabla = $this->extractTablaNumber($orden->MaquinaEng);

            if ($tabla === null) {
                return response()->json([
                    'success' => false,
                    'error' => 'No se pudo determinar la tabla de la orden',
                ], 400);
            }

            // Obtener todas las órdenes de la misma tabla ordenadas por FechaProg
            $ordenesTabla = EngProgramaEngomado::whereIn('Status', ['Programado', 'En Proceso'])
                ->whereNotNull('MaquinaEng')
                ->get()
                ->filter(function ($item) use ($tabla) {
                    return $this->extractTablaNumber($item->MaquinaEng) === $tabla;
                })
                ->sortBy('FechaProg')
                ->values();

            // Encontrar la posición actual de la orden
            $posicionActual = $ordenesTabla->search(function ($item) use ($orden) {
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
            $ordenAnterior = $ordenesTabla->get($posicionActual - 1);

            if (!$ordenAnterior) {
                return response()->json([
                    'success' => false,
                    'error' => 'No se encontró la orden anterior',
                ], 400);
            }

            DB::beginTransaction();

            // Intercambiar FechaProg (aumentar la prioridad = FechaProg más antigua)
            $fechaProgTemp = $orden->FechaProg;
            $orden->FechaProg = $ordenAnterior->FechaProg;
            $ordenAnterior->FechaProg = $fechaProgTemp;

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
     * Intercambia FechaProg con la orden inmediatamente posterior en la misma tabla
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function bajarPrioridad(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'id' => 'required|integer|exists:EngProgramaEngomado,Id',
            ]);

            $orden = EngProgramaEngomado::findOrFail($request->id);
            $tabla = $this->extractTablaNumber($orden->MaquinaEng);

            if ($tabla === null) {
                return response()->json([
                    'success' => false,
                    'error' => 'No se pudo determinar la tabla de la orden',
                ], 400);
            }

            // Obtener todas las órdenes de la misma tabla ordenadas por FechaProg
            $ordenesTabla = EngProgramaEngomado::whereIn('Status', ['Programado', 'En Proceso'])
                ->whereNotNull('MaquinaEng')
                ->get()
                ->filter(function ($item) use ($tabla) {
                    return $this->extractTablaNumber($item->MaquinaEng) === $tabla;
                })
                ->sortBy('FechaProg')
                ->values();

            // Encontrar la posición actual de la orden
            $posicionActual = $ordenesTabla->search(function ($item) use ($orden) {
                return $item->Id === $orden->Id;
            });

            if ($posicionActual === false || $posicionActual === $ordenesTabla->count() - 1) {
                // Ya está en la última posición o no se encontró
                return response()->json([
                    'success' => false,
                    'error' => 'La orden ya está en la última posición',
                ], 400);
            }

            // Obtener la orden posterior
            $ordenPosterior = $ordenesTabla->get($posicionActual + 1);

            if (!$ordenPosterior) {
                return response()->json([
                    'success' => false,
                    'error' => 'No se encontró la orden posterior',
                ], 400);
            }

            DB::beginTransaction();

            // Intercambiar FechaProg (disminuir la prioridad = FechaProg más reciente)
            $fechaProgTemp = $orden->FechaProg;
            $orden->FechaProg = $ordenPosterior->FechaProg;
            $ordenPosterior->FechaProg = $fechaProgTemp;

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

