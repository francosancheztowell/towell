<?php

declare(strict_types=1);

namespace App\Http\Controllers\mecanicos\OrdenesTrabajo;

use App\Http\Controllers\Controller;
use App\Models\Mecanicos\MecOrdenTrabajoModel;
use App\Services\Mecanicos\OrdenTrabajoAcceso;
use Illuminate\Http\JsonResponse;

/** El supervisor autoriza una OT Calificada; queda en solo lectura. `registrar` lo exige la ruta. */
class AutorizarOrdenTrabajoController extends Controller
{
    public function __construct(private readonly OrdenTrabajoAcceso $acceso) {}

    public function __invoke(string $folio): JsonResponse
    {
        $orden = $this->acceso->orden($folio);

        if ($orden->estatus() !== MecOrdenTrabajoModel::ESTATUS_CALIFICADO) {
            return response()->json([
                'success' => false,
                'error' => $orden->estatus() === MecOrdenTrabajoModel::ESTATUS_AUTORIZADO
                    ? 'La orden ya está autorizada.'
                    : 'Solo se pueden autorizar órdenes en estatus Calificado (el tejedor debe calificar todos los renglones).',
            ], 422);
        }

        $orden->update(['Estatus' => MecOrdenTrabajoModel::ESTATUS_AUTORIZADO]);

        return response()->json([
            'success' => true,
            'message' => 'Orden autorizada correctamente.',
            'data' => $orden->load('lineas'),
        ]);
    }
}
