<?php

declare(strict_types=1);

namespace App\Http\Controllers\mecanicos\OrdenesTrabajo;

use App\Http\Controllers\Controller;
use App\Models\Mecanicos\MecOrdenTrabajoLineModel;
use App\Services\Mecanicos\OrdenTrabajoAcceso;
use App\Services\Mecanicos\OrdenTrabajoDatos;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Elimina una OT Activa solo si sus renglones siguen vacíos (el placeholder con que
 * nace); con algún renglón registrado se rechaza. El permiso `eliminar` lo exige la ruta.
 */
class EliminarOrdenTrabajoController extends Controller
{
    public function __construct(private readonly OrdenTrabajoAcceso $acceso) {}

    public function __invoke(string $folio): JsonResponse
    {
        $this->acceso->exigir('eliminar', 'Los tejedores no pueden eliminar órdenes de trabajo.');
        $orden = $this->acceso->orden($folio);
        $this->acceso->exigirCaptura($orden);

        if ($orden->lineas->contains(fn ($linea) => ! OrdenTrabajoDatos::lineaSinCaptura($linea))) {
            return response()->json([
                'success' => false,
                'error' => 'La orden tiene renglones registrados; no se puede eliminar.',
            ], 422);
        }

        DB::transaction(function () use ($orden): void {
            MecOrdenTrabajoLineModel::where('Folio', $orden->Folio)->delete();
            $orden->delete();
        });

        return response()->json(['success' => true, 'message' => 'Orden de trabajo eliminada.']);
    }
}
