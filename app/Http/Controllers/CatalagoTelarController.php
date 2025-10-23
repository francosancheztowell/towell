<?php

namespace App\Http\Controllers;

use App\Models\ReqTelares;
use App\Imports\ReqTelaresImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class CatalagoTelarController extends Controller
{
    public function index(Request $request)
    {
        try {
            $q = ReqTelares::query();

            if ($request->filled('salon'))  $q->where('SalonTejidoId', 'like', "%{$request->salon}%");
            if ($request->filled('telar'))  $q->where('NoTelarId', 'like', "%{$request->telar}%");
            if ($request->filled('nombre')) $q->where('Nombre', 'like', "%{$request->nombre}%");
            if ($request->filled('grupo'))  $q->where('Grupo', 'like', "%{$request->grupo}%");

            $telares   = $q->orderBy('SalonTejidoId')->orderBy('NoTelarId')->get();
            $noResults = $telares->isEmpty();

            return view('catalagos.catalagoTelares', compact('telares','noResults'));
        } catch (\Exception $e) {
            Log::error('Telares index error: '.$e->getMessage());
            return view('catalagos.catalagoTelares', ['telares'=>collect(), 'noResults'=>true])
                   ->with('error','Error al cargar los telares');
        }
    }

    /** Excel */
    public function procesarExcel(Request $request)
    {
        $v = Validator::make($request->all(), [
            'archivo_excel' => 'required|file|mimes:xlsx,xls|max:10240'
        ]);
        if ($v->fails()) {
            return response()->json(['success'=>false,'message'=>'Archivo inválido','errors'=>$v->errors()], 400);
        }

        $file = $request->file('archivo_excel');
        DB::beginTransaction();
        try {
            $import = new ReqTelaresImport();
            Excel::import($import, $file);
            DB::commit();

            $stats = $import->getStats();
            return response()->json([
                'success'=>true,
                'message'=>"Procesado: {$stats['processed_rows']} filas (Creados {$stats['created_rows']}, Actualizados {$stats['updated_rows']}, Saltadas {$stats['skipped_rows']})",
                'data'=>$stats
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Excel telares error: '.$e->getMessage());
            return response()->json(['success'=>false,'message'=>'Error al procesar el Excel: '.$e->getMessage()], 500);
        }
    }

    /** Crear */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'SalonTejidoId' => 'required|string|max:20',
                'NoTelarId'     => 'required|string|max:10',
                'Nombre'        => 'nullable|string|max:30',
                'Grupo'         => 'nullable|string|max:30',
            ]);

            // Duplicados
            $dup = ReqTelares::where('SalonTejidoId', $request->SalonTejidoId)
                             ->where('NoTelarId', $request->NoTelarId)
                             ->exists();
            if ($dup) {
                return response()->json(['success'=>false,'message'=>'Ya existe un telar con el mismo salón y número'], 422);
            }

            $nombre = $request->Nombre ?: $this->makeName($request->SalonTejidoId, $request->NoTelarId);

            ReqTelares::create([
                'SalonTejidoId' => $request->SalonTejidoId,
                'NoTelarId'     => $request->NoTelarId,
                'Nombre'        => $nombre,
                'Grupo'         => $request->Grupo
            ]);

            return response()->json(['success'=>true,'message'=>"Telar '{$nombre}' creado exitosamente"]);
        } catch (\Exception $e) {
            Log::error('Crear telar error: '.$e->getMessage());
            return response()->json(['success'=>false,'message'=>'Error al crear: '.$e->getMessage()], 500);
        }
    }

    /** Actualizar por uniqueId = "Salon_Telar" */
    public function update(Request $request, $uniqueId)
    {
        try {
            Log::info('Update telar llamado', [
                'uniqueId' => $uniqueId,
                'request_data' => $request->all()
            ]);

            $request->validate([
                'SalonTejidoId' => 'required|string|max:20',
                'NoTelarId'     => 'required|string|max:10',
                'Nombre'        => 'nullable|string|max:30',
                'Grupo'         => 'nullable|string|max:30',
            ]);

            // Parsear uniqueId respetando salones con guiones bajos
            $pos = strrpos($uniqueId, '_');
            if ($pos === false) return response()->json(['success'=>false,'message'=>'ID de telar inválido'], 400);
            $salonKey = substr($uniqueId, 0, $pos);
            $telarKey = substr($uniqueId, $pos + 1);

            Log::info('Parsing uniqueId', [
                'salonKey' => $salonKey,
                'telarKey' => $telarKey
            ]);

            // Buscar el telar con diferentes variaciones
            $telar = ReqTelares::where('SalonTejidoId', $salonKey)->where('NoTelarId', $telarKey)->first();

            // Si no se encuentra, intentar con variaciones
            if (!$telar) {
                Log::info('Buscando telar con variaciones', [
                    'salonKey' => $salonKey,
                    'telarKey' => $telarKey
                ]);

                // Buscar todos los telares para debugging
                $todosLosTelares = ReqTelares::all();
                Log::info('Todos los telares en la base de datos', [
                    'count' => $todosLosTelares->count(),
                    'telares' => $todosLosTelares->map(function($t) {
                        return [
                            'id' => $t->id,
                            'SalonTejidoId' => $t->SalonTejidoId,
                            'NoTelarId' => $t->NoTelarId,
                            'Nombre' => $t->Nombre
                        ];
                    })->toArray()
                ]);

                Log::warning('Telar no encontrado', [
                    'salonKey' => $salonKey,
                    'telarKey' => $telarKey
                ]);
                return response()->json(['success'=>false,'message'=>'Telar no encontrado'], 404);
            }

            Log::info('Telar encontrado', [
                'telar_id' => $telar->id,
                'current_data' => $telar->toArray()
            ]);

            // Si cambia combinación, validar duplicados
            if ($request->SalonTejidoId !== $telar->SalonTejidoId || $request->NoTelarId !== $telar->NoTelarId) {
                $dup = ReqTelares::where('SalonTejidoId', $request->SalonTejidoId)
                                 ->where('NoTelarId', $request->NoTelarId)
                                 ->exists();
                if ($dup) return response()->json(['success'=>false,'message'=>'Ya existe otro telar con ese Salón/Telar'], 422);
            }

            $nombre = $request->Nombre ?: $this->makeName($request->SalonTejidoId, $request->NoTelarId);

            Log::info('Actualizando telar con datos', [
                'SalonTejidoId' => $request->SalonTejidoId,
                'NoTelarId' => $request->NoTelarId,
                'Nombre' => $nombre,
                'Grupo' => $request->Grupo
            ]);

            $resultado = $telar->update([
                'SalonTejidoId' => $request->SalonTejidoId,
                'NoTelarId'     => $request->NoTelarId,
                'Nombre'        => $nombre,
                'Grupo'         => $request->Grupo
            ]);

            $telarActualizado = $telar->fresh();
            Log::info('Resultado de actualización', [
                'resultado' => $resultado,
                'telar_actualizado' => $telarActualizado ? $telarActualizado->toArray() : 'No se pudo obtener datos actualizados'
            ]);

            return response()->json(['success'=>true,'message'=>"Telar '{$nombre}' actualizado exitosamente"]);
        } catch (\Exception $e) {
            Log::error('Actualizar telar error: '.$e->getMessage());
            return response()->json(['success'=>false,'message'=>'Error al actualizar: '.$e->getMessage()], 500);
        }
    }

    /** Eliminar por uniqueId */
    public function destroy($uniqueId)
    {
        try {
            $pos = strrpos($uniqueId, '_');
            if ($pos === false) return response()->json(['success'=>false,'message'=>'ID de telar inválido'], 400);
            $salonKey = substr($uniqueId, 0, $pos);
            $telarKey = substr($uniqueId, $pos + 1);

            $telar = ReqTelares::where('SalonTejidoId', $salonKey)->where('NoTelarId', $telarKey)->first();
            if (!$telar) return response()->json(['success'=>false,'message'=>'Telar no encontrado'], 404);

            $nm = $telar->Nombre;
            $telar->delete();

            return response()->json(['success'=>true,'message'=>"Telar '{$nm}' eliminado exitosamente"]);
        } catch (\Exception $e) {
            Log::error('Eliminar telar error: '.$e->getMessage());
            return response()->json(['success'=>false,'message'=>'Error al eliminar: '.$e->getMessage()], 500);
        }
    }

    /** Generador de nombre cuando no lo provee el usuario */
    private function makeName($salon, $telar): string
    {
        $up = strtoupper(trim((string)$salon));
        $pref = str_contains($up, 'JACQUARD') ? 'JAC' : (str_contains($up, 'SMITH') ? 'Smith' : strtoupper(substr($up, 0, 3)));
        return trim($pref.' '.$telar);
    }
}
