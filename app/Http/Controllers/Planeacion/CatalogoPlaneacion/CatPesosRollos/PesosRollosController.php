<?php

namespace App\Http\Controllers\Planeacion\CatalogoPlaneacion\CatPesosRollos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Planeacion\Catalogos\ReqPesosRollosTejido;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class PesosRollosController extends Controller
{
    /**
     * Mostrar vista de Pesos por Rollos
     */
    public function index(Request $request)
    {
        $pesosRollos = ReqPesosRollosTejido::orderBy('ItemId')
            ->orderBy('InventSizeId')
            ->get();

        return view('catalagos.pesos-rollos', compact('pesosRollos'));
    }

    /**
     * Crear nuevo registro de peso por rollo
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'ItemId' => 'required|string|max:20',
                'ItemName' => 'required|string|max:60',
                'InventSizeId' => 'required|string|max:10',
                'PesoRollo' => 'required|numeric|min:0'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validación fallida: ' . implode(', ', $validator->errors()->all())
                ], 422);
            }

            // Verificar si ya existe un registro con el mismo ItemId e InventSizeId
            $existente = ReqPesosRollosTejido::where('ItemId', $request->ItemId)
                ->where('InventSizeId', $request->InventSizeId)
                ->first();

            if ($existente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe un registro con el mismo ItemId e InventSizeId'
                ], 422);
            }

            $now = Carbon::now();
            $usuario = Auth::check() ? (Auth::user()->nombre ?? 'Sistema') : 'Sistema';

            $pesoRollo = ReqPesosRollosTejido::create([
                'ItemId' => $request->ItemId,
                'ItemName' => $request->ItemName,
                'InventSizeId' => $request->InventSizeId,
                'PesoRollo' => $request->PesoRollo,
                'FechaCreacion' => $now->toDateString(),
                'HoraCreacion' => $now->toTimeString(),
                'UsuarioCrea' => $usuario
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Peso por rollo creado exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al crear peso por rollo: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el registro: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar registro de peso por rollo
     */
    public function update(Request $request, $id)
    {
        try {
            $pesoRollo = ReqPesosRollosTejido::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'ItemId' => 'required|string|max:20',
                'ItemName' => 'required|string|max:60',
                'InventSizeId' => 'required|string|max:10',
                'PesoRollo' => 'required|numeric|min:0'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validación fallida: ' . implode(', ', $validator->errors()->all())
                ], 422);
            }

            // Verificar si ya existe otro registro con el mismo ItemId e InventSizeId
            $existente = ReqPesosRollosTejido::where('ItemId', $request->ItemId)
                ->where('InventSizeId', $request->InventSizeId)
                ->where('Id', '!=', $id)
                ->first();

            if ($existente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe otro registro con el mismo ItemId e InventSizeId'
                ], 422);
            }

            $now = Carbon::now();
            $usuario = Auth::check() ? (Auth::user()->nombre ?? 'Sistema') : 'Sistema';

            $pesoRollo->update([
                'ItemId' => $request->ItemId,
                'ItemName' => $request->ItemName,
                'InventSizeId' => $request->InventSizeId,
                'PesoRollo' => $request->PesoRollo,
                'FechaModificacion' => $now->toDateString(),
                'HoraModificacion' => $now->toTimeString(),
                'UsuarioModifica' => $usuario
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Peso por rollo actualizado exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al actualizar peso por rollo: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el registro: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar registro de peso por rollo
     */
    public function destroy($id)
    {
        try {
            $pesoRollo = ReqPesosRollosTejido::findOrFail($id);
            $pesoRollo->delete();

            return response()->json([
                'success' => true,
                'message' => 'Peso por rollo eliminado exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al eliminar peso por rollo: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el registro: ' . $e->getMessage()
            ], 500);
        }
    }
}
