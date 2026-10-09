<?php

declare(strict_types=1);

namespace App\Http\Controllers\mecanicos\OrdenesTrabajo\Lineas;

use App\Http\Controllers\Controller;
use App\Models\Mecanicos\MecOrdenTrabajoLineModel;
use App\Services\Mecanicos\OrdenTrabajoAcceso;
use App\Services\Mecanicos\OrdenTrabajoDatos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** El mecánico agrega una intervención (renglón) a una OT Activa. */
class CrearLineaController extends Controller
{
    public function __construct(private readonly OrdenTrabajoAcceso $acceso) {}

    public function __invoke(Request $request, string $folio): JsonResponse
    {
        $this->acceso->exigir('crear', 'No tienes permiso para agregar renglones a la orden.');
        $orden = $this->acceso->orden($folio);
        $this->acceso->exigirCaptura($orden);

        $datos = OrdenTrabajoDatos::normalizarLinea($request->validate(OrdenTrabajoDatos::reglasLinea()));
        OrdenTrabajoDatos::validarLineaCompleta($datos);

        $linea = MecOrdenTrabajoLineModel::create([...$datos, 'Folio' => $orden->Folio]);

        return response()->json([
            'success' => true,
            'message' => 'Renglón agregado correctamente.',
            'data' => $linea,
        ], 201);
    }
}
