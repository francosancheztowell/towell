<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ReqMatrizHilos;
use App\Models\ReqProgramaTejido;
use App\Models\ReqProgramaTejidoLine;
use Illuminate\Support\Facades\Log;

class MatrizHilosController extends Controller
{
    /**
     * Muestra la vista principal de Matriz de Hilos
     */
    public function index()
    {
        // Obtener registros usando el modelo
        $matrizHilos = ReqMatrizHilos::orderBy('Hilo')->get();

        // Asegurar que todos los registros tengan un ID accesible como 'id' (minúscula) para compatibilidad con la vista
        $matrizHilos = $matrizHilos->map(function($item) {
            // Obtener el ID real (Id con mayúscula)
            $id = $item->getKey(); // Esto obtendrá 'Id' porque es la clave primaria

            // Asegurar que también esté disponible como 'id' (minúscula) para la vista
            $item->id = $id;
            $item->setAttribute('id', $id);

            return $item;
        });

        return view('catalagos.matriz-hilos', [
            'matrizHilos' => $matrizHilos
        ]);
    }

    /**
     * Obtener lista de hilos únicos para select (JSON)
     */
    public function list()
    {
        try {
            $hilos = ReqMatrizHilos::select('Hilo', 'Fibra')
                ->distinct()
                ->orderBy('Hilo')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $hilos
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener hilos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear nuevo registro de matriz hilos
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'Hilo' => 'required|string|max:30',
                'Calibre' => 'nullable|numeric',
                'Calibre2' => 'nullable|numeric',
                'CalibreAX' => 'nullable|string|max:20',
                'Fibra' => 'nullable|string|max:30',
                'CodColor' => 'nullable|string|max:10',
                'NombreColor' => 'nullable|string|max:60',
                'N1' => 'nullable|numeric',
                'N2' => 'nullable|numeric',
            ]);

            $matrizHilo = ReqMatrizHilos::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Registro creado exitosamente',
                'data' => $matrizHilo
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear registro: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener un registro específico
     */
    public function show($id)
    {
        // Buscar usando la clave primaria 'Id'
        $matrizHilo = ReqMatrizHilos::find($id);

        // Si no se encuentra, intentar buscar directamente por Id
        if (!$matrizHilo) {
            $matrizHilo = ReqMatrizHilos::where('Id', $id)->first();
        }

        if (!$matrizHilo) {
            return response()->json([
                'success' => false,
                'message' => 'Registro no encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $matrizHilo
        ]);
    }

    /**
     * Actualizar un registro
     */
    public function update(Request $request, $id)
    {
        try {
            // Buscar usando la clave primaria 'Id'
            $matrizHilo = ReqMatrizHilos::find($id);

            // Si no se encuentra, intentar buscar directamente por Id
            if (!$matrizHilo) {
                $matrizHilo = ReqMatrizHilos::where('Id', $id)->first();
            }

            if (!$matrizHilo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Registro no encontrado con ID: ' . $id
                ], 404);
            }

            $validated = $request->validate([
                'Hilo' => 'required|string|max:30',
                'Calibre' => 'nullable|numeric',
                'Calibre2' => 'nullable|numeric',
                'CalibreAX' => 'nullable|string|max:20',
                'Fibra' => 'nullable|string|max:30',
                'CodColor' => 'nullable|string|max:10',
                'NombreColor' => 'nullable|string|max:60',
                'N1' => 'nullable|numeric',
                'N2' => 'nullable|numeric',
            ]);

            $hiloOriginal = $matrizHilo->Hilo;
            $hiloNuevo = $validated['Hilo'];

            // Verificar si el hilo está siendo usado en ReqProgramaTejido
            $enUso = ReqProgramaTejido::where('FibraRizo', $hiloOriginal)->exists();

            // Si el hilo está en uso y se intenta cambiar el nombre, bloquear la actualización
            if ($enUso && $hiloOriginal !== $hiloNuevo) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede cambiar el nombre del hilo "' . $hiloOriginal . '" porque está siendo utilizado en el programa de tejido (campo FibraRizo).'
                ], 422);
            }

            // Verificar si se actualizaron campos que afectan los cálculos (N1, N2, Calibre, Calibre2)
            $camposCalculo = ['N1', 'N2', 'Calibre', 'Calibre2'];
            $camposActualizados = false;
            foreach ($camposCalculo as $campo) {
                $valorOriginal = $matrizHilo->$campo;
                $valorNuevo = $validated[$campo] ?? null;

                // Normalizar valores null y vacíos
                $valorOriginal = ($valorOriginal === '' || $valorOriginal === null) ? null : (float) $valorOriginal;
                $valorNuevo = ($valorNuevo === '' || $valorNuevo === null) ? null : (float) $valorNuevo;

                // Comparar valores (considerando null como igual a null)
                if ($valorOriginal !== $valorNuevo) {
                    $camposActualizados = true;
                    break;
                }
            }

            // Actualizar el registro
            $matrizHilo->update($validated);

            // Si el hilo está en uso y se actualizaron campos de cálculo, recalcular MtsRizo en las líneas relacionadas
            if ($enUso && $camposActualizados) {
                $this->recalcularMtsRizoEnLineas($hiloOriginal);
            }

            return response()->json([
                'success' => true,
                'message' => 'Registro actualizado exitosamente',
                'data' => $matrizHilo
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error al actualizar MatrizHilos', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar registro: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Recalcula MtsRizo en todas las líneas de programa de tejido que usan el hilo especificado
     *
     * @param string $hilo Nombre del hilo
     */
    private function recalcularMtsRizoEnLineas(string $hilo)
    {
        try {
            // Constantes de la fórmula
            $constante1 = 1000;
            $constante2 = 0.59;
            $constante3 = 1.0162;

            // Obtener el registro actualizado de MatrizHilos
            $matrizHilo = ReqMatrizHilos::where('Hilo', $hilo)->first();
            if (!$matrizHilo) {
                return;
            }

            // Obtener N1 y N2 (con fallback a Calibre/Calibre2)
            $n1 = null;
            $n2 = null;

            if ($matrizHilo->N1 !== null && $matrizHilo->N1 !== '' && is_numeric($matrizHilo->N1)) {
                $n1 = (float) $matrizHilo->N1;
            } elseif ($matrizHilo->Calibre !== null && $matrizHilo->Calibre !== '' && is_numeric($matrizHilo->Calibre)) {
                $n1 = (float) $matrizHilo->Calibre;
            }

            if ($matrizHilo->N2 !== null && $matrizHilo->N2 !== '' && is_numeric($matrizHilo->N2)) {
                $n2 = (float) $matrizHilo->N2;
            } elseif ($matrizHilo->Calibre2 !== null && $matrizHilo->Calibre2 !== '' && is_numeric($matrizHilo->Calibre2)) {
                $n2 = (float) $matrizHilo->Calibre2;
            }

            // Si no hay valores válidos para N1 y N2, no se puede calcular
            if ($n1 === null || $n1 <= 0 || $n2 === null || $n2 <= 0) {
                return;
            }

            // Buscar todos los programas de tejido que usan este hilo
            $programas = ReqProgramaTejido::where('FibraRizo', $hilo)->get();

            foreach ($programas as $programa) {
                // Obtener CuentaRizo del programa
                $cuentaRizo = $programa->CuentaRizo;
                if (!$cuentaRizo || $cuentaRizo <= 0) {
                    continue;
                }

                // Obtener todas las líneas del programa que tienen Rizo
                $lineas = ReqProgramaTejidoLine::where('ProgramaId', $programa->Id)
                    ->whereNotNull('Rizo')
                    ->where('Rizo', '>', 0)
                    ->get();

                foreach ($lineas as $linea) {
                    $rizo = (float) $linea->Rizo;
                    if ($rizo <= 0) {
                        continue;
                    }

                    // Calcular ValorRizo1 y ValorRizo2
                    $valorRizo1 = (($n1 * ($rizo * $constante1)) / $constante2) / 2;
                    $valorRizo2 = (($n2 * ($rizo * $constante1)) / $constante2) / 2;

                    // Calcular MtsRizo
                    $mtsRizo = (($valorRizo1 + $valorRizo2) / $cuentaRizo) * $constante3;

                    // Actualizar solo si el valor es válido
                    if ($mtsRizo > 0) {
                        $linea->MtsRizo = $mtsRizo;
                        $linea->save();
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Error al recalcular MtsRizo en líneas', [
                'hilo' => $hilo,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Eliminar un registro
     */
    public function destroy($id)
    {
        try {
            // Buscar el registro usando la clave primaria 'Id'
            $matrizHilo = ReqMatrizHilos::find($id);

            if (!$matrizHilo) {
                // Si no se encuentra, intentar buscar directamente por Id
                $matrizHilo = ReqMatrizHilos::where('Id', $id)->first();
            }

            if (!$matrizHilo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Registro no encontrado con ID: ' . $id
                ], 404);
            }

            // Verificar si el hilo está siendo usado en ReqProgramaTejido
            $hilo = $matrizHilo->Hilo;
            if ($hilo) {
                $enUso = ReqProgramaTejido::where('FibraRizo', $hilo)->exists();

                if ($enUso) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No se puede eliminar el hilo "' . $hilo . '" porque está siendo utilizado en el programa de tejido (campo FibraRizo).'
                    ], 422);
                }
            }

            // Eliminar el registro
            $deleted = $matrizHilo->delete();

            if (!$deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo eliminar el registro'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Registro eliminado exitosamente',
                'deleted_id' => $id
            ]);

        } catch (\Exception $e) {
            Log::error('Error al eliminar MatrizHilos', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar registro: ' . $e->getMessage()
            ], 500);
        }
    }
}
