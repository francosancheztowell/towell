<?php

namespace App\Http\Controllers\Atadores\ProgramaAtadores;

use App\Http\Controllers\Controller;
use App\Models\Atadores\AtaDevolucionesModel;
use App\Models\Atadores\AtaMontadoTelasModel;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AtaDevolucionesController extends Controller
{
    /**
     * Registra una devolución asociada a un proceso de atado (AtaMontadoTelas).
     *
     * El registro se vincula al montado mediante RefId. Los campos Telar y Lote
     * del formulario son informativos (viven en el montado padre) y no se
     * persisten porque la tabla AtaDevoluciones no los contempla.
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['ok' => false, 'message' => 'No autenticado'], 401);
        }

        $data = $request->validate([
            'ref_id' => ['required', 'integer'],
            'no_julio' => ['nullable', 'string', 'max:20'],
            'no_produccion' => ['nullable', 'string', 'max:20'],
            'kilos' => ['nullable', 'numeric', 'min:0'],
            'metros' => ['nullable', 'numeric', 'min:0'],
            'ubicacion' => ['nullable', 'string', 'max:10'],
            'fecha_devol' => ['nullable', 'date'],
            'cuenta' => ['nullable', 'string', 'max:10'],
            'calibre' => ['nullable', 'string', 'max:10'],
            'hilo' => ['nullable', 'string', 'max:20'],
            'tipo' => ['nullable', 'string', 'max:5'],
            'obs' => ['nullable', 'string', 'max:255'],
            'config_id' => ['nullable', 'string', 'max:10'],
            'invent_size_id' => ['nullable', 'string', 'max:10'],
            'invent_color_id' => ['nullable', 'string', 'max:10'],
        ]);

        $montado = AtaMontadoTelasModel::find($data['ref_id']);
        if (!$montado) {
            return response()->json(['ok' => false, 'message' => 'No se encontró el atado asociado a la devolución'], 404);
        }

        // Exigir al menos un dato cuantitativo para evitar devoluciones vacías.
        $kilos = $data['kilos'] ?? null;
        $metros = $data['metros'] ?? null;
        if (($kilos === null || (float) $kilos <= 0) && ($metros === null || (float) $metros <= 0)) {
            return response()->json([
                'ok' => false,
                'message' => 'Captura al menos Kilos o Metros para registrar la devolución.',
            ], 422);
        }

        try {
            $devolucion = AtaDevolucionesModel::create([
                'RefId' => $montado->Id,
                'NoJulio' => $data['no_julio'] ?? $montado->NoJulio,
                'NoProduccion' => $data['no_produccion'] ?? $montado->NoProduccion,
                'Kilos' => $kilos,
                'Metros' => $metros,
                'Ubicacion' => $data['ubicacion'] ?? null,
                'FechaDevol' => $data['fecha_devol'] ?? Carbon::now('America/Mexico_City')->toDateString(),
                'Cuenta' => $data['cuenta'] ?? null,
                'Calibre' => $data['calibre'] ?? null,
                'Hilo' => $data['hilo'] ?? null,
                'Tipo' => $data['tipo'] ?? $montado->Tipo,
                'Obs' => $data['obs'] ?? null,
                'ConfigId' => $data['config_id'] ?? $montado->ConfigId,
                'InventSizeId' => $data['invent_size_id'] ?? $montado->InventSizeId,
                'InventColorId' => $data['invent_color_id'] ?? $montado->InventColorId,
                'Estatus' => 'Activo',
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al registrar devolución de atadores', [
                'ref_id' => $montado->Id,
                'no_julio' => $montado->NoJulio,
                'no_orden' => $montado->NoProduccion,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'No se pudo registrar la devolución: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Devolución registrada correctamente',
            'id' => $devolucion->Id,
        ]);
    }
}
