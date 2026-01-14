<?php

namespace App\Http\Controllers\Planeacion\CatalogoPlaneacion\CatEficiencias;

use App\Models\Planeacion\ReqEficienciaStd;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Imports\ReqEficienciaStdImport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class CatalagoEficienciaController extends Controller
{
    /**
     * Mostrar lista de eficiencias con filtros
     */
    public function index(Request $request)
    {
        // Obtener todos los resultados sin filtros del servidor
        // Los filtros se manejan del lado del cliente con JavaScript
        $eficiencia = ReqEficienciaStd::orderBy('SalonTejidoId')
                                    ->orderBy('NoTelarId')
                                    ->orderBy('FibraId')
                                    ->get();

        // Siempre hay resultados ya que no filtramos del lado del servidor
        $noResults = false;

        // Pasa los resultados
        return view('catalagos.catalagoEficiencia', compact('eficiencia', 'noResults'));
    }

    /**
     * Procesar archivo Excel de eficiencias
     */
    public function procesarExcel(Request $request)
    {
        // Aumentar tiempo límite de ejecución para archivos grandes
        set_time_limit(300); // 5 minutos

        try {
            // Validar el archivo
            $request->validate([
                'archivo_excel' => 'required|file|mimes:xlsx,xls|max:10240' // 10MB máximo
            ]);

            $archivo = $request->file('archivo_excel');

            DB::beginTransaction();

            try {
                // Crear instancia del importador
                $import = new ReqEficienciaStdImport();

                // Importar el archivo
                Excel::import($import, $archivo);

                // Obtener estadísticas
                $stats = $import->getStats();

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Archivo procesado exitosamente',
                    'data' => [
                        'registros_procesados' => $stats['processed_rows'],
                        'registros_creados' => $stats['created_rows'],
                        'registros_actualizados' => $stats['updated_rows'],
                        'total_errores' => count($stats['errores']),
                        'errores' => array_slice($stats['errores'], 0, 10) // Primeros 10 errores
                    ]
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor al procesar el archivo Excel: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear nueva eficiencia
     */
    public function store(Request $request)
    {
        try {
            // Validación rápida
            $request->validate([
                'NoTelarId' => 'required|string|max:10',
                'FibraId' => 'required|string|max:120',
                'Eficiencia' => 'required|numeric|min:0|max:1',
                'Densidad' => 'nullable|string|max:10'
            ]);

            // Usar solo el número del telar para evitar problemas de longitud
            $salon = $request->SalonTejidoId ?: 'JACQUARD'; // Usar salón enviado o JACQUARD por defecto

            // Verificar duplicados en una sola consulta
            if (ReqEficienciaStd::where('SalonTejidoId', $salon)
                               ->where('NoTelarId', $request->NoTelarId)
                               ->where('FibraId', $request->FibraId)
                               ->where('Densidad', $request->Densidad ?? 'Normal')
                               ->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe una eficiencia para este telar y tipo de fibra'
                ], 422);
            }

            // Crear registro
            ReqEficienciaStd::create([
                'SalonTejidoId' => $salon,
                'NoTelarId' => $request->NoTelarId, // Solo el número del telar
                'FibraId' => $request->FibraId,
                'Eficiencia' => $request->Eficiencia,
                'Densidad' => $request->Densidad ?? 'Normal'
            ]);

            return response()->json([
                'success' => true,
                'message' => "Eficiencia para '{$salon} {$request->NoTelarId} - {$request->FibraId}' creada exitosamente"
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la eficiencia: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar eficiencia existente
     */
    public function update(Request $request, ReqEficienciaStd $eficiencia)
    {
        try {
            $request->validate([
                'NoTelarId' => 'required|string|max:20',
                'FibraId' => 'required|string|max:120',
                'Eficiencia' => 'required|numeric|min:0|max:1',
                'Densidad' => 'nullable|string|max:10'
            ]);

            // Guardar valores ANTES de actualizar para buscar programas relacionados
            $eficienciaOriginal = (float)($eficiencia->Eficiencia ?? 0);
            $telarOriginal = $eficiencia->NoTelarId;
            $fibraOriginal = $eficiencia->FibraId;
            $densidadOriginal = $eficiencia->Densidad ?? 'Normal';

            // Usar solo el número del telar para evitar problemas de longitud
            $salon = $request->SalonTejidoId ?: 'JACQUARD'; // Usar salón enviado o JACQUARD por defecto

            // Verificar duplicados excluyendo el registro actual
            if (ReqEficienciaStd::where('SalonTejidoId', $salon)
                               ->where('NoTelarId', $request->NoTelarId)
                               ->where('FibraId', $request->FibraId)
                               ->where('Densidad', $request->Densidad ?? 'Normal')
                               ->where('Id', '!=', (int)$eficiencia->Id)
                               ->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe otra eficiencia para este telar y tipo de fibra'
                ], 422);
            }

            // Verificar si cambió la eficiencia ANTES de actualizar
            $eficienciaCambiada = abs($eficienciaOriginal - (float)$request->Eficiencia) > 0.0001;
            $nuevaEficiencia = (float)$request->Eficiencia;
            $nuevoTelar = $request->NoTelarId;
            $nuevaFibra = $request->FibraId;
            $nuevaDensidad = $request->Densidad ?? 'Normal';

            // Actualizar registro
            $eficiencia->update([
                'SalonTejidoId' => $salon,
                'NoTelarId' => $nuevoTelar,
                'FibraId' => $nuevaFibra,
                'Eficiencia' => $nuevaEficiencia,
                'Densidad' => $nuevaDensidad
            ]);

            // Si cambió la eficiencia, actualizar programas relacionados y recalcular fórmulas
            // Buscar con los valores NUEVOS (por si cambió telar o fibra también)
            if ($eficienciaCambiada) {
                $this->actualizarProgramasYRecalcular($nuevoTelar, $nuevaFibra, $nuevaDensidad, $nuevaEficiencia);
            }

            // Si cambió el telar o fibra, también actualizar los programas que usaban los valores antiguos
            if ($telarOriginal !== $nuevoTelar || $fibraOriginal !== $nuevaFibra || $densidadOriginal !== $nuevaDensidad) {
                // Actualizar programas que usaban los valores antiguos con los nuevos
                $this->actualizarProgramasYRecalcular($telarOriginal, $fibraOriginal, $densidadOriginal, $nuevaEficiencia);
            }

            return response()->json([
                'success' => true,
                'message' => "Eficiencia para '{$salon} {$request->NoTelarId} - {$request->FibraId}' actualizada exitosamente"
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la eficiencia: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualiza los programas de tejido que usan esta eficiencia y recalcula sus fórmulas
     */
    private function actualizarProgramasYRecalcular(string $telar, string $fibra, string $densidad, float $nuevaEficiencia)
    {
        try {
            // Buscar programas que usan esta eficiencia
            $programas = ReqProgramaTejido::where('NoTelarId', $telar)
                ->where(function ($query) use ($fibra) {
                    $query->where('FibraRizo', $fibra)
                          ->orWhere('FibraTrama', $fibra);
                })
                ->get();

            $actualizados = 0;
            foreach ($programas as $programa) {
                // Calcular la densidad del programa basándose en CalibreTrama
                $calibreTrama = $programa->CalibreTrama ?? $programa->CalibreTrama2;
                $densidadPrograma = ($calibreTrama !== null && (float)$calibreTrama > 40) ? 'Alta' : 'Normal';

                // Si la densidad coincide, actualizar EficienciaSTD y recalcular
                if ($densidadPrograma === $densidad) {
                    // Verificar si el valor es diferente antes de actualizar
                    $eficienciaActual = (float)($programa->EficienciaSTD ?? 0);
                    if (abs($eficienciaActual - $nuevaEficiencia) > 0.0001) {
                        // Guardar valores antes de actualizar para logging
                        $horasProdAntes = $programa->HorasProd ?? 0;
                        $stdToaHraAntes = $programa->StdToaHra ?? 0;
                        $eficienciaAntes = (float)($programa->EficienciaSTD ?? 0);

                        // Asegurarse de que el modelo tenga todos los valores necesarios cargados
                        // Recargar el modelo para tener todos los campos actualizados
                        $programa->refresh();

                        // Actualizar EficienciaSTD en el modelo y marcar como modificado
                        $programa->EficienciaSTD = $nuevaEficiencia;
                        $programa->setAttribute('EficienciaSTD', $nuevaEficiencia);

                        // Forzar que Eloquent detecte el cambio actualizando UpdatedAt
                        // Esto asegura que el Observer se ejecute
                        $programa->UpdatedAt = now();

                        // Guardar el programa para que el Observer recalcule las fórmulas
                        // El Observer se ejecutará automáticamente en el evento 'saved' y recalculará:
                        // - StdDia, ProdKgDia, StdHrsEfect, ProdKgDia2, HorasProd, DiasJornada, etc.
                        // y regenerará las líneas diarias
                        // El Observer usará el valor actualizado de EficienciaSTD que está en el modelo
                        // y también usará StdToaHra existente si está disponible
                        $programa->save();

                        // Recargar el modelo para verificar que se actualizó HorasProd
                        $programa->refresh();
                        $horasProdDespues = $programa->HorasProd ?? 0;

                        $actualizados++;
                    }
                }
            }

        } catch (\Exception $e) {
        }
    }

    /**
     * Eliminar una eficiencia
     */
    public function destroy(ReqEficienciaStd $eficiencia)
    {
        try {
            $telar = $eficiencia->NoTelarId;
            $fibra = $eficiencia->FibraId;
            $densidad = $eficiencia->Densidad ?? 'Normal';
            $salon = $eficiencia->SalonTejidoId ?? 'JACQUARD';

            // Verificar si la eficiencia está siendo utilizada en ReqProgramaTejido
            // La eficiencia se busca por: NoTelarId, FibraId (de FibraRizo o FibraTrama), y Densidad
            // Primero buscar registros que coincidan con NoTelarId y FibraId
            $programas = ReqProgramaTejido::where('NoTelarId', $telar)
                ->where(function ($query) use ($fibra) {
                    $query->where('FibraRizo', $fibra)
                          ->orWhere('FibraTrama', $fibra);
                })
                ->get();

            // Verificar si alguno de estos programas usaría esta eficiencia
            // (basándose en la densidad calculada)
            $enUso = false;
            foreach ($programas as $programa) {
                // Calcular la densidad del programa basándose en CalibreTrama
                $calibreTrama = $programa->CalibreTrama ?? $programa->CalibreTrama2;
                $densidadPrograma = ($calibreTrama !== null && (float)$calibreTrama > 40) ? 'Alta' : 'Normal';

                // Si la densidad coincide, entonces esta eficiencia está en uso
                if ($densidadPrograma === $densidad) {
                    $enUso = true;
                    break;
                }
            }

            if ($enUso) {
                return response()->json([
                    'message' => "No se puede eliminar la eficiencia porque esta siendo utilizada en el programa de tejido."
                ], 422);
            }

            $eficiencia->delete();

            return response()->json([
                'success' => true,
                'message' => "Eficiencia para '{$telar} - {$fibra}' eliminada exitosamente"
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la eficiencia: ' . $e->getMessage()
            ], 500);
        }
    }

}
