<?php

namespace App\Http\Controllers\Planeacion\CatalogoPlaneacion\CatAplicaciones;

use App\Models\Planeacion\ReqAplicaciones;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Models\Planeacion\ReqProgramaTejidoLine;
use App\Imports\ReqAplicacionesImport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class AplicacionesController extends Controller
{
    /**
     * Mostrar el listado de aplicaciones
     */
    public function index(Request $request)
    {

        // Nueva estructura: solo AplicacionId, Nombre, Factor
        $aplicaciones = ReqAplicaciones::orderBy('AplicacionId')
                                    ->orderBy('Nombre')
                                    ->get();


        $noResults = false;
        return view('catalagos.aplicaciones', compact('aplicaciones', 'noResults'));
    }

    /**
     * Procesar archivo Excel de aplicaciones
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

            // Usar transacciones
            DB::beginTransaction();

            try {
                // Procesar el archivo
                $importador = new ReqAplicacionesImport();
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
                Log::error('Error al procesar Excel de aplicaciones: ' . $e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);

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
     * Crear nueva aplicación
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'AplicacionId' => [
                    'required',
                    'string',
                    'max:50',
                    Rule::unique(ReqAplicaciones::class, 'AplicacionId'),
                ],
                'Nombre'       => 'required|string|max:100',
                'Factor'       => 'nullable|numeric',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error en la validación',
                    'errors'  => $validator->errors()
                ], 422);
            }

            $aplicacion = ReqAplicaciones::create([
                'AplicacionId' => $request->AplicacionId,
                'Nombre'       => $request->Nombre,
                'Factor'       => $request->Factor,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Aplicación creada exitosamente',
                'data'    => $aplicacion
            ]);

        } catch (\Exception $e) {
            Log::error('Error al crear aplicación: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la aplicación: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar aplicación
     */
    public function update(Request $request, $id)
    {
        try {
            $aplicacion = null;
            if (is_numeric($id)) {
                $aplicacion = ReqAplicaciones::find($id);
            }
            if (!$aplicacion) {
                $aplicacion = ReqAplicaciones::where('AplicacionId', $id)->first();
            }

            if (!$aplicacion) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aplicación no encontrada'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'AplicacionId' => [
                    'required',
                    'string',
                    'max:50',
                    Rule::unique(ReqAplicaciones::class, 'AplicacionId')->ignore($aplicacion->Id, 'Id'),
                ],
                'Nombre'       => 'required|string|max:100',
                'Factor'       => 'nullable|numeric',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error en la validación',
                    'errors'  => $validator->errors()
                ], 422);
            }

            // Guardar valores anteriores para comparar
            $factorAnterior = $aplicacion->Factor;
            $aplicacionId = $aplicacion->AplicacionId;

            $aplicacion->update([
                'AplicacionId' => $request->AplicacionId,
                'Nombre'       => $request->Nombre,
                'Factor'       => $request->Factor,
            ]);

            // Si cambió el Factor, recalcular fórmulas en ReqProgramaTejidoLine
            $factorNuevo = $request->Factor;
            $factorCambio = abs((float)$factorAnterior - (float)$factorNuevo) > 0.0001;

            if ($factorCambio) {
                $this->actualizarLineasPorCambioFactor($aplicacionId, $factorNuevo);
            }

            return response()->json([
                'success' => true,
                'message' => 'Aplicación actualizada exitosamente',
                'data'    => $aplicacion
            ]);

        } catch (\Exception $e) {
            Log::error('Error al actualizar aplicación: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la aplicación: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualiza las líneas de ReqProgramaTejidoLine cuando cambia el Factor de una aplicación
     * Fórmula: Aplicacion = Factor * Kilos
     */
    private function actualizarLineasPorCambioFactor(string $aplicacionId, ?float $nuevoFactor)
    {
        try {
            // Identificar programas que usan esta aplicación
            $programas = ReqProgramaTejido::where('AplicacionId', $aplicacionId)->get();


            $lineasActualizadas = 0;
            $programasIds = $programas->pluck('Id')->toArray();

            if (!empty($programasIds)) {
                // Buscar todas las líneas de estos programas
                $lineas = ReqProgramaTejidoLine::whereIn('ProgramaId', $programasIds)
                    ->whereNotNull('Kilos')
                    ->where('Kilos', '>', 0)
                    ->get();


                // Actualizar cada línea: Aplicacion = Factor * Kilos
                // Usar la misma precisión que el Observer (6 decimales)
                foreach ($lineas as $linea) {
                    $kilos = (float) ($linea->Kilos ?? 0);
                    $nuevoAplicacion = ($nuevoFactor !== null && $kilos > 0)
                        ? round((float) ($nuevoFactor * $kilos), 6)
                        : null;

                    $linea->update([
                        'Aplicacion' => $nuevoAplicacion
                    ]);

                    $lineasActualizadas++;
                }
            }
        } catch (\Exception $e) {
            Log::error('Error al actualizar líneas por cambio de factor: ' . $e->getMessage(), [
                'aplicacion_id' => $aplicacionId,
                'nuevo_factor' => $nuevoFactor,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Eliminar aplicación
     */
    public function destroy($id)
    {
        try {
            // Buscar por ID numérico o por AplicacionId
            $aplicacion = null;
            if (is_numeric($id)) {
                $aplicacion = ReqAplicaciones::find($id);
            }
            if (!$aplicacion) {
                $aplicacion = ReqAplicaciones::where('AplicacionId', $id)->first();
            }

            if (!$aplicacion) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aplicación no encontrada'
                ], 404);
            }

            $aplicacionId = $aplicacion->AplicacionId;

            // Verificar si se está usando en ReqProgramaTejido (campo AplicacionId)
            $usoEnProgramaTejido = ReqProgramaTejido::where('AplicacionId', $aplicacionId)->exists();

            // Verificar si se está usando en ReqProgramaTejidoLine (campo Aplicacion)
            $usoEnProgramaTejidoLine = ReqProgramaTejidoLine::where('Aplicacion', $aplicacionId)->exists();

            if ($usoEnProgramaTejido || $usoEnProgramaTejidoLine) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar la aplicación porque está siendo utilizada en programas de tejido.'
                ], 422);
            }

            $aplicacion->delete();

            return response()->json([
                'success' => true,
                'message' => 'Aplicación eliminada exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al eliminar aplicación: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la aplicación: ' . $e->getMessage()
            ], 500);
        }
    }
}
