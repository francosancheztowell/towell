<?php

declare(strict_types=1);

namespace App\Http\Controllers\mecanicos\OrdenesTrabajo\Lineas;

use App\Http\Controllers\Controller;
use App\Models\Mecanicos\MecOrdenTrabajoLineModel;
use App\Models\Mecanicos\MecOrdenTrabajoModel;
use App\Services\Mecanicos\OrdenTrabajoAcceso;
use App\Services\Mecanicos\OrdenTrabajoDatos;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Quita un renglón. El mecánico (eliminar) solo con la orden Activa; el supervisor
 * (registrar) o Sistemas corrigen en cualquier estatus salvo Autorizado o Cancelado.
 */
class EliminarLineaController extends Controller
{
    public function __construct(private readonly OrdenTrabajoAcceso $acceso) {}

    public function __invoke(string $folio, int $linea): JsonResponse
    {
        abort_unless($this->acceso->puedeEliminarLineas(), 403, 'No tienes permiso para eliminar renglones.');

        $orden = $this->acceso->orden($folio);
        $registro = $this->acceso->linea($orden, $linea);

        if ($this->acceso->bloqueaEliminarLinea($orden->estatus())) {
            return response()->json([
                'success' => false,
                'error' => 'La orden ya no admite eliminar renglones (finalizada, calificada, autorizada o cancelada).',
            ], 422);
        }

        $pasoACalificado = DB::transaction(function () use ($orden, $registro): bool {
            // Dentro de la transacción: dos borrados simultáneos no dejan la orden sin renglones.
            // Sin la relación: lleva ORDER BY y SQL Server lo rechaza junto a COUNT(*).
            if (MecOrdenTrabajoLineModel::where('Folio', $orden->Folio)->lockForUpdate()->count() <= 1) {
                abort(422, 'La orden debe conservar al menos un renglón. Elimina la orden completa si fue creada por error.');
            }
            $registro->delete();

            return $this->calificarSiQuedaCompleta($orden);
        });

        return response()->json([
            'success' => true,
            'message' => $pasoACalificado
                ? 'Renglón eliminado. Los renglones restantes ya están calificados: la orden pasó a Calificado.'
                : 'Renglón eliminado correctamente.',
            'orden' => $orden->fresh(),
        ]);
    }

    /**
     * Al quitar el único renglón sin calificar de una orden Terminada, la orden queda
     * igual que si el tejedor hubiera calificado el último: pasa a Calificado.
     */
    private function calificarSiQuedaCompleta(MecOrdenTrabajoModel $orden): bool
    {
        if ($orden->estatus() !== MecOrdenTrabajoModel::ESTATUS_TERMINADO || ! OrdenTrabajoDatos::todasCalificadas($orden->load('lineas'))) {
            return false;
        }

        $orden->update(['Estatus' => MecOrdenTrabajoModel::ESTATUS_CALIFICADO]);

        return true;
    }
}
