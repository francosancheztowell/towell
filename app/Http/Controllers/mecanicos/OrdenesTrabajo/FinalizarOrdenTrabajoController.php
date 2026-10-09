<?php

declare(strict_types=1);

namespace App\Http\Controllers\mecanicos\OrdenesTrabajo;

use App\Http\Controllers\Controller;
use App\Models\Mecanicos\MecOrdenTrabajoModel;
use App\Services\Mecanicos\CalificacionParoService;
use App\Services\Mecanicos\OrdenTrabajoAcceso;
use App\Services\Mecanicos\OrdenTrabajoDatos;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * El mecánico cierra la captura: Activo → Terminado, y deja de ser editable.
 *
 * Si el paro de origen ya está cerrado, su calificación se hereda aquí mismo y la
 * orden puede saltar directo a Calificado. Si sigue abierto, el cierre del paro la
 * calificará después (ver CalificacionParoService).
 */
class FinalizarOrdenTrabajoController extends Controller
{
    public function __construct(private readonly OrdenTrabajoAcceso $acceso) {}

    public function __invoke(string $folio, CalificacionParoService $calificaciones): JsonResponse
    {
        abort_unless(
            $this->acceso->puedeFinalizar(),
            403,
            'No tienes permiso para finalizar la orden. Se requiere modificar (mecánico) o registrar (supervisor).'
        );
        $orden = $this->acceso->orden($folio);

        $error = match (true) {
            ! $orden->admiteCaptura() => 'Solo se pueden finalizar órdenes en estatus Activo.',
            $orden->lineas->isEmpty() => 'La orden no tiene renglones para finalizar.',
            $orden->lineas->contains(fn ($linea) => OrdenTrabajoDatos::lineaSinCaptura($linea)) => 'Hay renglones sin captura. Completa o elimina los vacíos antes de finalizar.',
            default => null,
        };
        if ($error !== null) {
            return response()->json(['success' => false, 'error' => $error], 422);
        }

        $heredada = DB::transaction(function () use ($orden, $calificaciones): bool {
            $orden->update(['Estatus' => MecOrdenTrabajoModel::ESTATUS_TERMINADO]);

            return $calificaciones->calificarOrden($orden);
        });
        $orden->refresh()->load('lineas');

        return response()->json([
            'success' => true,
            'message' => $this->mensaje($orden->estatus(), $heredada),
            'data' => $orden,
        ]);
    }

    private function mensaje(string $estatus, bool $heredada): string
    {
        return match (true) {
            ! $heredada => 'Orden finalizada. Ya no se puede editar; el tejedor puede calificarla.',
            $estatus === MecOrdenTrabajoModel::ESTATUS_CALIFICADO => 'Orden finalizada y calificada con la calificación del paro. Falta que el supervisor la autorice.',
            default => 'Orden finalizada. Se aplicó la calificación del paro a los renglones pendientes.',
        };
    }
}
