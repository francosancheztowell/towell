<?php

declare(strict_types=1);

namespace App\Http\Controllers\mecanicos\OrdenesTrabajo;

use App\Http\Controllers\Controller;
use App\Services\Mecanicos\OrdenTrabajoAcceso;
use App\Services\Mecanicos\OrdenTrabajoDatos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Edita la cabecera de una OT Activa. Fecha de creación y estatus no se tocan aquí. */
class ActualizarOrdenTrabajoController extends Controller
{
    public function __construct(private readonly OrdenTrabajoAcceso $acceso) {}

    public function __invoke(Request $request, string $folio): JsonResponse
    {
        $this->acceso->exigir('modificar', 'No tienes permiso para modificar órdenes de trabajo.');
        $orden = $this->acceso->orden($folio);
        $this->acceso->exigirCaptura($orden);

        $datos = OrdenTrabajoDatos::normalizarCabecera(
            $request->validate(OrdenTrabajoDatos::reglasCabecera(), OrdenTrabajoDatos::mensajesCabecera())
        );
        OrdenTrabajoDatos::validarOrdenNoVacia($datos);

        $orden->update($datos);

        return response()->json([
            'success' => true,
            'message' => 'Cabecera de la orden actualizada.',
            'data' => $orden->load('lineas'),
        ]);
    }
}
