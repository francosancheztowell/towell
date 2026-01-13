<?php

namespace App\Http\Controllers\Planeacion\CatalogoPlaneacion\CatVelocidades;

use App\Models\ReqVelocidadStd;
use App\Models\ReqProgramaTejido;
use App\Imports\ReqVelocidadStdImport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class CatalagoVelocidadController extends Controller
{
    /**
     * Mostrar lista de velocidades
     */
    public function index(Request $request)
    {
        // Obtener todos los resultados sin filtros del servidor
        // Los filtros se manejan del lado del cliente con JavaScript
        $velocidad = ReqVelocidadStd::orderBy('SalonTejidoId')
                                    ->orderBy('NoTelarId')
                                    ->orderBy('FibraId')
                                    ->get();

        // Siempre hay resultados ya que no filtramos del lado del servidor
        $noResults = false;

        // Pasa los resultados
        return view('catalagos.catalagoVelocidad', compact('velocidad', 'noResults'));
    }

    /**
     * Procesar archivo Excel de velocidades
     */
    public function procesarExcel(Request $request)
    {
        try {
            // Validar el archivo
            $validator = Validator::make($request->all(), [
                'archivo_excel' => 'required|file|mimes:xlsx,xls|max:10240'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Archivo inválido. Debe ser un archivo Excel (.xlsx o .xls) de máximo 10MB.',
                    'errors' => $validator->errors()
                ], 400);
            }

            $archivo = $request->file('archivo_excel');
            $nombreArchivo = $archivo->getClientOriginalName();

            // Usar transacciones
            DB::beginTransaction();

            try {
                // Procesar el archivo
                $importador = new ReqVelocidadStdImport();
                Excel::import($importador, $archivo);

                // Obtener estadísticas
                $stats = $importador->getStats();

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Archivo procesado exitosamente',
                    'data' => [
                        'registros_procesados' => $stats['processed_rows'],
                        'registros_creados' => $stats['created_rows'],
                        'registros_actualizados' => $stats['updated_rows'],
                        'total_errores' => count($stats['errores']),
                        'errores' => array_slice($stats['errores'], 0, 10)
                    ]
                ]);

            } catch (\Exception $e) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Error interno del servidor al procesar el archivo Excel: ' . $e->getMessage()
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Error en procesarExcel: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error inesperado: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear nueva velocidad
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'SalonTejidoId' => 'nullable|string|max:20',
                'NoTelarId' => 'required|string|max:10',
                'FibraId' => 'required|string|max:60',
                'Velocidad' => 'required|integer|min:0',
                'Densidad' => 'nullable|string|max:10'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validación fallida: ' . implode(', ', $validator->errors()->all())
                ], 422);
            }

            $velocidadExistente = ReqVelocidadStd::where('NoTelarId', $request->NoTelarId)
                                                ->where('FibraId', $request->FibraId)
                                                ->where('Densidad', $request->Densidad ?? 'Normal')
                                                ->first();

            if ($velocidadExistente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe una velocidad con los mismos datos'
                ], 422);
            }

            $velocidad = ReqVelocidadStd::create([
                'SalonTejidoId' => $request->SalonTejidoId ?? 'Ninguno',
                'NoTelarId' => $request->NoTelarId,
                'FibraId' => $request->FibraId,
                'Velocidad' => $request->Velocidad,
                'Densidad' => $request->Densidad ?? 'Normal'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Velocidad creada exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al crear velocidad: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la velocidad: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar velocidad
     */
    public function update(Request $request, ReqVelocidadStd $velocidad)
    {
        try {
            $validator = Validator::make($request->all(), [
                'SalonTejidoId' => 'nullable|string|max:20',
                'NoTelarId' => 'required|string|max:10',
                'FibraId' => 'required|string|max:60',
                'Velocidad' => 'required|integer|min:0',
                'Densidad' => 'nullable|string|max:10'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validación fallida'
                ], 422);
            }

            $velocidadExistente = ReqVelocidadStd::where('NoTelarId', $request->NoTelarId)
                                                ->where('FibraId', $request->FibraId)
                                                ->where('Densidad', $request->Densidad ?? 'Normal')
                                                ->where('Id', '!=', $velocidad->Id)
                                                ->first();

            if ($velocidadExistente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe otra velocidad con los mismos datos'
                ], 422);
            }

            $salon = $request->SalonTejidoId ?: 'JACQUARD';

            // Guardar valores originales antes de la actualización
            $telarOriginal = $velocidad->NoTelarId;
            $fibraOriginal = $velocidad->FibraId;
            $densidadOriginal = $velocidad->Densidad ?? 'Normal';
            $velocidadOriginal = (int)($velocidad->Velocidad ?? 0);

            // Actualizar registro
            $velocidad->update([
                'SalonTejidoId' => $salon,
                'NoTelarId' => $request->NoTelarId,
                'FibraId' => $request->FibraId,
                'Velocidad' => $request->Velocidad,
                'Densidad' => $request->Densidad ?? 'Normal'
            ]);

            $nuevaVelocidad = (int)$request->Velocidad;

            // Si cambió la velocidad, o el telar, o la fibra, o la densidad, actualizar programas relacionados y recalcular fórmulas
            $cambioDetectado = $velocidadOriginal !== $nuevaVelocidad ||
                              $telarOriginal !== $request->NoTelarId ||
                              $fibraOriginal !== $request->FibraId ||
                              $densidadOriginal !== ($request->Densidad ?? 'Normal');

            if ($cambioDetectado) {
                $this->actualizarProgramasYRecalcular(
                    $telarOriginal, $fibraOriginal, $densidadOriginal, // Valores originales para buscar
                    $request->NoTelarId, $request->FibraId, ($request->Densidad ?? 'Normal'), $nuevaVelocidad // Valores nuevos para actualizar
                );
            }

            return response()->json([
                'success' => true,
                'message' => "Velocidad para '{$salon} {$request->NoTelarId} - {$request->FibraId}' actualizada exitosamente"
            ]);

        } catch (\Exception $e) {
            Log::error('Error al actualizar velocidad', ['id' => $velocidad->Id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la velocidad: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualiza los programas de tejido que usan esta velocidad y recalcula sus fórmulas
     * @param string $oldTelar El NoTelarId original de la velocidad
     * @param string $oldFibra El FibraId original de la velocidad
     * @param string $oldDensidad La Densidad original de la velocidad
     * @param string $newTelar El nuevo NoTelarId de la velocidad
     * @param string $newFibra El nuevo FibraId de la velocidad
     * @param string $newDensidad La nueva Densidad de la velocidad
     * @param int $nuevaVelocidad El nuevo valor de Velocidad
     */
    private function actualizarProgramasYRecalcular(
        string $oldTelar, string $oldFibra, string $oldDensidad,
        string $newTelar, string $newFibra, string $newDensidad, int $nuevaVelocidad
    ) {
        try {
            // Buscar programas que usaban la velocidad original
            $programas = ReqProgramaTejido::where('NoTelarId', $oldTelar)
                ->where(function ($query) use ($oldFibra) {
                    $query->where('FibraRizo', $oldFibra)
                          ->orWhere('FibraTrama', $oldFibra);
                })
                ->get();

            $actualizados = 0;
            foreach ($programas as $programa) {
                // Calcular la densidad del programa basándose en CalibreTrama
                $calibreTrama = $programa->CalibreTrama ?? $programa->CalibreTrama2;
                $densidadPrograma = ($calibreTrama !== null && (float)$calibreTrama > 40) ? 'Alta' : 'Normal';

                // Si la densidad coincide con la original, entonces este programa usaba la velocidad que se actualizó
                if ($densidadPrograma === $oldDensidad) {
                    // Guardar valores antes de actualizar para logging
                    $stdToaHraAntes = $programa->StdToaHra ?? 0;
                    $stdDiaAntes = $programa->StdDia ?? 0;
                    $diasJornadaAntes = $programa->DiasJornada ?? 0;
                    $velocidadAntes = (int)($programa->VelocidadSTD ?? 0);

                    // IMPORTANTE: NO hacer refresh aquí, necesitamos que Eloquent detecte el cambio
                    // Sincronizar el estado original ANTES de actualizar para que Eloquent pueda detectar cambios
                    $programa->syncOriginal();

                    // Actualizar los campos del programa con los nuevos valores de la velocidad
                    $programa->NoTelarId = $newTelar;
                    $programa->FibraRizo = $newFibra;
                    $programa->FibraTrama = $newFibra;

                    // IMPORTANTE: Actualizar VelocidadSTD - Eloquent debería detectarlo como "dirty" automáticamente
                    // Si el valor es diferente al original, se marcará como modificado
                    $programa->VelocidadSTD = $nuevaVelocidad;

                    // Forzar que Eloquent detecte el cambio actualizando UpdatedAt
                    // Esto asegura que el Observer se ejecute
                    $programa->UpdatedAt = now();

                    // Guardar el programa para que el Observer recalcule las fórmulas
                    // El Observer se ejecutará automáticamente en el evento 'saved' y recalculará:
                    // - StdToaHra (depende de VelocidadSTD)
                    // - StdDia (depende de StdToaHra y EficienciaSTD)
                    // - DiasJornada (depende de HorasProd, que depende de StdToaHra y EficienciaSTD)
                    $programa->save();

                    // Recargar el modelo para verificar que se actualizaron las fórmulas
                    $programa->refresh();
                    $stdToaHraDespues = $programa->StdToaHra ?? 0;
                    $stdDiaDespues = $programa->StdDia ?? 0;
                    $diasJornadaDespues = $programa->DiasJornada ?? 0;

                    $actualizados++;
                }
            }

        } catch (\Exception $e) {
        }
    }

    /**
     * Eliminar velocidad
     */
    public function destroy(ReqVelocidadStd $velocidad)
    {
        try {
            $telar = $velocidad->NoTelarId;
            $fibra = $velocidad->FibraId;
            $densidad = $velocidad->Densidad ?? 'Normal';

            // Verificar si la velocidad está siendo utilizada en ReqProgramaTejido
            $programas = ReqProgramaTejido::where('NoTelarId', $telar)
                ->where(function ($query) use ($fibra) {
                    $query->where('FibraRizo', $fibra)
                          ->orWhere('FibraTrama', $fibra);
                })
                ->get();

            $enUso = false;
            foreach ($programas as $programa) {
                // Calcular la densidad del programa basándose en CalibreTrama
                $calibreTrama = $programa->CalibreTrama ?? $programa->CalibreTrama2;
                $densidadPrograma = ($calibreTrama !== null && (float)$calibreTrama > 40) ? 'Alta' : 'Normal';

                if ($densidadPrograma === $densidad) {
                    $enUso = true;
                    break;
                }
            }

            if ($enUso) {
                return response()->json([
                    'message' => "No se puede eliminar la velocidad porque esta siendo utilizada en el programa de tejido."
                ], 422);
            }

            $velocidad->delete();

            return response()->json([
                'success' => true,
                'message' => "Velocidad para '{$telar} - {$fibra}' eliminada exitosamente"
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la velocidad: ' . $e->getMessage()
            ], 500);
        }
    }
}
