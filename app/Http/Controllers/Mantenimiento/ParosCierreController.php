<?php

namespace App\Http\Controllers\Mantenimiento;

use App\Http\Controllers\Controller;
use App\Models\Mantenimiento\ManFallasParos;
use App\Models\Mantenimiento\ManOperadoresMantenimiento;
use App\Services\Mantenimiento\ParoTelegramNotifier;
use App\Services\Mecanicos\CalificacionParoService;
use App\Support\Http\Concerns\HandlesApiErrors;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cierre de paros (pantalla "Finalizar paro") y el combo de quién atendió.
 */
class ParosCierreController extends Controller
{
    use HandlesApiErrors;

    /**
     * Finalizar un paro/falla (actualizar con datos de cierre).
     */
    public function finalizar(
        Request $request,
        int $id,
        ParoTelegramNotifier $notifier,
        CalificacionParoService $calificaciones,
    ): JsonResponse {
        try {
            $paro = ManFallasParos::find($id);

            if (! $paro) {
                return response()->json([
                    'success' => false,
                    'error' => 'Paro no encontrado',
                ], 404);
            }

            // Solo se cierran paros activos: recerrar uno ya terminado pisaría
            // HoraFin, FechaFin, NomAtendio y Calidad, y se perdería el registro
            // real de cuándo y quién lo atendió. La columna es NVARCHAR, así que
            // se compara sin distinguir mayúsculas ni espacios sobrantes.
            if (strcasecmp(trim((string) $paro->Estatus), 'Activo') !== 0) {
                return response()->json([
                    'success' => false,
                    'error' => 'Este paro ya fue finalizado.',
                ], 422);
            }

            $datos = $request->validate([
                'atendio' => 'required|string|max:150',
                'turno' => 'nullable|integer|in:1,2,3,4',
                'calidad' => 'required|integer|min:1|max:5',
                'obs_cierre' => 'nullable|string|max:255',
            ], [
                'atendio.required' => 'Indica quién atendió el paro.',
                'calidad.required' => 'Califica la atención del paro.',
                'calidad.min' => 'La calificación debe ser de al menos 1.',
                'calidad.max' => 'La calificación no puede pasar de 5.',
                'obs_cierre.max' => 'Las observaciones de cierre no pueden pasar de 255 caracteres.',
            ]);

            $usuario = Auth::user();
            $updateData = $this->datosDeCierre($request, $datos, $usuario->numero_empleado ?? null);

            // El cierre del paro y la calificación que hereda a sus órdenes de
            // trabajo van juntos: un paro cerrado cuya OT quedó sin calificar
            // deja al tejedor esperando una captura que ya nadie le va a pedir.
            $ordenesCalificadas = DB::connection('sqlsrv')->transaction(
                function () use ($paro, $updateData, $calificaciones): int {
                    $paro->update($updateData);
                    $paro->refresh();

                    return $calificaciones->propagarAOrdenesDelParo($paro);
                }
            );

            $notificado = $notifier->notifyClosed($paro, $usuario->nombre ?? null);

            return response()->json([
                'success' => true,
                'message' => 'Paro finalizado correctamente'
                    .($notificado ? ' y notificación enviada a Telegram' : ', pero no se pudo avisar por Telegram')
                    .($ordenesCalificadas > 0
                        ? '. Se calificaron '.$ordenesCalificadas.($ordenesCalificadas === 1 ? ' orden de trabajo' : ' órdenes de trabajo').' con esta calificación'
                        : ''),
                'data' => [
                    'id' => $paro->Id,
                    'folio' => $paro->Folio,
                    'notificacion_enviada' => $notificado,
                    'ordenes_calificadas' => $ordenesCalificadas,
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => collect($e->errors())->flatten()->first() ?? 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Error al finalizar paro/falla', 'No se pudo finalizar el paro. Intenta de nuevo.', context: ['id' => $id]);
        }
    }

    /**
     * Columnas que cambian al cerrar un paro; turno y observaciones solo si llegaron.
     *
     * @param  array<string, mixed>  $datos  Ya validados.
     * @return array<string, mixed>
     */
    private function datosDeCierre(Request $request, array $datos, ?string $cveAtendio): array
    {
        $ahora = now();

        $updateData = [
            'Estatus' => 'Terminado',
            'HoraFin' => $ahora->format('H:i:s'),
            'FechaFin' => $ahora->toDateString(),
            'NomAtendio' => $datos['atendio'],
            'CveAtendio' => $cveAtendio,
            'Calidad' => (int) $datos['calidad'],
        ];

        if ($request->filled('turno')) {
            $updateData['TurnoAtendio'] = (int) $datos['turno'];
        }

        if ($request->filled('obs_cierre')) {
            $updateData['ObsCierre'] = $datos['obs_cierre'];
        }

        return $updateData;
    }

    /**
     * Lista de operadores de mantenimiento para el select de "Atendió".
     */
    public function operadores(): JsonResponse
    {
        try {
            $operadores = ManOperadoresMantenimiento::select('Id', 'CveEmpl', 'NomEmpl', 'Turno', 'Depto')
                ->orderBy('NomEmpl')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $operadores,
            ]);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Error al obtener operadores de mantenimiento', 'No se pudieron cargar los operadores.');
        }
    }
}
