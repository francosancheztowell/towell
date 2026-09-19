<?php

declare(strict_types=1);

namespace App\Http\Controllers\ProgramaUrdEng\ReservarProgramar;

use App\Http\Controllers\Controller;
use App\Services\ProgramaUrdEng\CrearOrdenesService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Alta de ordenes de urdido y engomado. El trabajo lo hace CrearOrdenesService;
 * aqui solo se valida la forma del payload y se traduce el resultado a JSON.
 *
 * El permiso ('crear') lo aplica el middleware module.permission en
 * routes/modules/programa-urd-eng.php.
 */
class ProgramarUrdEngController extends Controller
{
    public function __construct(
        private CrearOrdenesService $ordenes
    ) {}

    public function crearOrdenes(Request $request): JsonResponse
    {
        $request->validate([
            'grupo' => 'required|array',
            'grupo.salonTejidoId' => 'nullable|string',
            'materialesEngomado' => 'required|array',
            'construccionUrdido' => 'required|array',
            'datosEngomado' => 'required|array',
        ]);

        $usuario = Auth::user();

        try {
            $data = $this->ordenes->crear(
                $request->only(['grupo', 'materialesEngomado', 'construccionUrdido', 'datosEngomado', 'fechaRequerimiento']),
                $usuario->numero_empleado ?? null,
                $usuario->nombre ?? null,
            );

            return response()->json([
                'success' => true,
                'message' => 'Órdenes creadas exitosamente',
                'data' => $data,
            ]);
        } catch (DomainException $e) {
            // Falta un dato de negocio (destino, fibra): es culpa del payload, no del servidor.
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        } catch (ValidationException $e) {
            Log::error('crearOrdenes: validación', ['errors' => $e->errors()]);

            return response()->json(['success' => false, 'error' => 'Error de validación', 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('crearOrdenes', ['msg' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json(['success' => false, 'error' => 'Error al crear órdenes: '.$e->getMessage()], 500);
        }
    }
}
