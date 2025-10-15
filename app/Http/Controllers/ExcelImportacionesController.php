<?php

namespace App\Http\Controllers;

use App\Imports\ExcelImport;
use App\Imports\ReqProgramaTejidoImport;
use App\Models\Planeacion;
use App\Models\RegistroImportacionesExcel;
use App\Models\ReqProgramaTejido;
use App\Models\TejInventarioTelares;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Schema;

class ExcelImportacionesController extends Controller
{
    //
    public function showForm()
    {
        return view('TEJIDO-SCHEDULING.import');
    }

    public function import(Request $request)
    {
        $request->validate([
            'archivo' => 'required|mimes:xlsx,xls'
        ]);

        $telaresRequeridos = [201, 202, 203, 204, 205, 206, 207, 208, 209, 210, 211, 213, 214, 215, 299, 300, 301, 302, 303, 304, 305, 306, 307, 308, 309, 310, 311, 312, 313, 314, 315, 316, 317, 318, 319, 320];


        try {
            DB::beginTransaction(); // 🚩 INICIA la transacción

            // 1. Guarda los ids de los registros existentes
            $idsExistentes = \App\Models\Planeacion::pluck('id')->toArray();

            // 2. Importa el archivo (esto inserta los nuevos)
            Excel::import(new ExcelImport, $request->file('archivo'));

            // 3. Valida que existan todos los telares requeridos
            $this->validarTelaresExistentes($telaresRequeridos);

            // 4. Si todo está bien, borra los registros viejos (solo esos)
            if (!empty($idsExistentes)) {
                \App\Models\Planeacion::whereIn('id', $idsExistentes)->delete();
            }

            // 5. Actualiza en_proceso en los nuevos registros
            $this->actualizarEnProceso();

            //contamos registros insertados y guardamos el registro de la importacion en la tabla: registro_importaciones_excel
            //Cuenta los registros actuales en Planeacion (TEJIDO_SCHEDULING)
            $total = \App\Models\Planeacion::count();
            RegistroImportacionesExcel::create([
                'usuario' => Auth::user()->nombre, // O 'email', según tu modelo User
                'total_registros' => $total,
            ]);

            DB::commit(); // 🚩 TERMINA y guarda todo
            // EN EL CONTROLADOR, solo regresa con el mensaje
            return back()->with('success', '¡Archivo importado exitosamente!');
        } catch (\Exception $e) {
            DB::rollBack(); // 🚩 Si hay error, DESHACE TODO
            return back()->with('error', 'Hubo un error al importar el archivo: ' . $e->getMessage());
        }
    }


    // En tu controlador, después de importar
    public function actualizarEnProceso()
    {
        // Resetea todos
        \App\Models\Planeacion::query()->update(['en_proceso' => 0]);

        // Por cada telar, selecciona el id con la fecha más baja
        $planeaciones = \App\Models\Planeacion::select('id', 'Telar', 'Inicio_Tejido')
            ->orderBy('Telar')
            ->orderBy('Inicio_Tejido')
            ->get();

        $agrupados = $planeaciones->groupBy('Telar');
        $ids = $agrupados->map(function ($items) {
            return $items->sortBy('Inicio_Tejido')->first()->id;
        })->values();

        \App\Models\Planeacion::whereIn('id', $ids)->update(['en_proceso' => 1]);
    }
    public function validarTelaresExistentes(array $telaresRequeridos)
    {

        // Busca todos los telares que existen en la tabla
        $telaresEnTabla = \App\Models\Planeacion::whereIn('Telar', $telaresRequeridos)
            ->pluck('Telar')
            ->unique()
            ->map(fn($t) => (int) $t)
            ->toArray();

        // Busca los telares que faltan
        $faltantes = array_diff($telaresRequeridos, $telaresEnTabla);

        if (!empty($faltantes)) {
            // Toma el primero que falte (puedes personalizar si quieres mostrar todos)
            $telarFaltante = reset($faltantes);
            // Lanza excepción personalizada
            throw new \Exception("No hay información disponible para el Telar {$telarFaltante}, debes cargar información para cada telar. Proceso anulado.");
        }
    }

    /**
     * Maneja la carga de catálogos desde archivos Excel
     */
    public function uploadCatalogos(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|mimes:xlsx,xls|max:10240' // 10MB máximo
        ]);

        try {
            $archivo = $request->file('excel_file');
            $nombreArchivo = $archivo->getClientOriginalName();

            // Limpiar registros anteriores antes de importar (sin TRUNCATE para evitar permisos/FK)
            ReqProgramaTejido::query()->delete();
            try {
                DB::statement("DBCC CHECKIDENT ('ReqProgramaTejido', RESEED, 0)");
            } catch (\Throwable $e) {
                // Si no se puede reseedear (permisos), continuar sin fallo
                Log::warning('No se pudo hacer RESEED a ReqProgramaTejido: ' . $e->getMessage());
            }

            // Contar registros antes de la importación (tras limpiar)
            $registrosAntes = 0;

            // Procesar el archivo Excel con manejo de errores mejorado
            $importador = new \App\Imports\ReqProgramaTejidoSimpleImport;
            Excel::import($importador, $archivo);

            // Obtener estadísticas del importador
            $stats = $importador->getStats();

            // Contar registros después de la importación
            $registrosDespues = ReqProgramaTejido::count();
            $registrosImportados = $registrosDespues - $registrosAntes;

            // Log de estadísticas para debugging
            Log::info('Estadísticas de importación', [
                'stats' => $stats,
                'registros_antes' => $registrosAntes,
                'registros_despues' => $registrosDespues,
                'registros_importados' => $registrosImportados
            ]);

            // Registrar la importación
            RegistroImportacionesExcel::create([
                'usuario' => Auth::user()->nombre ?? 'Usuario',
                'total_registros' => $registrosImportados,
                'tipo_importacion' => 'req_programa_tejido',
                'archivo_original' => $nombreArchivo
            ]);

            return response()->json([
                'success' => true,
                'message' => "Catálogos cargados exitosamente desde {$nombreArchivo}. Se importaron {$registrosImportados} registros a la tabla ReqProgramaTejido. Filas procesadas: {$stats['processed_rows']}, Filas saltadas: {$stats['skipped_rows']}.",
                'stats' => $stats,
                'registros_importados' => $registrosImportados
            ]);

        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $errores = [];
            foreach ($e->failures() as $failure) {
                $errores[] = "Fila {$failure->row()}: " . implode(', ', $failure->errors());
            }

            return response()->json([
                'success' => false,
                'message' => 'Error de validación en el archivo: ' . implode('; ', $errores)
            ], 422);

        } catch (\Exception $e) {
            // Log del error para debugging
            Log::error('Error en uploadCatalogos: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el archivo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Procesa los catálogos del archivo Excel
     */
    private function procesarCatalogos($archivo)
    {
        // Importar el archivo Excel a la tabla ReqProgramaTejido
        Excel::import(new \App\Imports\ReqProgramaTejidoImport, $archivo);

        // Simular tiempo de procesamiento adicional si es necesario
        sleep(1);
    }

    /**
     * Muestra los registros de ReqProgramaTejido
     */
    public function showReqProgramaTejido()
    {
        // Ordenar por el orden del Excel si existe RowNum; fallback: Telar numérico y CreatedAt desc
        $query = ReqProgramaTejido::query();
        if (Schema::hasColumn('ReqProgramaTejido', 'RowNum')) {
            $registros = $query->orderBy('RowNum', 'asc')->get();
        } else {
            $registros = $query->orderByRaw("CASE WHEN ISNUMERIC(NoTelarId)=1 THEN CAST(NoTelarId AS INT) ELSE 2147483647 END ASC")
                ->orderBy('CreatedAt', 'desc')
                ->get();
        }

        return view('modulos.req-programa-tejido', compact('registros'));
    }

    /**
     * Muestra el inventario de telares para reservar y programar
     */
    public function showReservarProgramar()
    {
        // Obtener inventario de telares ordenado por número de telar
        $inventarioTelares = TejInventarioTelares::orderBy('no_telar', 'asc')->get();

        return view('modulos.programa_urd_eng.reservar-programar', compact('inventarioTelares'));
    }
}
