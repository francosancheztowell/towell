<?php

declare(strict_types=1);

namespace App\Http\Controllers\mecanicos\OrdenesTrabajo\Lineas;

use App\Http\Controllers\Controller;
use App\Models\Mecanicos\MecOrdenTrabajoLineModel;
use App\Models\Mecanicos\MecOrdenTrabajoModel;
use App\Services\Mecanicos\CalificacionParoService;
use App\Services\Mecanicos\OrdenTrabajoAcceso;
use App\Services\Mecanicos\OrdenTrabajoDatos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * PUT de un renglón. Comparte URL con la calificación: en una orden Terminado, el
 * tejedor o el supervisor califican; en una Activa, el mecánico corrige su captura.
 */
class ActualizarLineaController extends Controller
{
    public function __construct(private readonly OrdenTrabajoAcceso $acceso) {}

    public function __invoke(Request $request, string $folio, int $linea): JsonResponse
    {
        $orden = $this->acceso->orden($folio);
        $registro = $this->acceso->linea($orden, $linea);

        if ($orden->estatus() === MecOrdenTrabajoModel::ESTATUS_TERMINADO && $this->acceso->puedeCalificar()) {
            return $this->calificar($request, $orden, $registro);
        }

        $this->acceso->exigir('modificar', 'No tienes permiso para modificar renglones.');
        $this->acceso->exigirCaptura($orden);

        $datos = OrdenTrabajoDatos::normalizarLinea($request->validate(OrdenTrabajoDatos::reglasLinea()));
        OrdenTrabajoDatos::validarLineaCompleta($datos);
        $registro->update($datos);

        return response()->json([
            'success' => true,
            'message' => 'Renglón actualizado correctamente.',
            'data' => $registro->fresh(),
        ]);
    }

    /** Guarda la nota del renglón; con todos calificados la orden pasa a Calificado. */
    private function calificar(Request $request, MecOrdenTrabajoModel $orden, MecOrdenTrabajoLineModel $registro): JsonResponse
    {
        $nota = (int) $request->validate([
            'Calificacion' => ['required', 'integer', 'between:'.CalificacionParoService::CALIFICACION_MINIMA.','.CalificacionParoService::CALIFICACION_MAXIMA],
        ])['Calificacion'];

        $usuario = Auth::user();
        $cve = trim((string) ($usuario->numero_empleado ?? ''));
        $nombre = trim((string) ($usuario->nombre ?? ''));
        if ($cve === '' || $nombre === '') {
            throw ValidationException::withMessages([
                'CveTejedor' => ['Tu usuario no tiene número de empleado o nombre configurados.'],
            ]);
        }

        $pasoACalificado = DB::transaction(function () use ($orden, $registro, $nota, $cve, $nombre): bool {
            $registro->update(['Calificacion' => $nota, 'CveTejedor' => $cve, 'NomTejedor' => $nombre]);

            if (! OrdenTrabajoDatos::todasCalificadas($orden->load('lineas'))) {
                return false;
            }
            $orden->update(['Estatus' => MecOrdenTrabajoModel::ESTATUS_CALIFICADO]);

            return true;
        });

        return response()->json([
            'success' => true,
            'message' => $pasoACalificado
                ? 'Calificación guardada. La orden pasó a Calificado.'
                : 'Calificación guardada correctamente.',
            'data' => $registro->fresh(),
            'orden' => $orden,
        ]);
    }
}
